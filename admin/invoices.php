<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .inv-item-row { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; align-items:center; margin-bottom:6px; }
        .inv-item-row select, .inv-item-row input { width:100%; padding:6px; border:1px solid #ccc; border-radius:6px; font-size:13px; }
        .inv-item-head { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; font-size:11px; color:#777; margin-bottom:4px; }
        .inv-remove-row { background:none; border:none; color:#a8442f; cursor:pointer; font-size:14px; }
        .inv-totals { text-align:right; margin-top:10px; font-size:14px; }
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
                    <h1>Invoices</h1>
                    <div class="adm-sub">Billing documents — standalone, or auto-generated from a Sales Order the moment it's confirmed</div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="adm-btn adm-btn-ghost" id="genFromSoBtn" title="Auto-create invoices for any confirmed sales order that doesn't have one yet"><i class="fas fa-bolt"></i> Generate from Sales Orders</button>
                    <button class="adm-btn adm-btn-primary" id="addInvBtn"><i class="fas fa-plus"></i> New Invoice</button>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All invoices</h2>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="text" id="invSearch" class="adm-input" placeholder="Search invoice#, customer, mobile" style="width:220px;">
                        <select id="invStatusFilter" class="adm-select">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="issued">Issued</option>
                            <option value="partially_paid">Partially paid</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button class="adm-btn adm-btn-ghost" id="invFilterBtn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
                <div class="adm-card-body" id="invTable">
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
        const STATUS_BADGE = { draft:'is-neutral', issued:'is-info', partially_paid:'is-amber', paid:'is-green', overdue:'is-danger', cancelled:'is-danger' };

        $(document).ready(function() {
            loadLookups().then(function() {
                loadInvoices();
                const fromSo = new URLSearchParams(window.location.search).get('from_so');
                if (fromSo) openInvModalFromSo(fromSo);
            });
            $('#addInvBtn').on('click', function() { openInvModal(null); });
            $('#invFilterBtn').on('click', loadInvoices);
            $('#invSearch').on('keyup', function(e) { if (e.key === 'Enter') loadInvoices(); });
            $('#genFromSoBtn').on('click', generateInvoicesFromSalesOrders);
        });

        function generateInvoicesFromSalesOrders() {
            Swal.fire({
                title: 'Generate invoices from Sales Orders?',
                text: 'Every confirmed sales order that doesn\'t have an invoice yet will get one automatically, using that order\'s own items and amounts.',
                icon: 'question', showCancelButton: true,
                confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459', confirmButtonText: 'Generate'
            }).then((result) => {
                if (!result.isConfirmed) return;
                Swal.fire({ title: 'Generating…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                $.ajax({
                    url: '../assets/db_query/admin/generate_invoices_for_sales_orders.php', type: 'POST', dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                title: response.created_count > 0 ? `${response.created_count} invoice(s) created` : 'Nothing to generate',
                                text: response.created_count > 0 ? response.created.join(', ') : 'Every confirmed sales order already has an invoice.',
                                icon: 'success', confirmButtonColor: '#1c5034'
                            });
                            loadInvoices();
                        } else {
                            Swal.fire({ title: 'Could not generate', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() { Swal.fire({ title: 'Could not generate', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
                });
            });
        }

        function loadLookups() {
            const p1 = $.ajax({ url: '../assets/db_query/admin/get_products_for_sales.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') PRODUCTS = data.products || []; })
                .fail(function() { PRODUCTS = []; });
            const p2 = $.ajax({ url: '../assets/db_query/admin/get_customers.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') CUSTOMERS = data.customers || []; })
                .fail(function() { CUSTOMERS = []; });
            return $.when(p1, p2);
        }

        function loadInvoices() {
            const params = { q: $('#invSearch').val() || '', status: $('#invStatusFilter').val() || '' };
            $.ajax({
                url: '../assets/db_query/admin/get_invoices.php', type: 'GET', data: params, dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') displayInvoices(data.invoices);
                    else $('#invTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load invoices.') + '</div>');
                },
                error: function() { $('#invTable').html('<div class="adm-error">Could not reach the server while loading invoices.</div>'); }
            });
        }

        function displayInvoices(rows) {
            if (rows.length === 0) {
                $('#invTable').html('<div class="adm-empty"><i class="fas fa-receipt"></i><p><strong>No invoices yet</strong></p><p>Create one to get started.</p></div>');
                return;
            }
            let html = '';
            const today = new Date().toISOString().substring(0,10);
            rows.forEach(inv => {
                const isOverdue = inv.due_date && inv.due_date.substring(0,10) < today && !['paid','cancelled'].includes(inv.status);
                const badge = STATUS_BADGE[isOverdue ? 'overdue' : inv.status] || 'is-neutral';
                const canEdit = ['draft','issued','partially_paid'].includes(inv.status);
                const canPay = ['issued','partially_paid'].includes(inv.status);
                html += `<tr>
                    <td class="adm-cell-title">${escapeHtml(inv.invoice_number)}<div class="adm-cell-sub">${formatDate(inv.invoice_date)}</div></td>
                    <td>${escapeHtml(inv.customer_name || '—')}<div class="adm-cell-sub">${escapeHtml(inv.customer_mobile || '')}</div></td>
                    <td><span class="adm-badge ${badge}">${isOverdue ? 'overdue' : inv.status.replace(/_/g,' ')}</span></td>
                    <td class="adm-money">₹${parseFloat(inv.grand_total).toFixed(2)}<div class="adm-cell-sub">Paid ₹${parseFloat(inv.amount_paid).toFixed(2)}</div></td>
                    <td style="white-space:nowrap;">
                        <button class="adm-icon-btn view-inv" data-id="${inv.id}" title="View"><i class="fas fa-eye"></i></button>
                        ${canEdit ? `<button class="adm-icon-btn edit-inv" data-id="${inv.id}" title="Edit"><i class="fas fa-pen"></i></button>` : ''}
                        <button class="adm-icon-btn print-inv" data-id="${inv.id}" title="Print / Download"><i class="fas fa-print"></i></button>
                        ${canPay ? `<button class="adm-icon-btn pay-inv" data-id="${inv.id}" data-balance="${(inv.grand_total - inv.amount_paid).toFixed(2)}" title="Record payment"><i class="fas fa-money-bill"></i></button>` : ''}
                        ${inv.status !== 'cancelled' && inv.status !== 'paid' ? `<button class="adm-icon-btn is-danger cancel-inv" data-id="${inv.id}" data-number="${escapeHtml(inv.invoice_number)}" title="Cancel"><i class="fas fa-ban"></i></button>` : ''}
                    </td>
                </tr>`;
            });
            $('#invTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Invoice #</th><th>Customer</th><th>Status</th><th>Amount</th><th>Actions</th></tr></thead>
                <tbody>${html}</tbody></table></div>`);

            $('.view-inv').on('click', function() { viewInvoice($(this).data('id')); });
            $('.edit-inv').on('click', function() { openInvModalById($(this).data('id')); });
            $('.print-inv').on('click', function() { window.open('print_invoice.php?id=' + $(this).data('id'), '_blank'); });
            $('.pay-inv').on('click', function() { recordPayment($(this).data('id'), $(this).data('balance')); });
            $('.cancel-inv').on('click', function() { cancelInvoice($(this).data('id'), $(this).data('number')); });
        }

        function viewInvoice(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_invoice.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load invoice', 'error'); return; }
                    const inv = data.invoice;
                    let itemRows = (inv.items || []).map(it => `<tr><td>${escapeHtml(it.product_name || ('#' + it.product_id))}</td><td>${it.quantity} ${escapeHtml(it.unit)}</td><td>₹${parseFloat(it.rate).toFixed(2)}</td><td>₹${parseFloat(it.line_total).toFixed(2)}</td></tr>`).join('');
                    Swal.fire({
                        title: inv.invoice_number, width: 700,
                        html: `<div style="text-align:left; font-size:14px;">
                            <p><strong>Customer:</strong> ${escapeHtml(inv.customer_name || '—')} (${escapeHtml(inv.customer_mobile || '—')})</p>
                            <p><strong>Status:</strong> ${escapeHtml(inv.status.replace(/_/g,' '))} &nbsp; <strong>Due:</strong> ${formatDate(inv.due_date)}</p>
                            <table class="adm-table" style="width:100%; font-size:13px;"><thead><tr><th>Product</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>${itemRows}</tbody></table>
                            <div style="text-align:right; margin-top:8px;">
                                <div>Subtotal: ₹${parseFloat(inv.subtotal).toFixed(2)}</div>
                                <div>Tax: ₹${parseFloat(inv.tax_amount).toFixed(2)}</div>
                                <div><strong>Grand total: ₹${parseFloat(inv.grand_total).toFixed(2)}</strong></div>
                                <div>Paid: ₹${parseFloat(inv.amount_paid).toFixed(2)}</div>
                            </div>
                        </div>`,
                        confirmButtonText: 'Close', confirmButtonColor: '#1c5034'
                    });
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function productOptions(selectedId) {
            let opts = '<option value="">Select product…</option>';
            PRODUCTS.forEach(p => { opts += `<option value="${p.id}" data-rate="${p.dis_price || p.price}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(p.product_name)}</option>`; });
            return opts;
        }
        function customerOptions(selectedId) {
            let opts = '<option value="">— Walk-in customer (enter details below) —</option>';
            CUSTOMERS.forEach(c => { opts += `<option value="${c.id}" ${String(c.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(c.username || c.email)} (${escapeHtml(c.phone_number || c.email || '')})</option>`; });
            return opts;
        }

        function invItemRowHtml(item) {
            item = item || {};
            return `<div class="inv-item-row">
                <select class="inv-product">${productOptions(item.product_id)}</select>
                <input type="number" class="inv-qty" min="0" step="0.01" placeholder="Qty" value="${item.quantity || ''}">
                <input type="text" class="inv-unit" placeholder="Unit" value="${item.unit || 'pcs'}">
                <input type="number" class="inv-rate" min="0" step="0.01" placeholder="Rate" value="${item.rate || ''}">
                <input type="number" class="inv-discount" min="0" step="0.01" placeholder="Disc" value="${item.discount || 0}">
                <input type="number" class="inv-tax" min="0" step="0.01" placeholder="Tax %" value="${item.tax || 0}">
                <span class="inv-line-total adm-money">₹0.00</span>
                <button type="button" class="inv-remove-row" title="Remove"><i class="fas fa-times"></i></button>
            </div>`;
        }

        function bindInvRowEvents() {
            $('#inv-items .inv-product, #inv-items .inv-qty, #inv-items .inv-rate, #inv-items .inv-discount, #inv-items .inv-tax').off('change input').on('change input', function() {
                const row = $(this).closest('.inv-item-row');
                if ($(this).hasClass('inv-product')) {
                    const rate = $(this).find(':selected').data('rate');
                    if (rate && !row.find('.inv-rate').val()) row.find('.inv-rate').val(rate);
                }
                recalcInvTotals();
            });
            $('#inv-items .inv-remove-row').off('click').on('click', function() {
                if ($('#inv-items .inv-item-row').length > 1) $(this).closest('.inv-item-row').remove();
                else $(this).closest('.inv-item-row').replaceWith(invItemRowHtml({}));
                bindInvRowEvents();
                recalcInvTotals();
            });
        }

        function collectInvRows() {
            const rows = [];
            $('#inv-items .inv-item-row').each(function() {
                const productId = $(this).find('.inv-product').val();
                const qty = parseFloat($(this).find('.inv-qty').val());
                if (!productId || !qty || qty <= 0) return;
                rows.push({
                    product_id: productId, quantity: qty, unit: $(this).find('.inv-unit').val() || 'pcs',
                    rate: parseFloat($(this).find('.inv-rate').val()) || 0,
                    discount: parseFloat($(this).find('.inv-discount').val()) || 0,
                    tax: parseFloat($(this).find('.inv-tax').val()) || 0
                });
            });
            return rows;
        }

        function recalcInvTotals() {
            let subtotal = 0, tax = 0;
            $('#inv-items .inv-item-row').each(function() {
                const qty = parseFloat($(this).find('.inv-qty').val()) || 0;
                const rate = parseFloat($(this).find('.inv-rate').val()) || 0;
                const disc = parseFloat($(this).find('.inv-discount').val()) || 0;
                const taxPct = parseFloat($(this).find('.inv-tax').val()) || 0;
                const base = (qty * rate) - disc;
                const lineTax = base * (taxPct / 100);
                subtotal += base; tax += lineTax;
                $(this).find('.inv-line-total').text('₹' + (base + lineTax).toFixed(2));
            });
            $('#inv-subtotal').text('₹' + subtotal.toFixed(2));
            $('#inv-tax').text('₹' + tax.toFixed(2));
            $('#inv-grand').text('₹' + (subtotal + tax).toFixed(2));
        }

        function openInvModalById(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_invoice.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load invoice', 'error'); return; }
                    openInvModal(data.invoice);
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function openInvModalFromSo(soId) {
            $.ajax({
                url: '../assets/db_query/admin/get_sales_order.php', type: 'GET', data: { id: soId }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load sales order', 'error'); return; }
                    const so = data.sales_order;
                    if (so.invoices && so.invoices.length > 0) {
                        Swal.fire('Already invoiced', `An invoice already exists for ${so.so_number} (${so.invoices[0].invoice_number}).`, 'warning');
                        return;
                    }
                    openInvModal({
                        sales_order_id: so.id, so_number: so.so_number, customer_id: so.customer_id,
                        customer_name: so.customer_name, customer_mobile: so.customer_mobile, customer_email: so.customer_email,
                        billing_address: so.billing_address, shipping_address: so.shipping_address,
                        items: (so.items || []).map(it => ({ product_id: it.product_id, sku: it.sku, quantity: it.quantity, unit: it.unit, rate: it.rate, discount: it.discount, tax: it.tax }))
                    });
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function openInvModal(inv) {
            const isEdit = inv && inv.id;
            const items = (inv && inv.items && inv.items.length) ? inv.items : [{}];
            const itemsHtml = items.map(invItemRowHtml).join('');

            Swal.fire({
                title: isEdit ? `Edit ${inv.invoice_number}` : (inv && inv.sales_order_id ? `New Invoice from ${inv.so_number}` : 'New Invoice'),
                customClass: { popup: 'swal-wide' },
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <input type="hidden" id="inv-so-id" value="${inv && inv.sales_order_id ? inv.sales_order_id : ''}">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div><label>Invoice date</label><input type="date" id="inv-date" class="swal2-input" style="margin:2px 0;" value="${inv && inv.invoice_date ? inv.invoice_date.substring(0,10) : new Date().toISOString().substring(0,10)}"></div>
                            <select id="inv-customer" class="swal2-input" style="margin:2px 0;">${customerOptions(inv ? inv.customer_id : '')}</select>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="inv-cust-name" class="swal2-input" style="margin:2px 0;" placeholder="Walk-in name" value="${inv ? escapeHtml(inv.customer_name || '') : ''}">
                            <input id="inv-cust-mobile" class="swal2-input" style="margin:2px 0;" placeholder="Mobile" value="${inv ? escapeHtml(inv.customer_mobile || '') : ''}">
                        </div>
                        <textarea id="inv-billing" class="swal2-textarea" placeholder="Billing address" style="margin:2px 0;">${inv ? escapeHtml(inv.billing_address || '') : ''}</textarea>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div><label>Due date</label><input type="date" id="inv-due-date" class="swal2-input" style="margin:2px 0;" value="${inv && inv.due_date ? inv.due_date.substring(0,10) : ''}"></div>
                            <select id="inv-status" class="swal2-input" style="margin:2px 0;">
                                ${['draft','issued','partially_paid','paid'].map(s => `<option value="${s}" ${inv && inv.status === s ? 'selected' : ''}>${s.replace(/_/g,' ')}</option>`).join('')}
                            </select>
                        </div>

                        <div style="margin-top:8px;"><strong>Line items</strong></div>
                        <div class="inv-item-head"><div>Product</div><div>Qty</div><div>Unit</div><div>Rate</div><div>Disc</div><div>Tax %</div><div>Total</div><div></div></div>
                        <div id="inv-items">${itemsHtml}</div>
                        <button type="button" id="inv-add-row" class="adm-btn adm-btn-ghost" style="margin-top:4px;"><i class="fas fa-plus"></i> Add item</button>

                        <div class="inv-totals">
                            <div>Subtotal: <span id="inv-subtotal">₹0.00</span></div>
                            <div>Tax: <span id="inv-tax">₹0.00</span></div>
                            <div><strong>Grand total: <span id="inv-grand">₹0.00</span></strong></div>
                        </div>
                        <textarea id="inv-notes" class="swal2-textarea" placeholder="Notes">${inv ? escapeHtml(inv.notes || '') : ''}</textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: isEdit ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                focusConfirm: false,
                didOpen: () => {
                    bindInvRowEvents();
                    recalcInvTotals();
                    $('#inv-add-row').on('click', function() { $('#inv-items').append(invItemRowHtml({})); bindInvRowEvents(); recalcInvTotals(); });
                    $('#inv-customer').on('change', function() {
                        const custId = $(this).val();
                        if (custId) fillCustomerDetails(custId);
                    });
                },
                preConfirm: () => {
                    const rows = collectInvRows();
                    if (rows.length === 0) { Swal.showValidationMessage('Add at least one valid line item'); return false; }
                    const customerId = $('#inv-customer').val();
                    const custName = $('#inv-cust-name').val().trim();
                    if (!customerId && !custName) { Swal.showValidationMessage('Select a customer or enter a walk-in customer name'); return false; }
                    return {
                        id: isEdit ? inv.id : null,
                        invoice_date: $('#inv-date').val(),
                        customer_id: customerId || '',
                        customer_name: custName,
                        customer_mobile: $('#inv-cust-mobile').val().trim(),
                        billing_address: $('#inv-billing').val().trim(),
                        shipping_address: $('#inv-billing').val().trim(),
                        sales_order_id: $('#inv-so-id').val(),
                        due_date: $('#inv-due-date').val(),
                        status: $('#inv-status').val(),
                        notes: $('#inv-notes').val().trim(),
                        items: JSON.stringify(rows)
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveInvoice(result.value);
            });
        }

        function fillCustomerDetails(custId) {
            // Instant fill from the already-loaded customer list (name, mobile)...
            const c = CUSTOMERS.find(x => String(x.id) === String(custId));
            if (c) {
                $('#inv-cust-name').val(c.username || c.email || '');
                $('#inv-cust-mobile').val(c.phone_number || '');
            }
            // ...then fetch their billing address (from their most recent order) in the background.
            $.ajax({
                url: '../assets/db_query/admin/get_customer_details.php', type: 'GET', data: { id: custId }, dataType: 'json',
                success: function(data) {
                    if (data.status === 'success' && data.customer) {
                        if (data.customer.name) $('#inv-cust-name').val(data.customer.name);
                        if (data.customer.mobile) $('#inv-cust-mobile').val(data.customer.mobile);
                        if (data.customer.address) $('#inv-billing').val(data.customer.address);
                    }
                }
            });
        }

        function saveInvoice(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_invoice.php', type: 'POST', data: data, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', text: response.invoice_number || '', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                        loadInvoices();
                        if (window.location.search.includes('from_so')) history.replaceState(null, '', 'invoices.php');
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The invoice was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() { Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
            });
        }

        function recordPayment(id, balance) {
            Swal.fire({
                title: 'Record payment',
                html: `<input id="pay-amount" type="number" min="0.01" step="0.01" class="swal2-input" placeholder="Amount" value="${balance}">
                       <select id="pay-mode" class="swal2-input">
                           <option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>
                           <option value="card">Card</option><option value="credit">Credit</option><option value="other">Other</option>
                       </select>`,
                showCancelButton: true, confirmButtonText: 'Record', confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const amt = parseFloat($('#pay-amount').val());
                    if (!amt || amt <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
                    return { id: id, amount: amt, payment_mode: $('#pay-mode').val() };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/record_invoice_payment.php', type: 'POST', data: result.value, dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Payment recorded', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadInvoices();
                            } else {
                                Swal.fire({ title: 'Could not record payment', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() { Swal.fire({ title: 'Could not record payment', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
                    });
                }
            });
        }

        function cancelInvoice(id, number) {
            Swal.fire({
                title: 'Cancel this invoice?', text: `"${number}" will be marked cancelled (not deleted).`,
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#a8442f', cancelButtonColor: '#6b6459', confirmButtonText: 'Cancel invoice'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/cancel_invoice.php', type: 'POST', data: { id: id }, dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Cancelled', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadInvoices();
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
