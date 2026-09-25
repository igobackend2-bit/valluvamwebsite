<?php
// List manual sales entries with optional filters: payment_status, q, date_from, date_to.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$payment_status = trim($_GET['payment_status'] ?? '');
$q = trim($_GET['q'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

try {
    $sql = "SELECT * FROM manual_sales WHERE 1=1";
    $params = [];

    if ($payment_status !== '') {
        $sql .= " AND payment_status = ?";
        $params[] = $payment_status;
    }
    if ($q !== '') {
        $sql .= " AND (sale_number LIKE ? OR customer_name LIKE ? OR customer_mobile LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($date_from !== '') {
        $sql .= " AND sales_date >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND sales_date <= ?";
        $params[] = $date_to;
    }
    $sql .= " ORDER BY id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'manual_sales' => $rows]);
} catch (PDOException $e) {
    error_log("Error listing manual sales: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'manual_sales' => []]);
}
