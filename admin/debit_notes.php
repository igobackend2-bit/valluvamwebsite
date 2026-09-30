<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Debit Notes', 'One numbered debit note per purchase return — the amount the supplier owes back or credits',
    '<button class="adm-btn adm-btn-ghost" id="missBtn"><i class="fas fa-file-circle-plus"></i> Issue for returns without a note</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Debit notes</h2>
        <div class="erp-filters"><select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="issued">Issued</option><option value="adjusted">Adjusted</option><option value="refunded">Refunded</option><option value="cancelled">Cancelled</option></select></div></div>
    <div class="adm-card-body"><div id="warn"></div><div id="list"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
E.suppliers().then(s => { $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join('')); load(); if (E.param('id')) openView(E.param('id')); });
$('#fSupplier,#fStatus').on('change', load);
function load() {
    E.loading($('#list'));
    E.api('procurement_api.php', { action: 'dn_list', supplier_id: $('#fSupplier').val(), status: $('#fStatus').val() }, { silent: true }).then(r => {
        $('#warn').html(r.returns_without_note ? `<div class="erp-warn">${r.returns_without_note} posted purchase return(s) have no debit note yet.</div>` : '');
        E.table($('#list'), [
            { label: 'Debit note', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.dn_number)}</a>` },
            { label: 'Date', render: x => E.date(x.dn_date) }, { label: 'Supplier', render: x => E.esc(x.supplier_name) },
            { label: 'Return', render: x => `<a class="erp-link" href="purchase_returns.php?id=${x.purchase_return_id}">${E.esc(x.return_number)}</a>` },
            { label: 'Bill', render: x => E.esc(x.supplier_invoice_no || '—') }, { label: 'Settlement', render: x => E.esc(String(x.settlement).replace('_', ' ')) },
            { label: 'Amount', num: true, render: x => E.money(x.total) }, { label: 'Status', render: x => E.badge(x.status) },
            { label: '', render: x => `<a class="adm-icon-btn" href="print_erp.php?type=dn&id=${x.id}" target="_blank" title="Print"><i class="fas fa-print"></i></a>` },
        ], r.rows, { empty: 'No debit notes yet', icon: 'fa-file-invoice' });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#missBtn').on('click', () => E.post('procurement_api.php', { action: 'dn_issue_missing' }).then(r => { E.toast(r.message); load(); }));
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
function openView(id) {
    E.api('procurement_api.php', { action: 'dn_get', id }).then(res => {
        const d = res.record;
        const html = E.kv([['Supplier', E.esc(d.supplier_name)], ['GSTIN', E.esc(d.gst_number || '—')], ['Date', E.date(d.dn_date)], ['Return', E.esc(d.return_number)], ['GRN', E.esc(d.grn_number || '—')], ['Bill', E.esc(d.supplier_invoice_no || '—')], ['Status', E.badge(d.status)]])
            + `<p>Reason: ${E.esc(d.reason)}</p><div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Rate</th><th class="erp-num">Value</th></tr></thead><tbody>`
            + d.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.money(i.line_value)}</td></tr>`).join('')
            + `</tbody></table></div><div class="erp-totals"><span>Total <strong>${E.money(d.total)}</strong></span></div><div id="vDocs"></div>`;
        const set = st => () => E.post('procurement_api.php', { action: 'dn_status', id: d.id, status: st }).then(x => { E.toast(x.message); load(); openView(d.id); });
        E.view(d.dn_number, html, [
            { label: 'Print', icon: 'fa-print', run: () => window.open('print_erp.php?type=dn&id=' + d.id, '_blank') },
            d.status === 'issued' && { label: 'Mark adjusted against a bill', icon: 'fa-check', run: set('adjusted') },
            d.status === 'issued' && { label: 'Mark refunded', icon: 'fa-money-bill', run: set('refunded') },
        ], { didOpen: () => E.docs($('#vDocs'), 'debit_note', d.id, 'CREDIT_DEBIT_NOTE') });
    }).catch(() => {});
}
JS
);
