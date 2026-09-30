<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Supplier 360', 'Everything about a supplier: purchases, payments, outstanding, returns, quality, price changes, lead time, documents',
    '<select class="adm-select" id="pick" style="min-width:240px"><option value="">Choose a supplier…</option></select>');
?>
<div id="summary">
<section class="adm-card"><div class="adm-card-head"><h2>All suppliers</h2><input class="adm-input" id="fQ" placeholder="Search"></div><div class="adm-card-body" id="list"></div></section>
</div>
<div id="detail" style="display:none">
    <div class="erp-kpis" id="kpis"></div>
    <div class="erp-tabs" id="tabs">
        <button class="erp-tab active" data-tab="ledger">Ledger</button><button class="erp-tab" data-tab="bills">Bills</button><button class="erp-tab" data-tab="payments">Payments</button>
        <button class="erp-tab" data-tab="orders">Orders & receipts</button><button class="erp-tab" data-tab="returns">Returns</button><button class="erp-tab" data-tab="prices">Price changes</button>
        <button class="erp-tab" data-tab="quotes">Quotations</button><button class="erp-tab" data-tab="profile">Profile</button><button class="erp-tab" data-tab="docs">Documents</button>
    </div>
    <section class="adm-card"><div class="adm-card-head"><h2 id="dTitle"></h2><div id="dInfo" class="erp-muted"></div></div><div class="adm-card-body" id="pane"></div></section>
