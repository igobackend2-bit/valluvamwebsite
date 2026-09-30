<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Raw Materials (Bulk)', 'Items bought in bulk (kg / L) and repacked into product packs — not shown on the website',
    '<a class="adm-btn adm-btn-ghost" href="repacking.php"><i class="fas fa-box"></i> Repacking</a><button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New raw material</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Bulk stock</h2><input type="text" class="adm-input" id="fQ" placeholder="Search"></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
function load() {
    E.loading($('#list'));
    E.api('inventory_ops_api.php', { action: 'rm_list' }, { silent: true }).then(r => { ROWS = r.rows; render(); }).catch(m => E.errorBox($('#list'), m, load));
}
function render() {
    const q = ($('#fQ').val() || '').toLowerCase();
    E.table($('#list'), [
        { label: 'Code', render: x => E.esc(x.code) },
        { label: 'Name', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.name)}</a>${x.category ? '<div class="erp-muted">' + E.esc(x.category) + '</div>' : ''}` },
        { label: 'Stock', num: true, render: x => `${x.low ? '<span class="erp-neg">' : ''}${E.qty(x.stock, x.unit)}${x.low ? ' (low)</span>' : ''}` },
        { label: 'Reorder at', num: true, render: x => x.reorder_level !== null ? E.qty(x.reorder_level, x.unit) : '—' },
        { label: 'Avg cost / unit', num: true, render: x => E.money(x.avg_cost) },
        { label: 'Stock value', num: true, render: x => E.money(x.stock_value) },
        { label: 'Status', render: x => E.badge(x.status) },
        { label: '', render: x => `<button class="adm-icon-btn" data-edit="${x.id}" title="Edit"><i class="fas fa-pen"></i></button>` },
    ], ROWS.filter(x => !q || (x.name + ' ' + x.code + ' ' + (x.category || '')).toLowerCase().includes(q)),
    { empty: 'No raw materials yet', emptyHint: 'Add bulk items you buy by weight/volume, then receive them with a GRN.', icon: 'fa-sack' });
}
$('#fQ').on('input', render);
$('#newBtn').on('click', () => openForm({}));
$('#list').on('click', '[data-edit]', function () { openForm(ROWS.find(x => x.id == $(this).data('edit'))); });
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function openForm(m) {
    const html = `<div class="erp-grid">${E.field('Name *', E.input('mN', m.name || ''), 'span-2')}${E.field('Category', E.input('mC', m.category || ''))}
        ${E.field('Unit *', E.select('mU', ['kg', 'g', 'L', 'ml', 'pcs'].map(u => `<option ${u === (m.unit || 'kg') ? 'selected' : ''}>${u}</option>`).join('')))}
        ${E.field('Reorder level', E.input('mR', m.reorder_level ?? '', 'type="number" min="0" step="any"'))}
        ${E.field('Status', E.select('mS', `<option value="active">Active</option><option value="inactive" ${m.status === 'inactive' ? 'selected' : ''}>Inactive</option>`))}
        ${E.field('Notes', E.textarea('mNo', m.notes || ''), 'span-all')}</div>
        <p class="erp-note">Stock is added only by receiving it on a Goods Receipt (or an approved stock adjustment).</p>`;
    E.form(m.id ? 'Edit ' + m.name : 'New raw material', html, () => E.post('inventory_ops_api.php', { action: 'rm_save', id: m.id || '', name: $('#mN').val(), category: $('#mC').val(), unit: $('#mU').val(),
        reorder_level: $('#mR').val(), status: $('#mS').val(), notes: $('#mNo').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 760 });
}
function openView(id) {
    E.api('inventory_ops_api.php', { action: 'rm_get', id }).then(r => {
        const m = r.record;
        const html = E.kv([['Code', E.esc(m.code)], ['Stock', E.qty(m.stock, m.unit)], ['Avg cost', E.money(m.avg_cost) + ' / ' + E.esc(m.unit)], ['Status', E.badge(m.status)]])
            + E.chain([['Price history', 'purchase_history.php?item=raw_material:' + m.id], ['Repack', 'repacking.php?raw=' + m.id]])
            + '<div class="erp-section-title">Batches</div>' + (m.batches.length ? `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Batch</th><th>Received</th><th>Supplier</th><th class="erp-num">Qty</th><th class="erp-num">Unit cost</th><th>Expiry</th></tr></thead><tbody>` +
              m.batches.map(b => `<tr><td>${E.esc(b.batch_number || '—')}</td><td>${E.date(b.received_date)}</td><td>${E.esc(b.supplier_name || '—')}</td><td class="erp-num">${E.qty(b.qty_received)}</td><td class="erp-num">${E.money(b.unit_cost)}</td><td>${E.date(b.expiry_date)}</td></tr>`).join('') + '</tbody></table></div>' : '<span class="erp-muted">None yet.</span>')
            + '<div class="erp-section-title">Movements</div>' + (m.movements.length ? `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>When</th><th>Type</th><th class="erp-num">Change</th><th class="erp-num">Balance</th><th>Reference</th><th>By</th></tr></thead><tbody>` +
              m.movements.map(v => `<tr><td>${E.date(v.created_at)}</td><td>${E.esc(v.movement_type.replace('_', ' '))}</td><td class="erp-num ${v.quantity < 0 ? 'erp-neg' : 'erp-pos'}">${E.qty(v.quantity)}</td><td class="erp-num">${E.qty(v.new_stock)}</td><td>${E.esc(v.reference_number || '')}</td><td>${E.esc(v.created_by || '')}</td></tr>`).join('') + '</tbody></table></div>' : '<span class="erp-muted">No movements yet.</span>');
        E.view(m.name, html);
    }).catch(() => {});
}
load();
JS
);
