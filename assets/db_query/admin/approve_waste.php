<?php
// Moves a waste record's status forward (approved/processed/disposed/cancelled).
// When the transition is reported -> approved AND the record has a
// product_id + quantity, this also transactionally reduces product_details.stock
// (SELECT...FOR UPDATE, clamped at 0) and best-effort inserts a stock_movements
// row (owned by another agent's migration — try/catch, ignore if it doesn't
// exist yet, per this codebase's non-blocking cross-module integration pattern).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'waste.approve');

$id = (int)($_POST['id'] ?? 0);
$newStatus = trim($_POST['status'] ?? 'approved');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

$validStatuses = ['approved','processed','disposed','cancelled'];
if (!$id || !in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'id and a valid target status are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM waste_records WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $waste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$waste) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Waste record not found']);
        exit;
    }

    $oldStatus = $waste['status'];
    $shouldDeductStock = ($newStatus === 'approved' && $oldStatus === 'reported' && $waste['product_id'] && $waste['quantity']);

    if ($shouldDeductStock) {
        $prodStmt = $pdo->prepare("SELECT stock FROM product_details WHERE id = ? FOR UPDATE");
        $prodStmt->execute([$waste['product_id']]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $newStock = max(0, (int)$product['stock'] - (int)$waste['quantity']);
            $updProd = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");
            $updProd->execute([$newStock, $waste['product_id']]);

            try {
                $mv = $pdo->prepare("INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_type, reference_number, created_by, created_at)
                                      VALUES (?, ?, 'waste', ?, 'waste', ?, ?, NOW())");
                $mv->execute([$waste['product_id'], $waste['warehouse_id'], -(int)$waste['quantity'], $waste['waste_id'], $adminUsername]);
            } catch (PDOException $e) {
                // stock_movements may not exist yet (owned by another agent's
                // migration) — never block waste approval on this.
                error_log("stock_movements insert skipped: " . $e->getMessage());
            }
        }
    }

    $upd = $pdo->prepare("UPDATE waste_records SET status = ?, approved_by = ?, updated_by = ? WHERE id = ?");
    $upd->execute([$newStatus, $adminUsername, $adminUsername, $id]);

    $pdo->commit();

    log_audit($pdo, 'approve', 'waste', $id, ['status' => $oldStatus], ['status' => $newStatus, 'stock_deducted' => $shouldDeductStock]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error approving waste record: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update waste record: ' . $e->getMessage()]);
}
