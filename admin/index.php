<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .adm-stat-link { text-decoration: none; color: inherit; display: block; }
        .adm-stat-link .adm-stat:hover { transform: translateY(-2px); transition: transform .15s ease; cursor: pointer; }
        .adm-alert-row { display: flex; align-items: center; gap: 10px; padding: 10px 4px; border-bottom: 1px solid #eee6d8; font-size: 13.5px; }
        .adm-alert-row:last-child { border-bottom: none; }
        .adm-alert-row i { width: 18px; text-align: center; }
        .adm-alert-row.is-danger i { color: var(--adm-red, #a8442f); }
        .adm-alert-row.is-amber i { color: var(--adm-amber, #b8862f); }
        .adm-alert-row.is-neutral i { color: #6b6459; }
    </style>
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Dashboard</h1>
                    <div class="adm-sub">Operations overview — sales, inventory, accounts and more</div>
                </div>
                <div class="adm-who">Signed in as <strong><?= htmlspecialchars($admin_full_name ?? $admin_username) ?></strong><?= $admin_role_name ? ' · ' . htmlspecialchars($admin_role_name) : '' ?></div>
            </div>

            <h2 style="font-size:14px;color:#6b6459;margin:18px 0 8px;text-transform:uppercase;letter-spacing:.04em;">Website orders &amp; catalog</h2>
            <div class="adm-stats">
                <a href="orders.php" class="adm-stat-link"><div class="adm-stat is-primary">
                    <div class="adm-stat-icon"><i class="fas fa-basket-shopping"></i></div>
                    <h3 id="totalOrders"><span class="adm-skel"></span></h3><p>Total orders</p>
                </div></a>
                <a href="orders.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-truck"></i></div>
                    <h3 id="pendingOrders"><span class="adm-skel"></span></h3><p>In progress</p>
                </div></a>
                <a href="orders.php" class="adm-stat-link"><div class="adm-stat is-green">
                    <div class="adm-stat-icon"><i class="fas fa-circle-check"></i></div>
                    <h3 id="deliveredOrders"><span class="adm-skel"></span></h3><p>Delivered</p>
                </div></a>
                <a href="products.php" class="adm-stat-link"><div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-box-open"></i></div>
                    <h3 id="totalProducts"><span class="adm-skel"></span></h3><p>Products listed</p>
                </div></a>
            </div>

            <h2 style="font-size:14px;color:#6b6459;margin:22px 0 8px;text-transform:uppercase;letter-spacing:.04em;">Sales &amp; invoices</h2>
            <div class="adm-stats">
                <a href="sales_orders.php" class="adm-stat-link"><div class="adm-stat is-primary">
                    <div class="adm-stat-icon"><i class="fas fa-file-lines"></i></div>
                    <h3 id="totalSalesOrders"><span class="adm-skel"></span></h3><p>Sales orders</p>
                </div></a>
                <a href="sales_orders.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <h3 id="pendingSalesOrders"><span class="adm-skel"></span></h3><p>Pending sales orders</p>
                </div></a>
                <a href="sales_orders.php" class="adm-stat-link"><div class="adm-stat is-green">
                    <div class="adm-stat-icon"><i class="fas fa-circle-check"></i></div>
                    <h3 id="completedSalesOrders"><span class="adm-skel"></span></h3><p>Completed</p>
                </div></a>
                <a href="sales_orders.php" class="adm-stat-link"><div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-ban"></i></div>
                    <h3 id="cancelledSalesOrders"><span class="adm-skel"></span></h3><p>Cancelled</p>
                </div></a>
                <a href="invoices.php" class="adm-stat-link"><div class="adm-stat is-primary">
                    <div class="adm-stat-icon"><i class="fas fa-file-invoice"></i></div>
                    <h3 id="totalInvoices"><span class="adm-skel"></span></h3><p>Total invoices</p>
                </div></a>
                <a href="invoices.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-clock"></i></div>
                    <h3 id="pendingInvoices"><span class="adm-skel"></span></h3><p>Pending invoices</p>
                </div></a>
                <a href="invoices.php" class="adm-stat-link"><div class="adm-stat is-green">
                    <div class="adm-stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                    <h3 id="paidInvoices"><span class="adm-skel"></span></h3><p>Paid invoices</p>
                </div></a>
                <a href="invoices.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-scale-unbalanced"></i></div>
                    <h3 id="outstandingAmount"><span class="adm-skel"></span></h3><p>Outstanding amount</p>
                </div></a>
            </div>

            <h2 style="font-size:14px;color:#6b6459;margin:22px 0 8px;text-transform:uppercase;letter-spacing:.04em;">Inventory &amp; dispatch</h2>
            <div class="adm-stats">
                <a href="inventory_overview.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <h3 id="lowStockProducts"><span class="adm-skel"></span></h3><p>Low stock products</p>
                </div></a>
                <a href="stock_movements.php" class="adm-stat-link"><div class="adm-stat is-green">
                    <div class="adm-stat-icon"><i class="fas fa-arrow-down"></i></div>
                    <h3 id="stockInToday"><span class="adm-skel"></span></h3><p>Stock in (today)</p>
                </div></a>
                <a href="stock_movements.php" class="adm-stat-link"><div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-arrow-up"></i></div>
                    <h3 id="stockOutToday"><span class="adm-skel"></span></h3><p>Stock out (today)</p>
                </div></a>
                <a href="delivery_challans.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-truck-ramp-box"></i></div>
                    <h3 id="dcPending"><span class="adm-skel"></span></h3><p>DCs awaiting dispatch</p>
                </div></a>
                <a href="manual_sales.php" class="adm-stat-link"><div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-cash-register"></i></div>
                    <h3 id="manualSalesToday"><span class="adm-skel"></span></h3><p>Manual sales (today)</p>
                </div></a>
            </div>

            <h2 style="font-size:14px;color:#6b6459;margin:22px 0 8px;text-transform:uppercase;letter-spacing:.04em;">Assets &amp; accounts</h2>
            <div class="adm-stats">
                <a href="assets.php" class="adm-stat-link"><div class="adm-stat is-primary">
                    <div class="adm-stat-icon"><i class="fas fa-boxes-stacked"></i></div>
                    <h3 id="totalAssets"><span class="adm-skel"></span></h3><p>Total assets</p>
                </div></a>
                <a href="assets.php" class="adm-stat-link"><div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-coins"></i></div>
                    <h3 id="assetValue"><span class="adm-skel"></span></h3><p>Asset value</p>
                </div></a>
                <a href="accounts.php" class="adm-stat-link"><div class="adm-stat is-green">
                    <div class="adm-stat-icon"><i class="fas fa-sack-dollar"></i></div>
                    <h3 id="totalIncome"><span class="adm-skel"></span></h3><p>Income</p>
                </div></a>
                <a href="accounts.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h3 id="totalExpense"><span class="adm-skel"></span></h3><p>Expenses</p>
                </div></a>
                <a href="accounts.php" class="adm-stat-link"><div class="adm-stat is-primary">
                    <div class="adm-stat-icon"><i class="fas fa-wallet"></i></div>
                    <h3 id="accountsBalance"><span class="adm-skel"></span></h3><p>Accounts balance</p>
                </div></a>
                <a href="waste.php" class="adm-stat-link"><div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-trash"></i></div>
                    <h3 id="wastePending"><span class="adm-skel"></span></h3><p>Waste pending approval</p>
                </div></a>
                <a href="reports.php" class="adm-stat-link"><div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-chart-line"></i></div>
                    <h3 id="monthlySales"><span class="adm-skel"></span></h3><p>Monthly sales</p>
                </div></a>
            </div>

            <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-top:18px;align-items:start;">
                <section class="adm-card">
                    <div class="adm-card-head">
                        <h2>Recent orders</h2>
                        <a href="orders.php" class="adm-btn adm-btn-ghost">View all</a>
                    </div>
                    <div class="adm-card-body" id="recentOrders">
                        <div class="adm-table-wrap">
                            <table class="adm-table"><tbody>
                                <tr><td colspan="5"><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                                <tr><td colspan="5"><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                                <tr><td colspan="5"><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                            </tbody></table>
                        </div>
                    </div>
                </section>

                <section class="adm-card">
                    <div class="adm-card-head">
                        <h2>Alerts</h2>
                    </div>
                    <div class="adm-card-body" id="alertsPanel">
                        <span class="adm-skel" style="width:100%;height:18px;display:block;margin-bottom:8px;"></span>
                        <span class="adm-skel" style="width:100%;height:18px;display:block;"></span>
                    </div>
                </section>
            </div>

            <section class="adm-card" style="margin-top:16px;">
                <div class="adm-card-head">
                    <h2>Recent stock movements</h2>
                    <a href="stock_movements.php" class="adm-btn adm-btn-ghost">View all</a>
                </div>
                <div class="adm-card-body" id="recentMovements">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td colspan="5"><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                        </tbody></table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            loadDashboardStats();
            loadRecentOrders();
            loadAlerts();
            loadRecentMovements();
        });

        function inr(n) { return '₹' + (parseFloat(n || 0)).toLocaleString('en-IN', {maximumFractionDigits: 0}); }

        function loadDashboardStats() {
            $.ajax({
                url: '../assets/db_query/admin/dashboard_stats.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        const s = data.stats;
                        $('#totalOrders').text(s.total_orders ?? 0);
                        $('#pendingOrders').text(s.pending_orders ?? 0);
                        $('#deliveredOrders').text(s.delivered_orders ?? 0);
                        $('#totalProducts').text(s.total_products ?? 0);

                        $('#totalSalesOrders').text(s.total_sales_orders ?? 0);
                        $('#pendingSalesOrders').text(s.pending_sales_orders ?? 0);
                        $('#completedSalesOrders').text(s.completed_sales_orders ?? 0);
                        $('#cancelledSalesOrders').text(s.cancelled_sales_orders ?? 0);
                        $('#totalInvoices').text(s.total_invoices ?? 0);
                        $('#pendingInvoices').text(s.pending_invoices ?? 0);
                        $('#paidInvoices').text(s.paid_invoices ?? 0);
                        $('#outstandingAmount').text(inr(s.outstanding_amount));

                        $('#lowStockProducts').text(s.low_stock_products ?? 0);
                        $('#stockInToday').text(s.stock_in_today ?? 0);
                        $('#stockOutToday').text(s.stock_out_today ?? 0);
                        $('#dcPending').text(s.delivery_challans_pending ?? 0);
                        $('#manualSalesToday').text(s.manual_sales_today ?? 0);

                        $('#totalAssets').text(s.total_assets ?? 0);
                        $('#assetValue').text(inr(s.asset_value));
                        $('#totalIncome').text(inr(s.total_income));
                        $('#totalExpense').text(inr(s.total_expense));
                        $('#accountsBalance').text(inr(s.accounts_balance));
                        $('#wastePending').text(s.waste_records_pending ?? 0);
                        $('#monthlySales').text(inr(s.monthly_sales));
                    } else {
                        $('.adm-stat h3').text('—');
                    }
                },
                error: function() { $('.adm-stat h3').text('—'); }
            });
        }

        function loadAlerts() {
            $.ajax({
                url: '../assets/db_query/admin/get_dashboard_alerts.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        if (!data.alerts.length) {
                            $('#alertsPanel').html('<div class="adm-empty"><i class="fas fa-check"></i><p><strong>All clear</strong></p><p>No alerts right now.</p></div>');
                            return;
                        }
                        let html = '';
                        data.alerts.forEach(a => {
                            html += `<div class="adm-alert-row is-${a.level}"><i class="fas ${a.icon}"></i><span>${escapeHtml(a.label)}</span></div>`;
                        });
                        $('#alertsPanel').html(html);
                    } else {
                        $('#alertsPanel').html('<div class="adm-error">Could not load alerts.</div>');
                    }
                },
                error: function() { $('#alertsPanel').html('<div class="adm-error">Could not reach the server.</div>'); }
            });
        }

        function loadRecentMovements() {
            $.ajax({
                url: '../assets/db_query/admin/get_stock_movements.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success' && data.movements && data.movements.length) {
                        let rows = '';
                        data.movements.slice(0, 8).forEach(m => {
                            rows += `<tr>
                                <td class="adm-cell-title">${escapeHtml(m.product_name || m.sku || '—')}</td>
                                <td>${escapeHtml(m.movement_type)}</td>
                                <td>${escapeHtml(String(m.quantity))}</td>
                                <td>${escapeHtml(m.reference_number || '—')}</td>
                                <td class="adm-cell-sub">${formatDate(m.created_at)}</td>
                            </tr>`;
                        });
                        $('#recentMovements').html(`<div class="adm-table-wrap"><table class="adm-table">
                            <thead><tr><th>Product</th><th>Type</th><th>Qty</th><th>Reference</th><th>Date</th></tr></thead>
                            <tbody>${rows}</tbody></table></div>`);
                    } else {
                        $('#recentMovements').html(emptyState('fa-arrow-right-arrow-left', 'No stock movements yet', 'Stock In / Stock Out activity will show up here.'));
                    }
                },
                error: function() { $('#recentMovements').html('<div class="adm-error">Could not reach the server while loading stock movements.</div>'); }
            });
        }

        function loadRecentOrders() {
            $.ajax({
                url: '../assets/db_query/admin/get_orders.php?limit=5',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success' && data.orders.length > 0) {
                        displayRecentOrders(data.orders);
                    } else if (data.status === 'success') {
                        $('#recentOrders').html(emptyState('fa-inbox', 'No orders yet', 'New orders placed on the site will show up here.'));
                    } else {
                        $('#recentOrders').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load recent orders.') + '</div>');
                    }
                },
                error: function() {
                    $('#recentOrders').html('<div class="adm-error">Could not reach the server while loading recent orders.</div>');
                }
            });
        }

        function displayRecentOrders(orders) {
            let rows = '';
            orders.forEach(order => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(order.receipt)}</td>
                    <td>${escapeHtml(order.first_name + ' ' + order.last_name)}</td>
                    <td class="adm-money">₹${parseFloat(order.amount).toFixed(2)}</td>
                    <td>${statusBadge(order.order_status)}</td>
                    <td class="adm-cell-sub">${formatDate(order.created_at)}</td>
                </tr>`;
            });
            $('#recentOrders').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);
        }

        function statusBadge(status) {
            const map = {
                ordered: ['is-neutral', 'Ordered'],
                packed: ['is-info', 'Packed'],
                couriered: ['is-amber', 'Out for delivery'],
                delivered: ['is-green', 'Delivered']
            };
            const [cls, label] = map[status] || map.ordered;
            return `<span class="adm-badge ${cls}">${label}</span>`;
        }

        function emptyState(icon, title, body) {
            return `<div class="adm-empty"><i class="fas ${icon}"></i><p><strong>${title}</strong></p><p>${body}</p></div>`;
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>
