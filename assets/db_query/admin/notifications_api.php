<?php
// ============================================================================
// Admin notifications (added 1 Oct 2026). One central alert list, refreshed
// from the live data at most every 5 minutes (or on demand). Every alert only
// shows to admins who hold its permission.
// ============================================================================
require_once __DIR__ . '/erp_ext.php';

$action = (string)erp_input('action', 'count');
erpx_guard($pdo, 'notifications.view');
$uid = (int)($_SESSION['admin_user_id'] ?? 0);

try {
    switch ($action) {
        case 'count':
            ntf_refresh($pdo);
            $rows = ntf_visible($pdo);
            $apr = 0;
            foreach (erp_rows($pdo, "SELECT r.module, p.approver_perm, COUNT(*) n FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module WHERE r.status IN ('submitted','under_review') GROUP BY r.module, p.approver_perm") as $a)
                if (erp_can($pdo, $a['approver_perm'] ?: 'purchase.approve')) $apr += (int)$a['n'];
            erp_out(['status' => 'success', 'unread' => count(array_filter($rows, function ($n) { return !$n['is_read']; })), 'open' => count($rows), 'approvals' => $apr,
                     'critical' => count(array_filter($rows, function ($n) { return $n['severity'] === 'critical'; }))]);

        case 'list':
            ntf_refresh($pdo, erp_input('refresh') === '1');
            erp_out(['status' => 'success', 'rows' => ntf_visible($pdo, erp_input('all') !== '1')]);

        case 'read':
        case 'read_all':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            $ids = $action === 'read' ? [(int)erp_input('id')] : array_map(function ($n) { return (int)$n['id']; }, ntf_visible($pdo));
            $ins = $pdo->prepare("INSERT IGNORE INTO admin_notification_reads (notification_id, admin_user_id) VALUES (?,?)");
            foreach ($ids as $id) if ($id) $ins->execute([$id, $uid]);
            erp_out(['status' => 'success']);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'notifications');
}
