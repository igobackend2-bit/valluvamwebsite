<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Fulfilment', 'Pick → pack → dispatch → deliver log for sales orders and website orders (the orders themselves stay on their own pages)');
?>
<div class="erp-filters" style="margin-bottom:12px"><select class="adm-select" id="fScope"><option value="open">Open orders</option><option value="period">All orders in period</option></select>
    <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
    <select class="adm-select" id="fStage"><option value="">Any stage</option><option value="none">Not started</option><option value="confirmed">Confirmed</option><option value="picked">Picked</option><option value="packed">Packed</option><option value="dispatched">Dispatched</option><option value="delivered">Delivered</option></select>
    <input class="adm-input" id="fQ" placeholder="Order, customer, mobile"></div>
<section class="adm-card"><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
$('#fScope,#fFrom,#fTo,#fStage').on('change', load); $('#fQ').on('input', render);
const NEXT = { none: 'picked', confirmed: 'picked', picked: 'packed', packed: 'dispatched', dispatched: 'delivered' };
function load() { E.loading($('#list')); E.api('sales_ext_api.php', { action: 'ful_list', scope: $('#fScope').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), stage: $('#fStage').val() }).then(r => { ROWS = r.rows; render(); }).catch(m => E.errorBox($('#list'), m, load)); }
function render() {
    const q = ($('#fQ').val() || '').toLowerCase();
    E.table($('#list'), [
        { label: 'Order', render: x => `<a class="erp-link" href="${x.link}">${E.esc(x.number)}</a><div class="erp-muted">${x.source_type === 'sales_order' ? 'Sales order' : 'Website order'}</div>` },
        { label: 'Date', render: x => E.date(x.date) }, { label: 'Customer', render: x => E.esc(x.customer || '') + `<div class="erp-muted">${E.esc(x.mobile || '')}</div>` },
        { label: 'Order status', render: x => E.badge(x.system_status) + (x.payment ? `<div class="erp-muted">${E.esc(x.payment)}</div>` : '') },
        { label: 'Total', num: true, render: x => E.money(x.total) },
        { label: 'Fulfilment', render: x => x.last ? E.badge(x.last.stage) + `<div class="erp-muted">${E.esc(x.last.by_user || '')} · ${E.date(x.last.created_at)} ${E.esc(x.last.tracking_ref || '')}</div>` : '<span class="erp-muted">not started</span>' },
        { label: '', render: (x, i) => { const n = NEXT[x.last ? x.last.stage : 'none']; return (n ? `<button class="adm-btn adm-btn-primary" data-log="${i}" data-st="${n}">Mark ${n}</button> ` : '') + `<button class="adm-btn adm-btn-ghost" data-more="${i}">More</button>`; } },
    ], ROWS.filter(x => !q || (x.number + ' ' + x.customer + ' ' + x.mobile).toLowerCase().includes(q)), { empty: 'No orders', icon: 'fa-boxes-packing' });
}
function log(x, stage) {
    E.form(`${x.number}: ${stage}`, `<div class="erp-grid">${E.field('Tracking / AWB / vehicle', E.input('lT', ''))}${E.field('Notes', E.input('lN', ''))}</div>
        ${stage === 'dispatched' && x.source_type === 'sales_order' ? '<p class="erp-note">Stock is deducted by the Delivery Challan; this only logs the dispatch.</p>' : ''}`,
        () => E.post('sales_ext_api.php', { action: 'ful_log', source_type: x.source_type, source_id: x.id, stage, tracking_ref: $('#lT').val(), notes: $('#lN').val() }, { silent: true }).then(r => { E.toast(r.message); load(); }), { width: 620, confirmText: 'Save' });
}
const list = () => ROWS.filter(x => { const q = ($('#fQ').val() || '').toLowerCase(); return !q || (x.number + ' ' + x.customer + ' ' + x.mobile).toLowerCase().includes(q); });
$('#list').on('click', '[data-log]', function () { log(list()[+$(this).data('log')], $(this).data('st')); });
$('#list').on('click', '[data-more]', function () {
    const x = list()[+$(this).data('more')];
    E.api('sales_ext_api.php', { action: 'ful_history', source_type: x.source_type, source_id: x.id }).then(r => E.view(x.number, '<div id="fh"></div>', ['confirmed', 'picked', 'packed', 'dispatched', 'delivered', 'returned', 'cancelled'].map(s => ({ label: s, run: () => log(x, s) })),
        { didOpen: () => E.table($('#fh'), [{ label: 'When', render: h => E.date(h.created_at) }, { label: 'Stage', render: h => E.badge(h.stage) }, { label: 'Tracking', render: h => E.esc(h.tracking_ref || '') }, { label: 'Notes', render: h => E.esc(h.notes || '') }, { label: 'By', render: h => E.esc(h.by_user || '') }], r.rows, { empty: 'Nothing logged yet' }) }));
});
load();
JS
);
