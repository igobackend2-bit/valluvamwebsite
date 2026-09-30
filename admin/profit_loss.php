<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Profit & Loss', 'Calculated from recorded sales, stock costs and expenses — nothing is typed in by hand',
    '<button class="adm-btn adm-btn-ghost" id="printBtn"><i class="fas fa-print"></i> Print</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2 id="title">Statement</h2>
        <div class="erp-filters"><input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <button class="adm-btn adm-btn-ghost" data-r="month">This month</button><button class="adm-btn adm-btn-ghost" data-r="last">Last month</button><button class="adm-btn adm-btn-ghost" data-r="fy">This FY</button></div></div>
    <div class="adm-card-body"><div id="warn"></div><div id="pl"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
$('#fFrom,#fTo').on('change', load);
$('#printBtn').on('click', () => window.print());
$('[data-r]').on('click', function () {
    const d = new Date(), r = $(this).data('r'), f = x => x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0');
    if (r === 'month') { $('#fFrom').val(E.monthStart()); $('#fTo').val(E.today()); }
    if (r === 'last') { $('#fFrom').val(f(new Date(d.getFullYear(), d.getMonth() - 1, 1))); $('#fTo').val(f(new Date(d.getFullYear(), d.getMonth(), 0))); }
    if (r === 'fy') { const y = d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1; $('#fFrom').val(y + '-04-01'); $('#fTo').val(E.today()); }
    load();
});
function load() {
    const $p = $('#pl'); E.loading($p); $('#warn').html('');
    E.api('erp_reports.php', { report: 'pnl', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }, { silent: true }).then(r => {
        $('#title').text('Statement · ' + E.date(r.from) + ' – ' + E.date(r.to));
        const row = (label, v, cls, link) => `<tr class="${cls || ''}"><td>${link ? `<a class="erp-link" href="${link}">${E.esc(label)}</a>` : E.esc(label)}</td><td class="${v < 0 ? 'erp-neg' : ''}">${E.money(v)}</td></tr>`;
        const ch = r.sales.by_channel, inv = r.inventory;
        $('#warn').html(r.warnings.map(w => `<div class="erp-warn">${E.esc(w)}</div>`).join(''));
        $p.html(`<table class="erp-pl">
            <tr class="head"><td>Sales</td><td></td></tr>
            ${row('Invoices', ch.invoices, 'sub', 'invoices.php')}${row('Manual sales', ch.manual_sales, 'sub', 'manual_sales.php')}${row('Credit sales', ch.credit_sales, 'sub', 'credit_sale.php')}
            ${row('Website orders', ch.website, 'sub', 'orders.php')}${r.sales.website_order_adjustments ? row('Website delivery / order-level adjustments', r.sales.website_order_adjustments, 'sub') : ''}
            ${row('Gross sales', r.sales.gross_sales)}${row('Less: sales returns', -r.sales.sales_returns, 'sub', 'sales_returns.php')}
            ${row('Net sales', r.net_sales, 'total')}
            <tr class="head"><td>Cost of goods sold</td><td></td></tr>
            ${row('Cost of stock sold (weighted average)' + (r.cogs_estimated_part ? ' — incl. ' + E.money(r.cogs_estimated_part) + ' estimated' : ''), -r.cogs, 'sub', 'product_profitability.php')}
            ${row('Gross profit' + (r.gross_margin_pct !== null ? ' (' + r.gross_margin_pct + '%)' : ''), r.gross_profit, 'total')}
            <tr class="head"><td>Operating expenses</td><td></td></tr>
            ${r.operating_expenses.map(e => row(e.category, -e.amount, 'sub', 'expenses.php')).join('') || '<tr class="sub"><td>No expenses recorded</td><td>₹0.00</td></tr>'}
            ${row('Inventory write-offs (waste, adjustments, unrecorded)', -r.inventory_write_offs, 'sub', 'stock_valuation.php')}
            ${row('Total expenses', -(r.operating_expenses_total + r.inventory_write_offs))}
            ${r.other_income.length ? '<tr class="head"><td>Other income</td><td></td></tr>' + r.other_income.map(e => row(e.category, e.amount, 'sub', 'accounts.php')).join('') : ''}
            ${row(r.net_profit >= 0 ? 'Net profit' : 'Net loss', r.net_profit, 'total')}
          </table>
          <div class="erp-section-title">Inventory roll-forward (checks the cost figures)</div>
          <table class="erp-pl">${row('Opening inventory', inv.opening)}${row('+ Purchases received (GRN / Stock In)', inv.purchases, 'sub', 'purchase_history.php')}${row('+ Freight, landed cost & price differences', inv.landed, 'sub', 'purchase_invoices.php')}
            ${row('− Purchase returns', -inv.purchase_returns, 'sub', 'purchase_returns.php')}${row('+ Repacking (packing cost added)', inv.repack_net, 'sub', 'repacking.php')}${row('+ Sales returns put back in stock', inv.sales_returns_restocked, 'sub')}
            ${row('− Cost of stock sold', -inv.cogs_ledger, 'sub')}${row('− Waste', -inv.waste, 'sub', 'waste.php')}${row('± Adjustments (net)', inv.adjustments_net, 'sub', 'stock_adjustments.php')}${row('± Unrecorded stock changes', inv.unrecorded_net, 'sub')}${inv.transfers_in_transit ? row('− Transfers in transit between warehouses', inv.transfers_in_transit, 'sub', 'stock_transfers.php') : ''}
            ${row('Closing inventory', inv.closing, 'total', 'stock_valuation.php')}</table>
          ${r.notes.map(n => `<p class="erp-note">${E.esc(n)}</p>`).join('')}`);
    }).catch(m => E.errorBox($p, m, load));
}
load();
JS
);
