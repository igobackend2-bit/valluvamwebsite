<?php
// List credit sales with optional filters: status, q, date_from, date_to.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/credit_sale_helper.php';

require_admin_session();
ensure_credit_sale_tables($pdo);

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

try {
    $sql = "SELECT * FROM credit_sales WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (credit_number LIKE ? OR customer_name LIKE ? OR customer_mobile LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($date_from !== '') {
        $sql .= " AND sale_date >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND sale_date <= ?";
        $params[] = $date_to;
    }
    $sql .= " ORDER BY id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['balance_due'] = round((float)$r['grand_total'] - (float)$r['amount_paid'], 2);
    }
    unset($r);

    echo json_encode(['status' => 'success', 'credit_sales' => $rows]);
} catch (PDOException $e) {
    error_log("Error listing credit sales: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'credit_sales' => []]);
}
