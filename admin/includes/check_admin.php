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
$role_allowed_pages = role_access_pages((string)$admin_role_name);
if ($role_allowed_pages !== null) {
    if (!in_array(basename($_SERVER['PHP_SELF']), $role_allowed_pages, true)) {
        header('Location: index.php');
        exit;
    }
}
?>
