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
    $stmt = $pdo->prepare("SELECT si.*, s.supplier_name, s.company_name, w.name AS warehouse_name
                            FROM stock_ins si
                            LEFT JOIN suppliers s ON s.id = si.supplier_id
                            LEFT JOIN warehouses w ON w.id = si.warehouse_id
                            WHERE si.id = ?");
    $stmt->execute([$id]);
    $stockIn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stockIn) {
        echo json_encode(['status' => 'error', 'message' => 'Stock in record not found']);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT sii.*, pd.product_name
                                 FROM stock_in_items sii
                                 LEFT JOIN product_details pd ON pd.id = sii.product_id
                                 WHERE sii.stock_in_id = ?
                                 ORDER BY sii.id ASC");
    $itemsStmt->execute([$id]);
    $stockIn['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'stock_in' => $stockIn]);
} catch (PDOException $e) {
    error_log("Error fetching stock in: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load stock in record: ' . $e->getMessage()]);
}
