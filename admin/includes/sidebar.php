<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-user-shield"></i> Admin Panel</h4>
        <small>Valluvam Products</small>
    </div>
    <ul class="sidebar-menu">
        <li><a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="orders.php" class="<?= $current_page === 'orders.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Orders</a></li>
        <li><a href="products.php" class="<?= $current_page === 'products.php' ? 'active' : '' ?>"><i class="fas fa-box"></i> Products</a></li>
        <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

