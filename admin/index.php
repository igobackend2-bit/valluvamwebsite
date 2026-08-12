<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Valluvam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin: 0;
        }
        .sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        .sidebar-menu i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .header-bar {
            background: white;
            padding: 15px 30px;
            margin: -30px -30px 30px -30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .stats-card h3 {
            margin: 0;
            font-size: 32px;
            color: #333;
        }
        .stats-card p {
            margin: 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user-shield"></i> Admin Panel</h4>
            <small>Valluvam Products</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <h2>Dashboard</h2>
            <div>
                <span>Welcome, <strong><?= htmlspecialchars($admin_username) ?></strong></span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon text-primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 id="totalOrders">-</h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 id="pendingOrders">-</h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 id="deliveredOrders">-</h3>
                    <p>Delivered Orders</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon text-info">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 id="totalProducts">-</h3>
                    <p>Total Products</p>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Recent Orders</h5>
            </div>
            <div class="card-body">
                <div id="recentOrders">Loading...</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.bundle.min.js"></script>
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
                        $('#totalOrders').text(data.stats.total_orders || 0);
                        $('#pendingOrders').text(data.stats.pending_orders || 0);
                        $('#deliveredOrders').text(data.stats.delivered_orders || 0);
                        $('#totalProducts').text(data.stats.total_products || 0);
                    }
                },
                error: function() {
                    console.error('Failed to load dashboard stats');
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
                        let html = '<table class="table table-hover"><thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                        data.orders.forEach(order => {
                            html += `<tr>
                                <td>${order.receipt}</td>
                                <td>${order.first_name} ${order.last_name}</td>
                                <td>₹${parseFloat(order.amount).toFixed(2)}</td>
                                <td><span class="badge badge-${getStatusBadgeClass(order.order_status)}">${order.order_status || 'ordered'}</span></td>
                                <td>${formatDate(order.created_at)}</td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        $('#recentOrders').html(html);
                    } else {
                        $('#recentOrders').html('<p class="text-muted">No recent orders</p>');
                    }
                },
                error: function() {
                    $('#recentOrders').html('<p class="text-danger">Failed to load recent orders</p>');
                }
            });
        }

        function getStatusBadgeClass(status) {
            const classes = {
                'ordered': 'primary',
                'packed': 'info',
                'couriered': 'warning',
                'delivered': 'success'
            };
            return classes[status] || 'secondary';
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' ' + date.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>

