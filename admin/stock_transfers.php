<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Stock Transfers', 'Move stock between warehouses: send (in transit, not sellable) → receive · total stock and value stay the same',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New transfer</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Transfers</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="draft">Draft</option><option value="in_transit">In transit</option><option value="received">Received</option><option value="cancelled">Cancelled</option></select>
            <select class="adm-select" id="fWh"><option value="">All warehouses</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo"><input class="adm-input" id="fQ" placeholder="Transfer no. or vehicle">
        </div></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let WH = [], ITEMS = [];
Promise.all([E.warehouses(), E.items()]).then(([w, i]) => { WH = w; ITEMS = i; $('#fWh').append(w.map(x => `<option value="${x.id}">${E.esc(x.name)}</option>`).join('')); load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('new')) { const it = (E.param('item') || '').split(':'); openForm({ from_warehouse_id: E.param('from'), items: it[1] ? [{ item_type: it[0], item_id: it[1] }] : [] }); } });
$('#fStatus,#fWh,#fFrom,#fTo').on('change', load);
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm({}));
function load() {
    E.loading($('#list'));
    E.api('warehouse_api.php', { action: 'tr_list', status: $('#fStatus').val(), warehouse_id: $('#fWh').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Transfer', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.transfer_number)}</a>` }, { label: 'Date', render: x => E.date(x.transfer_date) },
        { label: 'From → to', render: x => E.esc(x.from_name) + ' → ' + E.esc(x.to_name) }, { label: 'Items', num: true, render: x => x.item_count },
        { label: 'Value', num: true, render: x => x.status === 'draft' ? '—' : E.money(x.value) }, { label: 'Vehicle', render: x => E.esc(x.vehicle_number || '') }, { label: 'Status', render: x => E.badge(x.status) },
    ], r.rows, { empty: 'No transfers yet', icon: 'fa-right-left' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
function openForm(t) {
    const html = `<div class="erp-grid">${E.field('Date *', E.input('tDate', t.transfer_date || E.today(), 'type="date"'))}${E.field('From *', E.select('tFrom', E.options(WH, 'id', w => w.name, t.from_warehouse_id || 1, false)))}
        ${E.field('To *', E.select('tTo', E.options(WH, 'id', w => w.name, t.to_warehouse_id, 'Choose')))}${E.field('Vehicle', E.input('tVeh', t.vehicle_number || ''))}${E.field('Notes', E.textarea('tNotes', t.notes || ''), 'span-all')}</div>
        <div class="erp-section-title">Items</div><div id="tLines"></div>`;
    let ed;
    const save = extra => E.post('warehouse_api.php', Object.assign({ action: 'tr_save', id: t.id || '', transfer_date: $('#tDate').val(), from_warehouse_id: $('#tFrom').val(), to_warehouse_id: $('#tTo').val(), vehicle_number: $('#tVeh').val(), notes: $('#tNotes').val(),
        items: ed.get().map(i => ({ item_type: i.item_type, item_id: i.item_id, quantity: i.quantity })) }, extra), { silent: true }).then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); });
    E.form(t.id ? 'Edit ' + t.transfer_number : 'New stock transfer', html, btn => save(btn === 'deny' ? {} : { send_now: 1 }),
        { confirmText: 'Send now', denyText: 'Save draft', didOpen: () => { ed = E.lineEditor($('#tLines'), { items: ITEMS, columns: ['item', 'qty'], lines: (t.items || []).map(i => ({ item_type: i.item_type, item_id: i.item_id, quantity: i.quantity })) }); } });
}
function openView(id) {
    E.api('warehouse_api.php', { action: 'tr_get', id }).then(r => {
        const t = r.record;
        const html = E.kv([['From', E.esc(t.from_name)], ['To', E.esc(t.to_name)], ['Date', E.date(t.transfer_date)], ['Vehicle', E.esc(t.vehicle_number || '—')], ['Status', E.badge(t.status)],
                           t.sent_by ? ['Sent', E.esc(t.sent_by) + ' · ' + E.date(t.sent_at)] : null, t.received_by ? ['Received', E.esc(t.received_by) + ' · ' + E.date(t.received_at)] : null])
            + '<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">At source now</th><th class="erp-num">Unit cost</th><th class="erp-num">Value</th></tr></thead><tbody>'
            + t.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.qty(i.available_at_source)}</td><td class="erp-num">${t.status === 'draft' ? '—' : E.money(i.unit_cost)}</td><td class="erp-num">${t.status === 'draft' ? '—' : E.money(i.quantity * i.unit_cost)}</td></tr>`).join('')
            + `</tbody></table></div>${t.notes ? '<p class="erp-note">' + E.esc(t.notes) + '</p>' : ''}<div id="vDocs"></div>`;
        const act = (a, msg, o) => () => E.confirmAction(msg, '', o).then(reason => E.post('warehouse_api.php', { action: a, id: t.id, reason })).then(x => { E.toast(x.message); load(); openView(t.id); }).catch(() => {});
        E.view(t.transfer_number, html, [
            t.status === 'draft' && { label: 'Edit', icon: 'fa-pen', run: () => openForm(t) },
            t.status === 'draft' && { label: 'Send', cls: 'adm-btn-primary', icon: 'fa-truck-moving', run: act('tr_send', 'Send ' + t.transfer_number + '? Stock leaves ' + t.from_name + '.') },
            t.status === 'in_transit' && { label: 'Receive', cls: 'adm-btn-primary', icon: 'fa-box-open', run: act('tr_receive', 'Received everything at ' + t.to_name + '?') },
            ['draft', 'in_transit'].includes(t.status) && { label: 'Cancel', icon: 'fa-ban', run: act('tr_cancel', 'Cancel ' + t.transfer_number + '?' + (t.status === 'in_transit' ? ' The goods go back to ' + t.from_name + '.' : ''), { danger: true, reason: 'Reason' }) },
        ], { didOpen: () => E.docs($('#vDocs'), 'stock_transfer', t.id, 'TRANSPORT_RECEIPT') });
    }).catch(() => {});
}
JS
);
