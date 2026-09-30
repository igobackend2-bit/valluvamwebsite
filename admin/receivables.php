<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Customer Receivables', 'What customers still owe — invoices, credit sales and unpaid manual sales, less credit notes',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-kpis" id="kpis"></div>
<div class="erp-tabs"><button class="erp-tab active" data-tab="cust">By customer</button><button class="erp-tab" data-tab="docs">By document</button></div>
<section class="adm-card"><div class="adm-card-head"><h2 id="title">By customer</h2><input class="adm-input" id="fQ" placeholder="Customer / mobile"></div><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let D = null, TAB = 'cust';
const LINK = { invoice: 'invoices.php', credit_sale: 'credit_sale.php', manual_sale: 'manual_sales.php' };
const CC = [{ label: 'Customer', key: 'customer_name' }, { label: 'Mobile', key: 'customer_mobile', render: x => E.esc(x.customer_mobile || '—') }, { label: 'Open documents', key: 'documents', num: true },
            { label: 'Oldest', key: 'oldest', render: x => E.date(x.oldest) }, { label: 'Outstanding', key: 'outstanding', num: true, render: x => `<strong>${E.money(x.outstanding)}</strong>` }];
const DC = [{ label: 'Document', key: 'number', render: x => `<a class="erp-link" href="${LINK[x.source]}">${E.esc(x.number)}</a> <span class="erp-muted">${E.esc(x.source.replace('_', ' '))}</span>` },
            { label: 'Date', key: 'date', render: x => E.date(x.date) }, { label: 'Customer', key: 'customer_name' }, { label: 'Total', key: 'total', num: true, render: x => E.money(x.total) },
            { label: 'Paid', key: 'paid', num: true, render: x => E.money(x.paid) }, { label: 'Credit notes', key: 'credit_notes', num: true, render: x => E.money(x.credit_notes) },
            { label: 'Outstanding', key: 'outstanding', num: true, render: x => `<strong>${E.money(x.outstanding)}</strong>` + (x.note ? `<div class="erp-muted">${E.esc(x.note)}</div>` : '') }];
$('.erp-tab').on('click', function () { $('.erp-tab').removeClass('active'); $(this).addClass('active'); TAB = $(this).data('tab'); render(); });
$('#fQ').on('input', render);
$('#csvBtn').on('click', () => E.csv(rows(), (TAB === 'cust' ? CC : DC).map(c => ({ label: c.label, csv: x => x[c.key] })), 'receivables.csv'));
const rows = () => { const q = ($('#fQ').val() || '').toLowerCase(); return (TAB === 'cust' ? D.customers : D.documents).filter(x => !q || ((x.customer_name || '') + ' ' + (x.customer_mobile || '')).toLowerCase().includes(q)); };
function render() { if (!D) return; $('#title').text(TAB === 'cust' ? 'By customer' : 'By document'); E.table($('#list'), TAB === 'cust' ? CC : DC, rows(), { empty: 'Nothing outstanding', icon: 'fa-circle-check' }); }
E.loading($('#list'));
E.api('erp_reports.php', { report: 'receivables' }, { silent: true }).then(r => {
    D = r;
    const age = d => Math.floor((Date.now() - new Date(d)) / 86400000);
    const b = [0, 0, 0]; r.documents.forEach(x => { const a = age(x.date); b[a <= 30 ? 0 : a <= 60 ? 1 : 2] += x.outstanding; });
    $('#kpis').html(`<div class="adm-stat is-amber"><h3>${E.money(r.total)}</h3><p>Total receivable</p></div><div class="adm-stat is-neutral"><h3>${E.money(b[0])}</h3><p>0–30 days</p></div>
        <div class="adm-stat is-neutral"><h3>${E.money(b[1])}</h3><p>31–60 days</p></div><div class="adm-stat is-amber"><h3>${E.money(b[2])}</h3><p>Over 60 days</p></div>`);
    render();
}).catch(m => E.errorBox($('#list'), m));
JS
);
