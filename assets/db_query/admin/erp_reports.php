<?php
// ============================================================================
// Purchase / inventory / profit reports (added 30 Sep 2026) — read-only.
// Every figure is calculated from recorded transactions (no stored totals,
// no hard-coded values). Costs come from costing_engine.php.
//   ?report=dashboard | pnl | profitability | valuation | batches | supplier_summary |
//           supplier_ledger | purchases | price_history | pending | receivables | variance | item_trace
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/costing_engine.php';
require_once __DIR__ . '/erp_report_lib.php';

$report = (string) erp_input('report', '');
$perm = in_array($report, ['pnl', 'profitability', 'valuation', 'variance', 'dashboard', 'item_trace', 'receivables'], true) ? 'pnl.view' : 'purchase.view';
erp_guard($pdo, $perm);

$from = erp_date_safe(erp_input('date_from')) ?: date('Y-m-01');
$to = erp_date_safe(erp_input('date_to')) ?: date('Y-m-d');
if ($to < $from) erp_fail('"To" date is before "From" date.');

try {
    switch ($report) {
        case 'pnl':            erp_out(['status' => 'success', 'from' => $from, 'to' => $to] + rep_pnl($pdo, $from, $to));
        case 'profitability':  erp_out(['status' => 'success', 'from' => $from, 'to' => $to, 'rows' => rep_profitability($pdo, $from, $to)]);
        case 'valuation':      erp_out(['status' => 'success', 'as_of' => $to] + rep_valuation($pdo, $to));
        case 'batches':        erp_out(['status' => 'success', 'rows' => rep_batches($pdo, (int)erp_input('days', 60))]);
        case 'supplier_summary': erp_out(['status' => 'success', 'rows' => rep_supplier_summary($pdo)]);
        case 'supplier_ledger':  erp_out(['status' => 'success'] + rep_supplier_ledger($pdo, (int)erp_input('supplier_id')));
        case 'purchases':      erp_out(['status' => 'success', 'from' => $from, 'to' => $to] + rep_purchases($pdo, $from, $to));
        case 'price_history':  erp_out(['status' => 'success', 'rows' => rep_price_history($pdo)]);
        case 'pending':        erp_out(['status' => 'success'] + rep_pending($pdo));
        case 'receivables':    erp_out(['status' => 'success'] + rep_receivables($pdo));
        case 'variance':       erp_out(['status' => 'success', 'rows' => rep_variance($pdo)]);
        case 'item_trace':     erp_out(['status' => 'success'] + rep_item_trace($pdo, (string)erp_input('item_type', 'product'), (int)erp_input('item_id')));
        case 'dashboard':
            $p = rep_pnl($pdo, date('Y-m-01'), date('Y-m-d'));
            $v = rep_valuation($pdo, date('Y-m-d'));
            $r = rep_receivables($pdo);
            $payable = array_sum(array_column(rep_supplier_summary($pdo), 'outstanding'));
            $pend = rep_pending($pdo);
            try { $low = (int)erp_val($pdo, "SELECT COUNT(*) FROM product_details WHERE stock <= COALESCE(reorder_level, min_stock_level, 5)"); }
            catch (PDOException $e) { $low = (int)erp_val($pdo, "SELECT COUNT(*) FROM product_details WHERE stock <= 5"); }
            erp_out(['status' => 'success', 'period' => date('M Y'), 'kpi' => [
                'net_sales' => $p['net_sales'], 'purchases' => $p['inventory']['purchases'], 'inventory_value' => $v['total_value'],
                'receivables' => $r['total'], 'payables' => erp_m($payable), 'gross_profit' => $p['gross_profit'], 'net_profit' => $p['net_profit'],
                'expenses' => $p['operating_expenses_total'], 'low_stock' => $low, 'pending_pos' => count($pend['purchase_orders']),
                'cost_warnings' => $v['items_without_cost']]]);
        default:
            erp_fail('Unknown report.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'report');
}
