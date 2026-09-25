<?php
// Manual emergency "mark as Out of Stock" control for admin, used from the
// Inventory Overview page. Separate from any automated stock deduction —
// this lets admin force a product's stock to 0 on demand (e.g. a supply
// issue, a recall, a damaged batch) without touching any other stock flow.
//
// Sets product_details.stock to 0 and logs a stock_movements row (same
// shape/columns as save_stock_out.php) so it shows up correctly in the
// existing Stock Out history, dashboard alerts and the daily Stock Out
// email report — exactly like any other stock-out, just admin-triggered.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.adjust');

$product_id = $_POST['product_id'] ?? 0;
$reason = trim($_POST['reason'] ?? '');

if (!$product_id) {
    echo json_encode(['status' => 'error', 'message' => 'product_id is required']);
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, product_name, stock, CONCAT('PRD-', id) AS sku FROM product_details WHERE id = ? FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }

    $previousStock = (int)$product['stock'];

    if ($previousStock === 0) {
        // Already at 0 — nothing to change, but still a success from the
        // admin's point of view (the product is already Out of Stock).
        $pdo->commit();
        echo json_encode(['status' => 'success', 'stock' => 0, 'already_zero' => true]);
        exit;
    }

    $update = $pdo->prepare("UPDATE product_details SET stock = 0 WHERE id = ?");
    $update->execute([$product_id]);

    try {
        $movementInsert = $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id,
                                          quantity, previous_stock, new_stock, reference_type, reference_number,
                                          reason, created_by, created_at)
                                          VALUES ('stock_out', ?, ?, 1, ?, ?, 0, 'other', NULL, ?, ?, NOW())");
        $movementInsert->execute([
            $product_id,
            $product['sku'],
            -$previousStock,
            $previousStock,
            $reason !== '' ? $reason : 'Manually marked Out of Stock by admin (emergency)',
            $adminUsername,
        ]);
    } catch (PDOException $e) {
        // stock_movements not present — non-fatal, the stock change itself still applies.
        error_log("mark_out_of_stock: stock_movements insert failed: " . $e->getMessage());
    }

    $pdo->commit();

    log_audit($pdo, 'update', 'inventory', $product_id, ['stock' => $previousStock], ['stock' => 0, 'reason' => $reason ?: 'Manual out-of-stock']);

    echo json_encode(['status' => 'success', 'stock' => 0, 'product_name' => $product['product_name']]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error marking product out of stock: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update stock: ' . $e->getMessage()]);
}
