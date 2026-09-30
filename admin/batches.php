<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Batches & Expiry', 'Every batch / lot with what is left (sold earliest-expiry first), where it came from and when it expires',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2>Batches</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="open">With stock left</option><option value="">All batches</option><option value="expired">Expired</option><option value="expiring">Expiring soon</option><option value="ok">OK</option><option value="none">No expiry date</option><option value="empty">Used up</option></select>
            <select class="adm-select" id="fType"><option value="">All items</option><option value="product">Product packs</option><option value="raw_material">Raw materials</option></select>
            <select class="adm-select" id="fSup"><option value="">All suppliers</option></select>
            <input class="adm-input" id="fQ" placeholder="Item, batch, lot, supplier">
        </div></div>
    <div class="adm-card-body"><div id="list"></div><p class="erp-note">Sales and write-offs are assigned to batches automatically, earliest expiry first (FEFO). Batches are tracked for the business as a whole (not per warehouse).</p></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
const COLS = [
    { label: 'Item', key: 'item_name', render: b => `<strong>${E.esc(b.item_name)}</strong>` },
    { label: 'Batch / lot', key: 'batch_number', render: b => `<a class="erp-link" data-view="${b.id}">${E.esc(b.batch_number || 'Batch #' + b.id)}</a><div class="erp-muted">${E.esc(b.lot_number || '')}</div>` },
    { label: 'From', key: 'source_number', render: b => E.esc((b.source_type || '').replace('_', ' ') + ' ' + (b.source_number || '')) + `<div class="erp-muted">${E.esc(b.supplier_name || '')}</div>` },
    { label: 'Received', key: 'received_date', render: b => E.date(b.received_date) },
    { label: 'Mfg', key: 'manufacturing_date', render: b => E.date(b.manufacturing_date) },
    { label: 'Expiry', key: 'expiry_date', render: b => E.date(b.expiry_date) + (b.days_left !== null && b.remaining > 0 ? `<div class="erp-muted">${b.days_left < 0 ? Math.abs(b.days_left) + ' days ago' : b.days_left + ' days left'}</div>` : '') },
    { label: 'Received qty', key: 'qty_received', num: true, render: b => E.qty(b.qty_received) },
    { label: 'Left', key: 'remaining', num: true, render: b => `<strong>${E.qty(b.remaining)}</strong>` },
    { label: 'Unit cost', key: 'unit_cost', num: true, render: b => E.money(b.unit_cost) },
    { label: 'Value left', key: 'remaining_value', num: true, render: b => E.money(b.remaining_value) },
    { label: 'Status', key: 'expiry_status', render: b => E.badge(b.expiry_status) },
];
E.suppliers().then(s => { $('#fSup').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join('')); if (E.param('status')) $('#fStatus').val(E.param('status')); load(); if (E.param('id')) openView(E.param('id')); });
$('#fStatus,#fType,#fSup').on('change', load);
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
$('#csvBtn').on('click', () => E.csv(ROWS, COLS, 'batches.csv'));
function load() {
    E.loading($('#list'));
    E.api('warehouse_api.php', { action: 'batches', status: $('#fStatus').val(), item_type: $('#fType').val(), supplier_id: $('#fSup').val(), q: $('#fQ').val() }, { silent: true }).then(r => {
        ROWS = r.rows;
        $('#kpis').html(E.kpi('Expired with stock', r.counts.expired, r.counts.expired ? 'is-amber' : 'is-green', 'batches.php?status=expired') + E.kpi('Expiring in ' + r.alert_days + ' days', r.counts.expiring, r.counts.expiring ? 'is-amber' : 'is-green', 'batches.php?status=expiring') +
            E.kpi('Value in listed batches', E.money(ROWS.reduce((a, b) => a + E.num(b.remaining_value), 0))));
        E.table($('#list'), COLS, ROWS, { empty: 'No batches', icon: 'fa-barcode' });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
function openView(id) {
    E.api('warehouse_api.php', { action: 'batch_get', id }).then(r => {
        const b = r.record, o = b.origin;
        const html = E.kv([['Item', E.esc(b.item_name)], ['Batch', E.esc(b.batch_number || '#' + b.id)], ['Lot', E.esc(b.lot_number || '—')], ['Mfg', E.date(b.manufacturing_date)], ['Expiry', E.date(b.expiry_date)],
                           ['Received', E.qty(b.qty_received)], ['Returned to supplier', E.qty(b.qty_returned)], ['Sold / written off', E.qty(b.allocated)], ['Left', '<strong>' + E.qty(b.remaining) + '</strong>'], ['Unit cost', E.money(b.unit_cost)]])
            + (o ? E.chain([o.grn_id ? ['GRN ' + o.grn_number, 'goods_receipts.php?id=' + o.grn_id] : null, o.po_id ? ['PO ' + o.po_number, 'purchase_orders.php?id=' + o.po_id] : null,
                            o.supplier_id ? ['Supplier ' + o.supplier_name, 'supplier_360.php?id=' + o.supplier_id] : null, o.pinv_id ? ['Bill (landed ' + E.money(o.landed_unit) + '/unit)', 'purchase_invoices.php?id=' + o.pinv_id] : null]) : '')
            + '<div class="erp-section-title">Where it went (FEFO)</div><div id="al"></div><div id="vDocs"></div>';
        E.view('Batch ' + (b.batch_number || '#' + b.id), html, [], { didOpen: () => {
            E.table($('#al'), [{ label: 'When', render: a => E.date(a.created_at) }, { label: 'Movement', render: a => E.esc(a.movement_type) + ' · ' + E.esc(a.reference_type || '') + ' ' + E.esc(a.reference_number || '') }, { label: 'Qty', num: true, render: a => E.qty(a.quantity) }, { label: 'How', render: a => E.esc(a.allocation_type) }], b.allocations, { empty: 'Nothing sold from this batch yet' });
            E.docs($('#vDocs'), 'batch', b.id, 'BATCH_DOCUMENT'); } });
    }).catch(() => {});
}
JS
);
