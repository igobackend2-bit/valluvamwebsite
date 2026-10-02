<?php
// Executive stock handover — prepared → checked → acknowledged, with a frozen generated copy (added 2 Oct 2026)
require_once __DIR__ . '/includes/erp_page.php';
require_once __DIR__ . '/includes/stock_flow_common.php';
erp_page_start('Executive Handover', 'Stock, location stock, sales by customer, returns, purchases, damage and audits for a period — prepared, checked by a second person, acknowledged by the executive',
    '<button class="adm-btn adm-btn-primary" id="newBtn" style="display:none"><i class="fas fa-plus"></i> Prepare handover</button>');
?>
<section class="adm-card"><div class="adm-card-head"><h2>Handovers</h2></div><div class="adm-card-body" id="list"></div></section>
<style>@media print { .adm-sidebar, .adm-topbar, .erp-actions, .swal2-actions, #vDocs, .adm-mobile-bar { display: none !important; } .swal2-popup { width: 100% !important; box-shadow: none !important; } }
.sf-pack h4{margin:14px 0 6px;font-size:14px}.sf-pack table{font-size:12px}</style>
<?php erp_page_end(sf_common_js() . <<<'JS'
const E = ERP;
const fail = m => { if (m !== 'cancelled') E.alertError(m); };
SF.meta().then(() => { if (SF.can('stockflow.handover')) $('#newBtn').show(); load(); if (E.param('id')) openView(E.param('id')); }).catch(m => E.errorBox($('#list'), m));
function load() {
    E.loading($('#list'));
    E.api(SF.API, { action: 'hnd_list' }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Handover', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.handover_number)}</a>` }, { label: 'Period', render: x => E.date(x.period_from) + ' – ' + E.date(x.period_to) },
        { label: 'Location', render: x => E.esc(x.warehouse_name || 'All') }, { label: 'Executive', render: x => E.esc(x.executive_name) }, { label: 'Handover', render: x => E.date(x.handover_date) + ' ' + E.esc(String(x.handover_time).slice(0, 5)) },
        { label: 'Prepared', render: x => E.esc(x.prepared_by) }, { label: 'Checked', render: x => E.esc(x.checked_by || '—') }, { label: 'Acknowledged', render: x => x.ack_by ? E.esc(x.ack_by) + ' · ' + E.date(x.ack_at) : '—' },
        { label: 'Proofs', num: true, render: x => x.proofs }, { label: 'Status', render: x => SF.badge(x.status) },
    ], r.rows, { empty: 'No handovers yet', icon: 'fa-handshake' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
function pack(s) {
    const t = (cols, rows) => rows && rows.length ? `<div class="adm-table-wrap"><table class="erp-lines"><thead><tr>${cols.map(c => `<th class="${c[2] ? 'erp-num' : ''}">${c[0]}</th>`).join('')}</tr></thead><tbody>${rows.map(r => '<tr>' + cols.map(c => `<td class="${c[2] ? 'erp-num' : ''}">${c[1](r)}</td>`).join('') + '</tr>').join('')}</tbody></table></div>` : '<p class="erp-muted">None in this period.</p>';
    return `<div class="sf-pack"><p class="erp-muted">Generated ${E.esc(s.generated_at)} by ${E.esc(s.generated_by)} · period ${E.date(s.period[0])} – ${E.date(s.period[1])}</p>
        <h4>Overall stock</h4>${t([['Product', r => E.esc(r.product_name + (r.pack ? ' (' + r.pack + ')' : ''))], ['Category', r => E.esc(r.category || '')], ['Stock', r => E.qty(r.stock), 1]], s.overall_stock)}
        <h4>Location-wise stock</h4>${t([['Location', r => E.esc(r.warehouse)], ['Items', r => r.items, 1], ['Available', r => E.qty(r.available), 1], ['Damaged', r => E.qty(r.damaged), 1], ['Rejected', r => E.qty(r.rejected), 1], ['Expired', r => E.qty(r.expired), 1]], s.location_summary)}
        <h4>Customer-wise sales (total ${E.money(s.sales_total)})</h4>${t([['Customer', r => E.esc(r.customer)], ['Documents', r => r.documents, 1], ['Amount', r => E.money(r.amount), 1]], s.sales_by_customer)}
        <h4>Sales returns</h4>${t([['Return', r => E.esc(r.return_number)], ['Date', r => E.date(r.return_date)], ['Sale', r => E.esc(r.source_number)], ['Customer', r => E.esc(r.customer_name || '')], ['Value', r => E.money(r.total_value), 1], ['Refund / credit', r => E.money(r.refund_amount), 1]], s.sales_returns)}
        <h4>Purchases (goods received)</h4>${t([['GRN', r => E.esc(r.grn_number)], ['Date', r => E.date(r.received_date)], ['Supplier', r => E.esc(r.supplier_name)], ['PO', r => E.esc(r.po_number || '')], ['Accepted', r => E.qty(r.accepted), 1], ['Rejected', r => E.qty(r.rejected), 1], ['Value', r => E.money(r.value), 1]], s.purchases)}
        <h4>Purchase returns</h4>${t([['Return', r => E.esc(r.return_number)], ['Date', r => E.date(r.return_date)], ['Supplier', r => E.esc(r.supplier_name)], ['Reason', r => E.esc(r.reason)], ['Value', r => E.money(r.total_value), 1]], s.purchase_returns)}
        <h4>Damage</h4>${t([['Number', r => E.esc(r.damage_number)], ['Date', r => E.date(r.damage_date)], ['Item', r => E.esc(r.item_name)], ['Location', r => E.esc(r.warehouse)], ['Qty', r => E.qty(r.quantity), 1], ['Weight', r => SF.kg(r.weight_kg), 1], ['Reason', r => E.esc(r.reason)], ['Status', r => SF.badge(r.status)]], s.damage)}
        <h4>Stock audits</h4>${t([['Audit', r => E.esc(r.audit_number)], ['Month', r => E.esc(r.audit_month)], ['Location', r => E.esc(r.warehouse)], ['Auditor', r => E.esc(r.auditor_name)], ['Items', r => r.lines_total, 1], ['With difference', r => r.lines_with_difference, 1], ['Status', r => SF.badge(r.status)]], s.stock_audits)}</div>`;
}
$('#newBtn').on('click', () => E.form('Prepare executive handover', `<div class="erp-grid">${E.field('Period from *', E.input('hF', E.monthStart(), 'type="date"'))}${E.field('Period to *', E.input('hT', E.today(), 'type="date"'))}
    ${E.field('Location', E.select('hW', SF.whOptions('', 'All locations')))}${E.field('Executive receiving it *', E.input('hE', ''))}${E.field('Handover date', E.input('hD', E.today(), 'type="date"'))}${E.field('Handover time', E.input('hTm', SF.now(), 'type="time"'))}
    ${E.field('Remarks', E.input('hR', ''), 'span-all')}</div><button type="button" class="adm-btn adm-btn-ghost" id="hPrev"><i class="fas fa-eye"></i> Preview</button><div id="hPack"></div>
    <p class="sf-note">The figures are frozen into the handover when it is prepared (generated copy). Another person checks it; then the executive acknowledges it.</p>`,
    () => E.post(SF.API, { action: 'hnd_create', period_from: SF.v('hF'), period_to: SF.v('hT'), warehouse_id: SF.v('hW'), executive_name: SF.v('hE'), handover_date: SF.v('hD'), handover_time: SF.v('hTm'), remarks: SF.v('hR') }, { silent: true })
        .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); }),
    { width: 1100, confirmText: 'Prepare', didOpen: p => $(p).on('click', '#hPrev', () => E.api(SF.API, { action: 'hnd_preview', period_from: SF.v('hF'), period_to: SF.v('hT'), warehouse_id: SF.v('hW') }).then(r => $(p).find('#hPack').html(pack(r.snapshot))).catch(() => {})) }));
function openView(id) {
    E.api(SF.API, { action: 'hnd_get', id }).then(r => {
        const h = r.record, acts = [];
        const run = (a, extra) => E.post(SF.API, Object.assign({ action: a, id: h.id }, extra || {}), { silent: true }).then(x => { E.toast(x.message); load(); openView(h.id); }).catch(fail);
        if (h.status === 'prepared' && SF.can('stockflow.handover')) acts.push({ label: 'Mark checked', cls: 'adm-btn-primary', icon: 'fa-check-double', run: () => run('hnd_check') });
        if (h.status === 'checked' && SF.can('stockflow.handover_ack')) acts.push({ label: 'Acknowledge', cls: 'adm-btn-primary', icon: 'fa-signature', run: () => E.confirmAction('Acknowledge ' + h.handover_number + '?', 'Your login and the time are recorded.', { reason: 'Remarks (optional)', optional: true }).then(v => run('hnd_ack', { remarks: v })).catch(fail) });
        if (['prepared', 'checked'].includes(h.status) && SF.can('stockflow.handover')) acts.push({ label: 'Cancel', icon: 'fa-ban', run: () => E.confirmAction('Cancel?', '', { danger: true, reason: 'Reason' }).then(v => run('hnd_cancel', { reason: v })).catch(fail) });
        acts.push({ label: 'Print copy', icon: 'fa-print', run: () => window.print() });
        E.view(h.handover_number, E.kv([['Period', E.date(h.period_from) + ' – ' + E.date(h.period_to)], ['Location', E.esc(h.warehouse_name || 'All')], ['Executive', E.esc(h.executive_name)], ['Handover', E.date(h.handover_date) + ' ' + String(h.handover_time).slice(0, 5)],
            ['Prepared by', E.esc(h.prepared_by) + ' · ' + E.date(h.prepared_at)], ['Checked by', h.checked_by ? E.esc(h.checked_by) + ' · ' + E.date(h.checked_at) : '—'], ['Acknowledged', h.ack_by ? E.esc(h.ack_by) + ' · ' + E.date(h.ack_at) + ' ' + String(h.ack_at).slice(11, 16) : '—'], ['Status', SF.badge(h.status)]])
            + (h.ack_remarks ? `<p><strong>Acknowledgement:</strong> ${E.esc(h.ack_remarks)}</p>` : '') + pack(h.snapshot) + '<div id="vDocs"></div>', acts, { width: 1200, didOpen: () => E.docs($('#vDocs'), 'stock_handover', h.id, 'HANDOVER_COPY') });
    }).catch(() => {});
}
JS
);
