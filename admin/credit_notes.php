<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Credit Notes', 'One numbered credit note per sales return (refunded, or credit against the customer\'s balance)',
    '<button class="adm-btn adm-btn-ghost" id="missBtn"><i class="fas fa-file-circle-plus"></i> Issue for returns without a note</button>');
?>
<section class="adm-card"><div class="adm-card-head"><h2>Credit notes</h2><div class="erp-filters"><select class="adm-select" id="fStatus"><option value="">All</option><option value="issued">Credit on account</option><option value="refunded">Refunded</option><option value="cancelled">Cancelled</option></select><input class="adm-input" id="fQ" placeholder="Number, customer, mobile"></div></div>
    <div class="adm-card-body"><div id="warn"></div><div id="list"></div></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
$('#fStatus').on('change', load); let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
function load() {
    E.api('sales_ext_api.php', { action: 'cn_list', status: $('#fStatus').val(), q: $('#fQ').val() }).then(r => {
        $('#warn').html(r.returns_without_note ? `<div class="erp-warn">${r.returns_without_note} sales return(s) have no credit note yet.</div>` : '');
        E.table($('#list'), [{ label: 'Credit note', render: x => `<a class="erp-link" href="print_erp.php?type=cn&id=${x.id}" target="_blank">${E.esc(x.cn_number)}</a>` }, { label: 'Date', render: x => E.date(x.cn_date) },
            { label: 'Customer', render: x => E.esc(x.customer_name || '') + `<div class="erp-muted">${E.esc(x.customer_mobile || '')}</div>` }, { label: 'Against', render: x => E.esc(x.source_number || '') },
            { label: 'Return', render: x => `<a class="erp-link" href="sales_returns.php?id=${x.sales_return_id}">${E.esc(x.return_number)}</a>` }, { label: 'Amount', num: true, render: x => E.money(x.total) }, { label: 'Status', render: x => E.badge(x.status) }],
            r.rows, { empty: 'No credit notes yet', icon: 'fa-file-invoice' });
    });
}
$('#missBtn').on('click', () => E.post('sales_ext_api.php', { action: 'cn_issue_missing' }).then(r => { E.toast(r.message); load(); }));
load();
JS
);
