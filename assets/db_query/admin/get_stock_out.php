<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT so.*, w.name AS warehouse_name
                            FROM stock_outs so
                            LEFT JOIN warehouses w ON w.id = so.warehouse_id
                            WHERE so.id = ?");
    $stmt->execute([$id]);
    $stockOut = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stockOut) {
        echo json_encode(['status' => 'error', 'message' => 'Stock out record not found']);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT soi.*, pd.product_name
                                 FROM stock_out_items soi
                                 LEFT JOIN product_details pd ON pd.id = soi.product_id
                                 WHERE soi.stock_out_id = ?
                                 ORDER BY soi.id ASC");
    $itemsStmt->execute([$id]);
    $stockOut['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'stock_out' => $stockOut]);
} catch (PDOException $e) {
    error_log("Error fetching stock out: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load stock out record: ' . $e->getMessage()]);
}
