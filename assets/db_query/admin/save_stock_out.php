<?php
// Creates a Stock Out (standalone manual entries only — internal_transfer /
// damage / other; the sales/DC/manual-sales-triggered stock-outs are created
// programmatically by those other modules, not through this admin form,
// though every stock-out ends up listed on admin/stock_out.php regardless of
// origin). On create, transactionally reduces product_details.stock per
// item and inserts a stock_movements row per item, exactly like the
// adjust_stock.php shape in CONVENTIONS.md.
//
// SIMPLIFICATION: stock is always clamped at 0 (max(0, ...)), same as the
// existing adjust_stock.php. The brief's "authorized admin setting" to allow
// negative stock is NOT built in this pass.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'stock_out.create');

$stock_out_date = $_POST['stock_out_date'] ?? date('Y-m-d');
$reference_type = $_POST['reference_type'] ?? 'other';
$reference_number = trim($_POST['reference_number'] ?? '');
$warehouse_id = $_POST['warehouse_id'] ?? 1;
$vehicle_number = trim($_POST['vehicle_number'] ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$authorized_by = trim($_POST['authorized_by'] ?? '');
$itemsRaw = $_POST['items'] ?? '[]';

$allowedRefTypes = ['sales_order', 'delivery_challan', 'manual_sales', 'internal_transfer', 'damage', 'waste', 'other'];
if (!in_array($reference_type, $allowedRefTypes, true)) {
    $reference_type = 'other';
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one line item is required']);
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    $stock_out_number = next_document_number($pdo, 'stock_out', 'SOUT');

    $stmt = $pdo->prepare("INSERT INTO stock_outs (stock_out_number, stock_out_date, reference_type, reference_number,
                            warehouse_id, vehicle_number, customer_name, reason, authorized_by, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $stock_out_number, $stock_out_date, $reference_type, $reference_number ?: null, $warehouse_id ?: 1,
        $vehicle_number ?: null, $customer_name ?: null, $reason ?: null, $authorized_by ?: null, $adminUsername
    ]);
    $stockOutId = $pdo->lastInsertId();

    $itemInsert = $pdo->prepare("INSERT INTO stock_out_items (stock_out_id, product_id, sku, quantity, unit)
                                  VALUES (?, ?, ?, ?, ?)");
    $productLock = $pdo->prepare("SELECT stock, product_name FROM product_details WHERE id = ? FOR UPDATE");
    $productUpdate = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");
    $movementInsert = $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id,
                                      quantity, previous_stock, new_stock, reference_type, reference_number,
                                      reason, created_by, created_at)
                                      VALUES ('stock_out', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    foreach ($items as $item) {
        $productId = $item['product_id'] ?? 0;
        $qty = (int)($item['quantity'] ?? 0);
        if (!$productId || $qty <= 0) {
            continue;
        }
        $sku = trim($item['sku'] ?? '') ?: null;
        $unit = trim($item['unit'] ?? '') ?: 'pcs';

        $itemInsert->execute([$stockOutId, $productId, $sku, $qty, $unit]);

        $productLock->execute([$productId]);
        $product = $productLock->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            continue; // unknown product — item row kept for record, stock untouched
        }

        $previousStock = (int)$product['stock'];
        $newStock = max(0, $previousStock - $qty);

        $productUpdate->execute([$newStock, $productId]);

        $movementInsert->execute([
            $productId, $sku, $warehouse_id ?: 1, -$qty, $previousStock, $newStock,
            $reference_type, $reference_number ?: $stock_out_number, $reason ?: null, $adminUsername
        ]);
    }

    $pdo->commit();

    log_audit($pdo, 'create', 'stock_outs', $stockOutId, null, [
        'stock_out_number' => $stock_out_number, 'reference_type' => $reference_type, 'items' => $items
    ]);

    echo json_encode(['status' => 'success', 'id' => $stockOutId, 'stock_out_number' => $stock_out_number]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving stock out: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save stock out: ' . $e->getMessage()]);
}
