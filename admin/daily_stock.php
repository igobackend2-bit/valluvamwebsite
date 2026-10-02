<?php
// Daily stock — opening + in − out ± adjustments = closing, per location, quantity and weight (added 2 Oct 2026)
require_once __DIR__ . '/includes/erp_page.php';
require_once __DIR__ . '/includes/stock_flow_common.php';
erp_page_start('Daily Stock', 'Opening + purchase stock in + sales returns + transfers in − sales − stock out − purchase returns − damage − transfers out ± approved adjustments = closing (from the stock ledger)',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> CSV</button> <button class="adm-btn adm-btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Daily stock</h2>
        <div class="erp-filters"><label class="erp-muted">From</label><input type="date" class="adm-input" id="fFrom"><label class="erp-muted">To</label><input type="date" class="adm-input" id="fTo">
            <select class="adm-select" id="fWh"><option value="">All locations</option></select><input class="adm-input" id="fQ" placeholder="Filter item">
            <label><input type="checkbox" id="fW"> show weight</label></div></div>
    <div class="sf-kpis" id="kpis" style="padding:12px 16px 0"></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(sf_common_js() . <<<'JS'
const E = ERP;
let ROWS = [];
$('#fFrom,#fTo').val(E.today());
SF.meta().then(() => { $('#fWh').append(SF.whOptions('', false)); load(); }).catch(m => E.errorBox($('#list'), m));
$('#fFrom,#fTo,#fWh').on('change', load);
$('#fQ,#fW').on('input change', render);
function load() {
    E.loading($('#list'));
    E.api(SF.API, { action: 'daily', date: $('#fFrom').val(), date_to: $('#fTo').val(), warehouse_id: $('#fWh').val() }, { silent: true }).then(r => {
        ROWS = r.rows; const t = r.totals;
        $('#kpis').html([['Opening', t.opening], ['In', t.opening_entry + t.purchase_in + t.sales_return + t.transfer_in + t.stock_in], ['Out', t.sales + t.stock_out + t.purchase_return + t.damage + t.transfer_out], ['Adjustment', t.adjustment], ['Closing', t.closing]]
            .map(k => E.kpi(k[0], E.qty(k[1]), k[0] === 'Closing' ? 'is-green' : 'is-neutral')).join(''));
        render();
    }).catch(m => E.errorBox($('#list'), m, load));
}
const Q = v => v ? SF.diff(v) : '<span class="erp-muted">0</span>';
function render() {
    const q = String($('#fQ').val() || '').toLowerCase(), w = $('#fW').is(':checked');
    const rows = ROWS.filter(r => !q || String(r.item_name).toLowerCase().includes(q));
    const cols = [{ label: 'Location', render: x => E.esc(x.warehouse_name) }, { label: 'Item', render: x => E.esc(x.item_name) }, { label: 'Opening', num: true, render: x => E.qty(x.opening) },
        { label: 'Purchase in', num: true, render: x => Q(x.purchase_in) }, { label: 'Sales return', num: true, render: x => Q(x.sales_return) }, { label: 'Transfer in', num: true, render: x => Q(x.transfer_in) },
        { label: 'Other in', num: true, render: x => Q(x.stock_in + x.opening_entry) }, { label: 'Sales', num: true, render: x => Q(x.sales) }, { label: 'Stock out', num: true, render: x => Q(x.stock_out) },
        { label: 'Purchase return', num: true, render: x => Q(x.purchase_return) }, { label: 'Damage', num: true, render: x => Q(x.damage) }, { label: 'Transfer out', num: true, render: x => Q(x.transfer_out) },
        { label: 'Adjustment', num: true, render: x => Q(x.adjustment) }, { label: 'Closing', num: true, render: x => '<strong>' + E.qty(x.closing) + '</strong>' }, { label: 'In damaged stock', num: true, render: x => x.damaged_closing ? E.qty(x.damaged_closing) : '—' }];
    if (w) cols.push({ label: 'Opening wt', num: true, render: x => SF.kg(x.w_opening) }, { label: 'In wt', num: true, render: x => SF.kg(x.w_in_total) }, { label: 'Out wt', num: true, render: x => SF.kg(x.w_out_total) },
                     { label: 'Adj wt', num: true, render: x => SF.kg(x.w_adjustment) }, { label: 'Closing wt', num: true, render: x => '<strong>' + SF.kg(x.w_closing) + '</strong>' });
    E.table($('#list'), cols, rows, { empty: 'No stock movement or balance for this selection', icon: 'fa-calendar-day' });
}
$('#csvBtn').on('click', () => E.csv(ROWS, ['warehouse_name', 'item_name', 'opening', 'purchase_in', 'sales_return', 'transfer_in', 'stock_in', 'opening_entry', 'sales', 'stock_out', 'purchase_return', 'damage', 'transfer_out', 'adjustment', 'closing', 'damaged_closing', 'w_opening', 'w_in_total', 'w_out_total', 'w_adjustment', 'w_closing']
    .map(k => ({ label: k.replace(/_/g, ' '), key: k })), `daily_stock_${$('#fFrom').val()}_${$('#fTo').val()}.csv`));
JS
);
