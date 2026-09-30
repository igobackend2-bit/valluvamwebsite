<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Product Profitability', 'Revenue, cost of goods sold and margin per product — from actual sales and weighted-average cost',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2>By product</h2>
        <div class="erp-filters"><input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <select class="adm-select" id="fShow"><option value="">All sold products</option><option value="low">Low margin (&lt; 15%)</option><option value="loss">Loss-making</option><option value="est">With estimated COGS</option></select>
            <input class="adm-input" id="fQ" placeholder="Product / category"></div></div>
    <div class="adm-card-body"><div id="warn"></div><div id="list"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
$('#fFrom,#fTo').on('change', load); $('#fShow').on('change', render); $('#fQ').on('input', render);
const COLS = [
    { label: 'Product', key: 'product_name', render: x => `<strong>${E.esc(x.product_name)}</strong> <span class="erp-muted">${E.esc(x.pack || '')}</span><div class="erp-muted">${E.esc(x.category || '')}</div>` },
    { label: 'Qty sold', key: 'qty_sold', num: true, render: x => E.qty(x.qty_sold) },
    { label: 'Returns', key: 'returns_qty', num: true, render: x => E.qty(x.returns_qty) },
    { label: 'Net revenue', key: 'net_revenue', num: true, render: x => E.money(x.net_revenue) },
    { label: 'Avg cost', key: 'avg_cost', num: true, render: x => x.cost_missing ? '<span class="adm-badge is-amber">no cost</span>' : E.money(x.avg_cost) },
    { label: 'COGS', key: 'cogs', num: true, render: x => E.money(x.cogs) + (x.cogs_estimated > 0 ? `<div class="erp-muted" title="Billed quantity not deducted from stock, valued at average cost">incl. est. ${E.money(x.cogs_estimated)}</div>` : '') },
    { label: 'Gross profit', key: 'gross_profit', num: true, render: x => `<strong class="${x.gross_profit < 0 ? 'erp-neg' : 'erp-pos'}">${E.money(x.gross_profit)}</strong>` },
    { label: 'Margin', key: 'margin_pct', num: true, render: x => x.margin_pct === null ? '—' : `<span class="adm-badge is-${x.margin_pct < 0 ? 'red' : x.margin_pct < 15 ? 'amber' : 'green'}">${x.margin_pct}%</span>` },
    { label: 'Waste', key: 'waste_value', num: true, render: x => x.waste_qty ? E.qty(x.waste_qty) + ' · ' + E.money(x.waste_value) : '—' },
    { label: 'Stock / value', key: 'stock_value', num: true, render: x => E.qty(x.current_stock) + ' · ' + E.money(x.stock_value) },
];
$('#csvBtn').on('click', () => E.csv(ROWS, COLS.map(c => ({ label: c.label, csv: x => x[c.key] })), 'product_profitability.csv'));
function load() {
    E.loading($('#list'));
    E.api('erp_reports.php', { report: 'profitability', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }, { silent: true }).then(r => { ROWS = r.rows; render(); }).catch(m => E.errorBox($('#list'), m, load));
}
function render() {
    const q = ($('#fQ').val() || '').toLowerCase(), s = $('#fShow').val();
    const rows = ROWS.filter(x => (!q || (x.product_name + ' ' + (x.category || '')).toLowerCase().includes(q)) &&
        (!s || (s === 'low' && x.margin_pct !== null && x.margin_pct < 15) || (s === 'loss' && x.gross_profit < 0) || (s === 'est' && x.cogs_estimated > 0)));
    const sum = k => rows.reduce((a, x) => a + E.num(x[k]), 0);
    const rev = sum('net_revenue'), gp = sum('gross_profit');
    $('#kpis').html(`<div class="adm-stat is-primary"><h3>${E.money(rev)}</h3><p>Net revenue</p></div><div class="adm-stat is-neutral"><h3>${E.money(sum('cogs'))}</h3><p>Cost of goods sold</p></div>
        <div class="adm-stat ${gp < 0 ? 'is-amber' : 'is-green'}"><h3>${E.money(gp)}</h3><p>Gross profit</p></div><div class="adm-stat is-neutral"><h3>${rev > 0 ? (gp / rev * 100).toFixed(2) + '%' : '—'}</h3><p>Gross margin</p></div>`);
    const w = [];
    if (rows.some(x => x.cost_missing)) w.push('Some products have no purchase cost — set an opening cost in Stock Valuation.');
    if (rows.some(x => x.cogs_estimated > 0)) w.push('Some sales were billed but never deducted from stock (e.g. invoices without a Stock Out / manual sales not yet deducted); their COGS is estimated at average cost.');
    $('#warn').html(w.map(x => `<div class="erp-warn">${E.esc(x)}</div>`).join(''));
    E.table($('#list'), COLS, rows, { empty: 'No sales in this period', icon: 'fa-chart-pie' });
}
load();
JS
);
