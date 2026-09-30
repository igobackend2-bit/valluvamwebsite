<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Orders', 'Order stock from suppliers — stock changes only when goods are received (GRN)',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New purchase order</button>');
?>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>All purchase orders</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus">
                <option value="">All status</option><option value="open">Open (approved / part received)</option>
                <option value="draft">Draft</option><option value="pending_approval">Pending approval</option><option value="approved">Approved</option>
                <option value="partially_received">Partially received</option><option value="fully_received">Fully received</option>
                <option value="closed">Closed</option><option value="cancelled">Cancelled</option>
            </select>
            <select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <input type="date" class="adm-input" id="fFrom" title="From"><input type="date" class="adm-input" id="fTo" title="To">
            <input type="text" class="adm-input" id="fQ" placeholder="PO no. or supplier">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [], WH = [], ITEMS = [];
Promise.all([E.suppliers(), E.warehouses(), E.items()]).then(([s, w, i]) => {
    SUP = s; WH = w; ITEMS = i;
    $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join(''));
    if (E.param('status')) $('#fStatus').val(E.param('status'));
    load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('new')) openForm(null);
});
$('#fStatus,#fSupplier,#fFrom,#fTo').on('change', load);
let t; $('#fQ').on('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm(null));

function load() {
    const $l = $('#list'); E.loading($l);
    E.api('purchase_api.php', { action: 'po_list', status: $('#fStatus').val(), supplier_id: $('#fSupplier').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true })
     .then(r => E.table($l, [
        { label: 'PO', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.po_number)}</a>` },
        { label: 'Date', render: x => E.date(x.po_date) },
        { label: 'Supplier', render: x => E.esc(x.supplier_name) },
        { label: 'Expected', render: x => E.date(x.expected_delivery_date) },
        { label: 'Received / ordered', num: true, render: x => `${E.qty(x.received_qty)} / ${E.qty(x.ordered_qty)}` },
        { label: 'Total', num: true, render: x => E.money(x.grand_total) },
        { label: 'Status', render: x => E.badge(x.status) },
     ], r.rows, { empty: 'No purchase orders found', emptyHint: 'Create one with “New purchase order”.', icon: 'fa-file-signature' }))
     .catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function openForm(po) {
    po = po || {};
    const html = `<div class="erp-grid">
        ${E.field('Supplier *', E.select('pSup', E.options(SUP.filter(s => s.status === 'active' || String(s.id) === String(po.supplier_id)), 'id', s => s.supplier_name, po.supplier_id, 'Choose supplier')))}
        ${E.field('PO date *', E.input('pDate', po.po_date || E.today(), 'type="date"'))}
        ${E.field('Expected delivery', E.input('pExp', po.expected_delivery_date || '', 'type="date"'))}
        ${E.field('Receiving warehouse', E.select('pWh', E.options(WH, 'id', w => w.name, po.warehouse_id || 1, false)))}
        ${E.field('Buyer', E.input('pBuyer', po.buyer || ''))}
        ${E.field('Other charges ₹ (expected)', E.input('pOther', po.other_charges || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Notes', E.textarea('pNotes', po.notes || ''), 'span-all')}
      </div>
      <div class="erp-section-title">Items</div><div id="pLines"></div>
      <p class="erp-note">Rates are per pack for products and per kg / L for bulk raw materials. Creating or approving a PO does not change stock.</p>`;
    let editor;
    E.form(po.id ? 'Edit ' + po.po_number : 'New purchase order', html, (btn) => {
        const payload = { action: btn === 'deny' ? 'po_save' : 'po_submit', id: po.id || '', supplier_id: $('#pSup').val(), po_date: $('#pDate').val(),
                          expected_delivery_date: $('#pExp').val(), warehouse_id: $('#pWh').val(), buyer: $('#pBuyer').val(), other_charges: $('#pOther').val(),
                          notes: $('#pNotes').val(), items: editor.get() };
        if (!payload.supplier_id) return Promise.reject('Choose a supplier');
        if (!payload.items.length) return Promise.reject('Add at least one item');
        return E.post('purchase_api.php', payload, { silent: true }).then(r => { E.toast(r.message); load(); setTimeout(() => openView(r.id), 300); });
    }, { confirmText: 'Submit for approval', denyText: 'Save draft', didOpen: () => {
        editor = E.lineEditor($('#pLines'), { items: ITEMS, lines: po.items || [], extra: () => E.num($('#pOther').val()) });
        $('#pOther').on('input', () => editor.recalc());
    }});
}

function openView(id) {
    E.api('purchase_api.php', { action: 'po_get', id }).then(r => {
        const p = r.record;
        const items = p.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.qty(i.received_qty)}</td>
            <td class="erp-num">${E.qty(Math.max(0, i.quantity - i.received_qty))}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.money(i.discount_amount)}</td>
            <td class="erp-num">${E.num(i.tax_percent)}%</td><td class="erp-num">${E.money(i.line_total)}</td></tr>`).join('');
        const html = E.kv([['Supplier', `<a class="erp-link" href="supplier_ledger.php?id=${p.supplier_id}">${E.esc(p.supplier_name)}</a>`], ['PO date', E.date(p.po_date)],
                           ['Expected', E.date(p.expected_delivery_date)], ['Warehouse', E.esc(p.warehouse_name || '—')], ['Buyer', E.esc(p.buyer || '—')], ['Status', E.badge(p.status)],
                           p.approved_by ? ['Approved by', E.esc(p.approved_by) + ' · ' + E.date(p.approved_at)] : null])
            + E.chain([p.pr_id ? ['Request ' + p.pr_number, 'purchase_requests.php?id=' + p.pr_id] : null]
                .concat(p.grns.map(g => ['GRN ' + g.grn_number + ' (' + g.status + ')', 'goods_receipts.php?id=' + g.id]))
                .concat(p.invoices.map(v => ['Bill ' + v.supplier_invoice_no, 'purchase_invoices.php?id=' + v.id]))
                .concat(p.returns.map(x => ['Return ' + x.return_number, 'purchase_returns.php?id=' + x.id])))
            + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Ordered</th><th class="erp-num">Received</th><th class="erp-num">Pending</th>
               <th class="erp-num">Rate</th><th class="erp-num">Discount</th><th class="erp-num">GST</th><th class="erp-num">Total</th></tr></thead><tbody>${items}</tbody></table></div>
               <div class="erp-totals"><span>Subtotal <strong>${E.money(p.subtotal)}</strong></span><span>Discount <strong>${E.money(p.discount_total)}</strong></span>
               <span>GST <strong>${E.money(p.tax_total)}</strong></span><span>Other <strong>${E.money(p.other_charges)}</strong></span><span>Total <strong>${E.money(p.grand_total)}</strong></span></div>
               ${p.notes ? '<p class="erp-note">' + E.esc(p.notes) + '</p>' : ''}<div id="vDocs"></div>`;
        const act = (a, msg, extra) => () => E.confirmAction(msg, '', extra).then(reason => E.post('purchase_api.php', { action: a, id: p.id, reason }))
                                           .then(x => { E.toast(x.message); load(); openView(p.id); }).catch(() => {});
        E.view(p.po_number, html, [
            ['draft', 'pending_approval'].includes(p.status) && { label: 'Edit', icon: 'fa-pen', run: () => openForm(p) },
            ['draft', 'pending_approval'].includes(p.status) && { label: 'Approve', cls: 'adm-btn-primary', icon: 'fa-check', run: act('po_approve', 'Approve ' + p.po_number + '?') },
            ['approved', 'partially_received'].includes(p.status) && { label: 'Receive goods (GRN)', cls: 'adm-btn-primary', icon: 'fa-dolly', run: () => location.href = 'goods_receipts.php?po_id=' + p.id },
            ['approved', 'partially_received', 'fully_received'].includes(p.status) && { label: 'Close PO', icon: 'fa-lock', run: act('po_close', 'Close ' + p.po_number + '? No more goods can be received.', { reason: 'Reason (optional)', optional: true }) },
            !['cancelled', 'closed', 'fully_received'].includes(p.status) && !p.grns.some(g => g.status === 'posted') && { label: 'Cancel PO', icon: 'fa-ban', run: act('po_cancel', 'Cancel ' + p.po_number + '?', { danger: true, reason: 'Reason' }) },
        ], { didOpen: () => E.docs($('#vDocs'), 'purchase_order', p.id) });
    }).catch(() => {});
}
JS
);
