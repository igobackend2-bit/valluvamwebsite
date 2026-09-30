<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('GST Summary', 'Output GST on sales vs input GST on purchases and expenses, month by month — a working summary for filing',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export documents CSV</button>');
?>
<div class="erp-filters" style="margin-bottom:12px"><input type="date" class="adm-input" id="fFrom"> to <input type="date" class="adm-input" id="fTo"></div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card"><div class="adm-card-head"><h2>By month</h2></div><div class="adm-card-body" id="months"></div></section>
<section class="adm-card"><div class="adm-card-head"><h2>Documents</h2><select class="adm-select" id="fKind"><option value="">All</option><option value="output">Output (sales)</option><option value="input">Input (purchases)</option></select></div><div class="adm-card-body" id="docs"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let R = null;
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
$('#fFrom,#fTo').on('change', load); $('#fKind').on('change', docs);
const DC = [{ label: 'Date', key: 'd', render: x => E.date(x.d) }, { label: 'Type', key: 'type' }, { label: 'Number', key: 'n' }, { label: 'Party', key: 'party' }, { label: 'GSTIN', key: 'gstin', render: x => E.esc(x.gstin || '') },
            { label: 'Taxable', key: 'taxable', num: true, render: x => E.money(x.taxable) }, { label: 'GST', key: 'tax', num: true, render: x => E.money(x.tax) }, { label: 'Kind', key: 'kind' }];
function docs() { const k = $('#fKind').val(); E.table($('#docs'), DC, R.documents.filter(d => !k || d.kind === k), { empty: 'No documents' }); }
function load() {
    E.api('accounting_api.php', { action: 'gst', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }).then(r => {
        R = r;
        $('#kpis').html(E.kpi('Output GST (sales)', E.money(r.output_tax), 'is-primary') + E.kpi('Input GST (purchases & expenses)', E.money(r.input_tax)) + E.kpi(r.net_payable >= 0 ? 'Net GST payable' : 'Net input credit', E.money(Math.abs(r.net_payable)), 'is-amber'));
        E.table($('#months'), [{ label: 'Month', key: 'month' }, { label: 'Sales taxable', num: true, render: x => E.money(x.output_taxable) }, { label: 'Output GST', num: true, render: x => E.money(x.output_tax) },
            { label: 'Purchases taxable', num: true, render: x => E.money(x.input_taxable) }, { label: 'Input GST', num: true, render: x => E.money(x.input_tax) }, { label: 'Net payable', num: true, render: x => `<strong>${E.money(x.net_payable)}</strong>` }], r.months, { empty: 'No taxable documents' });
        $('#months').append(r.notes.map(n => `<p class="erp-note">${E.esc(n)}</p>`).join(''));
        docs();
    });
}
$('#csvBtn').on('click', () => R && E.csv(R.documents, DC, 'gst_documents.csv'));
load();
JS
);
