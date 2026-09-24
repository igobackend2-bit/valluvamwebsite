<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/includes/sidebar.php';
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
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php /* nav rendered by includes/sidebar.php above */ ?>

        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Dashboard</h1>
                    <div class="adm-sub">An overview of orders and catalog size</div>
                </div>
                <div class="adm-who">Signed in as <strong><?= htmlspecialchars($admin_username) ?></strong></div>
            </div>

            <div class="adm-stats">
                <div class="adm-stat is-primary">
                    <div class="adm-stat-icon"><i class="fas fa-basket-shopping"></i></div>
                    <h3 id="totalOrders"><span class="adm-skel"></span></h3>
                    <p>Total orders</p>
                </div>
                <div class="adm-stat is-amber">
                    <div class="adm-stat-icon"><i class="fas fa-truck"></i></div>
                    <h3 id="pendingOrders"><span class="adm-skel"></span></h3>
                    <p>In progress</p>
                </div>
                <div class="adm-stat is-green">
                    <div class="adm-stat-icon"><i class="fas fa-circle-check"></i></div>
                    <h3 id="deliveredOrders"><span class="adm-skel"></span></h3>
                    <p>Delivered</p>
                </div>
                <div class="adm-stat is-neutral">
                    <div class="adm-stat-icon"><i class="fas fa-box-open"></i></div>
                    <h3 id="totalProducts"><span class="adm-skel"></span></h3>
                    <p>Products listed</p>
                </div>
            </div>

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
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            loadDashboardStats();
            loadRecentOrders();
        });

        function loadDashboardStats() {
            $.ajax({
                url: '../assets/db_query/admin/dashboard_stats.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        $('#totalOrders').text(data.stats.total_orders ?? 0);
                        $('#pendingOrders').text(data.stats.pending_orders ?? 0);
                        $('#deliveredOrders').text(data.stats.delivered_orders ?? 0);
                        $('#totalProducts').text(data.stats.total_products ?? 0);
                    } else {
                        $('.adm-stat h3').text('—');
                    }
                },
                error: function() {
                    $('.adm-stat h3').text('—');
                }
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
