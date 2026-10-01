<?php
// ============================================================================
// Dashboards per admin user — Super Admin only (added 1 Oct 2026)
//   GET  ?action=list                         → dashboards + each user's choice
//   POST action=save&user_id= | &username=  &keys=["l1","accounts"]
// Needs user_dashboards_migration.sql.
// ============================================================================
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';
header('Content-Type: application/json');
require_admin_session();
if ((int)($_SESSION['admin_role_id'] ?? 0) !== 1) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Only the Super Admin can choose dashboards for users.']); exit; }

$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'list');
$out = function (array $d) { echo json_encode($d); exit; };
try {
    $pdo->query("SELECT 1 FROM admin_user_dashboards LIMIT 1");
} catch (PDOException $e) {
    $out(['status' => $action === 'list' ? 'success' : 'error', 'installed' => false, 'dashboards' => [], 'users' => new stdClass(),
          'message' => 'Run user_dashboards_migration.sql once to switch on dashboards per user.']);
}
$defs = role_dash_defs();
try {
    if ($action === 'list') {
        $map = [];
        foreach ($pdo->query("SELECT user_id, dash_key FROM admin_user_dashboards ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC) as $r) $map[$r['user_id']][] = $r['dash_key'];
        $roleDefault = [];
        foreach ($pdo->query("SELECT id, name FROM admin_roles")->fetchAll(PDO::FETCH_ASSOC) as $r) { $k = role_access_key($r['name']); $roleDefault[$r['id']] = $k === 'super' ? 'all' : ($k ?: ''); }
        $out(['status' => 'success', 'installed' => true, 'dashboards' => array_map(fn($k, $d) => ['key' => $k, 'label' => $d['label']], array_keys($defs), $defs),
              'users' => (object)$map, 'role_default' => (object)$roleDefault]);
    }
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid && trim((string)($_POST['username'] ?? '')) !== '') {
            $st = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?"); $st->execute([trim((string)$_POST['username'])]); $uid = (int)$st->fetchColumn();
        }
        $st = $pdo->prepare("SELECT id, username, role_id FROM admin_users WHERE id = ?"); $st->execute([$uid]); $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) $out(['status' => 'error', 'message' => 'Admin user not found.']);
        $keys = json_decode((string)($_POST['keys'] ?? '[]'), true);
        $keys = array_values(array_intersect(array_keys($defs), is_array($keys) ? $keys : []));
        $old = $pdo->prepare("SELECT dash_key FROM admin_user_dashboards WHERE user_id = ?"); $old->execute([$uid]); $oldKeys = $old->fetchAll(PDO::FETCH_COLUMN);
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM admin_user_dashboards WHERE user_id = ?")->execute([$uid]);
        $ins = $pdo->prepare("INSERT INTO admin_user_dashboards (user_id, dash_key, created_by) VALUES (?,?,?)");
        foreach ($keys as $k) $ins->execute([$uid, $k, $_SESSION['admin_username'] ?? null]);
        $pdo->commit();
        log_audit($pdo, 'update', 'admin_user_dashboards', $uid, ['dashboards' => $oldKeys], ['username' => $u['username'], 'dashboards' => $keys]);
        $out(['status' => 'success', 'message' => $keys ? 'Dashboards saved for ' . $u['username'] . '.' : $u['username'] . ' now uses the role\'s normal dashboard.']);
    }
    $out(['status' => 'error', 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[user dashboards] ' . $e->getMessage());
    $out(['status' => 'error', 'message' => 'Could not save the dashboards. Please try again.']);
}
