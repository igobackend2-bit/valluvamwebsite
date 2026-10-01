<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$admin_username  = $_SESSION['admin_username'] ?? 'Admin';
$admin_full_name = $_SESSION['admin_full_name'] ?? $admin_username;
$admin_role_name = $_SESSION['admin_role_name'] ?? '';
$admin_role_id    = $_SESSION['admin_role_id'] ?? null;

// Managers review purchase requests only; they must not access sales, customer,
// inventory, financial, supplier, or system-administration pages by direct URL.
// (1 Oct 2026) The same rule now covers every page-limited role — see role_access.php.
require_once __DIR__ . '/role_access.php';
$role_user_dash = [];   // dashboards the Super Admin gave this user
if ((int)($admin_role_id ?? 0) !== 1 && !empty($_SESSION['admin_user_id']) && is_file(__DIR__ . '/../../assets/db_query/config.php')) {
    try { require_once __DIR__ . '/../../assets/db_query/config.php'; $role_user_dash = user_dash_keys($pdo ?? null, (int)$_SESSION['admin_user_id']); } catch (Throwable $e) { $role_user_dash = []; }
}
$role_allowed_pages = role_access_pages((string)$admin_role_name, $role_user_dash);
if ($role_allowed_pages !== null) {
    if (!in_array(basename($_SERVER['PHP_SELF']), $role_allowed_pages, true)) {
        header('Location: index.php');
        exit;
    }
}
?>
