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
    <style>
        /* Orders page — scoped column alignment fix (doesn't touch admin.css / other pages) */
        #ordersTable table.adm-table { table-layout: fixed; }
        #ordersTable col.col-order   { width: 15%; }
        #ordersTable col.col-customer{ width: 20%; }
        #ordersTable col.col-phone   { width: 11%; }
        #ordersTable col.col-amount  { width: 9%; }
        #ordersTable col.col-payment { width: 9%; }
        #ordersTable col.col-status  { width: 15%; }
        #ordersTable col.col-date    { width: 13%; }
        #ordersTable col.col-items   { width: 8%; }
        #ordersTable th, #ordersTable td { overflow: hidden; text-overflow: ellipsis; }
        #ordersTable .adm-cell-title, #ordersTable .adm-cell-sub { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #ordersTable select.adm-select { width: 100%; }
        #ordersTable td.adm-items-cell { text-align: center; }
        .order-view-link { cursor: pointer; color: var(--adm-green); text-decoration: none; }
        .order-view-link:hover { text-decoration: underline; }
    </style>
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
                    <td class="adm-cell-title"><span class="order-view-link view-items-btn" data-order-id="${order.id}" data-receipt="${escapeHtml(order.receipt)}" title="Click to view products ordered">${escapeHtml(order.receipt)}</span></td>
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
                    <td class="adm-items-cell">
                        <button type="button" class="adm-icon-btn view-items-btn" data-order-id="${order.id}" data-receipt="${escapeHtml(order.receipt)}" title="View products ordered">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            });

            $('#ordersTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <colgroup>
                    <col class="col-order"><col class="col-customer"><col class="col-phone"><col class="col-amount">
                    <col class="col-payment"><col class="col-status"><col class="col-date"><col class="col-items">
                </colgroup>
                <thead><tr><th>Order</th><th>Customer</th><th>Phone</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th><th>Items</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.order-status-select').on('change', function() {
                const orderId = $(this).data('order-id');
                const newStatus = $(this).val();
                const currentStatus = $(this).data('current-status');
                if (newStatus === currentStatus) return;
                updateOrderStatus(orderId, newStatus, $(this));
            });

            $('.view-items-btn').on('click', function() {
                viewOrderItems($(this).data('order-id'), $(this).data('receipt'));
            });
        }

        function viewOrderItems(orderId, receipt) {
            Swal.fire({
                title: 'Loading items…',
                html: '<span class="adm-skel" style="width:100%;height:18px;"></span>',
                showConfirmButton: false,
                willOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: '../assets/db_query/admin/get_order_items.php?order_id=' + encodeURIComponent(orderId),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') {
                        Swal.fire({ title: 'Could not load items', text: data.message || 'Please try again.', icon: 'error', confirmButtonColor: '#1c5034' });
                        return;
                    }
                    renderOrderItemsModal(receipt, data.items);
                },
                error: function() {
                    Swal.fire({ title: 'Could not load items', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function renderOrderItemsModal(receipt, items) {
            if (!items || items.length === 0) {
                Swal.fire({
                    title: 'Order ' + escapeHtml(receipt),
                    html: '<div class="adm-empty"><i class="fas fa-box-open"></i><p><strong>No product lines found</strong></p><p>This order has no recorded items.</p></div>',
                    confirmButtonColor: '#1c5034',
                    confirmButtonText: 'Close',
                    width: 480
                });
                return;
            }

            let rows = '';
            let grandTotal = 0;
            items.forEach(item => {
                const lineTotal = parseFloat(item.line_total || (item.quantity * item.price));
                grandTotal += lineTotal;
                const imgSrc = '../assets/uploads/' + encodeURI(item.image || 'no-image.jpg');
                rows += `<tr>
                    <td style="text-align:left;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="${imgSrc}" class="adm-thumb" onerror="this.src='../images/logo.png'" alt="">
                            <div style="text-align:left;">
                                <div class="adm-cell-title">${escapeHtml(item.product_name || ('Product #' + item.product_id))}</div>
                                ${item.category ? '<div class="adm-cell-sub">' + escapeHtml(item.category) + '</div>' : ''}
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(String(item.quantity))}</td>
                    <td class="adm-money">₹${parseFloat(item.price).toFixed(2)}</td>
                    <td class="adm-money">₹${lineTotal.toFixed(2)}</td>
                </tr>`;
            });

            const html = `<div class="adm-table-wrap" style="text-align:left;">
                <table class="adm-table">
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot><tr><td colspan="3" style="text-align:right;font-weight:700;">Grand total</td><td class="adm-money" style="font-weight:700;">₹${grandTotal.toFixed(2)}</td></tr></tfoot>
                </table>
            </div>`;

            Swal.fire({
                title: 'Products in order ' + escapeHtml(receipt),
                html: html,
                confirmButtonColor: '#1c5034',
                confirmButtonText: 'Close',
                width: 620
            });
        }

        function updateOrderStatus(orderId, newStatus, selectElement) {
            Swal.fire({
                title: 'Update order status?',
                text: `Change status to "${newStatus}".`,
                icon: 'warning',
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
