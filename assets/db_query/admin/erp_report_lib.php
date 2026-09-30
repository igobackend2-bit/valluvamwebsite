<?php
// Report calculations shared by erp_reports.php and the existing get_report.php
// (supplier payable / customer outstanding). Needs erp_helper.php + costing_engine.php.

function erp_date_safe($v): ?string {
    $v = trim((string)$v);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}
function tbl_exists(PDO $pdo, string $t): bool {
    static $c = [];
    if (!isset($c[$t])) { try { $pdo->query("SELECT 1 FROM `$t` LIMIT 1"); $c[$t] = true; } catch (PDOException $e) { $c[$t] = false; } }
    return $c[$t];
}

/** Net (ex-tax) sales per product per channel in a period, from the existing sales tables. */
function rep_sales(PDO $pdo, string $from, string $to): array {
    $out = [];   // product_id => [qty, revenue, channels => [..]]
    $add = function ($pid, $ch, $q, $v) use (&$out) {
        $pid = (int)$pid;
        if (!isset($out[$pid])) $out[$pid] = ['qty' => 0.0, 'revenue' => 0.0, 'channels' => []];
        $out[$pid]['qty'] += (float)$q; $out[$pid]['revenue'] += (float)$v;
        $out[$pid]['channels'][$ch] = ($out[$pid]['channels'][$ch] ?? 0) + (float)$v;
    };
    $sets = [
        'invoices' => "SELECT ii.product_id, SUM(ii.quantity) q, SUM(ii.rate*ii.quantity - ii.discount) v FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                       WHERE i.status NOT IN ('draft','cancelled') AND i.invoice_date BETWEEN ? AND ? GROUP BY ii.product_id",
        'manual_sales' => "SELECT mi.product_id, SUM(mi.quantity) q, SUM(mi.rate*mi.quantity - mi.discount) v FROM manual_sale_items mi JOIN manual_sales m ON m.id = mi.manual_sale_id
                       WHERE m.sales_date BETWEEN ? AND ? GROUP BY mi.product_id",
        'credit_sales' => "SELECT ci.product_id, SUM(ci.quantity) q, SUM(ci.rate*ci.quantity - ci.discount) v FROM credit_sale_items ci JOIN credit_sales c ON c.id = ci.credit_sale_id
                       WHERE c.sale_date BETWEEN ? AND ? GROUP BY ci.product_id",
        'website' => "SELECT oi.product_id, SUM(oi.quantity) q, SUM(oi.price*oi.quantity) v FROM order_items oi JOIN orders o ON o.id = oi.order_id
                       WHERE COALESCE(o.order_status,'ordered') <> 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY oi.product_id",
    ];
    $tables = ['invoices' => 'invoices', 'manual_sales' => 'manual_sales', 'credit_sales' => 'credit_sales', 'website' => 'orders'];
    foreach ($sets as $ch => $sql) {
        if (!tbl_exists($pdo, $tables[$ch])) continue;
        foreach (erp_rows($pdo, $sql, [$from, $to]) as $r) $add($r['product_id'], $ch, $r['q'], $r['v']);
    }
    return $out;
}

