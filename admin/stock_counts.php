<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Stock Counts', 'Physical counting per warehouse: system quantities are frozen, you enter what you count, differences post after approval',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New stock count</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Stock counts</h2>
        <div class="erp-filters"><select class="adm-select" id="fStatus"><option value="">All status</option><option value="draft">Draft</option><option value="submitted">Submitted</option><option value="posted">Posted</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></select>
            <select class="adm-select" id="fWh"><option value="">All warehouses</option></select></div></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let WH = [];
E.warehouses().then(w => { WH = w; $('#fWh').append(w.map(x => `<option value="${x.id}">${E.esc(x.name)}</option>`).join('')); load(); if (E.param('id')) openView(E.param('id')); });
$('#fStatus,#fWh').on('change', load);
function load() {
    E.loading($('#list'));
    E.api('warehouse_api.php', { action: 'cnt_list', status: $('#fStatus').val(), warehouse_id: $('#fWh').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Count', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.count_number)}</a>` }, { label: 'Date', render: x => E.date(x.count_date) },
        { label: 'Warehouse', render: x => E.esc(x.warehouse_name) }, { label: 'Scope', render: x => E.esc(x.scope || '') },
        { label: 'Counted', num: true, render: x => `${x.counted_count} / ${x.item_count}` }, { label: 'Difference value', num: true, render: x => `<span class="${x.variance_value < 0 ? 'erp-neg' : ''}">${E.money(x.variance_value)}</span>` },
        { label: 'Status', render: x => E.badge(x.status) },
    ], r.rows, { empty: 'No stock counts yet', icon: 'fa-list-check' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
$('#newBtn').on('click', () => {
    E.form('New stock count', `<div class="erp-grid">${E.field('Warehouse *', E.select('cWh', E.options(WH, 'id', w => w.name, 1, false)))}${E.field('Count date *', E.input('cDate', E.today(), 'type="date"'))}
        ${E.field('Only category (optional)', E.input('cCat', '', 'placeholder="e.g. nuts"'))}${E.field('Include products with zero stock', '<label><input type="checkbox" id="cZero"> yes</label>')}
        ${E.field('Notes', E.textarea('cNotes', ''), 'span-all')}</div><p class="erp-note">The system quantities are taken now. Count, enter the figures and submit; the differences are posted only when an approver approves.</p>`,
        () => E.post('warehouse_api.php', { action: 'cnt_create', warehouse_id: $('#cWh').val(), count_date: $('#cDate').val(), category: $('#cCat').val(), include_zero: $('#cZero').is(':checked') ? 1 : 0, notes: $('#cNotes').val() }, { silent: true })
            .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); }), { width: 700, confirmText: 'Create' });
});
function openView(id) {
    E.api('warehouse_api.php', { action: 'cnt_get', id }).then(r => {
        const c = r.record, edit = ['draft', 'rejected'].includes(c.status);
        const html = E.kv([['Warehouse', E.esc(c.warehouse_name)], ['Date', E.date(c.count_date)], ['Scope', E.esc(c.scope || '')], ['Status', E.badge(c.status)], c.posted_by ? ['Posted', E.esc(c.posted_by) + ' · ' + E.date(c.posted_at)] : null])
            + (c.moved_since.length ? `<div class="erp-warn">Stock moved after this count started: ${c.moved_since.map(m => E.esc(m.item_name) + ' ' + (m.change > 0 ? '+' : '') + E.qty(m.change)).join(', ')}. Differences are against the frozen quantity.</div>` : '')
            + `<div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>System (frozen)</th><th>Counted</th><th>Difference</th><th>Value</th><th>Reason</th></tr></thead><tbody>`
            + c.items.map(i => `<tr data-id="${i.id}" data-sys="${i.system_qty}" data-cost="${i.unit_cost}"><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.system_qty)}</td>
                <td class="w-num">${edit ? `<input class="adm-input" type="number" min="0" step="any" data-f="c" value="${i.counted_qty ?? ''}">` : E.qty(i.counted_qty)}</td>
                <td class="erp-num" data-f="v">${i.counted_qty === null ? '' : E.qty(i.variance)}</td><td class="erp-num" data-f="val">${i.counted_qty === null ? '' : E.money(i.variance_value)}</td>
                <td>${edit ? `<input class="adm-input" data-f="r" value="${E.esc(i.reason || '')}">` : E.esc(i.reason || '')}</td></tr>`).join('')
            + '</tbody></table></div><div id="vDocs"></div>';
        const items = () => $('.swal2-popup tr[data-id]').map(function () { return { id: $(this).data('id'), counted_qty: $(this).find('[data-f=c]').val(), reason: $(this).find('[data-f=r]').val() }; }).get();
        const send = a => () => E.post('warehouse_api.php', { action: a, id: c.id, items: items() }).then(x => { E.toast(x.message); load(); openView(c.id); }).catch(() => {});
        E.view(c.count_number, html, [
            edit && { label: 'Save', icon: 'fa-floppy-disk', run: send('cnt_save') },
            edit && { label: 'Submit for approval', cls: 'adm-btn-primary', icon: 'fa-paper-plane', run: send('cnt_submit') },
            c.status === 'submitted' && { label: 'Open in Approvals', icon: 'fa-stamp', run: () => location.href = 'approvals.php?module=stock_count' },
            ['draft', 'rejected', 'submitted'].includes(c.status) && { label: 'Cancel', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + c.count_number + '?', '', { danger: true, reason: 'Reason' }).then(reason => E.post('warehouse_api.php', { action: 'cnt_cancel', id: c.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
        ], { didOpen: p => { E.docs($('#vDocs'), 'stock_count', c.id, 'OTHER');
            $(p).on('input', '[data-f=c]', function () { const $r = $(this).closest('tr'); if (this.value === '') { $r.find('[data-f=v],[data-f=val]').text(''); return; }
                const v = E.num(this.value) - E.num($r.data('sys')); $r.find('[data-f=v]').text(E.qty(v)); $r.find('[data-f=val]').text(E.money(v * E.num($r.data('cost')))); }); } });
    }).catch(() => {});
}
JS
);
