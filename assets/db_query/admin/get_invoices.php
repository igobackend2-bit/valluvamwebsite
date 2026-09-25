<?php
// List invoices with optional filters: status, customer_id, q, date_from,
// date_to, overdue=1 (due_date passed and not paid/cancelled).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$status = trim($_GET['status'] ?? '');
$customer_id = trim($_GET['customer_id'] ?? '');
$q = trim($_GET['q'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$overdue = ($_GET['overdue'] ?? '') === '1';

try {
    $sql = "SELECT * FROM invoices WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($customer_id !== '') {
        $sql .= " AND customer_id = ?";
        $params[] = $customer_id;
    }
    if ($q !== '') {
        $sql .= " AND (invoice_number LIKE ? OR customer_name LIKE ? OR customer_mobile LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($date_from !== '') {
        $sql .= " AND invoice_date >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND invoice_date <= ?";
        $params[] = $date_to;
    }
    if ($overdue) {
        $sql .= " AND due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('paid','cancelled')";
    }
    $sql .= " ORDER BY id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'invoices' => $rows]);
} catch (PDOException $e) {
    error_log("Error listing invoices: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'invoices' => []]);
}
