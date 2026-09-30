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
if (is_file(__DIR__ . '/erp_ext.php')) require_once __DIR__ . '/erp_ext.php';   // FEFO batches, dashboard extras (1 Oct 2026)

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
                'cost_warnings' => $v['items_without_cost']] + erp_dashboard_extra($pdo, $p)]);
        default:
            erp_fail('Unknown report.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'report');
}

/** Extra dashboard figures for the complete ERP (1 Oct 2026). Empty until erp_complete_migration.sql is run. */
function erp_dashboard_extra(PDO $pdo, array $pnl): array {
    try { $pdo->query("SELECT 1 FROM approval_policies LIMIT 1"); } catch (PDOException $e) { return []; }
    require_once __DIR__ . '/erp_ext.php';
    $today = date('Y-m-d'); $m1 = date('Y-m-01');
    $sToday = rep_pnl($pdo, $today, $today);
    $coll = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM accounts_transactions WHERE type = 'payment_received' AND status = 'completed' AND reference_type IN ('invoice','credit_sale','manual_sale') AND date BETWEEN ? AND ?", [$m1, $today])
          + (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM orders WHERE payment_status = 'paid' AND COALESCE(order_status,'') <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?", [$m1, $today])
          + (float)erp_val($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM manual_sales WHERE payment_status = 'paid' AND sales_date BETWEEN ? AND ?", [$m1, $today]);
    $supPay = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?", [$m1, $today]);
    $apr = (int)erp_val($pdo, "SELECT COUNT(*) FROM approval_requests WHERE status IN ('submitted','under_review')");
    fefo_sync($pdo);
    $days = (int)erp_setting($pdo, 'erp_expiry_alert_days', 30); $soon = date('Y-m-d', strtotime("+{$days} days"));
    $exp = 0; foreach (batch_remaining_rows($pdo) as $b) if ($b['remaining'] > 0.0005 && $b['expiry_date'] && $b['expiry_date'] <= $soon) $exp++;
    $billsDue = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_invoices pi WHERE pi.status = 'posted' AND pi.due_date IS NOT NULL AND pi.due_date <= ?
                                    AND pi.grand_total - COALESCE((SELECT SUM(amount) FROM purchase_payments pp WHERE pp.pinv_id = pi.id AND pp.status = 'completed'),0) > 0.005", [date('Y-m-d', strtotime('+7 days'))]);
    $pendPurch = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status IN ('submitted','manager_approved','approved')") + (int)erp_val($pdo, "SELECT COUNT(*) FROM rfqs WHERE status IN ('draft','sent','quoted')");
    return ['sales_today' => $sToday['net_sales'], 'collections' => erp_m($coll), 'supplier_payments' => erp_m($supPay), 'cogs' => $pnl['cogs'], 'pending_approvals' => $apr,
            'expiring_batches' => $exp, 'bills_due' => $billsDue, 'pending_purchases' => $pendPurch];
}
