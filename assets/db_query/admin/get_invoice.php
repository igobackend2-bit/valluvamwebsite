<?php
// Single invoice + its line items — for detail/print/payment views.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT inv.*, so.so_number FROM invoices inv
                            LEFT JOIN sales_orders so ON so.id = inv.sales_order_id WHERE inv.id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT ii.*, pd.product_name FROM invoice_items ii
                                 LEFT JOIN product_details pd ON pd.id = ii.product_id
                                 WHERE ii.invoice_id = ? ORDER BY ii.id ASC");
    $itemsStmt->execute([$id]);
    $invoice['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'invoice' => $invoice]);
} catch (PDOException $e) {
    error_log("Error fetching invoice: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load invoice']);
}
