<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
$role_id = $_SESSION['admin_role_id'] ?? null; // 1 = Super Admin
function nav_active($page, $current) { return $page === $current ? ' active' : ''; }
?>
<style>
.adm-nav-combined summary { display: flex; align-items: center; gap: 11px; padding: 10px 12px; border-radius: var(--adm-radius-sm); color: rgba(238,244,239,0.72); cursor: pointer; font-size: 14px; font-weight: 500; list-style: none; }
.adm-nav-combined summary::-webkit-details-marker { display: none; }
.adm-nav-combined summary::after { content: '\f107'; margin-left: auto; font: var(--fa-font-solid); font-size: 12px; transition: transform 160ms ease; }
.adm-nav-combined details[open] summary::after { transform: rotate(180deg); }
.adm-nav-combined summary:hover { background: rgba(255,255,255,0.07); color: #fff; }
.adm-nav-combined-links { padding-left: 12px; }
.adm-nav-combined-links a { font-size: 13px; }
</style>
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
        <li class="adm-nav-combined">
            <details<?= in_array($current_page, ['sales_orders.php', 'manual_sales.php'], true) ? ' open' : '' ?>>
                <summary><i class="fas fa-file-lines"></i> Sales Orders &amp; Manual Sales</summary>
                <div class="adm-nav-combined-links">
                    <a href="sales_orders.php" class="<?= nav_active('sales_orders.php', $current_page) ?>"><i class="fas fa-file-lines"></i> Sales Orders</a>
                    <a href="manual_sales.php" class="<?= nav_active('manual_sales.php', $current_page) ?>"><i class="fas fa-cash-register"></i> Manual Sales Entry</a>
                </div>
            </details>
        </li>
        <li><a href="credit_sale.php" class="<?= nav_active('credit_sale.php', $current_page) ?>"><i class="fas fa-hand-holding-dollar"></i> Credit Sale</a></li>
        <li><a href="invoices.php" class="<?= nav_active('invoices.php', $current_page) ?>"><i class="fas fa-file-invoice"></i> Invoices</a></li>
        <li><a href="orders.php" class="<?= nav_active('orders.php', $current_page) ?>"><i class="fas fa-basket-shopping"></i> Website Orders</a></li>
        <li><a href="sales_returns.php" class="<?= nav_active('sales_returns.php', $current_page) ?>"><i class="fas fa-rotate-left"></i> Sales Returns</a></li>
        <li><a href="fulfilment.php" class="<?= nav_active('fulfilment.php', $current_page) ?>"><i class="fas fa-boxes-packing"></i> Fulfilment</a></li>
        <li><a href="credit_notes.php" class="<?= nav_active('credit_notes.php', $current_page) ?>"><i class="fas fa-file-circle-minus"></i> Credit Notes</a></li>
        <li><a href="customer_360.php" class="<?= nav_active('customer_360.php', $current_page) ?>"><i class="fas fa-address-card"></i> Customer 360</a></li>
        <li><a href="sales_channels.php" class="<?= nav_active('sales_channels.php', $current_page) ?>"><i class="fas fa-shop"></i> Sales Channels</a></li>

        <li class="adm-nav-section">Inventory</li>
        <li><a href="products.php" class="<?= nav_active('products.php', $current_page) ?>"><i class="fas fa-box-open"></i> Products</a></li>
        <li><a href="categories.php" class="<?= nav_active('categories.php', $current_page) ?>"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="homepage.php" class="<?= nav_active('homepage.php', $current_page) ?>"><i class="fas fa-house"></i> Homepage</a></li>
        <li><a href="inventory_overview.php" class="<?= nav_active('inventory_overview.php', $current_page) ?>"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li><a href="stock_in.php" class="<?= nav_active('stock_in.php', $current_page) ?>"><i class="fas fa-dolly"></i> Stock In</a></li>
        <li><a href="stock_out.php" class="<?= nav_active('stock_out.php', $current_page) ?>"><i class="fas fa-hand-holding-box"></i> Stock Out</a></li>
        <li><a href="stock_movements.php" class="<?= nav_active('stock_movements.php', $current_page) ?>"><i class="fas fa-arrow-right-arrow-left"></i> Stock Movement</a></li>
        <li><a href="warehouse_stock.php" class="<?= nav_active('warehouse_stock.php', $current_page) ?>"><i class="fas fa-cubes"></i> Stock by Warehouse</a></li>
        <li><a href="stock_transfers.php" class="<?= nav_active('stock_transfers.php', $current_page) ?>"><i class="fas fa-right-left"></i> Stock Transfers</a></li>
        <li><a href="stock_counts.php" class="<?= nav_active('stock_counts.php', $current_page) ?>"><i class="fas fa-list-check"></i> Stock Counts</a></li>
        <li><a href="batches.php" class="<?= nav_active('batches.php', $current_page) ?>"><i class="fas fa-barcode"></i> Batches &amp; Expiry</a></li>
        <li><a href="warehouses.php" class="<?= nav_active('warehouses.php', $current_page) ?>"><i class="fas fa-building"></i> Warehouses</a></li>
        <li><a href="warehouse_locations.php" class="<?= nav_active('warehouse_locations.php', $current_page) ?>"><i class="fas fa-location-dot"></i> Warehouse Locations</a></li>
        <li><a href="raw_materials.php" class="<?= nav_active('raw_materials.php', $current_page) ?>"><i class="fas fa-sack"></i> Raw Materials</a></li>
        <li><a href="repacking.php" class="<?= nav_active('repacking.php', $current_page) ?>"><i class="fas fa-box"></i> Repacking</a></li>
        <li><a href="stock_adjustments.php" class="<?= nav_active('stock_adjustments.php', $current_page) ?>"><i class="fas fa-scale-balanced"></i> Stock Adjustments</a></li>
        <li><a href="stock_valuation.php" class="<?= nav_active('stock_valuation.php', $current_page) ?>"><i class="fas fa-coins"></i> Stock Valuation</a></li>

        <!-- Purchases (added 30 Sep 2026) -->
        <li class="adm-nav-section">Purchases</li>
        <li><a href="purchase_dashboard.php" class="<?= nav_active('purchase_dashboard.php', $current_page) ?>"><i class="fas fa-cart-flatbed"></i> Purchase Dashboard</a></li>
        <li><a href="purchase_requests.php" class="<?= nav_active('purchase_requests.php', $current_page) ?>"><i class="fas fa-clipboard-list"></i> Purchase Requests</a></li>
        <li><a href="rfqs.php" class="<?= nav_active('rfqs.php', $current_page) ?>"><i class="fas fa-envelope-open-text"></i> RFQ &amp; Quotations</a></li>
        <li><a href="purchase_orders.php" class="<?= nav_active('purchase_orders.php', $current_page) ?>"><i class="fas fa-file-signature"></i> Purchase Orders</a></li>
        <li><a href="shipments.php" class="<?= nav_active('shipments.php', $current_page) ?>"><i class="fas fa-truck"></i> Transport / Logistics</a></li>
        <li><a href="goods_receipts.php" class="<?= nav_active('goods_receipts.php', $current_page) ?>"><i class="fas fa-dolly"></i> Goods Receipts</a></li>
        <li><a href="quality_checks.php" class="<?= nav_active('quality_checks.php', $current_page) ?>"><i class="fas fa-microscope"></i> Quality Check</a></li>
        <li><a href="purchase_invoices.php" class="<?= nav_active('purchase_invoices.php', $current_page) ?>"><i class="fas fa-file-invoice-dollar"></i> Purchase Invoices</a></li>
        <li><a href="purchase_returns.php" class="<?= nav_active('purchase_returns.php', $current_page) ?>"><i class="fas fa-arrow-rotate-left"></i> Purchase Returns</a></li>
        <li><a href="debit_notes.php" class="<?= nav_active('debit_notes.php', $current_page) ?>"><i class="fas fa-file-invoice"></i> Debit Notes</a></li>
        <li><a href="purchase_payments.php" class="<?= nav_active('purchase_payments.php', $current_page) ?>"><i class="fas fa-money-bill-wave"></i> Purchase Payments</a></li>
        <li><a href="purchase_history.php" class="<?= nav_active('purchase_history.php', $current_page) ?>"><i class="fas fa-clock-rotate-left"></i> Purchase History</a></li>

        <li class="adm-nav-section">Accounts</li>
        <li><a href="accounts.php" class="<?= nav_active('accounts.php', $current_page) ?>"><i class="fas fa-wallet"></i> Transactions</a></li>
        <li><a href="expenses.php" class="<?= nav_active('expenses.php', $current_page) ?>"><i class="fas fa-receipt"></i> Expenses</a></li>
        <li><a href="receivables.php" class="<?= nav_active('receivables.php', $current_page) ?>"><i class="fas fa-hand-holding-dollar"></i> Receivables</a></li>
        <li><a href="chart_of_accounts.php" class="<?= nav_active('chart_of_accounts.php', $current_page) ?>"><i class="fas fa-sitemap"></i> Chart of Accounts</a></li>
        <li><a href="journals.php" class="<?= nav_active('journals.php', $current_page) ?>"><i class="fas fa-book"></i> Journal Entries</a></li>
        <li><a href="general_ledger.php" class="<?= nav_active('general_ledger.php', $current_page) ?>"><i class="fas fa-book-open"></i> General Ledger</a></li>
        <li><a href="financial_statements.php" class="<?= nav_active('financial_statements.php', $current_page) ?>"><i class="fas fa-file-contract"></i> Financial Statements</a></li>
        <li><a href="ap_ar_aging.php" class="<?= nav_active('ap_ar_aging.php', $current_page) ?>"><i class="fas fa-hourglass-half"></i> Payables &amp; Receivables</a></li>
        <li><a href="bank_accounts.php" class="<?= nav_active('bank_accounts.php', $current_page) ?>"><i class="fas fa-building-columns"></i> Bank &amp; Cash</a></li>
        <li><a href="gst_summary.php" class="<?= nav_active('gst_summary.php', $current_page) ?>"><i class="fas fa-percent"></i> GST Summary</a></li>
        <li><a href="financial_periods.php" class="<?= nav_active('financial_periods.php', $current_page) ?>"><i class="fas fa-calendar-check"></i> Periods &amp; Settings</a></li>

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
        <li><a href="supplier_360.php" class="<?= nav_active('supplier_360.php', $current_page) ?>"><i class="fas fa-id-card"></i> Supplier 360</a></li>

        <li class="adm-nav-section">Reports &amp; admin</li>
        <li><a href="reports.php" class="<?= nav_active('reports.php', $current_page) ?>"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="profit_loss.php" class="<?= nav_active('profit_loss.php', $current_page) ?>"><i class="fas fa-scale-unbalanced"></i> Profit &amp; Loss</a></li>
        <li><a href="product_profitability.php" class="<?= nav_active('product_profitability.php', $current_page) ?>"><i class="fas fa-chart-pie"></i> Product Profitability</a></li>
        <li><a href="erp_reports_center.php" class="<?= nav_active('erp_reports_center.php', $current_page) ?>"><i class="fas fa-chart-column"></i> ERP Reports</a></li>
        <li><a href="transaction_trace.php" class="<?= nav_active('transaction_trace.php', $current_page) ?>"><i class="fas fa-route"></i> Transaction Trace</a></li>
        <li><a href="approvals.php" class="<?= nav_active('approvals.php', $current_page) ?>"><i class="fas fa-stamp"></i> Approvals <span class="adm-badge is-amber" id="navAprBadge" style="display:none;margin-left:auto;"></span></a></li>
        <li><a href="notifications.php" class="<?= nav_active('notifications.php', $current_page) ?>"><i class="fas fa-bell"></i> Notifications <span class="adm-badge is-red" id="navNtfBadge" style="display:none;margin-left:auto;"></span></a></li>
        <li><a href="documents.php" class="<?= nav_active('documents.php', $current_page) ?>"><i class="fas fa-folder-open"></i> Documents</a></li>
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
<?php
// page-limited roles (1 Oct 2026): hide menu links they cannot open
require_once __DIR__ . '/role_access.php';
$nav_allowed = role_access_pages((string)($_SESSION['admin_role_name'] ?? ''));
if ($nav_allowed !== null): ?>
<script>
(function () {
    var ok = <?= json_encode($nav_allowed) ?>;
    document.querySelectorAll('.adm-sidebar a[href]').forEach(function (a) {
        var page = (a.getAttribute('href') || '').split('?')[0];
        if (!/^[a-z0-9_]+\.php$/.test(page) || ok.indexOf(page) !== -1) return;
        if (a.closest('.adm-nav-combined-links')) { a.style.display = 'none'; return; }
        var li = a.closest('li'); if (li) li.style.display = 'none';
    });
    document.querySelectorAll('.adm-sidebar li.adm-nav-combined').forEach(function (li) {
        var vis = [].some.call(li.querySelectorAll('.adm-nav-combined-links a'), function (x) { return x.style.display !== 'none'; });
        if (!vis) li.style.display = 'none';
    });
    var sec = null, any = false;   // hide section titles with nothing left under them
    document.querySelectorAll('.adm-sidebar .adm-nav > li').forEach(function (li) {
        if (li.classList.contains('adm-nav-section')) { if (sec && !any) sec.style.display = 'none'; sec = li; any = false; }
        else if (li.style.display !== 'none' && !li.classList.contains('adm-nav-divider')) any = true;
    });
    if (sec && !any) sec.style.display = 'none';
})();
</script>
<?php endif; ?>
<script>
/* approvals / notifications counters (1 Oct 2026) — silent if the ERP tables are not installed or no permission */
(function () {
    try {
        fetch('../assets/db_query/admin/notifications_api.php?action=count', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || d.status !== 'success') return;
            var a = document.getElementById('navAprBadge'), n = document.getElementById('navNtfBadge');
            if (a && d.approvals > 0) { a.textContent = d.approvals; a.style.display = 'inline-flex'; }
            if (n && d.unread > 0) { n.textContent = d.unread; n.style.display = 'inline-flex'; }
        }).catch(function () {});
    } catch (e) {}
})();
// Keep the currently open admin page visible in the long, scrollable navigation.
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.adm-sidebar');
    const activeLink = sidebar && sidebar.querySelector('a.active');
    if (!sidebar || !activeLink) return;

    sidebar.scrollTop = Math.max(0, activeLink.offsetTop - (sidebar.clientHeight - activeLink.offsetHeight) / 2);
});
</script>
