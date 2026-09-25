<?php
// Read-only audit log listing with filters: module, username, action,
// date_from, date_to, offset (50 rows per page, "Load more" pagination).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'audit_logs.view');

$module    = trim($_GET['module'] ?? '');
$username  = trim($_GET['username'] ?? '');
$action    = trim($_GET['action'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
$offset    = max(0, (int)($_GET['offset'] ?? 0));
$limit     = 50;

try {
    $sql = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];

    if ($module !== '') {
        $sql .= " AND module = ?";
        $params[] = $module;
    }
    if ($username !== '') {
        $sql .= " AND username LIKE ?";
        $params[] = "%$username%";
    }
    if ($action !== '') {
        $sql .= " AND action = ?";
        $params[] = $action;
    }
    if ($date_from !== '') {
        $sql .= " AND created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '') {
        $sql .= " AND created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    $sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Distinct modules seen, for the filter dropdown.
    $modules = [];
    try {
        $modStmt = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC");
        $modules = $modStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $modules = [];
    }

    echo json_encode(['status' => 'success', 'logs' => $logs, 'modules' => $modules, 'has_more' => count($logs) === $limit]);
} catch (PDOException $e) {
    error_log("Error listing audit logs: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'logs' => [], 'modules' => [], 'has_more' => false]);
}
