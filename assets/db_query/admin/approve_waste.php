<?php
// Moves a waste record's status forward (approved/processed/disposed/cancelled).
// When the transition is reported -> approved AND the record has a
// product_id + quantity, this also transactionally reduces product_details.stock
// (SELECT...FOR UPDATE, clamped at 0) and best-effort inserts a stock_movements
// row (owned by another agent's migration — try/catch, ignore if it doesn't
// exist yet, per this codebase's non-blocking cross-module integration pattern).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

// FIX (3 Oct 2026): wastage needs a proof photo and two approvals — Manager, then Admin — before stock goes down.
// The Manager's "Approve" sends it to the Admin (approval WST-… in Approvals); the Admin's approval replays this file.
require_once __DIR__ . '/erp_ext.php';
$wasteAdminStep = (($GLOBALS['erp_replay_request']['module'] ?? '') === 'waste_admin');
if (!$wasteAdminStep) require_permission($pdo, 'waste.approve');

$id = (int)($_POST['id'] ?? 0);
$newStatus = trim($_POST['status'] ?? 'approved');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

$validStatuses = ['approved','processed','disposed','cancelled'];
if (!$id || !in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'id and a valid target status are required']);
    exit;
}

$wPre = $id ? erp_row($pdo, "SELECT * FROM waste_records WHERE id = ?", [$id]) : null;
if ($wPre && $newStatus === 'approved' && $wPre['status'] === 'reported' && !$wasteAdminStep && (int)($_SESSION['admin_role_id'] ?? 0) !== 1) {
    // Manager step → send to the Admin
    if (!apr_policy($pdo, 'waste_admin')) { echo json_encode(['status' => 'error', 'message' => 'Run wastage_approval_migration.sql once in HeidiSQL to switch on Manager → Admin approval of wastage.']); exit; }
    if (!(int)erp_val($pdo, "SELECT COUNT(*) FROM erp_documents WHERE entity_type = 'waste_record' AND entity_id = ?", [$id])) {
        echo json_encode(['status' => 'error', 'message' => 'Attach a photo / proof of the wastage first (Proof button), then approve.']); exit;
    }
    if (erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'waste_admin' AND request_key = ? AND status IN ('submitted','under_review')", ['w' . $id])) {
        echo json_encode(['status' => 'error', 'message' => "{$wPre['waste_id']} is already waiting for the Admin."]); exit;
    }
    $apr = apr_open($pdo, 'waste_admin', 'w' . $id, $id, $wPre['waste_id'], "Wastage {$wPre['waste_id']}: " . ($wPre['quantity'] !== null ? $wPre['quantity'] . ' ' . $wPre['unit'] . ' · ' : '') . $wPre['reason'] . ' · Manager approved: ' . $adminUsername,
                    $wPre['estimated_value'] !== null ? (float)$wPre['estimated_value'] : null, 'approve_waste.php',
                    ['id' => $id, 'status' => 'approved', 'manager_approved_by' => $adminUsername, 'proof_doc_id' => (int)erp_val($pdo, "SELECT MAX(id) FROM erp_documents WHERE entity_type = 'waste_record' AND entity_id = ?", [$id])],
                    ['id' => $id, 'status' => 'cancelled']);
    log_audit($pdo, 'approve', 'waste', $id, ['status' => 'reported'], ['manager_approved_by' => $adminUsername, 'sent_to_admin' => $apr]);
    echo json_encode(['status' => 'success', 'message' => "{$wPre['waste_id']} approved by the Manager — sent to the Admin ({$apr}). Stock goes down after the Admin approves."]);
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
                // FIX (30 Sep 2026): previous_stock / new_stock are required columns, so this insert always
                // failed and approved waste never appeared in the Stock Movement ledger.
                $mv = $pdo->prepare("INSERT INTO stock_movements (product_id, sku, warehouse_id, movement_type, quantity, previous_stock, new_stock, reference_type, reference_number, reason, created_by, created_at)
                                      VALUES (?, ?, ?, 'waste', ?, ?, ?, 'waste', ?, ?, ?, NOW())");
                $mv->execute([$waste['product_id'], 'PRD-' . $waste['product_id'], $waste['warehouse_id'], $newStock - (int)$product['stock'], (int)$product['stock'], $newStock,
                              $waste['waste_id'], mb_substr('Waste: ' . $waste['reason'], 0, 255), $adminUsername]);
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
    if (in_array($newStatus, ['approved', 'cancelled'], true)) apr_close($pdo, 'waste_admin', 'w' . $id, $newStatus === 'cancelled' ? 'rejected' : 'approved', $_POST['_approval_remarks'] ?? null);   // FIX (3 Oct 2026)

    echo json_encode(['status' => 'success', 'message' => $newStatus === 'cancelled' ? "{$waste['waste_id']} rejected — stock unchanged." : ($wasteAdminStep ? "{$waste['waste_id']} approved by the Admin — stock reduced." : 'Updated.')]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error approving waste record: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update waste record: ' . $e->getMessage()]);
}
