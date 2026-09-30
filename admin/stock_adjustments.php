<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Stock Adjustments', 'Physical counts, damage, missing or found stock — applied only after approval, always with a reason',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-scale-balanced"></i> New adjustment</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Adjustments</h2><select class="adm-select" id="fStatus"><option value="">All</option><option value="pending">Pending approval</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ITEMS = [], WH = [];
const TYPES = [['physical_count', 'Physical count (enter counted qty)'], ['increase', 'Stock increase'], ['decrease', 'Stock decrease'], ['damage', 'Damage'], ['missing', 'Missing stock'], ['found', 'Found stock']];
Promise.all([E.items(), E.warehouses()]).then(([i, w]) => { ITEMS = i; WH = w; load(); });
$('#fStatus').on('change', load);
$('#newBtn').on('click', openForm);
function load() {
    E.loading($('#list'));
    E.api('inventory_ops_api.php', { action: 'adj_list', status: $('#fStatus').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'No.', render: x => E.esc(x.adj_number) }, { label: 'Date', render: x => E.date(x.adj_date) }, { label: 'Item', render: x => E.esc(x.item_name) },
        { label: 'Type', render: x => E.esc(x.adjustment_type.replace('_', ' ')) },
        { label: 'System → counted', render: x => x.adjustment_type === 'physical_count' ? `${E.qty(x.system_qty)} → ${E.qty(x.counted_qty)}` : '—' },
        { label: 'Change', num: true, render: x => `<span class="${x.quantity < 0 ? 'erp-neg' : 'erp-pos'}">${x.quantity > 0 ? '+' : ''}${E.qty(x.quantity)}</span>` },
        { label: 'Reason', render: x => E.esc(x.reason) }, { label: 'Requested', render: x => E.esc(x.requested_by || '') },
        { label: 'Status', render: x => E.badge(x.status) + (x.approved_by ? `<div class="erp-muted">${E.esc(x.approved_by)}</div>` : '') },
        { label: '', render: x => x.status === 'pending' ? `<button class="adm-btn adm-btn-primary" data-ok="${x.id}">Approve</button> <button class="adm-btn adm-btn-ghost" data-no="${x.id}">Reject</button>` : '' },
    ], r.rows, { empty: 'No adjustments', icon: 'fa-scale-balanced' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-ok],[data-no]', function () {
    const ok = $(this).is('[data-ok]'), id = $(this).data(ok ? 'ok' : 'no');
    E.confirmAction(ok ? 'Approve and apply this adjustment?' : 'Reject this adjustment?', ok ? 'Stock changes now and is written to the stock ledger.' : '', { danger: !ok })
     .then(() => E.post('inventory_ops_api.php', { action: ok ? 'adj_approve' : 'adj_reject', id })).then(r => { E.toast(r.message); load(); }).catch(() => {});
});
function openForm() {
    const html = `<div class="erp-grid">${E.field('Item *', E.select('aI', E.itemOptions(ITEMS)), 'span-2')}
        ${E.field('Type *', E.select('aT', TYPES.map(t => `<option value="${t[0]}">${t[1]}</option>`).join('')))}
        ${E.field('Counted quantity', E.input('aC', '', 'type="number" min="0" step="any"'), 'fc')}
        ${E.field('Quantity', E.input('aQ', '', 'type="number" min="0" step="any"'), 'fq')}
        ${E.field('Date *', E.input('aD', E.today(), 'type="date"'))}
        ${E.field('Warehouse', E.select('aW', E.options(WH, 'id', w => w.name, 1, false)))}
        ${E.field('Reason *', E.input('aR', ''), 'span-all')}</div><div id="aInfo" class="erp-note"></div>`;
    E.form('New stock adjustment', html, () => {
        const [t, id] = String($('#aI').val() || ':').split(':');
        return E.post('inventory_ops_api.php', { action: 'adj_save', item_type: t, item_id: id, adjustment_type: $('#aT').val(), counted_qty: $('#aC').val(), quantity: $('#aQ').val(),
            adj_date: $('#aD').val(), warehouse_id: $('#aW').val(), reason: $('#aR').val() }, { silent: true }).then(r => { E.toast(r.message); load(); });
    }, { width: 780, confirmText: 'Request adjustment', didOpen: () => {
        const sync = () => { const pc = $('#aT').val() === 'physical_count'; $('.fc').toggle(pc); $('.fq').toggle(!pc);
            const [t, id] = String($('#aI').val() || ':').split(':'); const it = ITEMS.find(i => i.item_type === t && String(i.item_id) === id);
            $('#aInfo').text(it ? 'Current system stock: ' + E.qty(it.stock, it.unit) : ''); };
        $('#aT,#aI').on('change', sync); sync();
    }});
}
JS
);
