<?php
// Single delivery challan + its line items — for detail/edit/print views.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT dc.*, so.so_number FROM delivery_challans dc
                            LEFT JOIN sales_orders so ON so.id = dc.sales_order_id WHERE dc.id = ?");
    $stmt->execute([$id]);
    $dc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dc) {
        echo json_encode(['status' => 'error', 'message' => 'Delivery challan not found']);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT dci.*, pd.product_name FROM delivery_challan_items dci
                                 LEFT JOIN product_details pd ON pd.id = dci.product_id
                                 WHERE dci.delivery_challan_id = ? ORDER BY dci.id ASC");
    $itemsStmt->execute([$id]);
    $dc['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'delivery_challan' => $dc]);
} catch (PDOException $e) {
    error_log("Error fetching delivery challan: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load delivery challan']);
}
