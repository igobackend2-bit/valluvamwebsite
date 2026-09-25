<?php
// List sales orders with optional filters: status, customer_id, q (search
// so_number/customer_name/mobile), date_from, date_to.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$status      = trim($_GET['status'] ?? '');
$customer_id = trim($_GET['customer_id'] ?? '');
$q           = trim($_GET['q'] ?? '');
$date_from   = trim($_GET['date_from'] ?? '');
$date_to     = trim($_GET['date_to'] ?? '');

try {
    $sql = "SELECT so.*,
                   (SELECT COUNT(*) FROM delivery_challans dc WHERE dc.sales_order_id = so.id AND dc.delivery_status != 'cancelled') AS dc_count,
                   (SELECT COUNT(*) FROM invoices inv WHERE inv.sales_order_id = so.id AND inv.status != 'cancelled') AS invoice_count
            FROM sales_orders so WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $sql .= " AND so.status = ?";
        $params[] = $status;
    }
    if ($customer_id !== '') {
        $sql .= " AND so.customer_id = ?";
        $params[] = $customer_id;
    }
    if ($q !== '') {
        $sql .= " AND (so.so_number LIKE ? OR so.customer_name LIKE ? OR so.customer_mobile LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($date_from !== '') {
        $sql .= " AND so.order_date >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND so.order_date <= ?";
        $params[] = $date_to;
    }
    $sql .= " ORDER BY so.id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'sales_orders' => $orders]);
} catch (PDOException $e) {
    error_log("Error listing sales orders: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'sales_orders' => []]);
}