</div>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [], D = null, TAB = 'ledger';
E.suppliers().then(s => $('#pick').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join('')).val(E.param('id') || ''));
$('#pick').on('change', function () { this.value ? openSup(this.value) : back(); });
E.tabs($('#tabs'), t => { TAB = t; render(); });
function back() { $('#detail').hide(); $('#summary').show(); history.replaceState(null, '', 'supplier_360.php'); }
E.api('procurement_api.php', { action: 'sup360_list' }).then(r => { ROWS = r.rows; list(); if (E.param('id')) openSup(E.param('id')); });
$('#fQ').on('input', list);
function list() {
    const q = ($('#fQ').val() || '').toLowerCase();
    E.table($('#list'), [
        { label: 'Supplier', render: x => `<a class="erp-link" data-sup="${x.id}">${E.esc(x.supplier_name)}</a><div class="erp-muted">${E.esc(x.company_name || '')} ${E.esc(x.mobile || '')}</div>` },
        { label: 'Purchases (billed)', num: true, render: x => E.money(x.total_invoiced) }, { label: 'Paid', num: true, render: x => E.money(x.total_paid) },
        { label: 'Outstanding', num: true, render: x => `<strong class="${x.outstanding > 0 ? 'erp-neg' : ''}">${E.money(x.outstanding)}</strong>` + (x.credit_limit ? `<div class="erp-muted">limit ${E.money(x.credit_limit)}</div>` : '') },
        { label: 'Received not billed', num: true, render: x => E.money(x.received_not_invoiced) }, { label: 'Overdue bills', num: true, render: x => x.overdue_invoices || '' },
        { label: 'Last purchase', render: x => E.date(x.last_purchase) }, { label: 'Last payment', render: x => E.date(x.last_payment) },
    ], ROWS.filter(x => !q || JSON.stringify(x).toLowerCase().includes(q)), { empty: 'No suppliers', icon: 'fa-truck-field' });
}
$('#list').on('click', '[data-sup]', function () { $('#pick').val($(this).data('sup')); openSup($(this).data('sup')); });
function openSup(id) {
    E.api('procurement_api.php', { action: 'sup360_get', id }).then(r => {
        D = r; $('#summary').hide(); $('#detail').show(); history.replaceState(null, '', 'supplier_360.php?id=' + id);
        const k = r.kpis, s = r.supplier;
        $('#dTitle').text(s.supplier_name); $('#dInfo').html([s.company_name, s.mobile, s.email, s.gst_number ? 'GSTIN ' + s.gst_number : '', s.payment_terms].filter(Boolean).map(E.esc).join(' · '));
        $('#kpis').html(E.kpi('Total purchases (billed)', E.money(k.total_purchases), 'is-primary') + E.kpi('Total paid', E.money(k.total_paid)) +
            E.kpi('Outstanding', E.money(k.outstanding), k.outstanding > 0 ? 'is-amber' : 'is-green', 'purchase_payments.php?supplier_id=' + id) + E.kpi('Purchase returns', E.money(k.purchase_returns)) +
            E.kpi('Last purchase', E.date(k.last_purchase)) + E.kpi('Last payment', E.date(k.last_payment)) +
            E.kpi('Rejected at receipt', E.qty(k.qty_rejected) + (k.rejection_rate_pct !== null ? ' (' + k.rejection_rate_pct + '%)' : '')) + E.kpi('Returned', E.qty(k.qty_returned)) +
            E.kpi('Avg lead time', k.avg_lead_days !== null ? k.avg_lead_days + ' days' : '—') + E.kpi('Orders (12 months)', k.orders_last_12m) +
            E.kpi('Transport cost', E.money(k.transport_cost) + ' · ' + k.transport_shipments + ' trips') +
            (k.credit_limit !== null ? E.kpi('Credit available', E.money(k.credit_available), k.credit_available < 0 ? 'is-amber' : 'is-green') : ''));
        render();
    }).catch(() => {});
}
function render() {
    if (!D) return;
    const $p = $('#pane'), id = D.supplier.id;
    if (TAB === 'ledger') E.table($p, [
        { label: 'Date', render: x => E.date(x.date) }, { label: 'Entry', render: x => E.esc(x.type) },
        { label: 'Reference', render: x => x.link ? `<a class="erp-link" href="${x.link[0]}?id=${x.link[1]}">${E.esc(x.ref)}</a> <span class="erp-muted">${E.esc(x.ref2 || '')}</span>` : E.esc(x.ref) },
        { label: 'We paid / credit (Dr)', num: true, render: x => x.debit ? E.money(x.debit) : '' }, { label: 'We owe (Cr)', num: true, render: x => x.credit ? E.money(x.credit) : '' },
        { label: 'Balance owed', num: true, render: x => E.money(x.balance) },
    ], D.ledger, { empty: 'No transactions yet' });
    if (TAB === 'bills') E.table($p, [
        { label: 'Bill', render: x => `<a class="erp-link" href="purchase_invoices.php?id=${x.id}">${E.esc(x.supplier_invoice_no)}</a> <span class="erp-muted">${E.esc(x.pinv_number)}</span>` },
        { label: 'Date', render: x => E.date(x.invoice_date) }, { label: 'Due', render: x => E.date(x.due_date) }, { label: 'Total', num: true, render: x => E.money(x.grand_total) }, { label: 'Status', render: x => E.badge(x.status) },
    ], D.bills, { empty: 'No bills' });
    if (TAB === 'payments') E.table($p, [
        { label: 'Payment', render: x => E.esc(x.payment_number) }, { label: 'Date', render: x => E.date(x.payment_date) }, { label: 'For bill', render: x => E.esc(x.supplier_invoice_no || 'Advance') },
        { label: 'Mode', render: x => E.esc(x.payment_mode) + ' ' + E.esc(x.reference_number || '') }, { label: 'Amount', num: true, render: x => E.money(x.amount) }, { label: 'Status', render: x => E.badge(x.status) },
    ], D.payments, { empty: 'No payments' });
    if (TAB === 'orders') { $p.html('<div id="o1"></div><div class="erp-section-title">Goods receipts</div><div id="o2"></div>');
        E.table($('#o1'), [{ label: 'PO', render: x => `<a class="erp-link" href="purchase_orders.php?id=${x.id}">${E.esc(x.po_number)}</a>` }, { label: 'Date', render: x => E.date(x.po_date) }, { label: 'Total', num: true, render: x => E.money(x.grand_total) }, { label: 'Status', render: x => E.badge(x.status) }], D.purchase_orders, { empty: 'No purchase orders' });
        E.table($('#o2'), [{ label: 'GRN', render: x => `<a class="erp-link" href="goods_receipts.php?id=${x.id}">${E.esc(x.grn_number)}</a>` }, { label: 'Date', render: x => E.date(x.received_date) }, { label: 'Status', render: x => E.badge(x.status) }], D.grns, { empty: 'No receipts' }); }
    if (TAB === 'returns') E.table($p, [{ label: 'Return', render: x => `<a class="erp-link" href="purchase_returns.php?id=${x.id}">${E.esc(x.return_number)}</a>` }, { label: 'Date', render: x => E.date(x.return_date) },
        { label: 'Settlement', render: x => E.esc(x.settlement) }, { label: 'Value', num: true, render: x => E.money(x.total_value) }, { label: 'Status', render: x => E.badge(x.status) }], D.returns, { empty: 'No returns' });
    if (TAB === 'prices') E.table($p, [{ label: 'Item', render: x => E.esc(x.item_name) }, { label: 'Receipts', num: true, render: x => x.purchases }, { label: 'Quantity', num: true, render: x => E.qty(x.qty, x.unit) },
        { label: 'First rate', num: true, render: x => E.money(x.first_rate) + `<div class="erp-muted">${E.date(x.first_date)}</div>` }, { label: 'Latest rate', num: true, render: x => E.money(x.last_rate) + `<div class="erp-muted">${E.date(x.last_date)}</div>` },
        { label: 'Average', num: true, render: x => E.money(x.avg_rate) }, { label: 'Change', num: true, render: x => x.change_pct === null ? '—' : `<span class="${x.change_pct > 0 ? 'erp-neg' : 'erp-pos'}">${x.change_pct > 0 ? '+' : ''}${x.change_pct}%</span>` }], D.price_changes, { empty: 'No purchases received yet' });
    if (TAB === 'quotes') E.table($p, [{ label: 'Quotation', render: x => `<a class="erp-link" href="rfqs.php?quote=${x.id}">${E.esc(x.quote_number)}</a>` }, { label: 'RFQ', render: x => E.esc(x.rfq_number || '—') },
        { label: 'Date', render: x => E.date(x.quote_date) }, { label: 'Total', num: true, render: x => E.money(x.grand_total) }, { label: 'Status', render: x => E.badge(x.status) }], D.quotations, { empty: 'No quotations' });
    if (TAB === 'docs') { $p.html('<div id="dd"></div><div class="erp-section-title">Documents on this supplier\'s transactions</div><div id="dl"></div>');
        E.docs($('#dd'), 'supplier', id, 'OTHER');
        E.table($('#dl'), [{ label: 'File', render: d => `<a class="erp-link" href="${E.BASE}erp_docs.php?action=download&id=${d.id}" target="_blank" rel="noopener">${E.esc(d.original_name)}</a>` }, { label: 'On', render: d => E.esc(d.entity_type.replace(/_/g, ' ')) + ' #' + d.entity_id }, { label: 'Uploaded', render: d => E.date(d.created_at) }], D.documents, { empty: 'No documents' }); }
    if (TAB === 'profile') {
        const p = D.profile;
        $p.html(`<div class="erp-grid">${E.field('Contact person', E.input('pC', p.contact_person))}${E.field('Contact phone', E.input('pP', p.contact_phone))}${E.field('Alternate phone', E.input('pA', p.alt_phone))}
            ${E.field('PAN', E.input('pPan', p.pan_number))}${E.field('Bank account name', E.input('pBn', p.bank_account_name))}
            ${E.field('Bank account no. (only last 4 kept)', E.input('pBa', '', 'placeholder="' + E.esc(p.bank_account_masked || 'not set') + '"'))}${E.field('IFSC', E.input('pIfsc', p.bank_ifsc))}${E.field('Bank', E.input('pBank', p.bank_name))}
            ${E.field('UPI ID', E.input('pUpi', p.upi_id))}${E.field('Credit limit ₹', E.input('pLim', p.credit_limit ?? '', 'type="number" min="0" step="any"'))}${E.field('Credit days', E.input('pDays', p.credit_days ?? '', 'type="number" min="0"'))}
            ${E.field('Opening balance ₹ (+ we owe)', E.input('pOb', p.opening_balance ?? 0, 'type="number" step="any"'))}${E.field('Opening balance date', E.input('pObd', p.opening_balance_date || '', 'type="date"'))}
            ${E.field('Notes', E.textarea('pN', p.notes), 'span-all')}</div>
            <div class="erp-actions"><button class="adm-btn adm-btn-primary" id="pSave"><i class="fas fa-floppy-disk"></i> Save profile</button>
            <a class="adm-btn adm-btn-ghost" href="suppliers.php">Edit name / GST / address in Suppliers</a></div>
            <p class="erp-note">Name, company, phone, e-mail, GSTIN, address and payment terms stay in the existing Suppliers page. ${p.updated_by ? 'Last changed by ' + E.esc(p.updated_by) + ' · ' + E.date(p.updated_at) : ''}</p>`);
        $('#pSave').on('click', () => E.post('procurement_api.php', { action: 'sup_profile_save', supplier_id: id, contact_person: $('#pC').val(), contact_phone: $('#pP').val(), alt_phone: $('#pA').val(), pan_number: $('#pPan').val(),
            bank_account_name: $('#pBn').val(), bank_account_number: $('#pBa').val(), bank_ifsc: $('#pIfsc').val(), bank_name: $('#pBank').val(), upi_id: $('#pUpi').val(), credit_limit: $('#pLim').val(), credit_days: $('#pDays').val(),
            opening_balance: $('#pOb').val(), opening_balance_date: $('#pObd').val(), notes: $('#pN').val() }).then(x => { E.toast(x.message); openSup(id); }));
    }
}
JS
);
