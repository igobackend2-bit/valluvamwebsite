<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Payables & Receivables', 'Accounts payable (what we owe suppliers) and accounts receivable (what customers owe) by age',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="ap">Accounts payable</button><button class="erp-tab" data-tab="ar">Accounts receivable</button></div>
<div class="erp-filters" style="margin-bottom:12px"><label>As of</label> <input type="date" class="adm-input" id="fAsOf"><select class="adm-select" id="fView"><option value="party">By party</option><option value="docs">By document</option></select><input class="adm-input" id="fQ" placeholder="Search"></div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card"><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let TAB = E.param('tab') || 'ap', R = null;
E.tabs($('#tabs'), t => { TAB = t; load(); }, TAB);
$('#fAsOf').val(E.today()).on('change', load); $('#fView').on('change', render); $('#fQ').on('input', render);
const B = [['current', 'Not due'], ['d1_30', '1–30 days'], ['d31_60', '31–60'], ['d61_90', '61–90'], ['d90p', '90+']];
function cols() {
    if ($('#fView').val() === 'party') return [{ label: TAB === 'ap' ? 'Supplier' : 'Customer', key: 'party', render: x => E.esc(x.party) }]
        .concat(B.map(b => ({ label: b[1], key: b[0], num: true, render: x => x[b[0]] ? E.money(x[b[0]]) : '' }))).concat([{ label: 'Total', key: 'total', num: true, render: x => `<strong>${E.money(x.total)}</strong>` }]);
    return [{ label: TAB === 'ap' ? 'Supplier' : 'Customer', key: TAB === 'ap' ? 'supplier_name' : 'customer', render: x => E.esc(TAB === 'ap' ? x.supplier_name : x.customer) + (x.mobile ? `<div class="erp-muted">${E.esc(x.mobile)}</div>` : '') },
        { label: 'Document', key: 'document', render: x => TAB === 'ap' ? (x.id ? `<a class="erp-link" href="purchase_invoices.php?id=${x.id}">${E.esc(x.document)}</a>` : E.esc(x.document)) : (x.link ? `<a class="erp-link" href="${E.esc(x.link)}">${E.esc(x.document)}</a>` : E.esc(x.document)) },
        { label: 'Date', key: 'date', render: x => E.date(x.date) }, { label: 'Due', key: 'due_date', render: x => E.date(x.due_date) },
        { label: 'Days overdue', key: 'days_overdue', num: true, render: x => x.days_overdue ? `<span class="erp-neg">${x.days_overdue}</span>` : '' },
        { label: 'Balance', key: 'balance', num: true, render: x => `<strong>${E.money(x.balance)}</strong>` }];
}
function data() { const q = ($('#fQ').val() || '').toLowerCase(); const rows = $('#fView').val() === 'party' ? R.by_party : R.rows; return rows.filter(x => !q || JSON.stringify(x).toLowerCase().includes(q)); }
function render() { if (R) E.table($('#list'), cols(), data(), { empty: 'Nothing outstanding', icon: 'fa-circle-check' }); }
function load() {
    E.loading($('#list'));
    E.api('accounting_api.php', { action: TAB === 'ap' ? 'ap_aging' : 'ar_aging', as_of: $('#fAsOf').val() }).then(r => {
        R = r;
        $('#kpis').html(E.kpi('Total ' + (TAB === 'ap' ? 'payable' : 'receivable'), E.money(r.total), 'is-primary') + B.map(b => E.kpi(b[1], E.money(r.buckets[b[0]]), b[0] === 'current' ? 'is-neutral' : 'is-amber')).join(''));
        render();
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#csvBtn').on('click', () => R && E.csv(data(), cols(), (TAB === 'ap' ? 'payables' : 'receivables') + '_ageing.csv'));
load();
JS
);
