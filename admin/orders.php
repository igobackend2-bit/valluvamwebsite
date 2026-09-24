<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Orders</h1>
                    <div class="adm-sub">Track and update order status</div>
                </div>
                <div class="adm-who">Signed in as <strong><?= htmlspecialchars($admin_username) ?></strong></div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All orders</h2>
                    <select id="statusFilter" class="adm-select">
                        <option value="">All status</option>
                        <option value="ordered">Ordered</option>
                        <option value="packed">Packed</option>
                        <option value="couriered">Out for delivery</option>
                        <option value="delivered">Delivered</option>
                    </select>
                </div>
                <div class="adm-card-body" id="ordersTable">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
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
            loadOrders();
            $('#statusFilter').on('change', loadOrders);
        });

        function loadOrders() {
            const status = $('#statusFilter').val();
            $.ajax({
                url: '../assets/db_query/admin/get_orders.php' + (status ? '?status=' + encodeURIComponent(status) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayOrders(data.orders);
                    } else {
                        $('#ordersTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load orders.') + '</div>');
                    }
                },
                error: function() {
                    $('#ordersTable').html('<div class="adm-error">Could not reach the server while loading orders.</div>');
                }
            });
        }

        function displayOrders(orders) {
            if (orders.length === 0) {
                $('#ordersTable').html('<div class="adm-empty"><i class="fas fa-inbox"></i><p><strong>No orders found</strong></p><p>Orders matching this filter will appear here.</p></div>');
                return;
            }

            let rows = '';
            orders.forEach(order => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(order.receipt)}</td>
                    <td>
                        <div class="adm-cell-title">${escapeHtml(order.first_name + ' ' + order.last_name)}</div>
                        <div class="adm-cell-sub">${escapeHtml(order.email)}</div>
                    </td>
                    <td>${escapeHtml(order.phone)}</td>
                    <td class="adm-money">₹${parseFloat(order.amount).toFixed(2)}</td>
                    <td><span class="adm-badge ${order.payment_method === 'COD' ? 'is-amber' : 'is-green'}">${escapeHtml(order.payment_method)}</span></td>
                    <td>
                        <select class="adm-select order-status-select" data-order-id="${order.id}" data-current-status="${order.order_status || 'ordered'}">
                            <option value="ordered" ${(order.order_status || 'ordered') === 'ordered' ? 'selected' : ''}>Ordered</option>
                            <option value="packed" ${order.order_status === 'packed' ? 'selected' : ''}>Packed</option>
                            <option value="couriered" ${order.order_status === 'couriered' ? 'selected' : ''}>Out for delivery</option>
                            <option value="delivered" ${order.order_status === 'delivered' ? 'selected' : ''}>Delivered</option>
                        </select>
                    </td>
                    <td class="adm-cell-sub">${formatDate(order.created_at)}</td>
                </tr>`;
            });

            $('#ordersTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Phone</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

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
                title: 'Update order status?',
                text: `Change status to "${newStatus}".`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Update',
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
                                Swal.fire({ title: 'Updated', text: 'Order status updated.', icon: 'success', confirmButtonColor: '#1c5034' });
                                selectElement.data('current-status', newStatus);
                                loadOrders();
                            } else {
                                Swal.fire({ title: 'Could not update', text: response.message || 'The status was not changed.', icon: 'error', confirmButtonColor: '#1c5034' });
                                selectElement.val(selectElement.data('current-status'));
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                            selectElement.val(selectElement.data('current-status'));
                        }
                    });
                } else {
                    selectElement.val(selectElement.data('current-status'));
                }
            });
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