/** Sales returns per product in a period. */
function rep_sales_returns(PDO $pdo, string $from, string $to): array {
    $out = [];
    foreach (erp_rows($pdo, "SELECT i.product_id, SUM(i.quantity) q, SUM(i.restock_qty) rq, SUM(i.damaged_qty) dq, SUM(i.line_value) v FROM sales_return_items i
                             JOIN sales_returns r ON r.id = i.return_id WHERE r.status = 'posted' AND r.return_date BETWEEN ? AND ? GROUP BY i.product_id", [$from, $to]) as $r)
        $out[(int)$r['product_id']] = ['qty' => (float)$r['q'], 'restock' => (float)$r['rq'], 'damaged' => (float)$r['dq'], 'value' => (float)$r['v']];
    return $out;
}

/**
 * Per-product profitability. COGS = cost of ledger sale outflows (weighted
 * average at the time) + landed costs on already-sold stock − cost of good
 * returns put back; plus an ESTIMATE for billed quantity that never left
 * stock in the ledger (e.g. an invoice with no Stock Out), valued at the
 * current average cost and flagged.
 */
function rep_profitability(PDO $pdo, string $from, string $to): array {
    $sales = rep_sales($pdo, $from, $to);
    $ret = rep_sales_returns($pdo, $from, $to);
    $run = ce_run($pdo, 'product', $from, $to);
    $rows = [];
    $products = erp_rows($pdo, "SELECT id, product_name, quantity AS pack, category, stock FROM product_details");
    foreach ($products as $p) {
        $pid = (int)$p['id'];
        $s = $sales[$pid] ?? null; $r = $ret[$pid] ?? null; $c = $run[$pid] ?? null;
        $ledgerSaleQty = $c['out']['sale'][0] ?? 0; $ledgerCogs = $c['out']['sale'][1] ?? 0;
        $restockValue = $c['in']['sales_return'][1] ?? 0;
        $wasteQty = $c['out']['waste'][0] ?? 0; $wasteVal = $c['out']['waste'][1] ?? 0;
        if (!$s && !$r && !$ledgerSaleQty && !$wasteQty) continue;
        $soldQty = $s['qty'] ?? 0;
        $unmatched = max(0, $soldQty - $ledgerSaleQty);   // billed but never deducted from stock
        $avg = $c['avg_cost'] ?? 0;
        $estCogs = round($unmatched * $avg, 2);
        $cogs = round($ledgerCogs + ($c['landed_to_cogs'] ?? 0) - $restockValue + $estCogs, 2);
        $revenue = round(($s['revenue'] ?? 0) - ($r['value'] ?? 0), 2);
        $gp = round($revenue - $cogs, 2);
        $rows[] = ['product_id' => $pid, 'product_name' => $p['product_name'], 'pack' => $p['pack'], 'category' => $p['category'],
                   'qty_sold' => round($soldQty, 3), 'ledger_sale_qty' => round($ledgerSaleQty, 3), 'gross_revenue' => round($s['revenue'] ?? 0, 2), 'returns_qty' => round($r['qty'] ?? 0, 3), 'returns_value' => round($r['value'] ?? 0, 2),
                   'net_revenue' => $revenue, 'avg_cost' => round($avg, 4), 'cogs' => $cogs, 'cogs_estimated' => $estCogs, 'unmatched_qty' => round($unmatched, 3),
                   'gross_profit' => $gp, 'margin_pct' => $revenue > 0 ? round($gp / $revenue * 100, 2) : null,
                   'waste_qty' => round($wasteQty, 3), 'waste_value' => round($wasteVal, 2), 'current_stock' => (float)$p['stock'],
                   'stock_value' => round(($c['closing_value'] ?? 0), 2), 'channels' => $s['channels'] ?? [], 'cost_missing' => $c['cost_missing'] ?? false];
    }
    usort($rows, function ($a, $b) { return $b['gross_profit'] <=> $a['gross_profit']; });
    return $rows;
}

function rep_pnl(PDO $pdo, string $from, string $to): array {
    $sales = rep_sales($pdo, $from, $to);
    $channels = ['invoices' => 0, 'manual_sales' => 0, 'credit_sales' => 0, 'website' => 0];
    foreach ($sales as $s) foreach ($s['channels'] as $ch => $v) $channels[$ch] += $v;
    $gross = array_sum($channels);
    // Website order totals include delivery charge / order-level discount beyond the item lines
    $webAdj = 0.0;
    if (tbl_exists($pdo, 'orders')) {
        $webTotal = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM orders WHERE COALESCE(order_status,'ordered') <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
        $webAdj = round($webTotal - $channels['website'], 2);
    }
    $returns = (float)erp_val($pdo, "SELECT COALESCE(SUM(total_value),0) FROM sales_returns WHERE status = 'posted' AND return_date BETWEEN ? AND ?", [$from, $to]);
    $netSales = round($gross + $webAdj - $returns, 2);

    $prof = rep_profitability($pdo, $from, $to);
    $cogs = round(array_sum(array_column($prof, 'cogs')), 2);
    $cogsEst = round(array_sum(array_column($prof, 'cogs_estimated')), 2);
    $gp = round($netSales - $cogs, 2);

    // Inventory movement (products + raw materials) — reconciles opening to closing
    $inv = ['opening' => 0, 'purchases' => 0, 'landed' => 0, 'purchase_returns' => 0, 'repack_net' => 0, 'sales_returns_restocked' => 0,
            'cogs_ledger' => 0, 'waste' => 0, 'adjustments_net' => 0, 'unrecorded_net' => 0, 'closing' => 0];
    foreach (['product', 'raw_material'] as $t) {
        $run = ce_run($pdo, $t, $from, $to);
        foreach ($run as $r) {
            $inv['opening'] += $r['opening_value']; $inv['closing'] += $r['closing_value'];
            $inv['purchases'] += $r['in']['purchase'][1] ?? 0;
            $inv['landed'] += $r['landed_to_stock'] + $r['landed_to_cogs'];
            $inv['purchase_returns'] += $r['out']['purchase_return'][1] ?? 0;
            $inv['repack_net'] += ($r['in']['repack_in'][1] ?? 0) - ($r['out']['repack_consume'][1] ?? 0);
            $inv['sales_returns_restocked'] += $r['in']['sales_return'][1] ?? 0;
            $inv['cogs_ledger'] += ($r['out']['sale'][1] ?? 0) + $r['landed_to_cogs'];
            $inv['waste'] += $r['out']['waste'][1] ?? 0;
            $inv['adjustments_net'] += ($r['in']['adjust_in'][1] ?? 0) - ($r['out']['adjust_out'][1] ?? 0);
            $inv['unrecorded_net'] += ($r['in']['unrecorded'][1] ?? 0) - ($r['out']['unrecorded'][1] ?? 0);
        }
    }
    foreach ($inv as $k => $v) $inv[$k] = round($v, 2);

    // Operating expenses & other income: existing Accounts → Transactions (completed)
    $exp = erp_rows($pdo, "SELECT category, ROUND(SUM(amount),2) amount FROM accounts_transactions WHERE type = 'expense' AND status = 'completed' AND date BETWEEN ? AND ? GROUP BY category ORDER BY amount DESC", [$from, $to]);
    $inc = erp_rows($pdo, "SELECT category, ROUND(SUM(amount),2) amount FROM accounts_transactions WHERE type = 'income' AND status = 'completed' AND date BETWEEN ? AND ? GROUP BY category ORDER BY amount DESC", [$from, $to]);
    $expTotal = round(array_sum(array_column($exp, 'amount')), 2);
    $incTotal = round(array_sum(array_column($inc, 'amount')), 2);
    $writeOffs = round($inv['waste'] - $inv['adjustments_net'] - $inv['unrecorded_net'], 2);
    $net = round($gp - $expTotal - $writeOffs + $incTotal, 2);
    $warnings = [];
    foreach ($exp as $e) if (preg_match('/purchase|stock|inventory|raw material/i', $e['category'])) $warnings[] = "Expense category \"{$e['category']}\" looks like stock purchases — if those goods were also received through Goods Receipts they are counted twice.";
    if ($cogsEst > 0) $warnings[] = 'COGS includes ₹' . number_format($cogsEst, 2) . ' ESTIMATED for billed quantity that was never deducted from stock (e.g. invoices without a Stock Out, manual sales not yet deducted).';
    $noDoc = array_filter($prof, function ($r) { return $r['ledger_sale_qty'] > $r['qty_sold'] + 0.0005; });
    if ($noDoc) $warnings[] = count($noDoc) . ' product(s) left stock for sales (Stock Out / delivery challan) in this period without a matching invoice or sale — their cost is in COGS but no revenue was recorded. Check that every dispatch was invoiced.';
    $missing = array_filter($prof, function ($r) { return $r['cost_missing']; });
    if ($missing) $warnings[] = count($missing) . ' sold product(s) have no purchase cost recorded — set an Opening Cost (Stock Valuation page) for accurate profit.';

    return [
        'sales' => ['by_channel' => array_map(function ($v) { return round($v, 2); }, $channels), 'website_order_adjustments' => $webAdj, 'gross_sales' => round($gross + $webAdj, 2),
                    'sales_returns' => round($returns, 2)],
        'net_sales' => $netSales, 'cogs' => $cogs, 'cogs_estimated_part' => $cogsEst, 'gross_profit' => $gp,
        'gross_margin_pct' => $netSales > 0 ? round($gp / $netSales * 100, 2) : null,
        'operating_expenses' => $exp, 'operating_expenses_total' => $expTotal,
        'inventory_write_offs' => $writeOffs, 'other_income' => $inc, 'other_income_total' => $incTotal,
        'net_profit' => $net, 'inventory' => $inv, 'warnings' => $warnings,
        'notes' => ['Sales are net of tax (GST is not revenue). Website orders have no tax split, so their item prices are used as-is.',
                    'COGS = weighted-average cost of stock that left the ledger for sales + landed costs on sold stock − cost of good returns put back.',
                    'Inventory write-offs = waste + negative adjustments + unrecorded stock changes (net).']];
}

function rep_valuation(PDO $pdo, string $asOf): array {
    $out = []; $total = 0; $noCost = 0;
    foreach (['product', 'raw_material'] as $t) {
        $run = ce_run($pdo, $t, null, $asOf);
        $names = $t === 'product' ? erp_rows($pdo, "SELECT id, product_name AS name, quantity AS pack, category, stock FROM product_details")
                                  : erp_rows($pdo, "SELECT id, name, unit AS pack, category, stock FROM raw_materials");
        foreach ($names as $n) {
            $r = $run[(int)$n['id']] ?? null;
            $qty = $r ? $r['closing_qty'] : 0;
            if ($qty <= 0 && ($r['closing_value'] ?? 0) == 0) continue;
            $missing = $r ? ($r['cost_missing'] || ($qty > 0 && $r['avg_cost'] <= 0)) : false;
            if ($missing) $noCost++;
            $total += $r['closing_value'];
            $out[] = ['item_type' => $t, 'item_id' => (int)$n['id'], 'name' => $n['name'], 'pack' => $n['pack'], 'category' => $n['category'], 'sku' => ($t === 'product' ? 'PRD-' : 'RM-') . $n['id'],
                      'qty' => $qty, 'current_stock' => (float)$n['stock'], 'avg_cost' => $r['avg_cost'], 'value' => $r['closing_value'], 'cost_missing' => $missing,
                      'opening_estimated' => $r['opening_estimated'] ?? false];
        }
    }
    usort($out, function ($a, $b) { return $b['value'] <=> $a['value']; });
    return ['rows' => $out, 'total_value' => round($total, 2), 'items_without_cost' => $noCost];
}

/**
 * Batch register with estimated remaining quantity. Sales don't record which
 * batch they used, so the current stock is allocated to batches newest-first
 * (FIFO consumption assumption: the oldest batches are sold first).
 */
function rep_batches(PDO $pdo, int $days): array {
    $rows = erp_rows($pdo, "SELECT b.*, s.supplier_name, w.name AS warehouse_name, g.grn_number FROM inventory_batches b LEFT JOIN suppliers s ON s.id = b.supplier_id
                            LEFT JOIN warehouses w ON w.id = b.warehouse_id LEFT JOIN goods_receipts g ON g.id = b.source_id AND b.source_type = 'grn'
                            ORDER BY b.item_type, b.item_id, b.received_date DESC, b.id DESC");
    $remaining = [];
    $rows = erp_attach_item_names($pdo, $rows);
    foreach ($rows as &$r) {
        $k = $r['item_type'] . ':' . $r['item_id'];
        if (!isset($remaining[$k])) $remaining[$k] = (float)(erp_item($pdo, $r['item_type'], (int)$r['item_id'])['stock'] ?? 0);
        $net = max(0, (float)$r['qty_received'] - (float)$r['qty_returned']);
        $alloc = min($net, $remaining[$k]);
        $remaining[$k] -= $alloc;
        $r['qty_available_est'] = round($alloc, 3);
        $r['value_est'] = round($alloc * $r['unit_cost'], 2);
        $r['days_to_expiry'] = $r['expiry_date'] ? (int)floor((strtotime($r['expiry_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
        $r['expiry_state'] = $r['days_to_expiry'] === null ? 'none' : ($r['days_to_expiry'] < 0 ? 'expired' : ($r['days_to_expiry'] <= $days ? 'expiring' : 'ok'));
    }
    unset($r);
    return $rows;
}

function rep_supplier_totals(PDO $pdo, int $sid): array {
    $inv = (float)erp_val($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM purchase_invoices WHERE supplier_id = ? AND status = 'posted'", [$sid]);
    $paid = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE supplier_id = ? AND status = 'completed'", [$sid]);
    $ret = erp_row($pdo, "SELECT COALESCE(SUM(total_value),0) v, COALESCE(SUM(refund_received),0) rf FROM purchase_returns WHERE supplier_id = ? AND status = 'posted' AND settlement IN ('credit_note','refund')", [$sid]);
    $received = (float)erp_val($pdo, "SELECT COALESCE(SUM(gi.accepted_qty * gi.rate),0) FROM goods_receipt_items gi JOIN goods_receipts g ON g.id = gi.grn_id WHERE g.supplier_id = ? AND g.status = 'posted'", [$sid]);
    $billedGrn = (float)erp_val($pdo, "SELECT COALESCE(SUM(pii.quantity * gi.rate),0) FROM purchase_invoice_items pii JOIN purchase_invoices pi ON pi.id = pii.pinv_id
                                      JOIN goods_receipt_items gi ON gi.id = pii.grn_item_id WHERE pi.supplier_id = ? AND pi.status = 'posted'", [$sid]);
    $outstanding = erp_m($inv - $paid - $ret['v'] + $ret['rf']);
    return ['total_invoiced' => erp_m($inv), 'total_paid' => erp_m($paid), 'returns_credit' => erp_m($ret['v']), 'refunds_received' => erp_m($ret['rf']),
            'outstanding' => $outstanding, 'received_not_invoiced' => erp_m(max(0, $received - $billedGrn))];
}

function rep_supplier_summary(PDO $pdo): array {
    $rows = [];
    foreach (erp_rows($pdo, "SELECT id, supplier_name, company_name, mobile, gst_number, status FROM suppliers ORDER BY supplier_name") as $s) {
        $t = rep_supplier_totals($pdo, (int)$s['id']);
        $t['overdue_invoices'] = count(array_filter(erp_rows($pdo, "SELECT id FROM purchase_invoices WHERE supplier_id = ? AND status = 'posted' AND due_date < CURDATE()", [$s['id']]),
            function ($r) use ($pdo) { $paid = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE pinv_id = ? AND status = 'completed'", [$r['id']]);
                                       return $paid + 0.005 < (float)erp_val($pdo, "SELECT grand_total FROM purchase_invoices WHERE id = ?", [$r['id']]); }));
        $t['last_purchase'] = erp_val($pdo, "SELECT MAX(received_date) FROM goods_receipts WHERE supplier_id = ? AND status = 'posted'", [$s['id']]) ?: null;
        $rows[] = $s + $t;
    }
    return $rows;
}

function rep_supplier_ledger(PDO $pdo, int $sid): array {
    $s = erp_row($pdo, "SELECT * FROM suppliers WHERE id = ?", [$sid]);
    if (!$s) erp_fail('Supplier not found.');
    $entries = [];
    foreach (erp_rows($pdo, "SELECT id, invoice_date d, pinv_number n, supplier_invoice_no x, grand_total a, due_date FROM purchase_invoices WHERE supplier_id = ? AND status = 'posted'", [$sid]) as $r)
        $entries[] = ['date' => $r['d'], 'type' => 'Purchase invoice', 'ref' => $r['n'], 'ref2' => $r['x'], 'link' => ['purchase_invoices.php', $r['id']], 'debit' => 0, 'credit' => (float)$r['a'], 'due_date' => $r['due_date']];
    foreach (erp_rows($pdo, "SELECT pp.id, pp.payment_date d, pp.payment_number n, pp.amount a, pp.payment_mode m, pi.supplier_invoice_no x FROM purchase_payments pp LEFT JOIN purchase_invoices pi ON pi.id = pp.pinv_id
                            WHERE pp.supplier_id = ? AND pp.status = 'completed'", [$sid]) as $r)
        $entries[] = ['date' => $r['d'], 'type' => 'Payment (' . $r['m'] . ')', 'ref' => $r['n'], 'ref2' => $r['x'] ?: 'Advance', 'link' => ['purchase_payments.php', $r['id']], 'debit' => (float)$r['a'], 'credit' => 0];
    foreach (erp_rows($pdo, "SELECT id, return_date d, return_number n, total_value v, refund_received rf, settlement, credit_note_number x FROM purchase_returns WHERE supplier_id = ? AND status = 'posted'", [$sid]) as $r) {
        if ($r['settlement'] === 'replacement') { $entries[] = ['date' => $r['d'], 'type' => 'Return (replacement)', 'ref' => $r['n'], 'ref2' => $r['x'], 'link' => ['purchase_returns.php', $r['id']], 'debit' => 0, 'credit' => 0]; continue; }
        $entries[] = ['date' => $r['d'], 'type' => 'Return / credit note', 'ref' => $r['n'], 'ref2' => $r['x'], 'link' => ['purchase_returns.php', $r['id']], 'debit' => (float)$r['v'], 'credit' => 0];
        if ((float)$r['rf'] > 0) $entries[] = ['date' => $r['d'], 'type' => 'Refund received', 'ref' => $r['n'], 'ref2' => '', 'link' => ['purchase_returns.php', $r['id']], 'debit' => 0, 'credit' => (float)$r['rf']];
    }
    usort($entries, function ($a, $b) { return strcmp($a['date'], $b['date']); });
    $bal = 0;
    foreach ($entries as &$e) { $bal += $e['credit'] - $e['debit']; $e['balance'] = erp_m($bal); }
    unset($e);
    return ['supplier' => $s, 'totals' => rep_supplier_totals($pdo, $sid), 'entries' => $entries,
            'purchase_orders' => erp_rows($pdo, "SELECT id, po_number, po_date, status, grand_total FROM purchase_orders WHERE supplier_id = ? ORDER BY id DESC", [$sid]),
            'grns' => erp_rows($pdo, "SELECT id, grn_number, received_date, status FROM goods_receipts WHERE supplier_id = ? ORDER BY id DESC", [$sid]),
            'price_history' => rep_price_history($pdo, $sid),
            'documents' => erp_rows($pdo, "SELECT id, entity_type, entity_id, doc_type, original_name, created_at FROM erp_documents WHERE
                                           (entity_type = 'supplier' AND entity_id = ?) OR
                                           (entity_type = 'purchase_order' AND entity_id IN (SELECT id FROM purchase_orders WHERE supplier_id = ?)) OR
                                           (entity_type = 'grn' AND entity_id IN (SELECT id FROM goods_receipts WHERE supplier_id = ?)) OR
                                           (entity_type = 'purchase_invoice' AND entity_id IN (SELECT id FROM purchase_invoices WHERE supplier_id = ?)) OR
                                           (entity_type = 'purchase_payment' AND entity_id IN (SELECT id FROM purchase_payments WHERE supplier_id = ?)) OR
                                           (entity_type = 'purchase_return' AND entity_id IN (SELECT id FROM purchase_returns WHERE supplier_id = ?)) ORDER BY id DESC",
                                           [$sid, $sid, $sid, $sid, $sid, $sid])];
}

/** Received purchases (posted GRN lines) grouped as requested. Value = accepted × GRN rate; landed = invoice landed cost where billed. */
function rep_purchases(PDO $pdo, string $from, string $to): array {
    $group = (string)erp_input('group_by', 'month');
    $w = ["g.status = 'posted'", 'g.received_date BETWEEN ? AND ?']; $p = [$from, $to];
    if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'g.supplier_id = ?'; $p[] = $sid; }
    if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = 'g.warehouse_id = ?'; $p[] = $wid; }
    if ($it = (string)erp_input('item_type', '')) { $w[] = 'gi.item_type = ?'; $p[] = $it; }
    if ($iid = (int)erp_input('item_id', 0)) { $w[] = 'gi.item_id = ?'; $p[] = $iid; }
    $lines = erp_rows($pdo, "SELECT g.received_date, g.grn_number, g.id AS grn_id, s.supplier_name, g.supplier_id, w.name AS warehouse_name, gi.item_type, gi.item_id,
                                    gi.accepted_qty, gi.rejected_qty, gi.rate, gi.unit,
                                    (SELECT pii.landed_unit_cost FROM purchase_invoice_items pii JOIN purchase_invoices x ON x.id = pii.pinv_id WHERE pii.grn_item_id = gi.id AND x.status = 'posted' ORDER BY pii.id DESC LIMIT 1) AS landed_unit
                             FROM goods_receipt_items gi JOIN goods_receipts g ON g.id = gi.grn_id JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN warehouses w ON w.id = g.warehouse_id
                             WHERE " . implode(' AND ', $w) . " ORDER BY g.received_date", $p);
    $lines = erp_attach_item_names($pdo, $lines);
    if ($cat = trim((string)erp_input('category', ''))) $lines = array_values(array_filter($lines, function ($l) use ($cat) { return strcasecmp((string)$l['item_category'], $cat) === 0; }));
    $groups = [];
    foreach ($lines as $l) {
        switch ($group) {
            case 'date': $k = $l['received_date']; break;
            case 'vendor': $k = $l['supplier_name']; break;
            case 'product': $k = $l['item_name']; break;
            case 'category': $k = $l['item_category'] ?: '(none)'; break;
            case 'warehouse': $k = $l['warehouse_name'] ?: '—'; break;
            default: $k = substr($l['received_date'], 0, 7);
        }
        if (!isset($groups[$k])) $groups[$k] = ['group' => $k, 'lines' => 0, 'accepted_qty' => 0, 'rejected_qty' => 0, 'value' => 0, 'landed_value' => 0];
        $g = &$groups[$k];
        $g['lines']++; $g['accepted_qty'] += $l['accepted_qty']; $g['rejected_qty'] += $l['rejected_qty'];
        $g['value'] += $l['accepted_qty'] * $l['rate'];
        $g['landed_value'] += $l['accepted_qty'] * ($l['landed_unit'] ?: $l['rate']);
        unset($g);
    }
    foreach ($groups as &$g) { $g['value'] = erp_m($g['value']); $g['landed_value'] = erp_m($g['landed_value']); $g['accepted_qty'] = erp_q($g['accepted_qty']); $g['rejected_qty'] = erp_q($g['rejected_qty']); }
    unset($g);
    $returns = erp_rows($pdo, "SELECT r.return_number, r.return_date, s.supplier_name, r.total_value, r.settlement, r.reason FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id
                               WHERE r.status = 'posted' AND r.return_date BETWEEN ? AND ? ORDER BY r.return_date", [$from, $to]);
    return ['group_by' => $group, 'groups' => array_values($groups), 'lines' => $lines, 'returns' => $returns,
            'total_value' => erp_m(array_sum(array_column($groups, 'value'))), 'total_landed' => erp_m(array_sum(array_column($groups, 'landed_value')))];
}

function rep_price_history(PDO $pdo, ?int $supplierId = null): array {
    $w = ["g.status = 'posted'", 'gi.accepted_qty > 0']; $p = [];
    if ($supplierId) { $w[] = 'g.supplier_id = ?'; $p[] = $supplierId; }
    if ($it = (string)erp_input('item_type', '')) { $w[] = 'gi.item_type = ?'; $p[] = $it; }
    if ($iid = (int)erp_input('item_id', 0)) { $w[] = 'gi.item_id = ?'; $p[] = $iid; }
    $rows = erp_rows($pdo, "SELECT g.received_date, g.grn_number, g.id AS grn_id, s.supplier_name, gi.item_type, gi.item_id, gi.accepted_qty, gi.unit, gi.rate, gi.batch_number,
                                   (SELECT pii.landed_unit_cost FROM purchase_invoice_items pii JOIN purchase_invoices x ON x.id = pii.pinv_id WHERE pii.grn_item_id = gi.id AND x.status = 'posted' ORDER BY pii.id DESC LIMIT 1) AS landed_unit,
                                   (SELECT x.supplier_invoice_no FROM purchase_invoice_items pii JOIN purchase_invoices x ON x.id = pii.pinv_id WHERE pii.grn_item_id = gi.id AND x.status = 'posted' ORDER BY pii.id DESC LIMIT 1) AS invoice_no
                            FROM goods_receipt_items gi JOIN goods_receipts g ON g.id = gi.grn_id JOIN suppliers s ON s.id = g.supplier_id
                            WHERE " . implode(' AND ', $w) . " ORDER BY gi.item_type, gi.item_id, g.received_date DESC, gi.id DESC LIMIT 2000", $p);
    return erp_attach_item_names($pdo, $rows);
}

function rep_pending(PDO $pdo): array {
    return [
        'purchase_orders' => erp_rows($pdo, "SELECT po.id, po.po_number, po.po_date, po.expected_delivery_date, po.status, po.grand_total, s.supplier_name,
                                               (SELECT COALESCE(SUM(GREATEST(quantity - received_qty,0)),0) FROM purchase_order_items i WHERE i.po_id = po.id) AS pending_qty
                                            FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                                            WHERE po.status IN ('draft','pending_approval','approved','partially_received') ORDER BY po.expected_delivery_date IS NULL, po.expected_delivery_date, po.id"),
        'draft_grns' => erp_rows($pdo, "SELECT g.id, g.grn_number, g.received_date, s.supplier_name FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id WHERE g.status = 'draft' ORDER BY g.id"),
        'unbilled_grns' => erp_rows($pdo, "SELECT g.id, g.grn_number, g.received_date, s.supplier_name FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id
                                          WHERE g.status = 'posted' AND NOT EXISTS (SELECT 1 FROM purchase_invoices pi WHERE pi.grn_id = g.id AND pi.status <> 'cancelled') ORDER BY g.id"),
        'pending_requests' => erp_rows($pdo, "SELECT id, pr_number, request_date, status FROM purchase_requests WHERE status IN ('submitted','approved') ORDER BY id"),
        'pending_adjustments' => (int)erp_val($pdo, "SELECT COUNT(*) FROM stock_adjustments WHERE status = 'pending'"),
    ];
}

/** Customer receivables from existing sales tables, less credit-note sales returns. */
function rep_receivables(PDO $pdo): array {
    $rows = [];
    $push = function ($src, $id, $num, $date, $name, $mobile, $total, $paid, $note = null) use (&$rows) {
        $rows[] = ['source' => $src, 'id' => (int)$id, 'number' => $num, 'date' => $date, 'customer_name' => $name ?: '—', 'customer_mobile' => $mobile,
                   'total' => erp_m($total), 'paid' => erp_m($paid), 'credit_notes' => 0.0, 'outstanding' => erp_m($total - $paid), 'note' => $note];
    };
    if (tbl_exists($pdo, 'invoices'))
        foreach (erp_rows($pdo, "SELECT id, invoice_number, invoice_date, customer_name, customer_mobile, grand_total, amount_paid FROM invoices WHERE status IN ('issued','partially_paid','overdue') AND grand_total > amount_paid") as $r)
            $push('invoice', $r['id'], $r['invoice_number'], $r['invoice_date'], $r['customer_name'], $r['customer_mobile'], $r['grand_total'], $r['amount_paid']);
    if (tbl_exists($pdo, 'credit_sales'))
        foreach (erp_rows($pdo, "SELECT id, credit_number, sale_date, customer_name, customer_mobile, grand_total, amount_paid FROM credit_sales WHERE status <> 'paid' AND grand_total > amount_paid") as $r)
            $push('credit_sale', $r['id'], $r['credit_number'], $r['sale_date'], $r['customer_name'], $r['customer_mobile'], $r['grand_total'], $r['amount_paid']);
    if (tbl_exists($pdo, 'manual_sales'))
        foreach (erp_rows($pdo, "SELECT id, sale_number, sales_date, customer_name, customer_mobile, grand_total, payment_status FROM manual_sales WHERE payment_status IN ('pending','credit','partially_paid')") as $r)
            $push('manual_sale', $r['id'], $r['sale_number'], $r['sales_date'], $r['customer_name'], $r['customer_mobile'], $r['grand_total'], 0,
                  $r['payment_status'] === 'partially_paid' ? 'Partially paid — the amount received is not recorded on manual sales, so the full total is shown.' : null);
    foreach (erp_rows($pdo, "SELECT source_type, source_id, SUM(refund_amount) v FROM sales_returns WHERE status = 'posted' AND settlement = 'credit_note' GROUP BY source_type, source_id") as $c)
        foreach ($rows as &$r) if ($r['source'] === $c['source_type'] && $r['id'] === (int)$c['source_id']) { $r['credit_notes'] = erp_m($c['v']); $r['outstanding'] = erp_m(max(0, $r['outstanding'] - $c['v'])); }
    unset($r);
    $rows = array_values(array_filter($rows, function ($r) { return $r['outstanding'] > 0.005; }));
    $byCustomer = [];
    foreach ($rows as $r) {
        $k = strtolower(trim($r['customer_name'])) . '|' . preg_replace('/\D/', '', (string)$r['customer_mobile']);
        if (!isset($byCustomer[$k])) $byCustomer[$k] = ['customer_name' => $r['customer_name'], 'customer_mobile' => $r['customer_mobile'], 'documents' => 0, 'outstanding' => 0, 'oldest' => $r['date']];
        $byCustomer[$k]['documents']++; $byCustomer[$k]['outstanding'] = erp_m($byCustomer[$k]['outstanding'] + $r['outstanding']);
        if ($r['date'] < $byCustomer[$k]['oldest']) $byCustomer[$k]['oldest'] = $r['date'];
    }
    $cust = array_values($byCustomer);
    usort($cust, function ($a, $b) { return $b['outstanding'] <=> $a['outstanding']; });
    return ['documents' => $rows, 'customers' => $cust, 'total' => erp_m(array_sum(array_column($rows, 'outstanding')))];
}

/** Ledger vs current stock, and changes that never reached the ledger. */
function rep_variance(PDO $pdo): array {
    $out = [];
    foreach (['product', 'raw_material'] as $t) {
        $run = ce_run($pdo, $t);
        foreach ($run as $iid => $r) {
            $cur = (float)$r['current_stock'];
            $diff = round($cur - $r['closing_qty'], 3);
            if (abs($diff) < 0.0005 && abs($r['unrecorded_qty']) < 0.0005) continue;
            $item = erp_item($pdo, $t, (int)$iid);
            $out[] = ['item_type' => $t, 'item_id' => $iid, 'name' => $item['name'] ?? "#$iid", 'current_stock' => $cur, 'ledger_stock' => $r['closing_qty'],
                      'difference' => $diff, 'unrecorded_in_ledger' => round($r['unrecorded_qty'], 3), 'avg_cost' => $r['avg_cost'], 'difference_value' => round($diff * $r['avg_cost'], 2)];
        }
    }
    return $out;
}

/** Everything about one item: purchases, batches, stock ledger with running cost, sales, profitability. */
function rep_item_trace(PDO $pdo, string $type, int $id): array {
    $type = erp_item_type($type);
    $item = erp_item($pdo, $type, $id);
    if (!$item) erp_fail('Item not found.');
    $run = ce_run($pdo, $type, null, null, [$id]);
    $ledger = $type === 'product'
        ? erp_rows($pdo, "SELECT created_at, movement_type, quantity, previous_stock, new_stock, reference_type, reference_number, reason, created_by FROM stock_movements WHERE product_id = ? ORDER BY id DESC LIMIT 300", [$id])
        : erp_rows($pdo, "SELECT created_at, movement_type, quantity, previous_stock, new_stock, reference_type, reference_number, reason, created_by FROM raw_material_movements WHERE raw_material_id = ? ORDER BY id DESC LIMIT 300", [$id]);
    $_GET['item_type'] = $type; $_GET['item_id'] = $id;
    $prof = [];
    if ($type === 'product') foreach (rep_profitability($pdo, '2000-01-01', date('Y-m-d')) as $r) if ($r['product_id'] === $id) $prof = $r;
    return ['item' => $item, 'costing' => $run[$id] ?? null, 'purchases' => rep_price_history($pdo), 'ledger' => $ledger,
            'batches' => array_values(array_filter(rep_batches($pdo, 60), function ($b) use ($type, $id) { return $b['item_type'] === $type && (int)$b['item_id'] === $id; })),
            'cost_entries' => erp_rows($pdo, "SELECT entry_date, entry_type, quantity, value, reference_type, reference_number, note, status FROM inventory_cost_entries WHERE item_type = ? AND item_id = ? ORDER BY entry_date DESC, id DESC", [$type, $id]),
            'profitability_all_time' => $prof];
}
