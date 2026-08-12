<?php
session_start();
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
    <title>Orders Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body { background-color: #f5f5f5; }
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1000;
        }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu a { display: block; padding: 15px 20px; color: white; text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); padding-left: 25px; }
        .sidebar-menu a.active { background: rgba(255,255,255,0.2); border-left: 4px solid white; }
        .sidebar-menu i { width: 20px; margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 30px; }
        .header-bar { background: white; padding: 15px 30px; margin: -30px -30px 30px -30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user-shield"></i> Admin Panel</h4>
            <small>Valluvam Products</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <h2>Orders Management</h2>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Orders</h5>
                <div>
                    <select id="statusFilter" class="form-control form-control-sm" style="display: inline-block; width: auto;">
                        <option value="">All Status</option>
                        <option value="ordered">Ordered</option>
                        <option value="packed">Packed</option>
                        <option value="couriered">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div id="ordersTable">Loading orders...</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            loadOrders();
            $('#statusFilter').on('change', function() {
                loadOrders();
            });
        });

        function loadOrders() {
            const status = $('#statusFilter').val();
            $.ajax({
                url: '../assets/db_query/admin/get_orders.php' + (status ? '?status=' + status : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayOrders(data.orders);
                    } else {
                        $('#ordersTable').html('<p class="text-danger">' + (data.message || 'Failed to load orders') + '</p>');
                    }
                },
                error: function() {
                    $('#ordersTable').html('<p class="text-danger">Failed to load orders</p>');
                }
            });
        }

        function displayOrders(orders) {
            if (orders.length === 0) {
                $('#ordersTable').html('<p class="text-muted">No orders found</p>');
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>' +
                '<th>Order ID</th><th>Customer</th><th>Email</th><th>Phone</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th>' +
                '</tr></thead><tbody>';

            orders.forEach(order => {
                html += `<tr>
                    <td><strong>${order.receipt}</strong></td>
                    <td>${order.first_name} ${order.last_name}</td>
                    <td>${order.email}</td>
                    <td>${order.phone}</td>
                    <td>₹${parseFloat(order.amount).toFixed(2)}</td>
                    <td><span class="badge badge-${order.payment_method === 'COD' ? 'warning' : 'success'}">${order.payment_method}</span></td>
                    <td>
                        <select class="form-control form-control-sm order-status-select" data-order-id="${order.id}" data-current-status="${order.order_status || 'ordered'}">
                            <option value="ordered" ${(order.order_status || 'ordered') === 'ordered' ? 'selected' : ''}>Ordered</option>
                            <option value="packed" ${order.order_status === 'packed' ? 'selected' : ''}>Packed</option>
                            <option value="couriered" ${order.order_status === 'couriered' ? 'selected' : ''}>Out for Delivery</option>
                            <option value="delivered" ${order.order_status === 'delivered' ? 'selected' : ''}>Delivered</option>
                        </select>
                    </td>
                    <td>${formatDate(order.created_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-info view-order-details" data-order-id="${order.id}" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            $('#ordersTable').html(html);

            // Handle status change
            $('.order-status-select').on('change', function() {
                const orderId = $(this).data('order-id');
                const newStatus = $(this).val();
                const currentStatus = $(this).data('current-status');

                if (newStatus === currentStatus) return;

                updateOrderStatus(orderId, newStatus, $(this));
            });
        }

        function updateOrderStatus(orderId, newStatus, selectElement) {
            Swal.fire({
                title: 'Update Order Status?',
                text: `Change status to "${newStatus}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Update',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/update_order_status.php',
                        type: 'POST',
                        data: { order_id: orderId, status: newStatus },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire('Success', 'Order status updated successfully', 'success');
                                selectElement.data('current-status', newStatus);
                                loadOrders(); // Reload to refresh
                            } else {
                                Swal.fire('Error', response.message || 'Failed to update status', 'error');
                                selectElement.val(selectElement.data('current-status')); // Revert
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Failed to update order status', 'error');
                            selectElement.val(selectElement.data('current-status')); // Revert
                        }
                    });
                } else {
                    selectElement.val(selectElement.data('current-status')); // Revert
                }
            });
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' ' + date.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>

