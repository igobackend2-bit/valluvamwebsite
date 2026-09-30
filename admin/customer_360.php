<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Customer 360', 'All sales, payments, returns, credit notes and outstanding for one customer — across invoices, manual & credit sales and website orders');
?>
<div class="erp-filters" style="margin-bottom:12px"><input class="adm-input" id="fQ" placeholder="Search name or mobile" style="min-width:280px"></div>
<section class="adm-card" id="sCard"><div class="adm-card-body" id="list"></div></section>
<div id="detail" style="display:none">
    <div class="adm-card-head" style="padding:0 0 10px"><h2 id="cName"></h2><button class="adm-btn adm-btn-ghost" id="backBtn"><i class="fas fa-arrow-left"></i> All customers</button></div>
    <div id="cInfo" class="erp-muted" style="margin-bottom:12px"></div>
    <div class="erp-kpis" id="kpis"></div>
    <div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="ledger">Ledger</button><button class="erp-tab" data-tab="docs">Sales</button><button class="erp-tab" data-tab="pay">Payments</button><button class="erp-tab" data-tab="ret">Returns & credit notes</button></div>
    <section class="adm-card"><div class="adm-card-body" id="pane"></div></section>
</div>
<?php erp_page_end(<<<'JS'
const E = ERP;
let D = null, TAB = 'ledger';
E.tabs($('#tabs'), t => { TAB = t; render(); });
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(search, 300); });
$('#backBtn').on('click', () => { $('#detail').hide(); $('#sCard').show(); });
function search() {
    E.loading($('#list'));
    E.api('sales_ext_api.php', { action: 'cust_search', q: $('#fQ').val() }).then(r => E.table($('#list'), [
        { label: 'Customer', render: x => `<a class="erp-link" data-key="${E.esc(x.key)}">${E.esc(x.name || '—')}</a>` }, { label: 'Mobile', render: x => E.esc(x.mobile || '') }, { label: 'E-mail', render: x => E.esc(x.email || '') },
        { label: 'Sales', num: true, render: x => x.documents }, { label: 'Total bought', num: true, render: x => E.money(x.total) }, { label: 'Last purchase', render: x => E.date(x.last) },
    ], r.rows, { empty: 'No customers found', icon: 'fa-users' })).catch(m => E.errorBox($('#list'), m, search));
}
$('#list').on('click', '[data-key]', function () { open($(this).data('key')); });
function open(key) {
    E.api('sales_ext_api.php', { action: 'cust_get', key }).then(r => {
        D = r; $('#sCard').hide(); $('#detail').show();
        const c = r.customer, k = r.kpis;
        $('#cName').text(c.names.join(' / ')); $('#cInfo').html([c.mobile, c.emails.join(', '), c.addresses[0]].filter(Boolean).map(E.esc).join(' · '));
        $('#kpis').html(E.kpi('Total sales', E.money(k.total_sales), 'is-primary') + E.kpi('Paid', E.money(k.total_paid)) + E.kpi('Outstanding', E.money(k.outstanding), k.outstanding > 0.005 ? 'is-amber' : 'is-green') +
            E.kpi('Credit sales', E.money(k.credit_sales)) + E.kpi('Returns', E.money(k.returns)) + E.kpi('Refunds', E.money(k.refunds)) + E.kpi('Customer since', E.date(k.first_purchase)) + E.kpi('Last purchase', E.date(k.last_purchase)));
        render();
    }).catch(() => {});
}
function render() {
    if (!D) return; const $p = $('#pane');
    if (TAB === 'ledger') E.table($p, [{ label: 'Date', render: x => E.date(x.date) }, { label: 'Entry', render: x => E.esc(x.type) }, { label: 'Reference', render: x => E.esc(x.ref) },
        { label: 'Billed (Dr)', num: true, render: x => x.debit ? E.money(x.debit) : '' }, { label: 'Paid / credited (Cr)', num: true, render: x => x.credit ? E.money(x.credit) : '' }, { label: 'Balance', num: true, render: x => `<strong>${E.money(x.balance)}</strong>` }], D.ledger);
    if (TAB === 'docs') E.table($p, [{ label: 'Sale', render: x => `<a class="erp-link" href="${x.link}">${E.esc(x.number)}</a><div class="erp-muted">${E.esc(x.type.replace('_', ' '))}</div>` }, { label: 'Date', render: x => E.date(x.date) },
        { label: 'Total', num: true, render: x => E.money(x.total) }, { label: 'Paid', num: true, render: x => E.money(x.paid) }, { label: 'Status', render: x => E.badge(String(x.status).split(' ')[0], x.status) },
        { label: '', render: x => `<a class="adm-btn adm-btn-ghost" href="transaction_trace.php?type=${x.type}&id=${x.id}">Trace</a>` }], D.documents);
    if (TAB === 'pay') E.table($p, [{ label: 'Txn', render: x => E.esc(x.transaction_id) }, { label: 'Date', render: x => E.date(x.date) }, { label: 'For', render: x => E.esc(x.reference_number) }, { label: 'Mode', render: x => E.esc(x.payment_mode) }, { label: 'Amount', num: true, render: x => E.money(x.amount) }],
        D.payments, { empty: 'No separate payments recorded (paid-at-sale and online payments show in the ledger)' });
    if (TAB === 'ret') E.table($p, [{ label: 'Return', render: x => `<a class="erp-link" href="sales_returns.php?id=${x.id}">${E.esc(x.return_number)}</a>` }, { label: 'Date', render: x => E.date(x.return_date) }, { label: 'Against', render: x => E.esc(x.source_number) },
        { label: 'Goods value', num: true, render: x => E.money(x.total_value) }, { label: 'Refund / credit', num: true, render: x => E.money(x.refund_amount) }, { label: 'Settlement', render: x => E.esc(x.settlement) }, { label: 'Credit note', render: x => E.esc(x.cn_number || '') }], D.returns, { empty: 'No returns' });
}
search();
JS
);
