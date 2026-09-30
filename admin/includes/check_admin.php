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
if ($admin_role_name === 'Manager') {
    $manager_pages = ['index.php', 'purchase_requests.php', 'my_account.php', 'logout.php'];
    if (!in_array(basename($_SERVER['PHP_SELF']), $manager_pages, true)) {
        header('Location: index.php');
        exit;
    }
}
?>
