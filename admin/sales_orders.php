<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Orders — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .so-item-row { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; align-items:center; margin-bottom:6px; }
        .so-item-row select, .so-item-row input { width:100%; padding:6px; border:1px solid #ccc; border-radius:6px; font-size:13px; }
        .so-item-head { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; font-size:11px; color:#777; margin-bottom:4px; }
        .so-remove-row { background:none; border:none; color:#a8442f; cursor:pointer; font-size:14px; }
        .so-totals { text-align:right; margin-top:10px; font-size:14px; }
        .so-totals div { margin-bottom:4px; }
        .swal-wide { width: 820px !important; }
    </style>
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Sales Orders</h1>
                    <div class="adm-sub">Offline / B2B / phone sales entered by admin staff</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addSoBtn"><i class="fas fa-plus"></i> New Sales Order</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All sales orders</h2>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="text" id="soSearch" class="adm-input" placeholder="Search SO#, customer, mobile" style="width:220px;">
                        <select id="soStatusFilter" class="adm-select">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="ready_for_dispatch">Ready for dispatch</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="delivered">Delivered</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button class="adm-btn adm-btn-ghost" id="soFilterBtn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
                <div class="adm-card-body" id="soTable">
                    <div class="adm-table-wrap"><table class="adm-table"><tbody>
                        <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                    </tbody></table></div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let PRODUCTS = [];
        let CUSTOMERS = [];
        const STATUS_BADGE = {
            draft: 'is-neutral', confirmed: 'is-info', processing: 'is-info',
            ready_for_dispatch: 'is-amber', dispatched: 'is-amber', delivered: 'is-green',
            completed: 'is-green', cancelled: 'is-danger'
        };

        $(document).ready(function() {
            loadLookups().then(loadSalesOrders);
            $('#addSoBtn').on('click', function() { openSoModal(null); });
            $('#soFilterBtn').on('click', loadSalesOrders);
            $('#soSearch').on('keyup', function(e) { if (e.key === 'Enter') loadSalesOrders(); });
        });

        function loadLookups() {
            const p1 = $.ajax({ url: '../assets/db_query/admin/get_products_for_sales.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') PRODUCTS = data.products || []; })
                .fail(function() { PRODUCTS = []; });
            const p2 = $.ajax({ url: '../assets/db_query/admin/get_customers.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') CUSTOMERS = data.customers || []; })
                .fail(function() { CUSTOMERS = []; });
            return $.when(p1, p2);
        }

        function loadSalesOrders() {
            const params = { q: $('#soSearch').val() || '', status: $('#soStatusFilter').val() || '' };
            $.ajax({
                url: '../assets/db_query/admin/get_sales_orders.php',
                type: 'GET', data: params, dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') displaySalesOrders(data.sales_orders);
                    else $('#soTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load sales orders.') + '</div>');
                },
                error: function() { $('#soTable').html('<div class="adm-error">Could not reach the server while loading sales orders.</div>'); }
            });
        }

        function displaySalesOrders(orders) {
            if (orders.length === 0) {
                $('#soTable').html('<div class="adm-empty"><i class="fas fa-file-invoice"></i><p><strong>No sales orders yet</strong></p><p>Create one to get started.</p></div>');
                return;
            }
            let rows = '';
            orders.forEach(o => {
                const badge = STATUS_BADGE[o.status] || 'is-neutral';
                const canEdit = o.status === 'draft' || o.status === 'confirmed';
                const canConvertDc = (o.status !== 'draft' && o.status !== 'cancelled') && Number(o.dc_count) === 0;
                const canConvertInv = o.status !== 'cancelled' && Number(o.invoice_count) === 0;
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(o.so_number)}<div class="adm-cell-sub">${formatDate(o.order_date)}</div></td>
                    <td>${escapeHtml(o.customer_name || '—')}<div class="adm-cell-sub">${escapeHtml(o.customer_mobile || '')}</div></td>
                    <td><span class="adm-badge ${badge}">${escapeHtml(o.status.replace(/_/g,' '))}</span></td>
                    <td class="adm-money">₹${parseFloat(o.grand_total).toFixed(2)}</td>
                    <td style="white-space:nowrap;">
                        <button class="adm-icon-btn view-so" data-id="${o.id}" title="View"><i class="fas fa-eye"></i></button>
                        ${canEdit ? `<button class="adm-icon-btn edit-so" data-id="${o.id}" title="Edit"><i class="fas fa-pen"></i></button>` : ''}
                        <button class="adm-icon-btn dup-so" data-id="${o.id}" title="Duplicate"><i class="fas fa-copy"></i></button>
                        <button class="adm-icon-btn print-so" data-id="${o.id}" title="Print"><i class="fas fa-print"></i></button>
                        ${canConvertDc ? `<button class="adm-icon-btn conv-dc" data-id="${o.id}" title="Convert to DC"><i class="fas fa-truck"></i></button>` : ''}
                        ${canConvertInv ? `<button class="adm-icon-btn conv-inv" data-id="${o.id}" title="Convert to Invoice"><i class="fas fa-receipt"></i></button>` : ''}
                        ${o.status !== 'cancelled' ? `<button class="adm-icon-btn is-danger cancel-so" data-id="${o.id}" data-number="${escapeHtml(o.so_number)}" title="Cancel"><i class="fas fa-ban"></i></button>` : ''}
                    </td>
                </tr>`;
            });
            $('#soTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>SO #</th><th>Customer</th><th>Status</th><th>Grand total</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody></table></div>`);

            $('.view-so').on('click', function() { viewSalesOrder($(this).data('id')); });
            $('.edit-so').on('click', function() { openSoModalById($(this).data('id')); });
            $('.dup-so').on('click', function() { openSoModalById($(this).data('id'), true); });
            $('.print-so').on('click', function() { window.open('print_sales_order.php?id=' + $(this).data('id'), '_blank'); });
            $('.conv-dc').on('click', function() { window.location = 'delivery_challans.php?from_so=' + $(this).data('id'); });
            $('.conv-inv').on('click', function() { window.location = 'invoices.php?from_so=' + $(this).data('id'); });
            $('.cancel-so').on('click', function() { cancelSalesOrder($(this).data('id'), $(this).data('number')); });
        }

        function viewSalesOrder(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_sales_order.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load sales order', 'error'); return; }
                    const o = data.sales_order;
                    let itemRows = '';
                    (o.items || []).forEach(it => {
                        itemRows += `<tr><td>${escapeHtml(it.product_name || ('#' + it.product_id))}</td><td>${it.quantity} ${escapeHtml(it.unit)}</td><td>₹${parseFloat(it.rate).toFixed(2)}</td><td>₹${parseFloat(it.discount).toFixed(2)}</td><td>${parseFloat(it.tax).toFixed(2)}%</td><td>₹${parseFloat(it.line_total).toFixed(2)}</td></tr>`;
                    });
                    Swal.fire({
                        title: o.so_number,
                        width: 700,
                        html: `
                            <div style="text-align:left; font-size:14px;">
                                <p><strong>Customer:</strong> ${escapeHtml(o.customer_name || '—')} (${escapeHtml(o.customer_mobile || '—')})</p>
                                <p><strong>Status:</strong> ${escapeHtml(o.status.replace(/_/g,' '))} &nbsp; <strong>Date:</strong> ${formatDate(o.order_date)}</p>
                                <table class="adm-table" style="width:100%; font-size:13px;">
                                    <thead><tr><th>Product</th><th>Qty</th><th>Rate</th><th>Disc</th><th>Tax</th><th>Total</th></tr></thead>
                                    <tbody>${itemRows}</tbody>
                                </table>
                                <div style="text-align:right; margin-top:8px;">
                                    <div>Subtotal: ₹${parseFloat(o.subtotal).toFixed(2)}</div>
                                    <div>Tax: ₹${parseFloat(o.total_tax).toFixed(2)}</div>
                                    <div><strong>Grand total: ₹${parseFloat(o.grand_total).toFixed(2)}</strong></div>
                                </div>
                                ${o.notes ? `<p><strong>Notes:</strong> ${escapeHtml(o.notes)}</p>` : ''}
                            </div>`,
                        confirmButtonText: 'Close', confirmButtonColor: '#1c5034'
                    });
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function openSoModalById(id, duplicate) {
            $.ajax({
                url: '../assets/db_query/admin/get_sales_order.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load sales order', 'error'); return; }
                    openSoModal(data.sales_order, duplicate);
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function productOptions(selectedId) {
            let opts = '<option value="">Select product…</option>';
            PRODUCTS.forEach(p => {
                opts += `<option value="${p.id}" data-rate="${p.dis_price || p.price}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(p.product_name)}</option>`;
            });
            return opts;
        }

        function customerOptions(selectedId) {
            let opts = '<option value="">— Walk-in customer (enter details below) —</option>';
            CUSTOMERS.forEach(c => {
                opts += `<option value="${c.id}" ${String(c.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(c.username || c.email)} (${escapeHtml(c.phone_number || c.email || '')})</option>`;
            });
            return opts;
        }

        function itemRowHtml(item) {
            item = item || {};
            return `<div class="so-item-row">
                <select class="so-product">${productOptions(item.product_id)}</select>
                <input type="number" class="so-qty" min="0" step="0.01" placeholder="Qty" value="${item.quantity || ''}">
                <input type="text" class="so-unit" placeholder="Unit" value="${item.unit || 'pcs'}">
                <input type="number" class="so-rate" min="0" step="0.01" placeholder="Rate" value="${item.rate || ''}">
                <input type="number" class="so-discount" min="0" step="0.01" placeholder="Disc" value="${item.discount || 0}">
                <input type="number" class="so-tax" min="0" step="0.01" placeholder="Tax %" value="${item.tax || 0}">
                <span class="so-line-total adm-money">₹0.00</span>
                <button type="button" class="so-remove-row" title="Remove"><i class="fas fa-times"></i></button>
            </div>`;
        }

        function openSoModal(order, duplicate) {
            const isEdit = order && !duplicate;
            const items = (order && order.items && order.items.length) ? order.items : [{}];
            let itemsHtml = items.map(itemRowHtml).join('');

            Swal.fire({
                title: isEdit ? `Edit ${order.so_number}` : 'New Sales Order',
                customClass: { popup: 'swal-wide' },
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div><label>Order date</label><input type="date" id="so-date" class="swal2-input" style="margin:2px 0;" value="${order ? order.order_date.substring(0,10) : new Date().toISOString().substring(0,10)}"></div>
                            <div><label>Existing customer</label><select id="so-customer" class="swal2-input" style="margin:2px 0;">${customerOptions(order ? order.customer_id : '')}</select></div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="so-cust-name" class="swal2-input" style="margin:2px 0;" placeholder="Walk-in name" value="${order ? escapeHtml(order.customer_name || '') : ''}">
                            <input id="so-cust-mobile" class="swal2-input" style="margin:2px 0;" placeholder="Mobile" value="${order ? escapeHtml(order.customer_mobile || '') : ''}">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="so-cust-email" class="swal2-input" style="margin:2px 0;" placeholder="Email (optional)" value="${order ? escapeHtml(order.customer_email || '') : ''}">
                            <input id="so-warehouse" class="swal2-input" style="margin:2px 0;" placeholder="Warehouse ID" value="${order ? order.warehouse_id : 1}">
                        </div>
                        <textarea id="so-cust-address" class="swal2-textarea" placeholder="Customer / shipping address" style="margin:2px 0;">${order ? escapeHtml(order.shipping_address || order.customer_address || '') : ''}</textarea>

                        <div style="margin-top:8px;"><strong>Line items</strong></div>
                        <div class="so-item-head"><div>Product</div><div>Qty</div><div>Unit</div><div>Rate</div><div>Disc</div><div>Tax %</div><div>Total</div><div></div></div>
                        <div id="so-items">${itemsHtml}</div>
                        <button type="button" id="so-add-row" class="adm-btn adm-btn-ghost" style="margin-top:4px;"><i class="fas fa-plus"></i> Add item</button>

                        <div class="so-totals">
                            <div>Subtotal: <span id="so-subtotal">₹0.00</span></div>
                            <div>Tax: <span id="so-tax">₹0.00</span></div>
                            <div><strong>Grand total: <span id="so-grand">₹0.00</span></strong></div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                            <select id="so-status" class="swal2-input" style="margin:2px 0;">
                                ${['draft','confirmed','processing','ready_for_dispatch','dispatched','delivered','completed'].map(s => `<option value="${s}" ${order && order.status === s ? 'selected' : ''}>${s.replace(/_/g,' ')}</option>`).join('')}
                            </select>
                            <input id="so-salesperson" class="swal2-input" style="margin:2px 0;" placeholder="Salesperson" value="${order ? escapeHtml(order.salesperson || '') : ''}">
                        </div>
                        <textarea id="so-notes" class="swal2-textarea" placeholder="Notes">${order ? escapeHtml(order.notes || '') : ''}</textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: isEdit ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                focusConfirm: false,
                didOpen: () => {
                    bindItemRowEvents();
                    recalcSoTotals();
                    $('#so-add-row').on('click', function() {
                        $('#so-items').append(itemRowHtml({}));
                        bindItemRowEvents();
                        recalcSoTotals();
                    });
                },
                preConfirm: () => {
                    const rows = collectItemRows('#so-items');
                    if (rows.length === 0) {
                        Swal.showValidationMessage('Add at least one valid line item');
                        return false;
                    }
                    const customerId = $('#so-customer').val();
                    const custName = $('#so-cust-name').val().trim();
                    if (!customerId && !custName) {
                        Swal.showValidationMessage('Select a customer or enter a walk-in customer name');
                        return false;
                    }
                    return {
                        id: order && isEdit ? order.id : null,
                        order_date: $('#so-date').val(),
                        customer_id: customerId || '',
                        customer_name: custName,
                        customer_mobile: $('#so-cust-mobile').val().trim(),
                        customer_email: $('#so-cust-email').val().trim(),
                        customer_address: $('#so-cust-address').val().trim(),
                        shipping_address: $('#so-cust-address').val().trim(),
                        billing_address: $('#so-cust-address').val().trim(),
                        warehouse_id: $('#so-warehouse').val() || 1,
                        status: $('#so-status').val(),
                        salesperson: $('#so-salesperson').val().trim(),
                        notes: $('#so-notes').val().trim(),
                        items: JSON.stringify(rows)
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveSalesOrder(result.value);
            });
        }

        function bindItemRowEvents() {
            $('#so-items .so-product, #so-items .so-qty, #so-items .so-rate, #so-items .so-discount, #so-items .so-tax').off('change input').on('change input', function() {
                const row = $(this).closest('.so-item-row');
                if ($(this).hasClass('so-product')) {
                    const rate = $(this).find(':selected').data('rate');
                    if (rate && !row.find('.so-rate').val()) row.find('.so-rate').val(rate);
                }
                recalcSoTotals();
            });
            $('#so-items .so-remove-row').off('click').on('click', function() {
                if ($('#so-items .so-item-row').length > 1) $(this).closest('.so-item-row').remove();
                else $(this).closest('.so-item-row').replaceWith(itemRowHtml({}));
                bindItemRowEvents();
                recalcSoTotals();
            });
        }

        function collectItemRows(containerSel) {
            const rows = [];
            $(containerSel + ' .so-item-row').each(function() {
                const productId = $(this).find('.so-product').val();
                const qty = parseFloat($(this).find('.so-qty').val());
                if (!productId || !qty || qty <= 0) return;
                rows.push({
                    product_id: productId,
                    quantity: qty,
                    unit: $(this).find('.so-unit').val() || 'pcs',
                    rate: parseFloat($(this).find('.so-rate').val()) || 0,
                    discount: parseFloat($(this).find('.so-discount').val()) || 0,
                    tax: parseFloat($(this).find('.so-tax').val()) || 0
                });
            });
            return rows;
        }

        function recalcSoTotals() {
            let subtotal = 0, tax = 0;
            $('#so-items .so-item-row').each(function() {
                const qty = parseFloat($(this).find('.so-qty').val()) || 0;
                const rate = parseFloat($(this).find('.so-rate').val()) || 0;
                const disc = parseFloat($(this).find('.so-discount').val()) || 0;
                const taxPct = parseFloat($(this).find('.so-tax').val()) || 0;
                const base = (qty * rate) - disc;
                const lineTax = base * (taxPct / 100);
                subtotal += base;
                tax += lineTax;
                $(this).find('.so-line-total').text('₹' + (base + lineTax).toFixed(2));
            });
            $('#so-subtotal').text('₹' + subtotal.toFixed(2));
            $('#so-tax').text('₹' + tax.toFixed(2));
            $('#so-grand').text('₹' + (subtotal + tax).toFixed(2));
        }

        function saveSalesOrder(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_sales_order.php', type: 'POST', data: data, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', text: response.so_number || '', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                        loadSalesOrders();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The sales order was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() { Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
            });
        }

        function cancelSalesOrder(id, number) {
            Swal.fire({
                title: 'Cancel this sales order?',
                text: `"${number}" will be marked cancelled (not deleted).`,
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#a8442f', cancelButtonColor: '#6b6459', confirmButtonText: 'Cancel order'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/cancel_sales_order.php', type: 'POST', data: { id: id }, dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Cancelled', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadSalesOrders();
                            } else {
                                Swal.fire({ title: 'Could not cancel', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() { Swal.fire({ title: 'Could not cancel', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
                    });
                }
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }
        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN');
        }
    </script>
</body>
</html>
