<?php
// ============================================================================
// Sales completion + ERP reports API (added 1 Oct 2026)
//   Sales channels (tag sales; channel profitability) · Fulfilment log
//   (pick / pack / dispatch / deliver on existing sales orders & website orders)
//   Credit notes · Customer 360 · Transaction trace (sale → stock movement →
//   batch → GRN → PO → supplier → bill → transport → landed cost → COGS → GP)
//   ERP reports: sales, category / channel profitability, COGS, customer
//   payments, GRN, QC, purchase returns, ERP audit
// Existing sales screens and tables are only read (the channel tag and the
// fulfilment log live in their own new tables).
// ============================================================================
require_once __DIR__ . '/accounting_engine.php';
require_once __DIR__ . '/erp_report_lib.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$perms = [
    'channel_save' => 'sales.channels', 'channel_tag' => 'sales.channels', 'channel_docs' => 'sales.channels',
    'ful_list' => 'sales.fulfilment', 'ful_log' => 'sales.fulfilment', 'ful_history' => 'sales.fulfilment',
    'cn_list' => 'customer.view', 'cn_get' => 'customer.view', 'cn_issue_missing' => 'sales_return.create',
    'cust_search' => 'customer.view', 'cust_get' => 'customer.view',
    'trace' => 'trace.view', 'trace_search' => 'trace.view',
    'erp_audit' => 'audit_logs.view',
];
erpx_guard($pdo, $perms[$action] ?? 'reports.erp');
if (in_array($action, ['channel_save', 'channel_tag', 'ful_log', 'cn_issue_missing'], true) && !$isWrite) erp_fail('Invalid request method.');

$from = erp_date(erp_input('date_from')) ?: date('Y-m-01');
$to = erp_date(erp_input('date_to')) ?: date('Y-m-d');
if ($to < $from) erp_fail('"To" date is before "From" date.');

function se_mobile($m): string { $d = preg_replace('/\D/', '', (string)$m); return strlen($d) > 10 ? substr($d, -10) : $d; }
/** Sale documents of the period with their channel, revenue (ex-tax) and customer. */
function se_sale_docs(PDO $pdo, string $from, string $to): array {
    $docs = [];
    foreach (erp_rows($pdo, "SELECT id, invoice_number n, invoice_date d, customer_name c, customer_mobile m, grand_total - tax_amount rev, tax_amount tax, grand_total g, status FROM invoices WHERE status NOT IN ('draft','cancelled') AND invoice_date BETWEEN ? AND ?", [$from, $to]) as $r)
        $docs[] = ['type' => 'invoice', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => $r['c'], 'mobile' => $r['m'], 'revenue' => (float)$r['rev'], 'tax' => (float)$r['tax'], 'total' => (float)$r['g'], 'status' => $r['status']];
    foreach (erp_rows($pdo, "SELECT id, sale_number n, sales_date d, customer_name c, customer_mobile m, grand_total - total_tax rev, total_tax tax, grand_total g, payment_status status FROM manual_sales WHERE sales_date BETWEEN ? AND ?", [$from, $to]) as $r)
        $docs[] = ['type' => 'manual_sale', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => $r['c'], 'mobile' => $r['m'], 'revenue' => (float)$r['rev'], 'tax' => (float)$r['tax'], 'total' => (float)$r['g'], 'status' => $r['status']];
    foreach (erp_rows($pdo, "SELECT id, credit_number n, sale_date d, customer_name c, customer_mobile m, grand_total - total_tax rev, total_tax tax, grand_total g, status FROM credit_sales WHERE sale_date BETWEEN ? AND ?", [$from, $to]) as $r)
        $docs[] = ['type' => 'credit_sale', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => $r['c'], 'mobile' => $r['m'], 'revenue' => (float)$r['rev'], 'tax' => (float)$r['tax'], 'total' => (float)$r['g'], 'status' => $r['status']];
    foreach (erp_rows($pdo, "SELECT id, receipt n, DATE(created_at) d, CONCAT(first_name,' ',last_name) c, phone m, amount g, payment_status, payment_method, order_status FROM orders
                             WHERE COALESCE(order_status,'') <> 'cancelled' AND (payment_status = 'paid' OR UPPER(payment_method) = 'COD') AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]) as $r)
        $docs[] = ['type' => 'website_order', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => trim($r['c']), 'mobile' => $r['m'], 'revenue' => (float)$r['g'], 'tax' => 0.0, 'total' => (float)$r['g'],
                   'status' => $r['order_status'] ?: $r['payment_status']];
    foreach ($docs as &$d) $d['channel'] = acc_channel($pdo, $d['type'], $d['id']);
    unset($d);
    return $docs;
}
/** stock-movement reference number → [source type, id] (for channel COGS and the trace). */
function se_ref_map(PDO $pdo): array {
    $m = [];
    foreach (erp_rows($pdo, "SELECT id, receipt FROM orders") as $r) $m['website_order|' . $r['receipt']] = ['website_order', (int)$r['id']];
    foreach (erp_rows($pdo, "SELECT id, sale_number FROM manual_sales") as $r) { $m['manual_sale|' . $r['sale_number']] = ['manual_sale', (int)$r['id']]; $m['manual_sales|' . $r['sale_number']] = ['manual_sale', (int)$r['id']]; }
    foreach (erp_rows($pdo, "SELECT id, credit_number FROM credit_sales") as $r) $m['credit_sale|' . $r['credit_number']] = ['credit_sale', (int)$r['id']];
    foreach (erp_rows($pdo, "SELECT id, invoice_number FROM invoices") as $r) $m['invoice|' . $r['invoice_number']] = ['invoice', (int)$r['id']];
    $inv = [];
    foreach (erp_rows($pdo, "SELECT id, sales_order_id FROM invoices WHERE sales_order_id IS NOT NULL AND status <> 'cancelled' ORDER BY id") as $r) $inv[(int)$r['sales_order_id']] = $inv[(int)$r['sales_order_id']] ?? (int)$r['id'];
    foreach (erp_rows($pdo, "SELECT id, so_number FROM sales_orders") as $r) $m['sales_order|' . $r['so_number']] = isset($inv[(int)$r['id']]) ? ['invoice', $inv[(int)$r['id']]] : ['sales_order', (int)$r['id']];
    foreach (erp_rows($pdo, "SELECT dc_number, sales_order_id FROM delivery_challans") as $r) $m['delivery_challan|' . $r['dc_number']] = isset($inv[(int)$r['sales_order_id']]) ? ['invoice', $inv[(int)$r['sales_order_id']]] : ['sales_order', (int)$r['sales_order_id']];
    foreach (erp_rows($pdo, "SELECT return_number, source_type, source_id FROM sales_returns") as $r) $m['sales_return|' . $r['return_number']] = [$r['source_type'], (int)$r['source_id']];
    return $m;
}
function se_channel_of(PDO $pdo, ?array $src): string {
    if (!$src) return 'offline';
    return $src[0] === 'sales_order' ? 'offline' : acc_channel($pdo, $src[0], $src[1]);
}

