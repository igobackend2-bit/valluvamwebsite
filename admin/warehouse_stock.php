<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Stock by Warehouse', 'Available, damaged, rejected and expired stock per warehouse · quarantine (waiting for QC) · committed to open sales orders',
    '<button class="adm-btn adm-btn-ghost" id="setBtn"><i class="fas fa-gear"></i> Settings</button> <button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-kpis" id="kpis"></div>
<div id="diffs"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2>Stock</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fWh"><option value="">All warehouses</option></select>
            <select class="adm-select" id="fType"><option value="">All items</option><option value="product">Product packs</option><option value="raw_material">Raw materials</option></select>
            <select class="adm-select" id="fBucket"><option value="">Any stock</option><option value="damaged">Has damaged</option><option value="rejected">Has rejected</option><option value="expired">Has expired</option><option value="quarantine">In quarantine</option></select>
            <input class="adm-input" id="fQ" placeholder="Item or SKU">
        </div></div>
    <div class="adm-card-body"><div id="list"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [], WH = [];
const COLS = [
    { label: 'Item', key: 'item_name', render: x => `<strong>${E.esc(x.item_name)}</strong><div class="erp-muted">${E.esc(x.item_sku || '')}</div>` },
    { label: 'Warehouse', key: 'warehouse_name', render: x => E.esc(x.warehouse_name) },
    { label: 'Available', key: 'available', num: true, render: x => `<strong>${E.qty(x.available)}</strong>` },
    { label: 'Committed', key: 'committed', num: true, render: x => x.committed ? E.qty(x.committed) : '' },
    { label: 'Free to sell', key: 'free_to_sell', num: true, render: x => `<span class="${x.free_to_sell < 0 ? 'erp-neg' : ''}">${E.qty(x.free_to_sell)}</span>` },
    { label: 'Quarantine (QC)', key: 'quarantine', num: true, render: x => x.quarantine ? E.qty(x.quarantine) : '' },
    { label: 'Damaged', key: 'damaged', num: true, render: x => x.damaged ? E.qty(x.damaged) : '' },
    { label: 'Rejected', key: 'rejected', num: true, render: x => x.rejected ? E.qty(x.rejected) : '' },
    { label: 'Expired', key: 'expired', num: true, render: x => x.expired ? E.qty(x.expired) : '' },
    { label: 'Value', key: 'value', num: true, render: x => E.money(x.value) },
    { label: '', render: (x, i) => `<button class="adm-btn adm-btn-ghost" data-act="${i}">Actions</button>` },
];
E.warehouses().then(w => { WH = w; $('#fWh').append(w.map(x => `<option value="${x.id}">${E.esc(x.name)}</option>`).join('')); load(); });
$('#fWh,#fType,#fBucket').on('change', load);
$('#fQ').on('input', render);
$('#csvBtn').on('click', () => E.csv(filtered(), COLS.filter(c => c.key), 'stock_by_warehouse.csv'));
function filtered() { const q = ($('#fQ').val() || '').toLowerCase(); return ROWS.filter(x => !q || (x.item_name + ' ' + (x.item_sku || '')).toLowerCase().includes(q)); }
function render() { E.table($('#list'), COLS, filtered(), { empty: 'No stock found', icon: 'fa-warehouse' }); }
function load() {
    E.loading($('#list'));
    E.api('warehouse_api.php', { action: 'stock', warehouse_id: $('#fWh').val(), item_type: $('#fType').val(), bucket: $('#fBucket').val() }, { silent: true }).then(r => {
        ROWS = r.rows; render();
        $('#kpis').html(r.summary.map(s => E.kpi(s.warehouse_name + ' · ' + s.items + ' items', E.money(s.value), 'is-primary')).join('') +
            E.kpi('Damaged / rejected / expired', E.qty(r.summary.reduce((a, s) => a + s.damaged + s.rejected + s.expired, 0)), 'is-amber') +
            E.kpi('In quarantine (QC)', E.qty(r.summary.reduce((a, s) => a + s.quarantine, 0))));
        $('#diffs').html(r.differences.length ? `<div class="erp-warn">${r.differences.length} item(s): warehouse totals differ from the stock figure (a change was made outside the stock ledger).
            ${r.differences.slice(0, 8).map(d => `<div>${E.esc(d.item_name)} — stock ${E.qty(d.master_stock)}, warehouses ${E.qty(d.warehouse_total)} <button class="adm-btn adm-btn-ghost" data-align="${d.item_type}:${d.item_id}">Place difference in a warehouse</button></div>`).join('')}</div>` : '');
    }).catch(m => E.errorBox($('#list'), m, load));
}
const whOpts = sel => E.options(WH, 'id', w => w.name, sel, false);
$('#diffs').on('click', '[data-align]', function () {
    const [t, id] = String($(this).data('align')).split(':');
    E.form('Align warehouse stock', E.field('Warehouse', E.select('aWh', whOpts(''))) + E.field('Reason *', E.input('aWhy', 'Stock changed outside the ledger')),
        () => E.post('warehouse_api.php', { action: 'realloc', mode: 'align', item_type: t, item_id: id, to_warehouse_id: $('#aWh').val(), reason: $('#aWhy').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 560 });
});
$('#list').on('click', '[data-act]', function () {
    const x = filtered()[+$(this).data('act')];
    const B = [['damaged', 'Damaged'], ['expired', 'Expired'], ['rejected', 'Rejected']].filter(b => x[b[0]] > 0);
    E.view(x.item_name + ' — ' + x.warehouse_name, E.kv([['Available', E.qty(x.available)], ['Damaged', E.qty(x.damaged)], ['Rejected', E.qty(x.rejected)], ['Expired', E.qty(x.expired)], ['Average cost', E.money(x.avg_cost)]]) + '<div id="mv"></div>', [
        x.available > 0 && { label: 'Move to damaged / expired', icon: 'fa-triangle-exclamation', run: () => moveOut(x) },
        B.length && { label: 'Dispose / return', icon: 'fa-trash', run: () => bucketOut(x, B) },
        B.length && { label: 'Back to sellable', icon: 'fa-rotate-left', run: () => restore(x, B) },
        x.available > 0 && WH.length > 1 && { label: 'Transfer', icon: 'fa-right-left', run: () => location.href = 'stock_transfers.php?new=1&item=' + x.item_type + ':' + x.item_id + '&from=' + x.warehouse_id },
        x.available > 0 && WH.length > 1 && { label: 'Correct allocation', icon: 'fa-pen', run: () => realloc(x) },
    ], { didOpen: () => E.api('warehouse_api.php', { action: 'moves', item_type: x.item_type, item_id: x.item_id, warehouse_id: x.warehouse_id, per_page: 30 }, { silent: true }).then(r => {
        E.table($('#mv'), [{ label: 'When', render: m => E.date(m.created_at) }, { label: 'Stock', render: m => E.esc(m.bucket) }, { label: 'Change', num: true, render: m => `<span class="${m.quantity < 0 ? 'erp-neg' : 'erp-pos'}">${m.quantity > 0 ? '+' : ''}${E.qty(m.quantity)}</span>` },
            { label: 'Source', render: m => E.esc(m.source) + ' · ' + E.esc([m.reference_type, m.reference_number].filter(Boolean).join(' ')) }, { label: 'Reason', render: m => E.esc(m.reason || '') }, { label: 'By', render: m => E.esc(m.created_by || '') }], r.rows, { empty: 'No movements' }); }) });
});
function batchSel(x) {
    return E.api('warehouse_api.php', { action: 'batches', item_type: x.item_type, item_id: x.item_id, status: 'open' }, { silent: true }).then(r => '<option value="">Any / not batch-specific</option>' + r.rows.map(b => `<option value="${b.id}">${E.esc(b.batch_number || 'Batch #' + b.id)} · exp ${E.date(b.expiry_date)} · ${E.qty(b.remaining)} left</option>`).join('')).catch(() => '<option value="">—</option>');
}
function moveOut(x) {
    batchSel(x).then(opts => E.form('Move out of sellable stock', `<div class="erp-grid">${E.field('To', E.select('mB', '<option value="damaged">Damaged</option><option value="expired">Expired</option>'))}${E.field('Quantity *', E.input('mQ', '', 'type="number" min="0" step="any"'))}
        ${E.field('Batch', E.select('mBatch', opts), 'span-2')}${E.field('Reason *', E.input('mR', ''), 'span-all')}</div><p class="erp-note">The quantity leaves sellable stock through the stock ledger (valued at average cost as a write-off) and is held in the chosen bucket until it is disposed of or restored.</p>`,
        () => E.post('warehouse_api.php', { action: 'bucket_move', item_type: x.item_type, item_id: x.item_id, warehouse_id: x.warehouse_id, bucket: $('#mB').val(), quantity: $('#mQ').val(), batch_id: $('#mBatch').val(), reason: $('#mR').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 680 }));
}
function bucketOut(x, B) {
    E.form('Dispose / return', `<div class="erp-grid">${E.field('From', E.select('oB', B.map(b => `<option value="${b[0]}">${b[1]} (${E.qty(x[b[0]])})</option>`).join('')))}${E.field('Quantity *', E.input('oQ', '', 'type="number" min="0" step="any"'))}
        ${E.field('What happened', E.select('oH', [['disposed', 'Disposed'], ['destroyed', 'Destroyed'], ['returned_to_supplier', 'Returned to supplier'], ['donated', 'Donated'], ['sold_as_scrap', 'Sold as scrap'], ['other', 'Other']].map(h => `<option value="${h[0]}">${h[1]}</option>`).join('')))}
        ${E.field('Note', E.input('oR', ''), 'span-all')}</div>`,
        () => E.post('warehouse_api.php', { action: 'bucket_out', item_type: x.item_type, item_id: x.item_id, warehouse_id: x.warehouse_id, bucket: $('#oB').val(), quantity: $('#oQ').val(), disposal: $('#oH').val(), reason: $('#oR').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 640 });
}
function restore(x, B) {
    E.form('Back to sellable stock', `<div class="erp-grid">${E.field('From', E.select('rB', B.map(b => `<option value="${b[0]}">${b[1]} (${E.qty(x[b[0]])})</option>`).join('')))}${E.field('Quantity *', E.input('rQ', '', 'type="number" min="0" step="any"'))}
        ${E.field('Inspection result *', E.input('rR', ''), 'span-all')}</div><p class="erp-note">Needs the "approve stock adjustments" permission. It re-enters stock at the current average cost.</p>`,
        () => E.post('warehouse_api.php', { action: 'bucket_restore', item_type: x.item_type, item_id: x.item_id, warehouse_id: x.warehouse_id, bucket: $('#rB').val(), quantity: $('#rQ').val(), reason: $('#rR').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 640 });
}
function realloc(x) {
    E.form('Correct allocation (not a transfer)', `<div class="erp-grid">${E.field('Move to warehouse', E.select('cWh', whOpts('')))}${E.field('Quantity *', E.input('cQ', '', 'type="number" min="0" step="any"'))}${E.field('Reason *', E.input('cR', 'Opening allocation correction'), 'span-all')}</div>
        <p class="erp-note">Use this only to fix where existing stock was recorded (for example the opening allocation). Real movements between warehouses go through Stock Transfers.</p>`,
        () => E.post('warehouse_api.php', { action: 'realloc', item_type: x.item_type, item_id: x.item_id, from_warehouse_id: x.warehouse_id, to_warehouse_id: $('#cWh').val(), quantity: $('#cQ').val(), reason: $('#cR').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 640 });
}
$('#setBtn').on('click', () => E.api('warehouse_api.php', { action: 'settings' }).then(s => E.form('Inventory settings', `<div class="erp-grid">
    ${E.field('Default warehouse', E.select('sWh', whOpts(s.default_warehouse)))}${E.field('Expiry alert (days before)', E.input('sDays', s.expiry_alert_days, 'type="number" min="1" max="365"'))}
    ${E.field('Quality check', `<label><input type="checkbox" id="sQc" ${s.qc_required ? 'checked' : ''}> Required before a goods receipt adds stock</label>`, 'span-2')}</div>`,
    () => E.post('warehouse_api.php', { action: 'settings_save', default_warehouse: $('#sWh').val(), expiry_alert_days: $('#sDays').val(), qc_required: $('#sQc').is(':checked') ? 1 : 0 }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 620 })));
JS
);
