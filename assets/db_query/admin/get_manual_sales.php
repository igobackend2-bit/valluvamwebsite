<?php
// List manual sales entries with optional filters: payment_status, q, date_from, date_to.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();
// FIX (3 Oct 2026): only staff who enter manual sales (Executive, Manager), the Accounts Team (money to collect) and the CEO (view) can read manual sales
require_once __DIR__ . '/../../../admin/includes/role_access.php';
$msKeys = array_merge([role_access_key((string)($_SESSION['admin_role_name'] ?? ''))], user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)));
if (!array_intersect(['accounts', 'ceo'], $msKeys)) require_permission($pdo, 'manual_sales.create');

$payment_status = trim($_GET['payment_status'] ?? '');
$q = trim($_GET['q'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

try {
    $sql = "SELECT m.*, (SELECT COALESCE(SUM(t.amount),0) FROM accounts_transactions t WHERE t.type = 'payment_received' AND t.status = 'completed' AND t.reference_type = 'manual_sale' AND t.reference_number = m.sale_number) AS received_later
             FROM manual_sales m WHERE 1=1";
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
