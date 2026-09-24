<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="adm-sidebar" aria-label="Admin navigation">
    <div class="adm-brand">
        <img src="../images/logo.png" alt="">
        <div class="adm-brand-text">
            <strong>Valluvam</strong>
            <span>Admin</span>
        </div>
    </div>
    <ul class="adm-nav">
        <li><a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
        <li><a href="orders.php" class="<?= $current_page === 'orders.php' ? 'active' : '' ?>"><i class="fas fa-receipt"></i> Orders</a></li>
        <li><a href="products.php" class="<?= $current_page === 'products.php' ? 'active' : '' ?>"><i class="fas fa-box-open"></i> Products</a></li>
        <li class="adm-nav-divider"></li>
        <li><a href="../index.php" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View site</a></li>
        <li><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Log out</a></li>
    </ul>
    <div class="adm-sidebar-foot">Valluvam Products</div>
</nav>
