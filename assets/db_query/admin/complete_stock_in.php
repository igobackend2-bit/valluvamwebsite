<?php
// Marks a Stock In as 'completed' and, for EACH line item, transactionally
// increases product_details.stock and inserts a stock_movements row.
// Mirrors the transactional shape of adjust_stock.php in CONVENTIONS.md, but
// applied per line item, all inside a single transaction for the whole
// stock-in (so a completed stock-in either fully applies or not at all).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'stock_in.create');

$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM stock_ins WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $stockIn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stockIn) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Stock in record not found']);
        exit;
    }
    if (in_array($stockIn['status'], ['completed', 'cancelled'], true)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'This stock in is already ' . $stockIn['status']]);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT * FROM stock_in_items WHERE stock_in_id = ?");
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($items) === 0) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'This stock in has no line items to complete']);
        exit;
    }

    $movementInsert = $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id,
                                      quantity, previous_stock, new_stock, reference_type, reference_number,
                                      reason, created_by, created_at)
                                      VALUES ('stock_in', ?, ?, ?, ?, ?, ?, 'stock_in', ?, ?, ?, NOW())");
    $productLock = $pdo->prepare("SELECT stock, product_name FROM product_details WHERE id = ? FOR UPDATE");
    $productUpdate = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");

    foreach ($items as $item) {
        $productLock->execute([$item['product_id']]);
        $product = $productLock->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            continue; // product removed since the stock-in was created — skip rather than fail the whole batch
        }

        $previousStock = (int)$product['stock'];
        $newStock = $previousStock + (int)$item['quantity'];

        $productUpdate->execute([$newStock, $item['product_id']]);

        $movementInsert->execute([
            $item['product_id'],
            $item['sku'],
            $stockIn['warehouse_id'],
            (int)$item['quantity'],
            $previousStock,
            $newStock,
            $stockIn['stock_in_number'],
            'Stock in ' . $stockIn['stock_in_number'],
            $adminUsername,
        ]);
    }

    $update = $pdo->prepare("UPDATE stock_ins SET status = 'completed' WHERE id = ?");
    $update->execute([$id]);

    $pdo->commit();

    log_audit($pdo, 'update', 'stock_ins', $id, ['status' => $stockIn['status']], ['status' => 'completed']);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error completing stock in: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to complete stock in: ' . $e->getMessage()]);
}
