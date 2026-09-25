<?php
// List delivery challans with optional filters: status, sales_order_id, q.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$status = trim($_GET['status'] ?? '');
$sales_order_id = trim($_GET['sales_order_id'] ?? '');
$q = trim($_GET['q'] ?? '');

try {
    $sql = "SELECT dc.*, so.so_number FROM delivery_challans dc
            LEFT JOIN sales_orders so ON so.id = dc.sales_order_id WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $sql .= " AND dc.delivery_status = ?";
        $params[] = $status;
    }
    if ($sales_order_id !== '') {
        $sql .= " AND dc.sales_order_id = ?";
        $params[] = $sales_order_id;
    }
    if ($q !== '') {
        $sql .= " AND (dc.dc_number LIKE ? OR dc.customer_name LIKE ? OR dc.customer_mobile LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY dc.id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'delivery_challans' => $rows]);
} catch (PDOException $e) {
    error_log("Error listing delivery challans: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'delivery_challans' => []]);
}
