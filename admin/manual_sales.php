<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Sales — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .ms-item-row { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; align-items:center; margin-bottom:6px; }
        .ms-item-row select, .ms-item-row input { width:100%; padding:6px; border:1px solid #ccc; border-radius:6px; font-size:13px; }
        .ms-item-head { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; font-size:11px; color:#777; margin-bottom:4px; }
        .ms-remove-row { background:none; border:none; color:#a8442f; cursor:pointer; font-size:14px; }
        .swal-wide { width: 780px !important; }
    </style>
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Manual Sales</h1>
                    <div class="adm-sub">Quick over-the-counter sale entry</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addMsBtn"><i class="fas fa-plus"></i> New Manual Sale</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>Recent manual sales</h2>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="text" id="msSearch" class="adm-input" placeholder="Search sale#, customer, mobile" style="width:220px;">
                        <button class="adm-btn adm-btn-ghost" id="msFilterBtn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
                <div class="adm-card-body" id="msTable">
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
        const PAY_STATUS_BADGE = { paid:'is-green', partially_paid:'is-amber', pending:'is-danger', credit:'is-info' };

        $(document).ready(function() {
            loadProducts().then(loadManualSales);
            $('#addMsBtn').on('click', function() { openMsModal(); });
            $('#msFilterBtn').on('click', loadManualSales);
            $('#msSearch').on('keyup', function(e) { if (e.key === 'Enter') loadManualSales(); });
        });

        function loadProducts() {
            return $.ajax({ url: '../assets/db_query/admin/get_products_for_sales.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') PRODUCTS = data.products || []; })
                .fail(function() { PRODUCTS = []; });
        }

        function loadManualSales() {
            $.ajax({
                url: '../assets/db_query/admin/get_manual_sales.php', type: 'GET', data: { q: $('#msSearch').val() || '' }, dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') displayManualSales(data.manual_sales);
                    else $('#msTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load manual sales.') + '</div>');
                },
                error: function() { $('#msTable').html('<div class="adm-error">Could not reach the server while loading manual sales.</div>'); }
            });
        }

        function displayManualSales(rows) {
            if (rows.length === 0) {
                $('#msTable').html('<div class="adm-empty"><i class="fas fa-cash-register"></i><p><strong>No manual sales yet</strong></p><p>Record one to get started.</p></div>');
                return;
            }
            let html = '';
            rows.forEach(s => {
                const badge = PAY_STATUS_BADGE[s.payment_status] || 'is-neutral';
                html += `<tr>
                    <td class="adm-cell-title">${escapeHtml(s.sale_number)}<div class="adm-cell-sub">${formatDate(s.sales_date)}</div></td>
                    <td>${escapeHtml(s.customer_name || '—')}<div class="adm-cell-sub">${escapeHtml(s.customer_mobile || '')}</div></td>
                    <td><span class="adm-badge ${badge}">${escapeHtml(s.payment_status.replace(/_/g,' '))}</span></td>
                    <td class="adm-money">₹${parseFloat(s.grand_total).toFixed(2)}</td>
                    <td>${Number(s.stock_deducted) === 1 ? '<span class="adm-badge is-green">Stock deducted</span>' : '<span class="adm-badge is-amber">Pending confirmation</span>'}</td>
                    <td style="white-space:nowrap;">
                        <button class="adm-icon-btn view-ms" data-id="${s.id}" title="View"><i class="fas fa-eye"></i></button>
                        ${Number(s.stock_deducted) === 0 ? `<button class="adm-icon-btn confirm-ms" data-id="${s.id}" data-number="${escapeHtml(s.sale_number)}" title="Confirm & deduct stock"><i class="fas fa-check-circle"></i></button>` : ''}
                    </td>
                </tr>`;
            });
            $('#msTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Sale #</th><th>Customer</th><th>Payment</th><th>Total</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>${html}</tbody></table></div>`);

            $('.view-ms').on('click', function() { viewMs($(this).data('id')); });
            $('.confirm-ms').on('click', function() { confirmMs($(this).data('id'), $(this).data('number')); });
        }

        function viewMs(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_manual_sale.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load sale', 'error'); return; }
                    const s = data.manual_sale;
                    let itemRows = (s.items || []).map(it => `<tr><td>${escapeHtml(it.product_name || ('#' + it.product_id))}</td><td>${it.quantity}</td><td>₹${parseFloat(it.rate).toFixed(2)}</td><td>₹${parseFloat(it.line_total).toFixed(2)}</td></tr>`).join('');
                    Swal.fire({
                        title: s.sale_number, width: 650,
                        html: `<div style="text-align:left; font-size:14px;">
                            <p><strong>Customer:</strong> ${escapeHtml(s.customer_name || '—')} (${escapeHtml(s.customer_mobile || '—')})</p>
                            <p><strong>Payment:</strong> ${escapeHtml(s.payment_mode)} — ${escapeHtml(s.payment_status.replace(/_/g,' '))}</p>
                            <table class="adm-table" style="width:100%; font-size:13px;"><thead><tr><th>Product</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>${itemRows}</tbody></table>
                            <div style="text-align:right; margin-top:8px;"><strong>Grand total: ₹${parseFloat(s.grand_total).toFixed(2)}</strong></div>
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

        function msItemRowHtml(item) {
            item = item || {};
            return `<div class="ms-item-row">
                <select class="ms-product">${productOptions(item.product_id)}</select>
                <input type="number" class="ms-qty" min="0" step="0.01" placeholder="Qty" value="${item.quantity || ''}">
                <input type="number" class="ms-rate" min="0" step="0.01" placeholder="Rate" value="${item.rate || ''}">
                <input type="number" class="ms-discount" min="0" step="0.01" placeholder="Disc" value="0">
                <input type="number" class="ms-tax" min="0" step="0.01" placeholder="Tax %" value="0">
                <span class="ms-line-total adm-money">₹0.00</span>
                <button type="button" class="ms-remove-row" title="Remove"><i class="fas fa-times"></i></button>
            </div>`;
        }

        function bindMsRowEvents() {
            $('#ms-items .ms-product, #ms-items .ms-qty, #ms-items .ms-rate, #ms-items .ms-discount, #ms-items .ms-tax').off('change input').on('change input', function() {
                const row = $(this).closest('.ms-item-row');
                if ($(this).hasClass('ms-product')) {
                    const rate = $(this).find(':selected').data('rate');
                    if (rate && !row.find('.ms-rate').val()) row.find('.ms-rate').val(rate);
                }
                recalcMsTotals();
            });
            $('#ms-items .ms-remove-row').off('click').on('click', function() {
                if ($('#ms-items .ms-item-row').length > 1) $(this).closest('.ms-item-row').remove();
                else $(this).closest('.ms-item-row').replaceWith(msItemRowHtml({}));
                bindMsRowEvents();
                recalcMsTotals();
            });
        }

        function collectMsRows() {
            const rows = [];
            $('#ms-items .ms-item-row').each(function() {
                const productId = $(this).find('.ms-product').val();
                const qty = parseFloat($(this).find('.ms-qty').val());
                if (!productId || !qty || qty <= 0) return;
                rows.push({
                    product_id: productId, quantity: qty,
                    rate: parseFloat($(this).find('.ms-rate').val()) || 0,
                    discount: parseFloat($(this).find('.ms-discount').val()) || 0,
                    tax: parseFloat($(this).find('.ms-tax').val()) || 0
                });
            });
            return rows;
        }

        function recalcMsTotals() {
            let subtotal = 0, tax = 0;
            $('#ms-items .ms-item-row').each(function() {
                const qty = parseFloat($(this).find('.ms-qty').val()) || 0;
                const rate = parseFloat($(this).find('.ms-rate').val()) || 0;
                const disc = parseFloat($(this).find('.ms-discount').val()) || 0;
                const taxPct = parseFloat($(this).find('.ms-tax').val()) || 0;
                const base = (qty * rate) - disc;
                const lineTax = base * (taxPct / 100);
                subtotal += base; tax += lineTax;
                $(this).find('.ms-line-total').text('₹' + (base + lineTax).toFixed(2));
            });
            $('#ms-grand').text('₹' + (subtotal + tax).toFixed(2));
        }

        function openMsModal() {
            const itemsHtml = [{}].map(msItemRowHtml).join('');
            Swal.fire({
                title: 'New Manual Sale',
                customClass: { popup: 'swal-wide' },
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="ms-cust-name" class="swal2-input" style="margin:2px 0;" placeholder="Customer name">
                            <input id="ms-cust-mobile" class="swal2-input" style="margin:2px 0;" placeholder="Mobile">
                        </div>
                        <textarea id="ms-cust-address" class="swal2-textarea" placeholder="Address (optional)"></textarea>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <select id="ms-pay-mode" class="swal2-input" style="margin:2px 0;">
                                <option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>
                                <option value="card">Card</option><option value="credit">Credit</option><option value="other">Other</option>
                            </select>
                            <select id="ms-pay-status" class="swal2-input" style="margin:2px 0;">
                                <option value="paid">Paid</option><option value="partially_paid">Partially paid</option>
                                <option value="pending">Pending</option><option value="credit">Credit</option>
                            </select>
                        </div>

                        <div style="margin-top:8px;"><strong>Items</strong></div>
                        <div class="ms-item-head"><div>Product</div><div>Qty</div><div>Rate</div><div>Disc</div><div>Tax %</div><div>Total</div><div></div></div>
                        <div id="ms-items">${itemsHtml}</div>
                        <button type="button" id="ms-add-row" class="adm-btn adm-btn-ghost" style="margin-top:4px;"><i class="fas fa-plus"></i> Add item</button>

                        <div style="text-align:right; margin-top:8px;"><strong>Grand total: <span id="ms-grand">₹0.00</span></strong></div>
                        <textarea id="ms-notes" class="swal2-textarea" placeholder="Notes"></textarea>
                    </div>
                `,
                showCancelButton: true, confirmButtonText: 'Save sale', confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459',
                focusConfirm: false,
                didOpen: () => {
                    bindMsRowEvents(); recalcMsTotals();
                    $('#ms-add-row').on('click', function() { $('#ms-items').append(msItemRowHtml({})); bindMsRowEvents(); recalcMsTotals(); });
                },
                preConfirm: () => {
                    const rows = collectMsRows();
                    if (rows.length === 0) { Swal.showValidationMessage('Add at least one valid line item'); return false; }
                    const custName = $('#ms-cust-name').val().trim();
                    if (!custName) { Swal.showValidationMessage('Customer name is required'); return false; }
                    return {
                        customer_name: custName,
                        customer_mobile: $('#ms-cust-mobile').val().trim(),
                        customer_address: $('#ms-cust-address').val().trim(),
                        payment_mode: $('#ms-pay-mode').val(),
                        payment_status: $('#ms-pay-status').val(),
                        notes: $('#ms-notes').val().trim(),
                        items: JSON.stringify(rows)
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveManualSale(result.value);
            });
        }

        function saveManualSale(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_manual_sale.php', type: 'POST', data: data, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        loadManualSales();
                        confirmMs(response.id, response.sale_number, true);
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The sale was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() { Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
            });
        }

        // A confirmation step before touching stock — prevents an accidental
        // duplicate stock deduction from a double-click or re-run.
        function confirmMs(id, number, justCreated) {
            Swal.fire({
                title: justCreated ? 'Sale saved — deduct stock now?' : 'Confirm stock deduction?',
                text: `This will reduce stock for every item in ${number}. This can only be done once per sale.`,
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Confirm & deduct stock', cancelButtonText: justCreated ? 'Later' : 'Cancel',
                confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/save_manual_sale.php', type: 'POST', data: { id: id, confirm: 1 }, dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Stock deducted', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                                loadManualSales();
                            } else {
                                Swal.fire({ title: 'Could not deduct stock', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() { Swal.fire({ title: 'Could not deduct stock', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
                    });
                } else if (!justCreated) {
                    // no-op
                } else {
                    loadManualSales();
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
