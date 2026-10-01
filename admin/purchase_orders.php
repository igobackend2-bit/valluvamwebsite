<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Orders', 'Order stock from suppliers — stock changes only when goods are received (GRN)',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New purchase order</button>');
?>
<div id="pqVer" data-v="<?= @filemtime(__DIR__ . '/assets/po_quotes.js') ?: 1 ?>" hidden></div>
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
// competitor quotations (1 Oct 2026) — separate file, the page works without it
const POQ_LOAD = $.ajax({ url: 'assets/po_quotes.js?v=' + ($('#pqVer').data('v') || 1), dataType: 'script', cache: true }).catch(() => null);
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
        ${E.field('Supplier *', E.select('pSup', E.options(SUP.filter(s => s.status === 'active' || String(s.id) === String(po.supplier_id)), 'id', s => s.supplier_name, po.supplier_id, 'Choose supplier')) +
            '<button type="button" class="adm-btn adm-btn-ghost" id="pNewSup" style="margin-top:8px;"><i class="fas fa-plus"></i> Add new supplier / shop</button>' +
            '<div id="pNewSupFields" hidden style="margin-top:10px;">' +
                '<input class="adm-input" id="pNewSupName" placeholder="Supplier or shop name *">' +
                '<input class="adm-input" id="pNewSupOwner" placeholder="Shop owner / contact person" style="margin-top:8px;">' +
                '<input class="adm-input" id="pNewSupMobile" placeholder="Owner mobile number" inputmode="numeric" style="margin-top:8px;">' +
                '<input class="adm-input" id="pNewSupGst" placeholder="GSTIN (leave blank if unregistered)" maxlength="15" style="margin-top:8px;text-transform:uppercase;">' +
                '<div class="erp-note" style="margin:8px 0 4px;">Payment details (collect before paying)</div>' +
                '<input class="adm-input" id="pNewSupHolder" placeholder="Account holder name" style="margin-top:4px;">' +
                '<input class="adm-input" id="pNewSupBank" placeholder="Bank name" style="margin-top:8px;">' +
                '<input class="adm-input" id="pNewSupAccount" placeholder="Account number" inputmode="numeric" style="margin-top:8px;">' +
                '<input class="adm-input" id="pNewSupIfsc" placeholder="IFSC code" maxlength="11" style="margin-top:8px;text-transform:uppercase;">' +
                '<input class="adm-input" id="pNewSupUpi" placeholder="UPI ID (optional)" style="margin-top:8px;">' +
                '<button type="button" class="adm-btn adm-btn-ghost" id="pNewSupCancel" style="margin-top:8px;">Use existing supplier</button></div>')}
        ${E.field('PO date *', E.input('pDate', po.po_date || E.today(), 'type="date"'))}
        ${E.field('Expected delivery', E.input('pExp', po.expected_delivery_date || '', 'type="date"'))}
        ${E.field('Receiving warehouse', E.select('pWh', E.options(WH, 'id', w => w.name, po.warehouse_id || 1, false)))}
        ${E.field('Buyer', E.input('pBuyer', po.buyer || ''))}
        ${E.field('Other charges ₹ (expected)', E.input('pOther', po.other_charges || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Notes', E.textarea('pNotes', po.notes || ''), 'span-all')}
      </div>
      <div class="erp-section-title">Competitor quotations (compare up to 3 suppliers)</div><div id="pQuotes"></div>
      <div class="erp-section-title">Items</div><div id="pLines"></div>
      <p class="erp-note">Rates are per pack for products and per kg / L for bulk raw materials. Creating or approving a PO does not change stock.</p>`;
    let editor, quotes = null;
    E.form(po.id ? 'Edit ' + po.po_number : 'New purchase order', html, (btn) => {
        const payload = { action: btn === 'deny' ? 'po_save' : 'po_submit', id: po.id || '', supplier_id: $('#pSup').val(), po_date: $('#pDate').val(),
                          expected_delivery_date: $('#pExp').val(), warehouse_id: $('#pWh').val(), buyer: $('#pBuyer').val(), other_charges: $('#pOther').val(),
                          notes: $('#pNotes').val(), items: editor.get() };
        if (!payload.items.length) return Promise.reject('Add at least one item');
        return (quotes ? quotes.check() : Promise.resolve()).then(() => selectedSupplier()).then(supplierId => {
            if (!supplierId) return Promise.reject('Choose a supplier or add a new supplier / shop');
            payload.supplier_id = supplierId;
            return E.post('purchase_api.php', payload, { silent: true });
        }).then(r => (quotes ? quotes.save(r.id) : Promise.resolve([])).then(problems => {
            E.toast(problems.length ? r.message + ' Note: ' + problems.join('; ') : r.message, problems.length ? 'warning' : 'success');
            load(); setTimeout(() => openView(r.id), 300);
        }));
    }, { confirmText: 'Submit for approval', denyText: 'Save draft', width: 1180, didOpen: () => {
        editor = E.lineEditor($('#pLines'), { items: ITEMS, lines: po.items || [], extra: () => E.num($('#pOther').val()) });
        $('#pOther').on('input', () => editor.recalc());
        $('#pNewSup').on('click', () => { $('#pSup').val('').prop('disabled', true); $('#pNewSupFields').prop('hidden', false); $('#pNewSupName').trigger('focus'); });
        $('#pNewSupCancel').on('click', () => { $('#pNewSupFields').prop('hidden', true); $('#pSup').prop('disabled', false); });
        POQ_LOAD.then(() => {
            if (!window.POQ) { $('#pQuotes').html('<div class="erp-note">Competitor quotations could not be loaded.</div>'); return; }
            quotes = POQ.mount($('#pQuotes'), { poId: po.id || 0, items: ITEMS, suppliers: SUP, lines: () => editor.get(), onUse: applyQuote });
        });
    }});

    /** "Use this quotation": fills the PO supplier, expected delivery, freight and item lines from the chosen quotation. */
    function applyQuote(q) {
        const name = String(q.supplier_name || '').trim().toLowerCase();
        const s = SUP.find(x => (q.supplier_id && String(x.id) === String(q.supplier_id)) || String(x.supplier_name).trim().toLowerCase() === name);
        if (s) {
            $('#pNewSupFields').prop('hidden', true);
            if (!$('#pSup option[value="' + s.id + '"]').length) $('#pSup').append(`<option value="${s.id}">${E.esc(s.supplier_name)}</option>`);
            $('#pSup').prop('disabled', false).val(String(s.id));
        } else {
            $('#pSup').val('').prop('disabled', true); $('#pNewSupFields').prop('hidden', false);
            $('#pNewSupName').val(q.supplier_name || ''); $('#pNewSupOwner').val(q.contact_person || ''); $('#pNewSupMobile').val(q.mobile || ''); $('#pNewSupGst').val(q.gst_number || '');
            $('#pNewSupHolder').val(q.account_holder_name || ''); $('#pNewSupBank').val(q.bank_name || ''); $('#pNewSupAccount').val(q.bank_account_number || '');
            $('#pNewSupIfsc').val(q.bank_ifsc || ''); $('#pNewSupUpi').val(q.upi_id || '');
        }
        if (q.delivery_days !== '' && q.delivery_days !== null && q.delivery_days !== undefined && $('#pDate').val()) {
            const d = new Date($('#pDate').val() + 'T00:00:00'); d.setDate(d.getDate() + E.num(q.delivery_days));
            $('#pExp').val(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'));
        }
        if (E.num(q.freight) > 0) $('#pOther').val(E.num(q.freight));
        const lines = (q.items || []).filter(l => l.item_type && l.item_id && E.num(l.rate) > 0)
            .map(l => ({ item_type: l.item_type, item_id: l.item_id, quantity: E.num(l.quantity) || '', rate: l.rate, discount_amount: 0, tax_percent: l.tax_percent || 0 }));
        if (lines.length) {
            $('#pLines').off();
            editor = E.lineEditor($('#pLines'), { items: ITEMS, lines, extra: () => E.num($('#pOther').val()) });
        }
        const skipped = (q.items || []).filter(l => !(l.item_type && l.item_id)).length;
        $('#pLines').prev('.erp-section-title').html('Items' + (lines.length ? ` <span class="adm-badge is-green">filled from ${E.esc(q.supplier_name)}</span>` : '') +
            (skipped ? ` <span class="adm-badge is-amber">${skipped} unmatched item(s) not added</span>` : ''));
        $('#pLines')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/** Revisions, RFQ link, transport, approvals, credit limit (1 Oct 2026). */
function poExtras(p) {
    $('#vDocs').before('<div id="vX"></div>');
    E.api('procurement_api.php', { action: 'po_extras', id: p.id }, { silent: true }).then(x => {
        let h = '';
        if (x.credit.over_limit) h += `<div class="erp-warn">Supplier credit limit ${E.money(x.credit.limit)} is exceeded: outstanding ${E.money(x.credit.outstanding)} + this PO ${E.money(p.grand_total)}.</div>`;
        if (x.link) h += `<div class="erp-docs-row"><i class="fas fa-envelope-open-text"></i> From <a class="erp-link" href="rfqs.php?id=${x.link.rfq_id}">${E.esc(x.link.rfq_number || '')}</a> / quotation <a class="erp-link" href="rfqs.php?quote=${x.link.quotation_id}">${E.esc(x.link.quote_number || '')}</a></div>`;
        x.shipments.forEach(s => { h += `<div class="erp-docs-row"><i class="fas fa-truck"></i> <a class="erp-link" href="shipments.php?id=${s.id}">${E.esc(s.shipment_number)}</a> ${E.badge(s.status)} ${E.esc(s.vehicle_number || '')} ${s.lr_number ? 'LR ' + E.esc(s.lr_number) : ''} · ${E.money(s.total_cost)}</div>`; });
        if (x.revisions.length) h += '<div class="erp-section-title">Amendments</div>' + x.revisions.map(r => `<div class="erp-docs-row">Rev ${r.revision_no} · ${E.money(r.old_total)} → ${E.money(r.new_total)} · ${E.esc(r.reason)} · ${E.esc(r.requested_by || '')} ${E.date(r.requested_at)} ${E.badge(r.status)}
            ${r.decided_by ? '<span class="erp-muted">' + E.esc(r.decided_by) + ' ' + E.date(r.decided_at) + '</span>' : ''} ${r.status === 'pending' ? `<a class="erp-link" href="approvals.php?module=po_amendment">open in Approvals</a>` : ''}</div>`).join('');
        if (x.approvals.length) h += '<div class="erp-section-title">Approvals</div>' + x.approvals.map(a => `<div class="erp-muted">${E.esc(a.request_number)} · ${E.badge(a.status)} submitted by ${E.esc(a.submitted_by || '')} ${E.date(a.submitted_at)}${a.decided_by ? ' · decided by ' + E.esc(a.decided_by) + ' ' + E.date(a.decided_at) : ''}${a.remarks ? ' — ' + E.esc(a.remarks) : ''}</div>`).join('');
        $('#vX').html(h);
    }).catch(() => {});
}
function amendForm(p) {
    const html = `<div class="erp-grid">${E.field('Reason for the amendment *', E.input('aWhy', ''), 'span-2')}${E.field('Expected delivery', E.input('aExp', p.expected_delivery_date || '', 'type="date"'))}
        ${E.field('Other charges ₹', E.input('aOther', p.other_charges || 0, 'type="number" min="0" step="any"'))}${E.field('Notes', E.textarea('aNotes', p.notes || ''), 'span-all')}</div>
      <div class="erp-section-title">Lines (quantity cannot go below what is already received)</div>
      <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Received</th><th>Qty</th><th>Rate ₹</th><th>Discount ₹</th><th>GST %</th></tr></thead><tbody>
      ${p.items.map(i => `<tr data-id="${i.id}"><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.received_qty)}</td>
         <td class="w-num"><input class="adm-input" type="number" step="any" min="${E.num(i.received_qty)}" data-f="q" value="${E.num(i.quantity)}"></td>
         <td class="w-num"><input class="adm-input" type="number" step="any" min="0" data-f="r" value="${E.num(i.rate)}"></td>
         <td class="w-num"><input class="adm-input" type="number" step="any" min="0" data-f="d" value="${E.num(i.discount_amount)}"></td>
         <td class="w-sm"><input class="adm-input" type="number" step="any" min="0" data-f="t" value="${E.num(i.tax_percent)}"></td></tr>`).join('')}</tbody></table></div>
      <div class="erp-section-title">Add new lines</div><div id="aNew"></div>
      <p class="erp-note">The current PO stays in force until an approver approves the amendment in Approvals. The revision keeps the old and the new version.</p>`;
    let ed;
    E.form('Amend ' + p.po_number, html, () => {
        const items = $('.swal2-popup tr[data-id]').map(function () { const $r = $(this); return { id: $r.data('id'), quantity: $r.find('[data-f=q]').val(), rate: $r.find('[data-f=r]').val(), discount_amount: $r.find('[data-f=d]').val(), tax_percent: $r.find('[data-f=t]').val() }; }).get()
            .concat(ed.get().map(i => ({ item_type: i.item_type, item_id: i.item_id, quantity: i.quantity, rate: i.rate, discount_amount: i.discount_amount, tax_percent: i.tax_percent })));
        if (!$('#aWhy').val().trim()) return Promise.reject('Give the reason');
        return E.post('procurement_api.php', { action: 'po_amend', id: p.id, reason: $('#aWhy').val(), expected_delivery_date: $('#aExp').val(), other_charges: $('#aOther').val(), notes: $('#aNotes').val(), items }, { silent: true })
            .then(x => { E.toast(x.message); load(); setTimeout(() => openView(p.id), 300); });
    }, { confirmText: 'Send amendment for approval', didOpen: () => { ed = E.lineEditor($('#aNew'), { items: ITEMS, lines: [] }); } });
}
function selectedSupplier() {
    if ($('#pNewSupFields').prop('hidden')) return Promise.resolve($('#pSup').val());
    const name = $('#pNewSupName').val().trim();
    const mobile = $('#pNewSupMobile').val().trim();
    const gst = $('#pNewSupGst').val().trim().toUpperCase();
    const ifsc = $('#pNewSupIfsc').val().trim().toUpperCase();
    if (!name) return Promise.reject('Enter the new supplier or shop name');
    if (SUP.some(s => String(s.supplier_name).trim().toLowerCase() === name.toLowerCase())) {
        return Promise.reject('This supplier already exists. Choose it from the supplier list.');
    }
    if (gst && !/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/.test(gst)) return Promise.reject('Enter a valid 15-character GSTIN or leave it blank for an unregistered supplier');
    if (ifsc && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc)) return Promise.reject('Enter a valid 11-character IFSC code');
    return E.post('save_supplier.php', { supplier_name: name, owner_name: $('#pNewSupOwner').val().trim(), mobile: mobile, gst_number: gst,
        account_holder_name: $('#pNewSupHolder').val().trim(), bank_name: $('#pNewSupBank').val().trim(), bank_account_number: $('#pNewSupAccount').val().trim(),
        bank_ifsc: ifsc, upi_id: $('#pNewSupUpi').val().trim(), status: 'active' }, { silent: true })
        .then(() => E.api('get_suppliers.php', {}, { silent: true }))
        .then(r => {
            SUP = r.suppliers || [];
            const supplier = SUP.find(s => String(s.supplier_name).trim().toLowerCase() === name.toLowerCase());
            if (!supplier) return Promise.reject('Supplier was saved but could not be selected. Please reopen the purchase order.');
            return supplier.id;
        });
}

function openView(id) {
    E.api('purchase_api.php', { action: 'po_get', id }).then(r => {
        const p = r.record;
        const items = p.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.qty(i.received_qty)}</td>
            <td class="erp-num">${E.qty(Math.max(0, i.quantity - i.received_qty))}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.money(i.discount_amount)}</td>
            <td class="erp-num">${E.num(i.tax_percent)}%</td><td class="erp-num">${E.money(i.line_total)}</td></tr>`).join('');
        const html = E.kv([['Supplier', `<a class="erp-link" href="supplier_ledger.php?id=${p.supplier_id}">${E.esc(p.supplier_name)}</a>`], p.supplier_gst ? ['Supplier GSTIN', E.esc(p.supplier_gst)] : null, ['PO date', E.date(p.po_date)],
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
               ${p.notes ? '<p class="erp-note">' + E.esc(p.notes) + '</p>' : ''}<div id="vQuotes"></div><div id="vDocs"></div>`;
        const act = (a, msg, extra) => () => E.confirmAction(msg, '', extra).then(reason => E.post('purchase_api.php', { action: a, id: p.id, reason }))
                                           .then(x => { E.toast(x.message); load(); openView(p.id); }).catch(() => {});
        E.view(p.po_number, html, [
            ['draft', 'pending_approval'].includes(p.status) && { label: 'Edit', icon: 'fa-pen', run: () => openForm(p) },
            p.status === 'pending_approval' && { label: 'Approve on the Dashboard', cls: 'adm-btn-primary', icon: 'fa-gauge-high', run: () => location.href = 'index.php' },   // approvals only from the Dashboard (1 Oct 2026)
            ['approved', 'partially_received'].includes(p.status) && { label: 'Receive goods (GRN)', cls: 'adm-btn-primary', icon: 'fa-dolly', run: () => location.href = 'goods_receipts.php?po_id=' + p.id },
            ['approved', 'partially_received', 'fully_received'].includes(p.status) && { label: 'Close PO', icon: 'fa-lock', run: act('po_close', 'Close ' + p.po_number + '? No more goods can be received.', { reason: 'Reason (optional)', optional: true }) },
            !['cancelled', 'closed', 'fully_received'].includes(p.status) && !p.grns.some(g => g.status === 'posted') && { label: 'Cancel PO', icon: 'fa-ban', run: act('po_cancel', 'Cancel ' + p.po_number + '?', { danger: true, reason: 'Reason' }) },
            // amendments, rejection, transport, print (1 Oct 2026)
            ['approved', 'partially_received'].includes(p.status) && { label: 'Amend', icon: 'fa-pen-ruler', run: () => amendForm(p) },
            p.status === 'pending_approval' && { label: 'Reject', icon: 'fa-xmark', run: () => E.confirmAction('Reject ' + p.po_number + '?', 'It goes back to draft.', { danger: true, reason: 'Reason' }).then(reason => E.post('procurement_api.php', { action: 'po_reject', id: p.id, reason })).then(x => { E.toast(x.message); load(); openView(p.id); }).catch(() => {}) },
            !['cancelled', 'draft'].includes(p.status) && { label: 'Transport', icon: 'fa-truck', run: () => location.href = 'shipments.php?po_id=' + p.id },
            { label: 'Print', icon: 'fa-print', run: () => window.open('print_erp.php?type=po&id=' + p.id, '_blank') },
        ], { didOpen: () => { E.docs($('#vDocs'), 'purchase_order', p.id, 'PURCHASE_ORDER'); poExtras(p); POQ_LOAD.then(() => { if (window.POQ) /* only the shop we buy from — comparison is on RFQ & Quotations (1 Oct 2026) */ (POQ.chosen || POQ.view)($('#vQuotes'), p); }); }, width: 1180 });
    }).catch(() => {});
}
JS
);
