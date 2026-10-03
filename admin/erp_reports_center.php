<?php
require_once __DIR__ . '/includes/erp_page.php';
// FIX (3 Oct 2026): show only the reports this user can run — the Audit report needs audit_logs.view, the others reports.erp.
// A team with neither goes back to the dashboard instead of a page where every report says "You do not have permission".
$vp_rep_erp = vp_admin_can('reports.erp'); $vp_rep_audit = vp_admin_can('audit_logs.view');
if (!$vp_rep_erp && !$vp_rep_audit) { header('Location: index.php'); exit; }
erp_page_start('ERP Reports', 'Sales, COGS, category profitability, customer payments, GRN, QC, purchase returns and audit — with filters and CSV export',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-filters" style="margin-bottom:12px">
    <span id="vpRepPerm" hidden data-erp="<?= $vp_rep_erp ? 1 : 0 ?>" data-audit="<?= $vp_rep_audit ? 1 : 0 ?>"></span><?php /* FIX (3 Oct 2026) */ ?>
    <select class="adm-select" id="rep" style="min-width:240px">
        <option value="sales_report">Sales report</option><option value="cogs_report">COGS report</option><option value="category_profitability">Category profitability</option>
        <option value="customer_payments">Customer payment report</option><option value="grn_report">GRN report</option><option value="qc_report">QC report</option>
        <option value="purchase_return_report">Purchase return report</option><option value="erp_audit">Audit report</option>
    </select>
    <input type="date" class="adm-input" id="fFrom"> to <input type="date" class="adm-input" id="fTo">
    <select class="adm-select" id="fCh" data-for="sales_report cogs_report"><option value="">All channels</option><option value="website">Website</option><option value="offline">Offline</option><option value="b2b">B2B</option></select>
    <select class="adm-select" id="fType" data-for="sales_report"><option value="">All sale types</option><option value="invoice">Invoices</option><option value="manual_sale">Manual sales</option><option value="credit_sale">Credit sales</option><option value="website_order">Website orders</option></select>
    <input class="adm-input" id="fCat" placeholder="Category" data-for="cogs_report" style="width:130px">
    <select class="adm-select" id="fSup" data-for="grn_report"><option value="">All suppliers</option></select>
    <select class="adm-select" id="fMod" data-for="erp_audit"><option value="">All modules</option></select><select class="adm-select" id="fAct" data-for="erp_audit"><option value="">All actions</option></select><input class="adm-input" id="fUser" placeholder="User" data-for="erp_audit" style="width:120px">
    <input class="adm-input" id="fQ" placeholder="Search in results">
</div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card"><div class="adm-card-body"><div id="list"></div><div id="pager"></div>
    <p class="erp-note">Also see: <a class="erp-link" href="purchase_history.php">Purchase report</a> · <a class="erp-link" href="supplier_ledger.php">Supplier outstanding</a> · <a class="erp-link" href="warehouse_stock.php">Stock by warehouse</a> ·
    <a class="erp-link" href="stock_movements.php">Stock movement</a> · <a class="erp-link" href="stock_valuation.php">Stock valuation</a> · <a class="erp-link" href="batches.php">Batch & expiry</a> · <a class="erp-link" href="product_profitability.php">Product profitability</a> ·
    <a class="erp-link" href="sales_channels.php">Channel profitability</a> · <a class="erp-link" href="profit_loss.php">Profit & Loss</a> · <a class="erp-link" href="general_ledger.php">General ledger</a> · <a class="erp-link" href="ap_ar_aging.php">Payables / receivables</a> ·
    <a class="erp-link" href="financial_statements.php?tab=cf">Cash flow</a> · <a class="erp-link" href="expenses.php">Expense report</a> · <a class="erp-link" href="reports.php">Existing reports</a></p></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [], PAGE = 1, COLS = [];
// FIX (3 Oct 2026): remove the reports this user cannot run (see the PHP check at the top)
(function () { const f = $('#vpRepPerm'); if (String(f.data('audit')) !== '1') $('#rep option[value="erp_audit"]').remove(); if (String(f.data('erp')) !== '1') $('#rep option').not('[value="erp_audit"]').remove(); })();
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
E.suppliers().then(s => $('#fSup').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join('')));
if (E.param('r')) $('#rep').val(E.param('r'));
if (!$('#rep').val()) $('#rep').prop('selectedIndex', 0);   // FIX (3 Oct 2026): a link to a report this user cannot run opens the first one they can
$('#rep,#fFrom,#fTo,#fCh,#fType,#fSup,#fMod,#fAct').on('change', () => { PAGE = 1; load(); });
let tq; $('#fCat,#fUser').on('input', () => { clearTimeout(tq); tq = setTimeout(() => { PAGE = 1; load(); }, 400); });
$('#fQ').on('input', render);
$('#csvBtn').on('click', () => E.csv(filtered(), COLS, $('#rep').val() + '.csv'));
const it = x => E.esc(x.item_name || '');
const DEF = {
    sales_report: [{ label: 'Date', key: 'date', render: x => E.date(x.date) }, { label: 'Type', key: 'type' }, { label: 'Number', key: 'number', render: x => `<a class="erp-link" href="transaction_trace.php?type=${x.type}&id=${x.id}">${E.esc(x.number)}</a>` },
        { label: 'Customer', key: 'customer' }, { label: 'Mobile', key: 'mobile' }, { label: 'Channel', key: 'channel' }, { label: 'Net (ex GST)', key: 'revenue', num: true, render: x => E.money(x.revenue) }, { label: 'GST', key: 'tax', num: true, render: x => E.money(x.tax) },
        { label: 'Total', key: 'total', num: true, render: x => E.money(x.total) }, { label: 'Status', key: 'status' }],
    cogs_report: [{ label: 'Date', key: 'date', render: x => E.date(x.date) }, { label: 'Item', key: 'item_name', render: it }, { label: 'Category', key: 'item_category' }, { label: 'What', key: 'kind' }, { label: 'Reference', key: 'reference' },
        { label: 'Channel', key: 'channel' }, { label: 'Qty', key: 'quantity', num: true, render: x => E.qty(x.quantity) }, { label: 'Unit cost', key: 'unit_cost', num: true, render: x => x.unit_cost === null ? '' : E.money(x.unit_cost) }, { label: 'COGS', key: 'cogs', num: true, render: x => E.money(x.cogs) }],
    category_profitability: [{ label: 'Category', key: 'category' }, { label: 'Products', key: 'products', num: true }, { label: 'Qty sold', key: 'qty_sold', num: true, render: x => E.qty(x.qty_sold) }, { label: 'Net revenue', key: 'net_revenue', num: true, render: x => E.money(x.net_revenue) },
        { label: 'Returns', key: 'returns_value', num: true, render: x => E.money(x.returns_value) }, { label: 'COGS', key: 'cogs', num: true, render: x => E.money(x.cogs) }, { label: 'Gross profit', key: 'gross_profit', num: true, render: x => `<strong>${E.money(x.gross_profit)}</strong>` },
        { label: 'Margin', key: 'margin_pct', num: true, render: x => x.margin_pct === null ? '—' : x.margin_pct + '%' }, { label: 'Waste', key: 'waste_value', num: true, render: x => E.money(x.waste_value) }, { label: 'Stock value', key: 'stock_value', num: true, render: x => E.money(x.stock_value) }],
    customer_payments: [{ label: 'Date', key: 'date', render: x => E.date(x.date) }, { label: 'Number', key: 'number' }, { label: 'Customer', key: 'customer' }, { label: 'For', key: 'reference_number', render: x => E.esc(x.reference_type + ' ' + x.reference_number) },
        { label: 'Mode', key: 'payment_mode' }, { label: 'Source', key: 'source' }, { label: 'Amount', key: 'amount', num: true, render: x => E.money(x.amount) }],
    grn_report: [{ label: 'Date', key: 'received_date', render: x => E.date(x.received_date) }, { label: 'GRN', key: 'grn_number', render: x => `<a class="erp-link" href="goods_receipts.php?id=${x.grn_id}">${E.esc(x.grn_number)}</a>` }, { label: 'Status', key: 'status' }, { label: 'Supplier', key: 'supplier_name' },
        { label: 'Warehouse', key: 'warehouse_name' }, { label: 'PO', key: 'po_number' }, { label: 'Item', key: 'item_name', render: it }, { label: 'Ordered', key: 'ordered_qty', num: true, render: x => E.qty(x.ordered_qty) }, { label: 'Received', key: 'received_qty', num: true, render: x => E.qty(x.received_qty) },
        { label: 'Accepted', key: 'accepted_qty', num: true, render: x => E.qty(x.accepted_qty) }, { label: 'Rejected', key: 'rejected_qty', num: true, render: x => E.qty(x.rejected_qty) }, { label: 'Rate', key: 'rate', num: true, render: x => E.money(x.rate) },
        { label: 'Value', key: 'accepted_value', num: true, render: x => E.money(x.accepted_value) }, { label: 'Batch', key: 'batch_number' }, { label: 'Expiry', key: 'expiry_date', render: x => E.date(x.expiry_date) }, { label: 'QC', key: 'qc_status' }],
    qc_report: [{ label: 'QC', key: 'qc_number', render: x => `<a class="erp-link" href="quality_checks.php?id=${x.qc_id}">${E.esc(x.qc_number)}</a>` }, { label: 'Status', key: 'status' }, { label: 'Inspected', key: 'inspection_date', render: x => E.date(x.inspection_date) + ' ' + E.esc(x.inspected_by || '') },
        { label: 'GRN', key: 'grn_number' }, { label: 'Supplier', key: 'supplier_name' }, { label: 'Item', key: 'item_name', render: it }, { label: 'Batch', key: 'batch_number' }, { label: 'Received', key: 'received_qty', num: true, render: x => E.qty(x.received_qty) },
        { label: 'Accepted', key: 'accepted_qty', num: true, render: x => E.qty(x.accepted_qty) }, { label: 'Rejected', key: 'rejected_qty', num: true, render: x => E.qty(x.rejected_qty) }, { label: 'Damaged', key: 'damaged_qty', num: true, render: x => E.qty(x.damaged_qty) }, { label: 'Result', key: 'result' }, { label: 'Reason', key: 'rejection_reason' }],
    purchase_return_report: [{ label: 'Date', key: 'return_date', render: x => E.date(x.return_date) }, { label: 'Return', key: 'return_number', render: x => `<a class="erp-link" href="purchase_returns.php?id=${x.return_id}">${E.esc(x.return_number)}</a>` }, { label: 'Debit note', key: 'dn_number' },
        { label: 'Supplier', key: 'supplier_name' }, { label: 'GRN', key: 'grn_number' }, { label: 'Item', key: 'item_name', render: it }, { label: 'Qty', key: 'quantity', num: true, render: x => E.qty(x.quantity) }, { label: 'Rate', key: 'rate', num: true, render: x => E.money(x.rate) },
        { label: 'Value', key: 'line_value', num: true, render: x => E.money(x.line_value) }, { label: 'Settlement', key: 'settlement' }, { label: 'Reason', key: 'reason' }],
    erp_audit: [{ label: 'When', key: 'created_at', render: x => E.esc(x.created_at) }, { label: 'User', key: 'username' }, { label: 'Action', key: 'action' }, { label: 'Module', key: 'module' }, { label: 'Record', key: 'record_id' },
        { label: 'Before', key: 'old_value', render: x => `<div class="erp-muted" style="max-width:320px;overflow-wrap:anywhere">${E.esc(x.old_value || '')}</div>` }, { label: 'After', key: 'new_value', render: x => `<div class="erp-muted" style="max-width:320px;overflow-wrap:anywhere">${E.esc(x.new_value || '')}</div>` }],
};
function filtered() { const q = ($('#fQ').val() || '').toLowerCase(); return ROWS.filter(x => !q || JSON.stringify(x).toLowerCase().includes(q)); }
function render() { E.table($('#list'), COLS, filtered(), { empty: 'Nothing for these filters', icon: 'fa-chart-column' }); }
function load() {
    const rep = $('#rep').val(); COLS = DEF[rep];
    $('[data-for]').each(function () { $(this).toggle(String($(this).data('for')).split(' ').includes(rep)); });
    E.loading($('#list')); $('#kpis').html(''); $('#pager').html('');
    E.api('sales_ext_api.php', { action: rep, date_from: $('#fFrom').val(), date_to: $('#fTo').val(), channel: $('#fCh').val(), type: $('#fType').val(), category: $('#fCat').val(), supplier_id: $('#fSup').val(),
                                  module: $('#fMod').val(), audit_action: $('#fAct').val(), user: $('#fUser').val(), page: PAGE, per_page: 100 }, { silent: true }).then(r => {
        ROWS = r.rows; render();
        if (rep === 'sales_report') $('#kpis').html(E.kpi('Net sales (ex GST)', E.money(r.totals.revenue), 'is-primary') + E.kpi('GST', E.money(r.totals.tax)) + E.kpi('Total billed', E.money(r.totals.total)) + E.kpi('Documents', ROWS.length));
        if (rep === 'cogs_report') $('#kpis').html(E.kpi('COGS from stock movements', E.money(r.total), 'is-primary') + E.kpi('Estimated (billed, not deducted)', E.money(r.estimated_undeducted)));
        if (rep === 'customer_payments') $('#kpis').html(E.kpi('Collected', E.money(r.total), 'is-primary') + Object.keys(r.by_mode).map(m => E.kpi(m, E.money(r.by_mode[m]))).join(''));
        if (rep === 'erp_audit') {
            if ($('#fMod option').length === 1) { $('#fMod').append(r.modules.map(m => `<option>${E.esc(m)}</option>`).join('')); $('#fAct').append(r.actions.map(a => `<option>${E.esc(a)}</option>`).join('')); }
            E.pager($('#pager'), r.total, PAGE, 100, p => { PAGE = p; load(); });
        }
    }).catch(m => E.errorBox($('#list'), m, load));
}
load();
JS
);
