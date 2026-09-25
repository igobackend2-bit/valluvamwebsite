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
?>