try {
    switch ($action) {
        // ============================================================ CHANNELS
        case 'channels':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT c.*, (SELECT COUNT(*) FROM sales_channel_map m WHERE m.channel_id = c.id) AS tagged FROM sales_channels c ORDER BY c.id")]);

        case 'channel_save':
            $id = (int)erp_input('id', 0);
            $name = trim((string)erp_input('name', ''));
            if ($name === '') erp_invalid('Enter the channel name.');
            $type = in_array(erp_input('channel_type'), ['website', 'offline', 'b2b', 'marketplace', 'other'], true) ? erp_input('channel_type') : 'offline';
            if ($id) {
                $pdo->prepare("UPDATE sales_channels SET name = ?, channel_type = ?, status = ? WHERE id = ?")->execute([$name, $type, erp_input('status') === 'inactive' ? 'inactive' : 'active', $id]);
            } else {
                $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
                if (erp_val($pdo, "SELECT id FROM sales_channels WHERE code = ?", [$code])) erp_invalid('A channel with that name exists.');
                $pdo->prepare("INSERT INTO sales_channels (code, name, channel_type) VALUES (?,?,?)")->execute([$code, $name, $type]);
                $id = (int)$pdo->lastInsertId();
            }
            log_audit($pdo, 'save', 'sales_channels', $id, null, ['name' => $name, 'type' => $type]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "Channel {$name} saved."]);

        case 'channel_docs':
            $docs = se_sale_docs($pdo, $from, $to);
            if ($t = erp_input('type')) $docs = array_values(array_filter($docs, function ($d) use ($t) { return $d['type'] === $t; }));
            if ($c = erp_input('channel')) $docs = array_values(array_filter($docs, function ($d) use ($c) { return $d['channel'] === $c; }));
            erp_out(['status' => 'success', 'rows' => array_slice($docs, 0, 1000)]);

        case 'channel_tag':
            $ch = erp_row($pdo, "SELECT * FROM sales_channels WHERE id = ? AND status = 'active'", [(int)erp_input('channel_id')]);
            if (!$ch) erp_invalid('Choose an active channel.');
            $type = (string)erp_input('source_type');
            if (!in_array($type, ['invoice', 'manual_sale', 'credit_sale', 'website_order'], true)) erp_invalid('Invalid sale type.');
            $ids = array_values(array_filter(array_map('intval', erp_json_input('source_ids'))));
            if (!$ids) erp_invalid('Choose the sales to tag.');
            $ins = $pdo->prepare("INSERT INTO sales_channel_map (source_type, source_id, channel_id, set_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id), set_by = VALUES(set_by), set_at = NOW()");
            foreach ($ids as $i) $ins->execute([$type, $i, $ch['id'], erp_user()]);
            log_audit($pdo, 'update', 'sales_channel_map', null, null, ['type' => $type, 'ids' => $ids, 'channel' => $ch['code']]);
            erp_out(['status' => 'success', 'message' => count($ids) . " sale(s) tagged as {$ch['name']}. Journals already posted keep their original channel label; reports use the new tag."]);

        case 'channel_profitability':
            $docs = se_sale_docs($pdo, $from, $to);
            $ch = [];
            $blank = ['revenue' => 0, 'tax' => 0, 'documents' => 0, 'returns' => 0, 'cogs' => 0];
            foreach ($docs as $d) { $c = $d['channel']; $ch[$c] = $ch[$c] ?? $blank; $ch[$c]['revenue'] += $d['revenue']; $ch[$c]['tax'] += $d['tax']; $ch[$c]['documents']++; }
            foreach (erp_rows($pdo, "SELECT source_type, source_id, total_value FROM sales_returns WHERE status = 'posted' AND return_date BETWEEN ? AND ?", [$from, $to]) as $r) {
                $c = acc_channel($pdo, $r['source_type'], (int)$r['source_id']); $ch[$c] = $ch[$c] ?? $blank; $ch[$c]['returns'] += (float)$r['total_value'];
            }
            $map = se_ref_map($pdo);
            $trail = []; ce_run($pdo, 'product', $from, $to, null, $trail);
            $unassigned = 0.0;
            foreach ($trail as $t) {
                if ($t['kind'] !== 'move' || !in_array($t['bucket'], ['sale', 'sales_return'], true)) { if ($t['kind'] === 'value' && $t['bucket'] === 'landed_cogs') $unassigned += $t['value']; continue; }
                $src = $map[strtolower($t['ref_type']) . '|' . $t['ref_no']] ?? null;
                $c = se_channel_of($pdo, $src);
                $ch[$c] = $ch[$c] ?? $blank;
                $ch[$c]['cogs'] += -$t['value'];     // sale value is negative (stock out); a restocked return is positive
            }
            $names = [];
            foreach (erp_rows($pdo, "SELECT code, name FROM sales_channels") as $r) $names[$r['code']] = $r['name'];
            // COGS estimated for billed quantity never deducted from stock (same rule as P&L) goes to the invoice's channel (offline by default)
            $est = array_sum(array_column(rep_profitability($pdo, $from, $to), 'cogs_estimated'));
            $out = [];
            foreach ($ch as $code => $v) {
                $net = erp_m($v['revenue'] - $v['returns']);
                $cogs = erp_m($v['cogs'] + ($code === 'offline' ? $est + $unassigned : 0));
                $out[] = ['channel' => $code, 'name' => $names[$code] ?? $code, 'documents' => $v['documents'], 'gross_sales' => erp_m($v['revenue']), 'returns' => erp_m($v['returns']), 'net_sales' => $net,
                          'gst_collected' => erp_m($v['tax']), 'cogs' => $cogs, 'gross_profit' => erp_m($net - $cogs), 'margin_pct' => $net > 0 ? round(($net - $cogs) / $net * 100, 2) : null];
            }
            usort($out, function ($a, $b) { return $b['net_sales'] <=> $a['net_sales']; });
            erp_out(['status' => 'success', 'from' => $from, 'to' => $to, 'rows' => $out,
                     'notes' => ['Channel = the tag set in Sales Channels; untagged website orders are "Website", everything else "Offline".', 'COGS follows each stock movement back to its sale; estimated COGS for billed but undeducted quantity and landed costs on already-sold stock are shown under Offline.']]);

        // ============================================================ FULFILMENT
        case 'ful_list':
            $stageOf = function ($type, $id) use ($pdo) { return erp_row($pdo, "SELECT stage, created_at, by_user, tracking_ref FROM fulfilment_logs WHERE source_type = ? AND source_id = ? ORDER BY id DESC LIMIT 1", [$type, $id]); };
            $rows = [];
            $open = erp_input('scope', 'open') === 'open';
            foreach (erp_rows($pdo, "SELECT so.id, so.so_number n, so.order_date d, so.customer_name c, so.customer_mobile m, so.status, so.grand_total g, w.name AS warehouse_name,
                                            (SELECT COUNT(*) FROM delivery_challans dc WHERE dc.sales_order_id = so.id AND dc.delivery_status IN ('dispatched','in_transit','delivered')) AS dispatched_dcs
                                     FROM sales_orders so LEFT JOIN warehouses w ON w.id = so.warehouse_id WHERE " . ($open ? "so.status IN ('confirmed','processing','ready_for_dispatch','dispatched')" : "so.order_date BETWEEN ? AND ?") . " ORDER BY so.id DESC LIMIT 300", $open ? [] : [$from, $to]) as $r)
                $rows[] = ['source_type' => 'sales_order', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => $r['c'], 'mobile' => $r['m'], 'system_status' => $r['status'], 'total' => (float)$r['g'],
                           'warehouse' => $r['warehouse_name'], 'last' => $stageOf('sales_order', (int)$r['id']), 'link' => 'sales_orders.php?id=' . $r['id'], 'dispatched_dcs' => (int)$r['dispatched_dcs']];
            foreach (erp_rows($pdo, "SELECT id, receipt n, DATE(created_at) d, CONCAT(first_name,' ',last_name) c, phone m, order_status, payment_status, payment_method, amount, city FROM orders
                                     WHERE " . ($open ? "COALESCE(order_status,'') NOT IN ('delivered','cancelled') AND (payment_status = 'paid' OR UPPER(payment_method) = 'COD')" : "DATE(created_at) BETWEEN ? AND ?") . " ORDER BY id DESC LIMIT 300", $open ? [] : [$from, $to]) as $r)
                $rows[] = ['source_type' => 'website_order', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => trim($r['c']), 'mobile' => $r['m'], 'system_status' => $r['order_status'] ?: 'ordered',
                           'payment' => $r['payment_method'] . ' / ' . $r['payment_status'], 'total' => (float)$r['amount'], 'warehouse' => $r['city'], 'last' => $stageOf('website_order', (int)$r['id']), 'link' => 'orders.php?id=' . $r['id']];
            if ($st = erp_input('stage')) $rows = array_values(array_filter($rows, function ($r) use ($st) { return ($r['last']['stage'] ?? 'none') === $st; }));
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'ful_log':
            $type = (string)erp_input('source_type');
            if (!in_array($type, ['sales_order', 'website_order'], true)) erp_invalid('Invalid order type.');
            $id = (int)erp_input('source_id');
            $exists = $type === 'sales_order' ? erp_val($pdo, "SELECT id FROM sales_orders WHERE id = ?", [$id]) : erp_val($pdo, "SELECT id FROM orders WHERE id = ?", [$id]);
            if (!$exists) erp_invalid('Order not found.');
            $stage = (string)erp_input('stage');
            $order = ['confirmed' => 1, 'picked' => 2, 'packed' => 3, 'dispatched' => 4, 'delivered' => 5, 'returned' => 6, 'cancelled' => 9];
            if (!isset($order[$stage])) erp_invalid('Choose the stage.');
            $last = erp_val($pdo, "SELECT stage FROM fulfilment_logs WHERE source_type = ? AND source_id = ? ORDER BY id DESC LIMIT 1", [$type, $id]);
            if ($last === $stage) erp_invalid("It is already marked {$stage}.");
            if ($last && in_array($last, ['cancelled', 'delivered'], true) && $stage !== 'returned') erp_invalid("It is already {$last}.");
            if ($stage === 'dispatched' && $type === 'sales_order' && !(int)erp_val($pdo, "SELECT COUNT(*) FROM delivery_challans WHERE sales_order_id = ? AND delivery_status IN ('dispatched','in_transit','delivered')", [$id]))
                erp_invalid('Dispatch sales orders with a Delivery Challan first (that is what deducts the stock); then log it here.');
            $pdo->prepare("INSERT INTO fulfilment_logs (source_type, source_id, stage, tracking_ref, notes, by_user) VALUES (?,?,?,?,?,?)")
                ->execute([$type, $id, $stage, erp_input('tracking_ref') ?: null, erp_input('notes') ?: null, erp_user()]);
            log_audit($pdo, 'fulfilment', $type === 'sales_order' ? 'sales_orders' : 'orders', $id, ['stage' => $last], ['stage' => $stage, 'tracking' => erp_input('tracking_ref')]);
            erp_out(['status' => 'success', 'message' => "Marked {$stage}. (The order's own status is still managed on its page.)"]);

        case 'ful_history':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT * FROM fulfilment_logs WHERE source_type = ? AND source_id = ? ORDER BY id", [(string)erp_input('source_type'), (int)erp_input('source_id')])]);

        // ============================================================ CREDIT NOTES
        case 'cn_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'c.status = ?'; $p[] = $s; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(c.cn_number LIKE ? OR c.customer_name LIKE ? OR c.customer_mobile LIKE ? OR r.return_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT c.*, r.return_number, r.source_number, r.settlement FROM credit_notes c JOIN sales_returns r ON r.id = c.sales_return_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY c.id DESC LIMIT 500", $p);
            $missing = (int)erp_val($pdo, "SELECT COUNT(*) FROM sales_returns r WHERE r.status = 'posted' AND r.settlement <> 'replacement' AND NOT EXISTS (SELECT 1 FROM credit_notes c WHERE c.sales_return_id = r.id)");
            erp_out(['status' => 'success', 'rows' => $rows, 'returns_without_note' => $missing]);

        case 'cn_get':
            $c = erp_row($pdo, "SELECT c.*, r.return_number, r.return_date, r.source_number, r.reason, r.settlement, r.refund_mode, r.total_value FROM credit_notes c JOIN sales_returns r ON r.id = c.sales_return_id WHERE c.id = ?", [(int)erp_input('id')]);
            if (!$c) erp_fail('Credit note not found.');
            $c['items'] = erp_rows($pdo, "SELECT i.*, p.product_name FROM sales_return_items i LEFT JOIN product_details p ON p.id = i.product_id WHERE i.return_id = ?", [$c['sales_return_id']]);
            erp_out(['status' => 'success', 'record' => $c]);

        case 'cn_issue_missing':
            $made = [];
            foreach (erp_rows($pdo, "SELECT r.id FROM sales_returns r WHERE r.status = 'posted' AND r.settlement <> 'replacement' AND NOT EXISTS (SELECT 1 FROM credit_notes c WHERE c.sales_return_id = r.id) ORDER BY r.id") as $r) { $n = cn_issue_for_return($pdo, (int)$r['id']); if ($n) $made[] = $n; }
            erp_out(['status' => 'success', 'message' => $made ? 'Issued ' . implode(', ', $made) . '.' : 'Every return already has a credit note.']);

        // ============================================================ CUSTOMER 360
        case 'cust_search':
            $q = trim((string)erp_input('q', ''));
            $like = "%$q%";
            $all = [];
            $add = function ($name, $mobile, $email, $date, $amount) use (&$all) {
                $mk = se_mobile($mobile);
                $k = $mk !== '' ? 'm:' . $mk : 'n:' . mb_strtolower(trim((string)$name));
                if ($k === 'n:') return;
                if (!isset($all[$k])) $all[$k] = ['key' => $k, 'name' => $name, 'mobile' => $mobile, 'email' => $email, 'documents' => 0, 'total' => 0, 'last' => $date];
                $all[$k]['documents']++; $all[$k]['total'] += (float)$amount;
                if ($date > $all[$k]['last']) $all[$k]['last'] = $date;
                if (!$all[$k]['email'] && $email) $all[$k]['email'] = $email;
            };
            $cond = $q === '' ? '1=1' : '(customer_name LIKE ? OR customer_mobile LIKE ?)';
            $pp = $q === '' ? [] : [$like, $like];
            foreach (erp_rows($pdo, "SELECT customer_name, customer_mobile, customer_email, invoice_date d, grand_total g FROM invoices WHERE status <> 'cancelled' AND $cond ORDER BY id DESC LIMIT 2000", $pp) as $r) $add($r['customer_name'], $r['customer_mobile'], $r['customer_email'], $r['d'], $r['g']);
            foreach (erp_rows($pdo, "SELECT customer_name, customer_mobile, sales_date d, grand_total g FROM manual_sales WHERE $cond ORDER BY id DESC LIMIT 2000", $pp) as $r) $add($r['customer_name'], $r['customer_mobile'], null, $r['d'], $r['g']);
            foreach (erp_rows($pdo, "SELECT customer_name, customer_mobile, sale_date d, grand_total g FROM credit_sales WHERE $cond ORDER BY id DESC LIMIT 2000", $pp) as $r) $add($r['customer_name'], $r['customer_mobile'], null, $r['d'], $r['g']);
            foreach (erp_rows($pdo, "SELECT CONCAT(first_name,' ',last_name) customer_name, phone customer_mobile, email, DATE(created_at) d, amount g FROM orders WHERE COALESCE(order_status,'') <> 'cancelled' AND (payment_status = 'paid' OR UPPER(payment_method) = 'COD')" .
                                     ($q === '' ? '' : " AND (CONCAT(first_name,' ',last_name) LIKE ? OR phone LIKE ? OR email LIKE ?)") . " ORDER BY id DESC LIMIT 2000", $q === '' ? [] : [$like, $like, $like]) as $r) $add(trim($r['customer_name']), $r['customer_mobile'], $r['email'], $r['d'], $r['g']);
            $rows = array_values($all);
            foreach ($rows as &$r) $r['total'] = erp_m($r['total']);
            unset($r);
            usort($rows, function ($a, $b) { return strcmp($b['last'], $a['last']); });
            erp_out(['status' => 'success', 'rows' => array_slice($rows, 0, 200)]);

        case 'cust_get':
            $key = (string)erp_input('key');
            if (!preg_match('/^(m|n):(.+)$/u', $key, $mm)) erp_fail('Choose a customer.');
            $byMobile = $mm[1] === 'm'; $val = $mm[2];
            $match = function ($name, $mobile) use ($byMobile, $val) { return $byMobile ? se_mobile($mobile) === $val : mb_strtolower(trim((string)$name)) === $val; };
            $docs = []; $ledger = []; $names = [];
            foreach (erp_rows($pdo, "SELECT id, invoice_number n, invoice_date d, customer_name c, customer_mobile m, customer_email e, billing_address a, grand_total g, amount_paid p, status, due_date FROM invoices WHERE status NOT IN ('draft','cancelled')") as $r) {
                if (!$match($r['c'], $r['m'])) continue;
                $names[$r['c']] = true;
                $docs[] = ['type' => 'invoice', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'total' => (float)$r['g'], 'paid' => (float)$r['p'], 'status' => $r['status'], 'link' => 'invoices.php?id=' . $r['id'], 'email' => $r['e'], 'address' => $r['a']];
                $ledger[] = ['date' => $r['d'], 'type' => 'Invoice', 'ref' => $r['n'], 'debit' => (float)$r['g'], 'credit' => 0];
            }
            foreach (erp_rows($pdo, "SELECT id, sale_number n, sales_date d, customer_name c, customer_mobile m, customer_address a, grand_total g, payment_status, payment_mode FROM manual_sales") as $r) {
                if (!$match($r['c'], $r['m'])) continue;
                $names[$r['c']] = true;
                $paid = $r['payment_status'] === 'paid' ? (float)$r['g'] : 0;
                $docs[] = ['type' => 'manual_sale', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'total' => (float)$r['g'], 'paid' => $paid, 'status' => $r['payment_status'], 'link' => 'manual_sales.php?id=' . $r['id'], 'address' => $r['a']];
                $ledger[] = ['date' => $r['d'], 'type' => 'Manual sale', 'ref' => $r['n'], 'debit' => (float)$r['g'], 'credit' => 0];
                if ($paid) $ledger[] = ['date' => $r['d'], 'type' => 'Paid at sale (' . $r['payment_mode'] . ')', 'ref' => $r['n'], 'debit' => 0, 'credit' => $paid];
            }
            foreach (erp_rows($pdo, "SELECT id, credit_number n, sale_date d, customer_name c, customer_mobile m, customer_address a, grand_total g, amount_paid p, status FROM credit_sales") as $r) {
                if (!$match($r['c'], $r['m'])) continue;
                $names[$r['c']] = true;
                $docs[] = ['type' => 'credit_sale', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'total' => (float)$r['g'], 'paid' => (float)$r['p'], 'status' => $r['status'], 'link' => 'credit_sale.php?id=' . $r['id'], 'address' => $r['a']];
                $ledger[] = ['date' => $r['d'], 'type' => 'Credit sale', 'ref' => $r['n'], 'debit' => (float)$r['g'], 'credit' => 0];
            }
            foreach (erp_rows($pdo, "SELECT id, receipt n, DATE(created_at) d, CONCAT(first_name,' ',last_name) c, phone m, email e, CONCAT_WS(', ', street_address, apartment, city, state, postcode) a, amount g, payment_status, payment_method, order_status FROM orders WHERE COALESCE(order_status,'') <> 'cancelled'") as $r) {
                if (!$match(trim($r['c']), $r['m'])) continue;
                if ($r['payment_status'] !== 'paid' && strtoupper((string)$r['payment_method']) !== 'COD') continue;
                $names[trim($r['c'])] = true;
                $paid = $r['payment_status'] === 'paid' ? (float)$r['g'] : 0;
                $docs[] = ['type' => 'website_order', 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'total' => (float)$r['g'], 'paid' => $paid, 'status' => ($r['order_status'] ?: 'ordered') . ' / ' . $r['payment_status'],
                           'link' => 'orders.php?id=' . $r['id'], 'email' => $r['e'], 'address' => $r['a']];
                $ledger[] = ['date' => $r['d'], 'type' => 'Website order', 'ref' => $r['n'], 'debit' => (float)$r['g'], 'credit' => 0];
                if ($paid) $ledger[] = ['date' => $r['d'], 'type' => 'Paid online / COD collected', 'ref' => $r['n'], 'debit' => 0, 'credit' => $paid];
            }
            if (!$docs) erp_fail('No sales found for this customer.');
            $numbers = array_column($docs, 'number');
            $payments = [];
            if ($numbers) {
                $in = implode(',', array_fill(0, count($numbers), '?'));
                $payments = erp_rows($pdo, "SELECT id, transaction_id, date, reference_type, reference_number, amount, payment_mode, status, description FROM accounts_transactions
                                            WHERE type = 'payment_received' AND status = 'completed' AND reference_type IN ('invoice','credit_sale','manual_sale') AND reference_number IN ($in) ORDER BY date, id", $numbers);
                foreach ($payments as $p) $ledger[] = ['date' => $p['date'], 'type' => 'Payment received (' . $p['payment_mode'] . ')', 'ref' => $p['reference_number'] . ' · ' . $p['transaction_id'], 'debit' => 0, 'credit' => (float)$p['amount']];
            }
            $returns = [];
            foreach ($docs as $d) foreach (erp_rows($pdo, "SELECT r.id, r.return_number, r.return_date, r.total_value, r.refund_amount, r.settlement, c.cn_number FROM sales_returns r LEFT JOIN credit_notes c ON c.sales_return_id = r.id
                                                           WHERE r.status = 'posted' AND r.source_type = ? AND r.source_id = ?", [$d['type'], $d['id']]) as $r) {
                $returns[] = $r + ['source_number' => $d['number']];
                if ($r['settlement'] === 'credit_note') $ledger[] = ['date' => $r['return_date'], 'type' => 'Credit note ' . ($r['cn_number'] ?: ''), 'ref' => $r['return_number'], 'debit' => 0, 'credit' => (float)$r['refund_amount']];
                elseif ($r['settlement'] === 'refund') { $ledger[] = ['date' => $r['return_date'], 'type' => 'Return', 'ref' => $r['return_number'], 'debit' => 0, 'credit' => (float)$r['refund_amount']];
                                                       $ledger[] = ['date' => $r['return_date'], 'type' => 'Refund paid', 'ref' => $r['return_number'], 'debit' => (float)$r['refund_amount'], 'credit' => 0]; }
            }
            usort($ledger, function ($a, $b) { return strcmp($a['date'], $b['date']); });
            $bal = 0; foreach ($ledger as &$l) { $bal += $l['debit'] - $l['credit']; $l['balance'] = erp_m($bal); } unset($l);
            $total = erp_m(array_sum(array_column($docs, 'total')));
            $credit = array_filter($docs, function ($d) { return $d['type'] === 'credit_sale'; });
            $first = $docs[0];
            $emails = array_values(array_unique(array_filter(array_column($docs, 'email'))));
            $addresses = array_values(array_unique(array_filter(array_column($docs, 'address'))));
            erp_out(['status' => 'success', 'customer' => ['key' => $key, 'names' => array_keys($names), 'mobile' => $byMobile ? $val : null, 'emails' => $emails, 'addresses' => array_slice($addresses, 0, 5)],
                     'kpis' => ['total_sales' => $total, 'documents' => count($docs), 'total_paid' => erp_m(array_sum(array_column($ledger, 'credit')) - array_sum(array_map(function ($r) { return $r['settlement'] === 'refund' || $r['settlement'] === 'credit_note' ? (float)$r['refund_amount'] : 0; }, $returns))),
                                'outstanding' => erp_m($bal), 'credit_sales' => erp_m(array_sum(array_column($credit, 'total'))), 'returns' => erp_m(array_sum(array_column($returns, 'total_value'))),
                                'refunds' => erp_m(array_sum(array_map(function ($r) { return $r['settlement'] === 'refund' ? (float)$r['refund_amount'] : 0; }, $returns))),
                                'first_purchase' => min(array_column($docs, 'date')), 'last_purchase' => max(array_column($docs, 'date'))],
                     'documents' => $docs, 'ledger' => $ledger, 'payments' => $payments, 'returns' => $returns]);

        // ============================================================ TRANSACTION TRACE
        case 'trace_search':
            $q = trim((string)erp_input('q', ''));
            if ($q === '') erp_fail('Enter an invoice / order / sale number, or a product name.');
            $like = "%$q%";
            $hits = [];
            foreach ([['invoice', "SELECT id, invoice_number n, invoice_date d, customer_name c FROM invoices WHERE invoice_number LIKE ? OR customer_name LIKE ? ORDER BY id DESC LIMIT 15"],
                      ['website_order', "SELECT id, receipt n, DATE(created_at) d, CONCAT(first_name,' ',last_name) c FROM orders WHERE receipt LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ? ORDER BY id DESC LIMIT 15"],
                      ['manual_sale', "SELECT id, sale_number n, sales_date d, customer_name c FROM manual_sales WHERE sale_number LIKE ? OR customer_name LIKE ? ORDER BY id DESC LIMIT 15"],
                      ['credit_sale', "SELECT id, credit_number n, sale_date d, customer_name c FROM credit_sales WHERE credit_number LIKE ? OR customer_name LIKE ? ORDER BY id DESC LIMIT 15"],
                      ['sales_order', "SELECT id, so_number n, order_date d, customer_name c FROM sales_orders WHERE so_number LIKE ? OR customer_name LIKE ? ORDER BY id DESC LIMIT 15"]] as [$t, $sql])
                foreach (erp_rows($pdo, $sql, [$like, $like]) as $r) $hits[] = ['type' => $t, 'id' => (int)$r['id'], 'number' => $r['n'], 'date' => $r['d'], 'customer' => $r['c']];
            $products = erp_rows($pdo, "SELECT id, product_name, quantity AS pack, stock FROM product_details WHERE product_name LIKE ? OR id = ? ORDER BY product_name LIMIT 15", [$like, (int)$q]);
            erp_out(['status' => 'success', 'documents' => $hits, 'products' => $products]);

        case 'trace':
            $type = (string)erp_input('type');
            $id = (int)erp_input('id');
            $head = null; $lines = []; $refs = [];
            switch ($type) {
                case 'invoice':
                    $head = erp_row($pdo, "SELECT id, invoice_number AS number, invoice_date AS date, customer_name AS customer, customer_mobile AS mobile, grand_total AS total, tax_amount AS tax, amount_paid AS paid, status, sales_order_id, dc_number FROM invoices WHERE id = ?", [$id]);
                    if (!$head) break;
                    $lines = erp_rows($pdo, "SELECT product_id, quantity, rate, discount, tax, line_total FROM invoice_items WHERE invoice_id = ?", [$id]);
                    foreach ($lines as &$l) $l['revenue'] = erp_m((float)$l['rate'] * (float)$l['quantity'] - (float)$l['discount']);
                    unset($l);
                    $refs = [['invoice', $head['number']]];
                    if ($head['sales_order_id']) {
                        $so = erp_val($pdo, "SELECT so_number FROM sales_orders WHERE id = ?", [$head['sales_order_id']]);
                        if ($so) $refs[] = ['sales_order', $so];
                        foreach (erp_rows($pdo, "SELECT dc_number FROM delivery_challans WHERE sales_order_id = ?", [$head['sales_order_id']]) as $dc) $refs[] = ['delivery_challan', $dc['dc_number']];
                    }
                    if ($head['dc_number']) $refs[] = ['delivery_challan', $head['dc_number']];
                    break;
                case 'website_order':
                    $head = erp_row($pdo, "SELECT id, receipt AS number, DATE(created_at) AS date, CONCAT(first_name,' ',last_name) AS customer, phone AS mobile, amount AS total, 0 AS tax, IF(payment_status = 'paid', amount, 0) AS paid, CONCAT(COALESCE(order_status,'ordered'),' / ',payment_status) AS status FROM orders WHERE id = ?", [$id]);
                    if (!$head) break;
                    $lines = erp_rows($pdo, "SELECT product_id, quantity, price AS rate, 0 AS discount, 0 AS tax, price * quantity AS line_total FROM order_items WHERE order_id = ?", [$id]);
                    foreach ($lines as &$l) $l['revenue'] = erp_m((float)$l['rate'] * (float)$l['quantity']);
                    unset($l);
                    $refs = [['website_order', $head['number']]];
                    break;
                case 'manual_sale':
                    $head = erp_row($pdo, "SELECT id, sale_number AS number, sales_date AS date, customer_name AS customer, customer_mobile AS mobile, grand_total AS total, total_tax AS tax, IF(payment_status = 'paid', grand_total, 0) AS paid, payment_status AS status FROM manual_sales WHERE id = ?", [$id]);
                    if (!$head) break;
                    $lines = erp_rows($pdo, "SELECT product_id, quantity, rate, discount, tax, line_total FROM manual_sale_items WHERE manual_sale_id = ?", [$id]);
                    foreach ($lines as &$l) $l['revenue'] = erp_m((float)$l['rate'] * (float)$l['quantity'] - (float)$l['discount']);
                    unset($l);
                    $refs = [['manual_sale', $head['number']], ['manual_sales', $head['number']]];
                    break;
                case 'credit_sale':
                    $head = erp_row($pdo, "SELECT id, credit_number AS number, sale_date AS date, customer_name AS customer, customer_mobile AS mobile, grand_total AS total, total_tax AS tax, amount_paid AS paid, status FROM credit_sales WHERE id = ?", [$id]);
                    if (!$head) break;
                    $lines = erp_rows($pdo, "SELECT product_id, quantity, rate, discount, tax, line_total FROM credit_sale_items WHERE credit_sale_id = ?", [$id]);
                    foreach ($lines as &$l) $l['revenue'] = erp_m((float)$l['rate'] * (float)$l['quantity'] - (float)$l['discount']);
                    unset($l);
                    $refs = [['credit_sale', $head['number']]];
                    break;
                case 'sales_order':
                    $head = erp_row($pdo, "SELECT id, so_number AS number, order_date AS date, customer_name AS customer, customer_mobile AS mobile, grand_total AS total, total_tax AS tax, 0 AS paid, status FROM sales_orders WHERE id = ?", [$id]);
                    if (!$head) break;
                    $lines = erp_rows($pdo, "SELECT product_id, quantity, rate, discount, tax, line_total FROM sales_order_items WHERE sales_order_id = ?", [$id]);
                    foreach ($lines as &$l) $l['revenue'] = erp_m((float)$l['rate'] * (float)$l['quantity'] - (float)$l['discount']);
                    unset($l);
                    $refs = [['sales_order', $head['number']]];
                    foreach (erp_rows($pdo, "SELECT dc_number FROM delivery_challans WHERE sales_order_id = ?", [$id]) as $dc) $refs[] = ['delivery_challan', $dc['dc_number']];
                    foreach (erp_rows($pdo, "SELECT invoice_number FROM invoices WHERE sales_order_id = ?", [$id]) as $iv) $refs[] = ['invoice', $iv['invoice_number']];
                    break;
                default:
                    erp_fail('Choose a sale document to trace.');
            }
            if (!$head) erp_fail('Document not found.');
            $head['channel'] = $type === 'sales_order' ? 'offline' : acc_channel($pdo, $type, $id);
            fefo_sync($pdo);
            $pids = array_values(array_unique(array_map('intval', array_column($lines, 'product_id'))));
            $trail = []; ce_run($pdo, 'product', null, null, $pids ?: [0], $trail);
            $cost = []; foreach ($trail as $t) if ($t['kind'] === 'move' && $t['id']) $cost[$t['id']] = $t;
            $refNos = array_values(array_unique(array_column($refs, 1)));
            $moves = [];
            if ($refNos && $pids) {
                $inR = implode(',', array_fill(0, count($refNos), '?')); $inP = implode(',', array_fill(0, count($pids), '?'));
                $moves = erp_rows($pdo, "SELECT m.*, w.name AS warehouse_name FROM stock_movements m LEFT JOIN warehouses w ON w.id = m.warehouse_id WHERE m.reference_number IN ($inR) AND m.product_id IN ($inP) AND m.new_stock < m.previous_stock ORDER BY m.id", array_merge($refNos, $pids));
            }
            $entities = [[$type, $id]];
            $totRev = 0; $totCogs = 0; $estimated = false;
            $pool = []; foreach ($moves as $m) $pool[(int)$m['product_id']][] = $m;
            foreach ($lines as &$l) {
                $pid = (int)$l['product_id'];
                $item = erp_item($pdo, 'product', $pid);
                $l['product_name'] = $item['name'] ?? ('#' . $pid);
                $l['movements'] = [];
                $need = (float)$l['quantity']; $cogs = 0;
                foreach ($pool[$pid] ?? [] as $k => $m) {
                    if ($need <= 0.0005) break;
                    $q = (float)$m['previous_stock'] - (float)$m['new_stock'];
                    $t = $cost[(int)$m['id']] ?? null;
                    $mv = ['movement_id' => (int)$m['id'], 'date' => $m['created_at'], 'warehouse' => $m['warehouse_name'], 'reference' => $m['reference_type'] . ' ' . $m['reference_number'], 'quantity' => erp_q($q),
                           'unit_cost' => $t['unit_cost'] ?? null, 'cogs' => $t ? erp_m(-$t['value']) : null, 'batches' => []];
                    foreach (erp_rows($pdo, "SELECT a.quantity, b.* FROM batch_allocations a JOIN inventory_batches b ON b.id = a.batch_id WHERE a.item_type = 'product' AND a.movement_id = ?", [$m['id']]) as $b) {
                        $o = null;
                        if ($b['source_type'] === 'grn') {
                            $o = erp_row($pdo, "SELECT g.id AS grn_id, g.grn_number, g.received_date, g.warehouse_id, po.id AS po_id, po.po_number, po.po_date, s.id AS supplier_id, s.supplier_name,
                                                       gi.received_qty, gi.accepted_qty, gi.rejected_qty, gi.rate AS purchase_rate,
                                                       pi.id AS pinv_id, pi.pinv_number, pi.supplier_invoice_no, pii.landed_unit_cost, pii.allocated_charges
                                                FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id JOIN goods_receipt_items gi ON gi.id = ?
                                                LEFT JOIN purchase_orders po ON po.id = g.po_id
                                                LEFT JOIN purchase_invoice_items pii ON pii.grn_item_id = gi.id LEFT JOIN purchase_invoices pi ON pi.id = pii.pinv_id AND pi.status = 'posted'
                                                WHERE g.id = ? ORDER BY pi.id DESC LIMIT 1", [$b['source_item_id'], $b['source_id']]);
                            if ($o) {
                                $o['shipments'] = erp_rows($pdo, "SELECT id, shipment_number, transport_company, transporter_name, vehicle_number, lr_number, total_cost, cost_applied FROM inbound_shipments WHERE (grn_id = ? OR (po_id = ? AND grn_id IS NULL)) AND status <> 'cancelled'", [$o['grn_id'], $o['po_id']]);
                                $o['transport_per_unit'] = 0;
                                $shipLanded = (float)erp_val($pdo, "SELECT COALESCE(SUM(value),0) FROM inventory_cost_entries WHERE reference_type = 'shipment' AND item_type = 'product' AND item_id = ? AND source_id IN (SELECT id FROM inbound_shipments WHERE grn_id = ?) AND status = 'active'", [$pid, $o['grn_id']]);
                                if ((float)$o['accepted_qty'] > 0) $o['transport_per_unit'] = erp_u($shipLanded / (float)$o['accepted_qty']);
                                $o['qc'] = erp_row($pdo, "SELECT id, qc_number, status, inspected_by, inspection_date FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$o['grn_id']]);
                                $o['po_approved_by'] = $o['po_id'] ? erp_val($pdo, "SELECT approved_by FROM purchase_orders WHERE id = ?", [$o['po_id']]) : null;
                                $o['payments'] = $o['pinv_id'] ? erp_rows($pdo, "SELECT id, payment_number, payment_date, amount, payment_mode, reference_number FROM purchase_payments WHERE pinv_id = ? AND status = 'completed'", [$o['pinv_id']]) : [];
                                foreach ([['grn', $o['grn_id']], ['purchase_order', $o['po_id']], ['purchase_invoice', $o['pinv_id']], ['supplier', $o['supplier_id']]] as $e) if ($e[1]) $entities[] = [$e[0], (int)$e[1]];
                                foreach ($o['shipments'] as $s) $entities[] = ['shipment', (int)$s['id']];
                                if ($o['qc']) $entities[] = ['qc', (int)$o['qc']['id']];
                                foreach ($o['payments'] as $p) $entities[] = ['purchase_payment', (int)$p['id']];
                            }
                        } elseif ($b['source_type'] === 'repack') {
                            $o = erp_row($pdo, "SELECT j.id AS repack_id, j.repack_number, j.repack_date, r.name AS raw_material, j.material_cost, j.packing_cost FROM repack_jobs j JOIN raw_materials r ON r.id = j.raw_material_id WHERE j.id = ?", [$b['source_id']]);
                        }
                        $entities[] = ['batch', (int)$b['id']];
                        $mv['batches'][] = ['batch_id' => (int)$b['id'], 'batch_number' => $b['batch_number'], 'lot_number' => $b['lot_number'], 'expiry_date' => $b['expiry_date'], 'manufacturing_date' => $b['manufacturing_date'],
                                            'quantity' => erp_q($b['quantity']), 'unit_cost' => (float)$b['unit_cost'], 'source_type' => $b['source_type'], 'origin' => $o];
                    }
                    $l['movements'][] = $mv;
                    $cogs += $mv['cogs'] ?? 0; $need -= $q;
                    unset($pool[$pid][$k]);
                }
                if ($need > 0.0005) {   // billed but not (yet) deducted from stock → estimated at the current average cost
                    $avg = ce_current_avg($pdo, 'product', $pid);
                    $l['cogs_estimated'] = erp_m($need * $avg); $cogs += $l['cogs_estimated']; $estimated = true;
                    $l['undeducted_qty'] = erp_q($need);
                }
                $l['cogs'] = erp_m($cogs); $l['gross_profit'] = erp_m($l['revenue'] - $cogs);
                $l['margin_pct'] = $l['revenue'] > 0 ? round($l['gross_profit'] / $l['revenue'] * 100, 2) : null;
                $totRev += $l['revenue']; $totCogs += $cogs;
            }
            unset($l);
            $receipts = erp_rows($pdo, "SELECT transaction_id, date, amount, payment_mode, reference_number FROM accounts_transactions WHERE type = 'payment_received' AND status = 'completed' AND reference_number = ?", [$head['number']]);
            $journals = erp_rows($pdo, "SELECT id, journal_number, event, entry_date, total, status FROM journal_entries WHERE source_type = ? AND source_id = ?", [$type, (string)$id]);
            // documents across the whole chain
            $docs = []; $seen = [];
            foreach ($entities as [$t, $i]) {
                foreach (array_merge([[$t, $i]], doc_related($pdo, $t, $i)) as [$tt, $ii]) {
                    if (isset($seen["$tt:$ii"])) continue;
                    $seen["$tt:$ii"] = true;
                    foreach (erp_rows($pdo, "SELECT d.id, d.original_name, d.doc_type, d.created_at, d.uploaded_by, COALESCE(m.category,'') category FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id
                                             WHERE d.entity_type = ? AND d.entity_id = ? AND COALESCE(m.status,'active') = 'active'", [$tt, $ii]) as $d)
                        $docs[] = $d + ['entity_type' => $tt, 'entity_label' => (doc_entities()[$tt][0] ?? $tt) . ' ' . doc_entity_label($pdo, $tt, $ii), 'category' => $d['category'] ?: doc_type_category($d['doc_type'])];
                }
            }
            $auditMods = ['purchase_orders', 'goods_receipts', 'purchase_invoices', 'purchase_payments', 'quality_checks', 'inbound_shipments'];
            $audit = [];
            $ids = []; foreach ($entities as [$t, $i]) $ids[$t][] = $i;
            $mapMod = ['purchase_order' => 'purchase_orders', 'grn' => 'goods_receipts', 'purchase_invoice' => 'purchase_invoices', 'purchase_payment' => 'purchase_payments', 'qc' => 'quality_checks', 'shipment' => 'inbound_shipments'];
            foreach ($mapMod as $t => $mod) if (!empty($ids[$t])) {
                $in = implode(',', array_map('intval', array_unique($ids[$t])));
                foreach (erp_rows($pdo, "SELECT username, action, module, record_id, created_at FROM audit_logs WHERE module = ? AND record_id IN ($in) ORDER BY id", [$mod]) as $a) $audit[] = $a;
            }
            usort($audit, function ($a, $b) { return strcmp($a['created_at'], $b['created_at']); });
            erp_out(['status' => 'success', 'type' => $type, 'document' => $head, 'lines' => $lines,
                     'totals' => ['revenue' => erp_m($totRev), 'cogs' => erp_m($totCogs), 'gross_profit' => erp_m($totRev - $totCogs), 'margin_pct' => $totRev > 0 ? round(($totRev - $totCogs) / $totRev * 100, 2) : null, 'cogs_estimated' => $estimated,
                                  'outstanding' => erp_m((float)$head['total'] - (float)$head['paid'])],
                     'receipts' => $receipts, 'journals' => $journals, 'documents' => $docs, 'audit' => $audit]);

        // ============================================================ REPORTS
        case 'sales_report':
            $docs = se_sale_docs($pdo, $from, $to);
            if ($c = erp_input('channel')) $docs = array_values(array_filter($docs, function ($d) use ($c) { return $d['channel'] === $c; }));
            if ($t = erp_input('type')) $docs = array_values(array_filter($docs, function ($d) use ($t) { return $d['type'] === $t; }));
            if ($q = mb_strtolower(trim((string)erp_input('q', '')))) $docs = array_values(array_filter($docs, function ($d) use ($q) { return strpos(mb_strtolower($d['number'] . ' ' . $d['customer'] . ' ' . $d['mobile']), $q) !== false; }));
            usort($docs, function ($a, $b) { return strcmp($b['date'], $a['date']); });
            erp_out(['status' => 'success', 'rows' => $docs, 'totals' => ['revenue' => erp_m(array_sum(array_column($docs, 'revenue'))), 'tax' => erp_m(array_sum(array_column($docs, 'tax'))), 'total' => erp_m(array_sum(array_column($docs, 'total')))]]);

        case 'category_profitability':
            $cats = [];
            foreach (rep_profitability($pdo, $from, $to) as $r) {
                $k = $r['category'] ?: 'Uncategorised';
                if (!isset($cats[$k])) $cats[$k] = ['category' => $k, 'products' => 0, 'qty_sold' => 0, 'net_revenue' => 0, 'cogs' => 0, 'gross_profit' => 0, 'returns_value' => 0, 'waste_value' => 0, 'stock_value' => 0];
                $cats[$k]['products']++;
                foreach (['qty_sold', 'net_revenue', 'cogs', 'gross_profit', 'returns_value', 'waste_value', 'stock_value'] as $f) $cats[$k][$f] += $r[$f];
            }
            foreach ($cats as &$c) { foreach ($c as $f => $v) if (is_float($v)) $c[$f] = erp_m($v); $c['margin_pct'] = $c['net_revenue'] > 0 ? round($c['gross_profit'] / $c['net_revenue'] * 100, 2) : null; }
            unset($c);
            $rows = array_values($cats);
            usort($rows, function ($a, $b) { return $b['gross_profit'] <=> $a['gross_profit']; });
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'cogs_report':
            $map = se_ref_map($pdo);
            $trail = []; ce_run($pdo, 'product', $from, $to, ($pid = (int)erp_input('product_id', 0)) ? [$pid] : null, $trail);
            $cat = trim((string)erp_input('category', ''));
            $rows = [];
            foreach ($trail as $t) {
                if (!in_array($t['bucket'], ['sale', 'sales_return', 'landed_cogs'], true)) continue;
                $src = $t['kind'] === 'move' ? ($map[strtolower($t['ref_type']) . '|' . $t['ref_no']] ?? null) : null;
                $rows[] = ['date' => $t['date'], 'item_type' => 'product', 'item_id' => $t['item_id'], 'movement_id' => $t['id'], 'kind' => $t['bucket'] === 'sale' ? 'Sold' : ($t['bucket'] === 'sales_return' ? 'Return restocked' : 'Landed cost on sold stock'),
                           'reference' => trim($t['ref_type'] . ' ' . $t['ref_no']), 'quantity' => -$t['qty'], 'unit_cost' => $t['unit_cost'] ?? null, 'cogs' => erp_m(-$t['value'] * ($t['bucket'] === 'landed_cogs' ? -1 : 1)),
                           'channel' => $t['kind'] === 'move' ? se_channel_of($pdo, $src) : 'offline', 'source' => $src ? $src[0] . ':' . $src[1] : null];
            }
            $rows = erp_attach_item_names($pdo, $rows);
            if ($cat !== '') $rows = array_values(array_filter($rows, function ($r) use ($cat) { return strcasecmp((string)$r['item_category'], $cat) === 0; }));
            if ($ch = erp_input('channel')) $rows = array_values(array_filter($rows, function ($r) use ($ch) { return $r['channel'] === $ch; }));
            $est = array_sum(array_column(rep_profitability($pdo, $from, $to), 'cogs_estimated'));
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => erp_m(array_sum(array_column($rows, 'cogs'))), 'estimated_undeducted' => erp_m($est),
                     'note' => 'Each row is a stock movement valued at the weighted-average cost at that moment. "Estimated" = billed quantity that never left stock (shown in P&L too).']);

        case 'customer_payments':
            $rows = erp_rows($pdo, "SELECT transaction_id AS number, date, party_name AS customer, reference_type, reference_number, amount, payment_mode, 'cash-book' AS source FROM accounts_transactions
                                    WHERE type = 'payment_received' AND status = 'completed' AND reference_type IN ('invoice','credit_sale','manual_sale') AND date BETWEEN ? AND ?", [$from, $to]);
            foreach (erp_rows($pdo, "SELECT receipt AS number, DATE(created_at) date, CONCAT(first_name,' ',last_name) customer, 'website_order' reference_type, receipt reference_number, amount, payment_method payment_mode, 'online' source
                                     FROM orders WHERE payment_status = 'paid' AND COALESCE(order_status,'') <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]) as $r) $rows[] = $r;
            foreach (erp_rows($pdo, "SELECT sale_number AS number, sales_date date, customer_name customer, 'manual_sale' reference_type, sale_number reference_number, grand_total amount, payment_mode, 'paid at sale' source
                                     FROM manual_sales WHERE payment_status = 'paid' AND sales_date BETWEEN ? AND ?", [$from, $to]) as $r) $rows[] = $r;
            usort($rows, function ($a, $b) { return strcmp($b['date'], $a['date']); });
            $modes = []; foreach ($rows as $r) $modes[$r['payment_mode']] = erp_m(($modes[$r['payment_mode']] ?? 0) + $r['amount']);
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => erp_m(array_sum(array_column($rows, 'amount'))), 'by_mode' => $modes]);

        case 'grn_report':
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT g.grn_number, g.id AS grn_id, g.received_date, g.status, s.supplier_name, w.name AS warehouse_name, po.po_number, gi.item_type, gi.item_id, gi.ordered_qty,
                                                                       gi.received_qty, gi.accepted_qty, gi.rejected_qty, gi.unit, gi.rate, gi.accepted_qty * gi.rate AS accepted_value, gi.batch_number, gi.expiry_date, gi.qc_status, gi.rejection_reason
                                                                FROM goods_receipt_items gi JOIN goods_receipts g ON g.id = gi.grn_id JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN warehouses w ON w.id = g.warehouse_id
                                                                LEFT JOIN purchase_orders po ON po.id = g.po_id WHERE g.received_date BETWEEN ? AND ?" . (($sid = (int)erp_input('supplier_id', 0)) ? " AND g.supplier_id = {$sid}" : '') . " ORDER BY g.received_date DESC, g.id DESC", [$from, $to]));
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'qc_report':
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT q.qc_number, q.id AS qc_id, q.status, q.inspection_date, q.inspected_by, g.grn_number, s.supplier_name, i.item_type, i.item_id, i.batch_number,
                                                                       i.received_qty, i.accepted_qty, i.rejected_qty, i.damaged_qty, i.result, i.rejection_reason
                                                                FROM quality_check_items i JOIN quality_checks q ON q.id = i.qc_id JOIN goods_receipts g ON g.id = q.grn_id JOIN suppliers s ON s.id = g.supplier_id
                                                                WHERE DATE(q.created_at) BETWEEN ? AND ? ORDER BY q.id DESC", [$from, $to]));
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'purchase_return_report':
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT r.return_number, r.id AS return_id, r.return_date, r.settlement, r.reason, s.supplier_name, g.grn_number, d.dn_number, i.item_type, i.item_id, i.quantity, i.unit, i.rate, i.line_value
                                                                FROM purchase_return_items i JOIN purchase_returns r ON r.id = i.return_id JOIN suppliers s ON s.id = r.supplier_id LEFT JOIN goods_receipts g ON g.id = r.grn_id
                                                                LEFT JOIN debit_notes d ON d.purchase_return_id = r.id WHERE r.status = 'posted' AND r.return_date BETWEEN ? AND ? ORDER BY r.return_date DESC", [$from, $to]));
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'erp_audit':
            $w = ["created_at BETWEEN ? AND ?"]; $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
            if ($m = erp_input('module')) { $w[] = 'module = ?'; $p[] = $m; }
            if ($a = erp_input('audit_action')) { $w[] = 'action = ?'; $p[] = $a; }
            if ($u = trim((string)erp_input('user', ''))) { $w[] = 'username LIKE ?'; $p[] = "%$u%"; }
            if ($r = trim((string)erp_input('record_id', ''))) { $w[] = 'record_id = ?'; $p[] = $r; }
            [$lim, $off] = erp_page_args(100);
            $where = implode(' AND ', $w);
            $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE {$where}", $p);
            $rows = erp_rows($pdo, "SELECT id, username, action, module, record_id, old_value, new_value, created_at FROM audit_logs WHERE {$where} ORDER BY id DESC LIMIT {$lim} OFFSET {$off}", $p);
            foreach ($rows as &$r) { foreach (['old_value', 'new_value'] as $k) if ($r[$k] !== null && strlen($r[$k]) > 1500) $r[$k] = substr($r[$k], 0, 1500) . '…'; }
            unset($r);
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => $total,
                     'modules' => array_column(erp_rows($pdo, "SELECT DISTINCT module FROM audit_logs ORDER BY module"), 'module'),
                     'actions' => array_column(erp_rows($pdo, "SELECT DISTINCT action FROM audit_logs ORDER BY action"), 'action')]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'sales');
}
