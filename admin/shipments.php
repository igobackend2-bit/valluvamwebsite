<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Transport / Logistics', 'Incoming shipments: transporter, vehicle, LR, dates and costs — added to landed cost once, or recorded as an expense',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New shipment</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Inbound shipments</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="planned">Planned</option><option value="in_transit">In transit</option><option value="arrived">Arrived</option><option value="cancelled">Cancelled</option></select>
            <select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input class="adm-input" id="fQ" placeholder="Shipment, LR, vehicle, transporter, PO">
        </div></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [];
E.suppliers().then(s => { SUP = s; $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join('')); load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('po_id') || E.param('grn_id')) openForm({ po_id: E.param('po_id'), grn_id: E.param('grn_id') }); });
$('#fStatus,#fSupplier,#fFrom,#fTo').on('change', load);
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm({}));
const MODES = [['road', 'Road'], ['rail', 'Rail'], ['air', 'Air'], ['sea', 'Sea'], ['courier', 'Courier'], ['own_vehicle', 'Own vehicle'], ['other', 'Other']];
const TREAT = [['landed', 'Add to stock cost (landed cost) — we pay the transporter'], ['expense', 'Record as an expense (not in stock cost)'], ['supplier_paid', 'Paid by the supplier (no cost to us)']];
function load() {
    E.loading($('#list'));
    E.api('procurement_api.php', { action: 'shp_list', status: $('#fStatus').val(), supplier_id: $('#fSupplier').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Shipment', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.shipment_number)}</a>` },
        { label: 'PO / GRN', render: x => E.esc([x.po_number, x.grn_number].filter(Boolean).join(' / ') || '—') },
        { label: 'Supplier', render: x => E.esc(x.supplier_name || '—') },
        { label: 'Transporter', render: x => E.esc(x.transport_company || x.transporter_name || '—') + `<div class="erp-muted">${E.esc(x.vehicle_number || '')} ${x.lr_number ? 'LR ' + E.esc(x.lr_number) : ''}</div>` },
        { label: 'Dispatch / arrival', render: x => E.date(x.dispatch_date) + ' → ' + (x.actual_arrival ? E.date(x.actual_arrival) : '<span class="erp-muted">exp. ' + E.date(x.expected_arrival) + '</span>') },
        { label: 'Cost', num: true, render: x => E.money(x.total_cost) + `<div class="erp-muted">${x.cost_treatment === 'landed' ? (x.cost_applied == 1 ? 'in stock cost' : 'not yet in cost') : x.cost_treatment.replace('_', ' ')}</div>` },
        { label: 'Paid', num: true, render: x => x.cost_treatment === 'landed' ? E.money(x.amount_paid) : '—' },
        { label: 'Status', render: x => E.badge(x.status) },
    ], r.rows, { empty: 'No shipments recorded', icon: 'fa-truck' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
function openForm(s) {
    const html = `<div class="erp-grid">
        ${E.field('Purchase order no.', E.input('sPo', s.po_number || '', 'placeholder="PO-2026-…"'))}
        ${E.field('Goods receipt no.', E.input('sGrn', s.grn_number || '', 'placeholder="GRN-2026-… (when received)"'))}
        ${E.field('Supplier (if no PO)', E.select('sSup', E.options(SUP, 'id', x => x.supplier_name, s.supplier_id, '—')))}
        ${E.field('Transport company', E.input('sCo', s.transport_company || ''))}
        ${E.field('Transporter / contact', E.input('sTr', s.transporter_name || ''))}
        ${E.field('Mode', E.select('sMode', MODES.map(m => `<option value="${m[0]}" ${s.transport_mode === m[0] ? 'selected' : ''}>${m[1]}</option>`).join('')))}
        ${E.field('Vehicle no.', E.input('sVeh', s.vehicle_number || ''))}
        ${E.field('Driver', E.input('sDrv', s.driver_name || ''))}
        ${E.field('Driver phone', E.input('sDph', s.driver_phone || ''))}
        ${E.field('LR no.', E.input('sLr', s.lr_number || ''))}
        ${E.field('Consignment no.', E.input('sCn', s.consignment_number || ''))}
        ${E.field('Dispatch date', E.input('sDd', s.dispatch_date || '', 'type="date"'))}
        ${E.field('Expected arrival', E.input('sEa', s.expected_arrival || '', 'type="date"'))}
        ${E.field('Actual arrival', E.input('sAa', s.actual_arrival || '', 'type="date"'))}
        ${E.field('Freight ₹', E.input('sF', s.freight_amount || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Loading ₹', E.input('sL', s.loading_charge || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Unloading ₹', E.input('sU', s.unloading_charge || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Handling ₹', E.input('sH', s.handling_charge || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Other ₹', E.input('sO', s.other_charge || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Cost treatment', E.select('sT', TREAT.map(m => `<option value="${m[0]}" ${(s.cost_treatment || 'landed') === m[0] ? 'selected' : ''}>${m[1]}</option>`).join('')), 'span-2')}
        ${E.field('Notes', E.textarea('sNotes', s.notes || ''), 'span-all')}</div>
      <p class="erp-note">If the supplier's bill already includes this freight, choose "Paid by the supplier" so it is not counted twice.</p>`;
    const resolve = () => Promise.all([
        $('#sPo').val() ? E.api('purchase_api.php', { action: 'po_list', q: $('#sPo').val().trim() }, { silent: true }).then(r => (r.rows.find(x => x.po_number === $('#sPo').val().trim()) || {}).id || Promise.reject('Purchase order not found')) : null,
        $('#sGrn').val() ? E.api('purchase_api.php', { action: 'grn_list', q: $('#sGrn').val() }, { silent: true }).then(r => (r.rows.find(x => x.grn_number === $('#sGrn').val().trim()) || {}).id || Promise.reject('Goods receipt not found')) : null]);
    E.form(s.id ? 'Edit ' + s.shipment_number : 'New shipment', html, () => resolve().then(([poId, grnId]) => E.post('procurement_api.php', { action: 'shp_save', id: s.id || '', po_id: poId || s.po_id || '', grn_id: grnId || '',
            supplier_id: $('#sSup').val(), transport_company: $('#sCo').val(), transporter_name: $('#sTr').val(), transport_mode: $('#sMode').val(), vehicle_number: $('#sVeh').val(), driver_name: $('#sDrv').val(),
            driver_phone: $('#sDph').val(), lr_number: $('#sLr').val(), consignment_number: $('#sCn').val(), dispatch_date: $('#sDd').val(), expected_arrival: $('#sEa').val(), actual_arrival: $('#sAa').val(),
            freight_amount: $('#sF').val(), loading_charge: $('#sL').val(), unloading_charge: $('#sU').val(), handling_charge: $('#sH').val(), other_charge: $('#sO').val(), cost_treatment: $('#sT').val(), notes: $('#sNotes').val() }, { silent: true }))
        .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); }), { didOpen: () => {
            if (s.po_id && !s.po_number) E.api('purchase_api.php', { action: 'po_get', id: s.po_id }, { silent: true }).then(r => $('#sPo').val(r.record.po_number));
            if (s.grn_id && !s.grn_number) E.api('purchase_api.php', { action: 'grn_get', id: s.grn_id }, { silent: true }).then(r => { $('#sGrn').val(r.record.grn_number); $('#sPo').val(r.record.po_number || ''); });
        } });
}
function openView(id) {
    E.api('procurement_api.php', { action: 'shp_get', id }).then(res => {
        const s = res.record;
        const unpaid = E.r2(s.total_cost - s.amount_paid);
        const html = E.kv([['Supplier', E.esc(s.supplier_name || '—')], ['Transporter', E.esc(s.transport_company || s.transporter_name || '—')], ['Mode', E.esc(s.transport_mode)], ['Vehicle', E.esc(s.vehicle_number || '—')],
                           ['Driver', E.esc((s.driver_name || '—') + (s.driver_phone ? ' · ' + s.driver_phone : ''))], ['LR / consignment', E.esc([s.lr_number, s.consignment_number].filter(Boolean).join(' / ') || '—')],
                           ['Dispatch', E.date(s.dispatch_date)], ['Expected', E.date(s.expected_arrival)], ['Arrived', E.date(s.actual_arrival)], ['Status', E.badge(s.status)]])
            + E.chain([s.po_id ? ['PO ' + s.po_number, 'purchase_orders.php?id=' + s.po_id] : null, s.grn_id ? ['GRN ' + s.grn_number + ' (' + s.grn_status + ')', 'goods_receipts.php?id=' + s.grn_id] : null].concat(s.expenses.map(x => ['Expense ' + x.expense_number, 'expenses.php?id=' + x.id])))
            + `<table class="erp-pl" style="max-width:420px">${[['Freight', s.freight_amount], ['Loading', s.loading_charge], ['Unloading', s.unloading_charge], ['Handling', s.handling_charge], ['Other', s.other_charge]].map(x => `<tr class="sub"><td>${x[0]}</td><td>${E.money(x[1])}</td></tr>`).join('')}
               <tr class="total"><td>Total transport cost</td><td>${E.money(s.total_cost)}</td></tr></table>
               <p class="erp-note">Treatment: <strong>${E.esc(TREAT.find(t => t[0] === s.cost_treatment)[1])}</strong>${s.cost_treatment === 'landed' ? (s.cost_applied == 1 ? ' · added to stock cost on ' + E.date(s.cost_applied_at) : ' · not yet added to stock cost') : ''}</p>`
            + (s.bill_charges > 0 && s.cost_treatment === 'landed' ? `<div class="erp-warn">The supplier bill for this GRN already has ₹${E.num(s.bill_charges).toFixed(2)} freight/loading in stock cost. Add this transport only if it is a separate charge.</div>` : '')
            + (s.cost_entries.length ? '<div class="erp-section-title">Added to landed cost</div>' + s.cost_entries.map(c => `<div class="erp-muted">${E.esc(c.item_name)}: ${E.money(c.value)}</div>`).join('') : '')
            + (s.payments.length ? '<div class="erp-section-title">Payments to the transporter</div>' + s.payments.map(p => `<div class="erp-docs-row">${E.date(p.payment_date)} · ${E.money(p.amount)} · ${E.esc(p.payment_mode)} ${E.esc(p.reference_number || '')} ${E.badge(p.status)}
                 ${p.status === 'completed' ? `<button class="adm-icon-btn is-danger" data-pc="${p.id}" title="Cancel payment"><i class="fas fa-ban"></i></button>` : ''}</div>`).join('') : '')
            + '<div id="vDocs"></div>';
        const act = (a, extra) => () => E.post('procurement_api.php', Object.assign({ action: a, id: s.id }, extra || {})).then(x => {
            if (x.status === 'confirm') return E.confirmAction('Separate charge?', x.message, { confirmText: 'Yes, it is separate' }).then(() => act(a, Object.assign({}, extra, { confirm_separate: 1 }))());
            E.toast(x.message); load(); openView(s.id); }).catch(() => {});
        E.view(s.shipment_number, html, [
            s.status !== 'cancelled' && s.cost_applied != 1 && { label: 'Edit', icon: 'fa-pen', run: () => openForm(s) },
            s.status === 'planned' && { label: 'In transit', icon: 'fa-truck-moving', run: act('shp_status', { status: 'in_transit' }) },
            ['planned', 'in_transit'].includes(s.status) && { label: 'Arrived', icon: 'fa-flag-checkered', run: act('shp_status', { status: 'arrived' }) },
            s.status !== 'cancelled' && s.cost_treatment === 'landed' && s.cost_applied != 1 && { label: 'Add to stock cost', cls: 'adm-btn-primary', icon: 'fa-coins', run: act('shp_apply_cost') },
            s.status !== 'cancelled' && s.cost_treatment === 'landed' && unpaid > 0 && { label: 'Record payment', icon: 'fa-money-bill', run: () => pay(s, unpaid) },
            s.status !== 'cancelled' && s.cost_treatment === 'expense' && !s.expenses.length && { label: 'Record as expense', icon: 'fa-receipt', run: act('shp_to_expense') },
            s.status !== 'cancelled' && s.cost_applied != 1 && s.amount_paid == 0 && { label: 'Cancel', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + s.shipment_number + '?', '', { danger: true, reason: 'Reason' }).then(reason => E.post('procurement_api.php', { action: 'shp_cancel', id: s.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
        ], { didOpen: (p) => { E.docs($('#vDocs'), 'shipment', s.id, 'TRANSPORT_RECEIPT');
            $(p).on('click', '[data-pc]', function () { const pid = $(this).data('pc'); E.confirmAction('Cancel this payment?', '', { danger: true, reason: 'Reason' }).then(reason => E.post('procurement_api.php', { action: 'shp_pay_cancel', payment_id: pid, reason })).then(x => { E.toast(x.message); load(); openView(s.id); }).catch(() => {}); }); } });
    }).catch(() => {});
}
function pay(s, unpaid) {
    const html = `<div class="erp-grid">${E.field('Amount ₹ *', E.input('pA', unpaid, 'type="number" min="0" step="any"'))}${E.field('Date *', E.input('pD', E.today(), 'type="date"'))}
        ${E.field('Mode', E.select('pM', ['bank_transfer', 'upi', 'cash', 'cheque', 'card', 'other'].map(m => `<option value="${m}">${m.replace('_', ' ')}</option>`).join('')))}${E.field('Reference / UTR', E.input('pR', ''))}</div>`;
    E.form('Pay transporter — ' + s.shipment_number, html, () => E.post('procurement_api.php', { action: 'shp_pay', id: s.id, amount: $('#pA').val(), payment_date: $('#pD').val(), payment_mode: $('#pM').val(), reference_number: $('#pR').val() }, { silent: true })
        .then(x => { E.toast(x.message); load(); openView(s.id); }), { width: 640, confirmText: 'Record payment' });
}
JS
);
