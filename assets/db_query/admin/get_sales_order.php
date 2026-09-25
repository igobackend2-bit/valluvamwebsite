<?php
// Single sales order + its line items — used by detail/edit/print/convert views.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Sales order not found']);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT soi.*, pd.product_name FROM sales_order_items soi
                                 LEFT JOIN product_details pd ON pd.id = soi.product_id
                                 WHERE soi.sales_order_id = ? ORDER BY soi.id ASC");
    $itemsStmt->execute([$id]);
    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $dcStmt = $pdo->prepare("SELECT id, dc_number, delivery_status FROM delivery_challans WHERE sales_order_id = ? AND delivery_status != 'cancelled' ORDER BY id DESC");
    $dcStmt->execute([$id]);
    $order['delivery_challans'] = $dcStmt->fetchAll(PDO::FETCH_ASSOC);

    $invStmt = $pdo->prepare("SELECT id, invoice_number, status FROM invoices WHERE sales_order_id = ? AND status != 'cancelled' ORDER BY id DESC");
    $invStmt->execute([$id]);
    $order['invoices'] = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'sales_order' => $order]);
} catch (PDOException $e) {
    error_log("Error fetching sales order: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load sales order']);
}
