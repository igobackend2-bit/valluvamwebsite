<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Sale — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .cs-item-row { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; align-items:center; margin-bottom:6px; }
        .cs-item-row select, .cs-item-row input { width:100%; padding:6px; border:1px solid #ccc; border-radius:6px; font-size:13px; }
        .cs-item-head { display:grid; grid-template-columns: 2fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 32px; gap:6px; font-size:11px; color:#777; margin-bottom:4px; }
        .cs-remove-row { background:none; border:none; color:#a8442f; cursor:pointer; font-size:14px; }
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
                    <h1>Credit Sale</h1>
                    <div class="adm-sub">Goods given to a customer now, paid for later — tracks what's owed and lets you record repayments as they come in.</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addCsBtn"><i class="fas fa-plus"></i> New Credit Sale</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All credit sales</h2>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="text" id="csSearch" class="adm-input" placeholder="Search credit#, customer, mobile" style="width:220px;">
                        <select id="csStatusFilter" class="adm-select">
                            <option value="">All statuses</option>
                            <option value="outstanding">Outstanding</option>
                            <option value="partially_paid">Partially paid</option>
                            <option value="paid">Paid</option>
                        </select>
                        <button class="adm-btn adm-btn-ghost" id="csFilterBtn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
                <div class="adm-card-body" id="csTable">
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
        const CS_STATUS_BADGE = { paid:'is-green', partially_paid:'is-amber', outstanding:'is-danger' };

        $(document).ready(function() {
            loadProducts().then(loadCreditSales);
            $('#addCsBtn').on('click', function() { openCsModal(); });
            $('#csFilterBtn').on('click', loadCreditSales);
            $('#csStatusFilter').on('change', loadCreditSales);
            $('#csSearch').on('keyup', function(e) { if (e.key === 'Enter') loadCreditSales(); });
        });

        function loadProducts() {
            return $.ajax({ url: '../assets/db_query/admin/get_products_for_sales.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') PRODUCTS = data.products || []; })
                .fail(function() { PRODUCTS = []; });
        }

        function loadCreditSales() {
            $.ajax({
                url: '../assets/db_query/admin/get_credit_sales.php', type: 'GET',
                data: { q: $('#csSearch').val() || '', status: $('#csStatusFilter').val() || '' }, dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') displayCreditSales(data.credit_sales);
                    else $('#csTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load credit sales.') + '</div>');
                },
                error: function() { $('#csTable').html('<div class="adm-error">Could not reach the server while loading credit sales.</div>'); }
            });
        }

        function displayCreditSales(rows) {
            if (rows.length === 0) {
                $('#csTable').html('<div class="adm-empty"><i class="fas fa-hand-holding-dollar"></i><p><strong>No credit sales yet</strong></p><p>Record one to get started.</p></div>');
                return;
            }
            let html = '';
            rows.forEach(s => {
                const badge = CS_STATUS_BADGE[s.status] || 'is-neutral';
                html += `<tr>
                    <td class="adm-cell-title">${escapeHtml(s.credit_number)}<div class="adm-cell-sub">${formatDate(s.sale_date)}</div></td>
                    <td>${escapeHtml(s.customer_name || '—')}<div class="adm-cell-sub">${escapeHtml(s.customer_mobile || '')}</div></td>
                    <td class="adm-money">₹${parseFloat(s.grand_total).toFixed(2)}</td>
                    <td class="adm-money">₹${parseFloat(s.amount_paid).toFixed(2)}</td>
                    <td class="adm-money"><strong>₹${parseFloat(s.balance_due).toFixed(2)}</strong></td>
                    <td><span class="adm-badge ${badge}">${escapeHtml(s.status.replace(/_/g,' '))}</span></td>
                    <td>${Number(s.stock_deducted) === 1 ? '<span class="adm-badge is-green">Stock deducted</span>' : '<span class="adm-badge is-amber">Pending confirmation</span>'}</td>
                    <td style="white-space:nowrap;">
                        <button class="adm-icon-btn view-cs" data-id="${s.id}" title="View"><i class="fas fa-eye"></i></button>
                        ${Number(s.stock_deducted) === 0 ? `<button class="adm-icon-btn confirm-cs" data-id="${s.id}" data-number="${escapeHtml(s.credit_number)}" title="Confirm & deduct stock"><i class="fas fa-check-circle"></i></button>` : ''}
                        ${s.status !== 'paid' ? `<button class="adm-btn adm-btn-ghost record-pay" data-id="${s.id}" data-due="${s.balance_due}" data-number="${escapeHtml(s.credit_number)}" title="Record payment"><i class="fas fa-indian-rupee-sign"></i> Payment</button>` : ''}
                    </td>
                </tr>`;
            });
            $('#csTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Credit #</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance Due</th><th>Status</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>${html}</tbody></table></div>`);

            $('.view-cs').on('click', function() { viewCs($(this).data('id')); });
            $('.confirm-cs').on('click', function() { confirmCs($(this).data('id'), $(this).data('number')); });
            $('.record-pay').on('click', function() { openRecordPayment($(this).data('id'), $(this).data('number'), $(this).data('due')); });
        }

        function viewCs(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_credit_sale.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load credit sale', 'error'); return; }
                    const s = data.credit_sale;
                    let itemRows = (s.items || []).map(it => `<tr><td>${escapeHtml(it.product_name || ('#' + it.product_id))}</td><td>${it.quantity}</td><td>₹${parseFloat(it.rate).toFixed(2)}</td><td>₹${parseFloat(it.line_total).toFixed(2)}</td></tr>`).join('');
                    let payRows = (s.payments || []).map(p => `<tr><td>${formatDate(p.created_at)}</td><td>₹${parseFloat(p.amount).toFixed(2)}</td><td>${escapeHtml(p.payment_mode || '—')}</td><td>${escapeHtml(p.notes || '')}</td></tr>`).join('');
                    Swal.fire({
                        title: s.credit_number, width: 680,
                        html: `<div style="text-align:left; font-size:14px;">
                            <p><strong>Customer:</strong> ${escapeHtml(s.customer_name || '—')} (${escapeHtml(s.customer_mobile || '—')})</p>
                            <table class="adm-table" style="width:100%; font-size:13px;"><thead><tr><th>Product</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>${itemRows}</tbody></table>
                            <div style="text-align:right; margin-top:8px;">
                                <div>Grand total: ₹${parseFloat(s.grand_total).toFixed(2)}</div>
                                <div>Paid: ₹${parseFloat(s.amount_paid).toFixed(2)}</div>
                                <div><strong>Balance due: ₹${parseFloat(s.balance_due).toFixed(2)}</strong></div>
                            </div>
                            ${payRows ? `<div style="margin-top:10px;"><strong>Payment history</strong><table class="adm-table" style="width:100%; font-size:13px;"><thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Notes</th></tr></thead><tbody>${payRows}</tbody></table></div>` : ''}
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

        function csItemRowHtml(item) {
            item = item || {};
            return `<div class="cs-item-row">
                <select class="cs-product">${productOptions(item.product_id)}</select>
                <input type="number" class="cs-qty" min="0" step="0.01" placeholder="Qty" value="${item.quantity || ''}">
                <input type="number" class="cs-rate" min="0" step="0.01" placeholder="Rate" value="${item.rate || ''}">
                <input type="number" class="cs-discount" min="0" step="0.01" placeholder="Disc" value="0">
                <input type="number" class="cs-tax" min="0" step="0.01" placeholder="Tax %" value="0">
                <span class="cs-line-total adm-money">₹0.00</span>
                <button type="button" class="cs-remove-row" title="Remove"><i class="fas fa-times"></i></button>
            </div>`;
        }

        function bindCsRowEvents() {
            $('#cs-items .cs-product, #cs-items .cs-qty, #cs-items .cs-rate, #cs-items .cs-discount, #cs-items .cs-tax').off('change input').on('change input', function() {
                const row = $(this).closest('.cs-item-row');
                if ($(this).hasClass('cs-product')) {
                    const rate = $(this).find(':selected').data('rate');
                    if (rate && !row.find('.cs-rate').val()) row.find('.cs-rate').val(rate);
                }
                recalcCsTotals();
            });
            $('#cs-items .cs-remove-row').off('click').on('click', function() {
                if ($('#cs-items .cs-item-row').length > 1) $(this).closest('.cs-item-row').remove();
                else $(this).closest('.cs-item-row').replaceWith(csItemRowHtml({}));
                bindCsRowEvents();
                recalcCsTotals();
            });
        }

        function collectCsRows() {
            const rows = [];
            $('#cs-items .cs-item-row').each(function() {
                const productId = $(this).find('.cs-product').val();
                const qty = parseFloat($(this).find('.cs-qty').val());
                if (!productId || !qty || qty <= 0) return;
                rows.push({
                    product_id: productId, quantity: qty,
                    rate: parseFloat($(this).find('.cs-rate').val()) || 0,
                    discount: parseFloat($(this).find('.cs-discount').val()) || 0,
                    tax: parseFloat($(this).find('.cs-tax').val()) || 0
                });
            });
            return rows;
        }

        function recalcCsTotals() {
            let subtotal = 0, tax = 0;
            $('#cs-items .cs-item-row').each(function() {
                const qty = parseFloat($(this).find('.cs-qty').val()) || 0;
                const rate = parseFloat($(this).find('.cs-rate').val()) || 0;
                const disc = parseFloat($(this).find('.cs-discount').val()) || 0;
                const taxPct = parseFloat($(this).find('.cs-tax').val()) || 0;
                const base = (qty * rate) - disc;
                const lineTax = base * (taxPct / 100);
                subtotal += base; tax += lineTax;
                $(this).find('.cs-line-total').text('₹' + (base + lineTax).toFixed(2));
            });
            $('#cs-grand').text('₹' + (subtotal + tax).toFixed(2));
        }

        function openCsModal() {
            const itemsHtml = [{}].map(csItemRowHtml).join('');
            Swal.fire({
                title: 'New Credit Sale',
                customClass: { popup: 'swal-wide' },
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="cs-cust-name" class="swal2-input" style="margin:2px 0;" placeholder="Customer name">
                            <input id="cs-cust-mobile" class="swal2-input" style="margin:2px 0;" placeholder="Mobile">
                        </div>
                        <textarea id="cs-cust-address" class="swal2-textarea" placeholder="Address (optional)"></textarea>

                        <div style="margin-top:8px;"><strong>Items</strong></div>
                        <div class="cs-item-head"><div>Product</div><div>Qty</div><div>Rate</div><div>Disc</div><div>Tax %</div><div>Total</div><div></div></div>
                        <div id="cs-items">${itemsHtml}</div>
                        <button type="button" id="cs-add-row" class="adm-btn adm-btn-ghost" style="margin-top:4px;"><i class="fas fa-plus"></i> Add item</button>

                        <div style="text-align:right; margin-top:8px;"><strong>Grand total: <span id="cs-grand">₹0.00</span></strong></div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                            <input id="cs-amount-received" type="number" min="0" step="0.01" class="swal2-input" style="margin:2px 0;" placeholder="Amount received now (optional)">
                            <select id="cs-pay-mode" class="swal2-input" style="margin:2px 0;">
                                <option value="">Payment mode (if any received)</option>
                                <option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>
                                <option value="card">Card</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="adm-sub" style="margin:2px 0 6px;">Leave "Amount received" blank or 0 if the customer is taking everything on credit — the rest is tracked as balance due and you can record payments later.</div>
                        <textarea id="cs-notes" class="swal2-textarea" placeholder="Notes"></textarea>
                    </div>
                `,
                showCancelButton: true, confirmButtonText: 'Save credit sale', confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459',
                focusConfirm: false,
                didOpen: () => {
                    bindCsRowEvents(); recalcCsTotals();
                    $('#cs-add-row').on('click', function() { $('#cs-items').append(csItemRowHtml({})); bindCsRowEvents(); recalcCsTotals(); });
                },
                preConfirm: () => {
                    const rows = collectCsRows();
                    if (rows.length === 0) { Swal.showValidationMessage('Add at least one valid line item'); return false; }
                    const custName = $('#cs-cust-name').val().trim();
                    if (!custName) { Swal.showValidationMessage('Customer name is required'); return false; }
                    return {
                        customer_name: custName,
                        customer_mobile: $('#cs-cust-mobile').val().trim(),
                        customer_address: $('#cs-cust-address').val().trim(),
                        amount_received: $('#cs-amount-received').val() || 0,
                        payment_mode: $('#cs-pay-mode').val(),
                        notes: $('#cs-notes').val().trim(),
                        items: JSON.stringify(rows)
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveCreditSale(result.value);
            });
        }

        function saveCreditSale(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_credit_sale.php', type: 'POST', data: data, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        loadCreditSales();
                        confirmCs(response.id, response.credit_number, true);
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The credit sale was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() { Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
            });
        }

        // A confirmation step before touching stock — prevents an accidental
        // duplicate stock deduction from a double-click or re-run.
        function confirmCs(id, number, justCreated) {
            Swal.fire({
                title: justCreated ? 'Credit sale saved — deduct stock now?' : 'Confirm stock deduction?',
                text: `This will reduce stock for every item in ${number}. This can only be done once per sale.`,
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Confirm & deduct stock', cancelButtonText: justCreated ? 'Later' : 'Cancel',
                confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/save_credit_sale.php', type: 'POST', data: { id: id, confirm: 1 }, dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Stock deducted', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                                loadCreditSales();
                            } else {
                                Swal.fire({ title: 'Could not deduct stock', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() { Swal.fire({ title: 'Could not deduct stock', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
                    });
                } else if (justCreated) {
                    loadCreditSales();
                }
            });
        }

        function openRecordPayment(id, number, due) {
            Swal.fire({
                title: `Record payment — ${number}`,
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <div class="adm-sub" style="margin-bottom:6px;">Balance due: ₹${parseFloat(due).toFixed(2)}</div>
                        <input id="rp-amount" type="number" min="0.01" step="0.01" class="swal2-input" style="margin:2px 0;" placeholder="Amount received">
                        <select id="rp-mode" class="swal2-input" style="margin:2px 0;">
                            <option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>
                            <option value="card">Card</option><option value="other">Other</option>
                        </select>
                        <textarea id="rp-notes" class="swal2-textarea" placeholder="Notes (optional)"></textarea>
                    </div>
                `,
                showCancelButton: true, confirmButtonText: 'Record payment', confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const amount = parseFloat($('#rp-amount').val());
                    if (!amount || amount <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
                    return { id: id, amount: amount, payment_mode: $('#rp-mode').val(), notes: $('#rp-notes').val().trim() };
                }
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/record_credit_sale_payment.php', type: 'POST', data: result.value, dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Payment recorded', icon: 'success', confirmButtonColor: '#1c5034', timer: 1400, showConfirmButton: false });
                            loadCreditSales();
                        } else {
                            Swal.fire({ title: 'Could not record payment', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() { Swal.fire({ title: 'Could not record payment', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
                });
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
