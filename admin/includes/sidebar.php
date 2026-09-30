<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
$role_id = $_SESSION['admin_role_id'] ?? null; // 1 = Super Admin
function nav_active($page, $current) { return $page === $current ? ' active' : ''; }
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
        <li><a href="index.php" class="<?= nav_active('index.php', $current_page) ?>"><i class="fas fa-gauge-high"></i> Dashboard</a></li>

        <li class="adm-nav-section">Sales</li>
        <li><a href="sales_orders.php" class="<?= nav_active('sales_orders.php', $current_page) ?>"><i class="fas fa-file-lines"></i> Sales Orders</a></li>
        <li><a href="manual_sales.php" class="<?= nav_active('manual_sales.php', $current_page) ?>"><i class="fas fa-cash-register"></i> Manual Sales Entry</a></li>
        <li><a href="credit_sale.php" class="<?= nav_active('credit_sale.php', $current_page) ?>"><i class="fas fa-hand-holding-dollar"></i> Credit Sale</a></li>
        <li><a href="invoices.php" class="<?= nav_active('invoices.php', $current_page) ?>"><i class="fas fa-file-invoice"></i> Invoices</a></li>
        <li><a href="orders.php" class="<?= nav_active('orders.php', $current_page) ?>"><i class="fas fa-basket-shopping"></i> Website Orders</a></li>
        <li><a href="sales_returns.php" class="<?= nav_active('sales_returns.php', $current_page) ?>"><i class="fas fa-rotate-left"></i> Sales Returns</a></li>

        <li class="adm-nav-section">Inventory</li>
        <li><a href="products.php" class="<?= nav_active('products.php', $current_page) ?>"><i class="fas fa-box-open"></i> Products</a></li>
        <li><a href="categories.php" class="<?= nav_active('categories.php', $current_page) ?>"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="homepage.php" class="<?= nav_active('homepage.php', $current_page) ?>"><i class="fas fa-house"></i> Homepage</a></li>
        <li><a href="inventory_overview.php" class="<?= nav_active('inventory_overview.php', $current_page) ?>"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li><a href="stock_in.php" class="<?= nav_active('stock_in.php', $current_page) ?>"><i class="fas fa-dolly"></i> Stock In</a></li>
        <li><a href="stock_out.php" class="<?= nav_active('stock_out.php', $current_page) ?>"><i class="fas fa-hand-holding-box"></i> Stock Out</a></li>
        <li><a href="stock_movements.php" class="<?= nav_active('stock_movements.php', $current_page) ?>"><i class="fas fa-arrow-right-arrow-left"></i> Stock Movement</a></li>
        <li><a href="warehouses.php" class="<?= nav_active('warehouses.php', $current_page) ?>"><i class="fas fa-building"></i> Warehouses</a></li>
        <li><a href="raw_materials.php" class="<?= nav_active('raw_materials.php', $current_page) ?>"><i class="fas fa-sack"></i> Raw Materials</a></li>
        <li><a href="repacking.php" class="<?= nav_active('repacking.php', $current_page) ?>"><i class="fas fa-box"></i> Repacking</a></li>
        <li><a href="stock_adjustments.php" class="<?= nav_active('stock_adjustments.php', $current_page) ?>"><i class="fas fa-scale-balanced"></i> Stock Adjustments</a></li>
        <li><a href="stock_valuation.php" class="<?= nav_active('stock_valuation.php', $current_page) ?>"><i class="fas fa-coins"></i> Stock Valuation</a></li>

        <!-- Purchases (added 30 Sep 2026) -->
        <li class="adm-nav-section">Purchases</li>
        <li><a href="purchase_dashboard.php" class="<?= nav_active('purchase_dashboard.php', $current_page) ?>"><i class="fas fa-cart-flatbed"></i> Purchase Dashboard</a></li>
        <li><a href="purchase_requests.php" class="<?= nav_active('purchase_requests.php', $current_page) ?>"><i class="fas fa-clipboard-list"></i> Purchase Requests</a></li>
        <li><a href="purchase_orders.php" class="<?= nav_active('purchase_orders.php', $current_page) ?>"><i class="fas fa-file-signature"></i> Purchase Orders</a></li>
        <li><a href="goods_receipts.php" class="<?= nav_active('goods_receipts.php', $current_page) ?>"><i class="fas fa-dolly"></i> Goods Receipts</a></li>
        <li><a href="purchase_invoices.php" class="<?= nav_active('purchase_invoices.php', $current_page) ?>"><i class="fas fa-file-invoice-dollar"></i> Purchase Invoices</a></li>
        <li><a href="purchase_returns.php" class="<?= nav_active('purchase_returns.php', $current_page) ?>"><i class="fas fa-arrow-rotate-left"></i> Purchase Returns</a></li>
        <li><a href="purchase_payments.php" class="<?= nav_active('purchase_payments.php', $current_page) ?>"><i class="fas fa-money-bill-wave"></i> Purchase Payments</a></li>
        <li><a href="purchase_history.php" class="<?= nav_active('purchase_history.php', $current_page) ?>"><i class="fas fa-clock-rotate-left"></i> Purchase History</a></li>

        <li class="adm-nav-section">Accounts</li>
        <li><a href="accounts.php" class="<?= nav_active('accounts.php', $current_page) ?>"><i class="fas fa-wallet"></i> Transactions</a></li>
        <li><a href="expenses.php" class="<?= nav_active('expenses.php', $current_page) ?>"><i class="fas fa-receipt"></i> Expenses</a></li>
        <li><a href="receivables.php" class="<?= nav_active('receivables.php', $current_page) ?>"><i class="fas fa-hand-holding-dollar"></i> Receivables</a></li>

        <li class="adm-nav-section">Assets</li>
        <li><a href="assets.php" class="<?= nav_active('assets.php', $current_page) ?>"><i class="fas fa-boxes-stacked"></i> Assets</a></li>

        <li class="adm-nav-section">Waste</li>
        <li><a href="waste.php" class="<?= nav_active('waste.php', $current_page) ?>"><i class="fas fa-trash"></i> Waste Records</a></li>

        <li class="adm-nav-section">Customers</li>
        <li><a href="customers.php" class="<?= nav_active('customers.php', $current_page) ?>"><i class="fas fa-users"></i> Customers</a></li>
        <li><a href="account_requests.php" class="<?= nav_active('account_requests.php', $current_page) ?>"><i class="fas fa-user-gear"></i> Account Requests</a></li>
        <li><a href="leads.php" class="<?= nav_active('leads.php', $current_page) ?>"><i class="fas fa-envelope-open-text"></i> Leads</a></li>
        <li><a href="reviews.php" class="<?= nav_active('reviews.php', $current_page) ?>"><i class="fas fa-star"></i> Reviews</a></li>
        <li><a href="feedback.php" class="<?= nav_active('feedback.php', $current_page) ?>"><i class="fas fa-comment-dots"></i> Feedback</a></li>
        <li><a href="coupons.php" class="<?= nav_active('coupons.php', $current_page) ?>"><i class="fas fa-tags"></i> Coupons</a></li>

        <li class="adm-nav-section">Suppliers</li>
        <li><a href="suppliers.php" class="<?= nav_active('suppliers.php', $current_page) ?>"><i class="fas fa-truck-field"></i> Suppliers</a></li>
        <li><a href="supplier_ledger.php" class="<?= nav_active('supplier_ledger.php', $current_page) ?>"><i class="fas fa-book"></i> Supplier Ledger</a></li>

        <li class="adm-nav-section">Reports &amp; admin</li>
        <li><a href="reports.php" class="<?= nav_active('reports.php', $current_page) ?>"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="profit_loss.php" class="<?= nav_active('profit_loss.php', $current_page) ?>"><i class="fas fa-scale-unbalanced"></i> Profit &amp; Loss</a></li>
        <li><a href="product_profitability.php" class="<?= nav_active('product_profitability.php', $current_page) ?>"><i class="fas fa-chart-pie"></i> Product Profitability</a></li>
        <li><a href="audit_logs.php" class="<?= nav_active('audit_logs.php', $current_page) ?>"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
        <?php if ((int)$role_id === 1): ?>
        <li><a href="admin_users.php" class="<?= nav_active('admin_users.php', $current_page) ?>"><i class="fas fa-user-shield"></i> Admin Users</a></li>
        <?php endif; ?>
        <li><a href="my_account.php" class="<?= nav_active('my_account.php', $current_page) ?>"><i class="fas fa-id-badge"></i> My Account</a></li>
        <li><a href="settings.php" class="<?= nav_active('settings.php', $current_page) ?>"><i class="fas fa-gear"></i> Settings</a></li>

        <li class="adm-nav-divider"></li>
        <li><a href="../index.php" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View site</a></li>
        <li><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Log out</a></li>
    </ul>
    <div class="adm-sidebar-foot">Valluvam Products</div>
</nav>
<script>
// Keep the currently open admin page visible in the long, scrollable navigation.
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.adm-sidebar');
    const activeLink = sidebar && sidebar.querySelector('a.active');
    if (!sidebar || !activeLink) return;

    sidebar.scrollTop = Math.max(0, activeLink.offsetTop - (sidebar.clientHeight - activeLink.offsetHeight) / 2);
});
</script>
