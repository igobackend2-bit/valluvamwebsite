<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Warehouse Locations', 'Optional zone → rack → shelf → bin codes, and where each item is kept',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New location</button> <button class="adm-btn adm-btn-ghost" id="assignBtn"><i class="fas fa-location-dot"></i> Assign item</button>');
?>
<div class="erp-filters" style="margin-bottom:12px"><select class="adm-select" id="fWh"><option value="">All warehouses</option></select></div>
<section class="adm-card"><div class="adm-card-head"><h2>Locations</h2></div><div class="adm-card-body" id="list"></div></section>
<section class="adm-card"><div class="adm-card-head"><h2>Item locations</h2></div><div class="adm-card-body" id="assign"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let WH = [], LOC = [], ITEMS = [];
Promise.all([E.warehouses(), E.items()]).then(([w, i]) => { WH = w; ITEMS = i; $('#fWh').append(w.map(x => `<option value="${x.id}">${E.esc(x.name)}</option>`).join('')); load(); });
$('#fWh').on('change', load);
function load() {
    E.api('warehouse_api.php', { action: 'loc_list', warehouse_id: $('#fWh').val() }).then(r => {
        LOC = r.rows;
        const byId = {}; LOC.forEach(l => byId[l.id] = l);
        const path = l => { const p = []; let c = l; while (c) { p.unshift(c.code); c = byId[c.parent_id]; } return p.join(' › '); };
        E.table($('#list'), [{ label: 'Location', render: l => `<strong>${E.esc(path(l))}</strong><div class="erp-muted">${E.esc(l.name || '')}</div>` }, { label: 'Level', render: l => E.esc(l.level) },
            { label: 'Warehouse', render: l => E.esc(l.warehouse_name) }, { label: 'Items', num: true, render: l => l.items }, { label: 'Status', render: l => E.badge(l.status) },
            { label: '', render: l => `<button class="adm-btn adm-btn-ghost" data-edit="${l.id}">Edit</button> <button class="adm-btn adm-btn-ghost" data-st="${l.id}" data-to="${l.status === 'active' ? 'inactive' : 'active'}">${l.status === 'active' ? 'Deactivate' : 'Activate'}</button>` }],
            LOC, { empty: 'No locations yet (optional)', emptyHint: 'Create zones, racks, shelves and bins if you want item-level locations.', icon: 'fa-location-dot' });
        E.table($('#assign'), [{ label: 'Item', render: a => E.esc(a.item_name) }, { label: 'Warehouse', render: a => E.esc(a.warehouse_name) }, { label: 'Location', render: a => E.esc(a.location_code) }], r.assignments, { empty: 'No items assigned' });
    });
}
function form(l) {
    l = l || {};
    E.form(l.id ? 'Edit location' : 'New location', `<div class="erp-grid">${E.field('Warehouse *', E.select('lWh', E.options(WH, 'id', w => w.name, l.warehouse_id || $('#fWh').val() || 1, false)))}
        ${E.field('Level *', E.select('lLv', ['zone', 'rack', 'shelf', 'bin'].map(x => `<option ${l.level === x ? 'selected' : ''}>${x}</option>`).join('')))}
        ${E.field('Inside (parent)', E.select('lPar', '<option value="">— top level —</option>' + LOC.filter(x => x.id !== l.id).map(x => `<option value="${x.id}" ${String(x.id) === String(l.parent_id) ? 'selected' : ''}>${E.esc(x.code)} (${x.level}, ${E.esc(x.warehouse_name)})</option>`).join('')))}
        ${E.field('Code *', E.input('lCode', l.code || '', 'placeholder="Z1-R2-S3-B4"'))}${E.field('Name', E.input('lName', l.name || ''))}</div>`,
        () => E.post('warehouse_api.php', { action: 'loc_save', id: l.id || '', warehouse_id: $('#lWh').val(), level: $('#lLv').val(), parent_id: $('#lPar').val(), code: $('#lCode').val(), name: $('#lName').val() }, { silent: true }).then(x => { E.toast(x.message); load(); }), { width: 720 });
}
$('#newBtn').on('click', () => form());
$('#list').on('click', '[data-edit]', function () { form(LOC.find(l => l.id == $(this).data('edit'))); });
$('#list').on('click', '[data-st]', function () { E.post('warehouse_api.php', { action: 'loc_status', id: $(this).data('st'), status: $(this).data('to') }).then(() => load()); });
$('#assignBtn').on('click', () => E.form('Where is this item kept?', `<div class="erp-grid">${E.field('Item *', E.select('aIt', E.itemOptions(ITEMS)), 'span-2')}
    ${E.field('Location *', E.select('aLoc', LOC.filter(l => l.status === 'active').map(l => `<option value="${l.id}">${E.esc(l.code)} · ${E.esc(l.warehouse_name)}</option>`).join('')), 'span-2')}</div>`,
    () => { const [t, id] = String($('#aIt').val() || ':').split(':'); if (!id) return Promise.reject('Choose an item');
        return E.post('warehouse_api.php', { action: 'item_loc_assign', item_type: t, item_id: id, location_id: $('#aLoc').val() }, { silent: true }).then(x => { E.toast(x.message); load(); }); }, { width: 720 }));
JS
);
