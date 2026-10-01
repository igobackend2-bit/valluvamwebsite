<?php
// ============================================================================
// Role dashboards (added 1 Oct 2026) — "My tasks" + KPIs for:
//   executive (Valluvam Team Executive) · manager (Valluvam Team Manager) ·
//   l1 (L1 Sourcing) · admin (Admin) · ceo (CEO) · accounts (Accounts Team)
// Super Admin sees every section. Read-only: approvals are done with the
// existing module actions (purchase_api / approvals_api), which check roles again.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/pf_lib.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';

erp_guard($pdo, null);
$action = (string)erp_input('action', 'get');

function rd_tables(PDO $pdo): bool { try { $pdo->query("SELECT 1 FROM purchase_flows LIMIT 1"); $pdo->query("SELECT 1 FROM approval_requests LIMIT 1"); return true; } catch (PDOException $e) { return false; } }
function rd_kpi(string $label, $value, string $fmt = 'n', string $hint = '', string $tone = ''): array { return compact('label', 'value', 'fmt', 'hint', 'tone'); }
function rd_hours(PDO $pdo, string $sql, array $p = []): ?float { $v = erp_val($pdo, $sql, $p); return $v === null || $v === false ? null : round((float)$v, 1); }
function rd_items(PDO $pdo, int $prId): string {
    $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_request_items WHERE pr_id = ? ORDER BY id LIMIT 4", [$prId]));
    $rows = pf_attach_units($pdo, $rows);
    return implode(', ', array_map(fn($r) => $r['item_name'] . ' × ' . ($r['input_unit'] ? rtrim(rtrim(number_format((float)$r['input_qty'], 3, '.', ''), '0'), '.') . ' ' . $r['input_unit'] : (float)$r['quantity']), $rows));
}
/** Flow stage of every request (same order as the Purchase Flow page). */
function rd_flows(PDO $pdo, string $where = '1=1', array $p = []): array {
    $rows = erp_rows($pdo, "SELECT pr.id, pr.pr_number, pr.request_date, pr.required_by, pr.status AS pr_status, pr.requested_by, pr.created_by, pr.backend_approved_at,
                                   f.quote_status, f.payment_id, f.payment_proof_doc_id, f.delivery_mode, f.loading_dc_doc_id, f.shop_bill_doc_id, f.unload_check, f.unloading_dc_doc_id,
                                   f.grn_id, f.qc_id, f.po_id, po.po_number, po.status AS po_status, po.grand_total, po.expected_delivery_date, s.supplier_name,
                                   (SELECT COUNT(*) FROM pr_quotes q WHERE q.pr_id = pr.id) AS quote_count, qc.status AS qc_status, g.status AS grn_status
                            FROM purchase_requests pr LEFT JOIN purchase_flows f ON f.pr_id = pr.id LEFT JOIN purchase_orders po ON po.id = f.po_id
                            LEFT JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN quality_checks qc ON qc.id = f.qc_id LEFT JOIN goods_receipts g ON g.id = f.grn_id
                            WHERE {$where} ORDER BY pr.id DESC LIMIT 200", $p);
    foreach ($rows as &$r) {
        $poOk = in_array($r['po_status'], ['approved', 'partially_received', 'fully_received', 'closed'], true);
        $qcDone = in_array($r['qc_status'], ['passed', 'partially_passed', 'rejected'], true);
        $r['stage'] = match (true) {
            $r['pr_status'] === 'draft' => ['draft', 'Draft'],
            $r['pr_status'] === 'submitted' => ['manager', 'Waiting for Manager'],
            $r['pr_status'] === 'manager_approved' => ['admin_pr', 'Waiting for Admin'],
            in_array($r['pr_status'], ['manager_rejected', 'backend_rejected', 'rejected', 'cancelled'], true) => ['closed', ucfirst(str_replace('_', ' ', $r['pr_status']))],
            ($r['quote_status'] ?? 'collecting') !== 'approved' => (($r['quote_status'] ?? '') === 'submitted' ? ['admin_shop', 'Shop waiting for Admin'] : ['quotes', 'Collect 3 quotations']),
            !$r['po_number'] => ['po_missing', 'PO not created'],
            !$poOk => ['admin_po', 'PO waiting for approval'],
            !($r['payment_id'] && $r['payment_proof_doc_id']) => ['pay', 'Waiting for payment'],
            !$r['delivery_mode'] => ['transport', 'Enter transport'],
            !($r['loading_dc_doc_id'] && $r['shop_bill_doc_id']) => ['docs', 'Attach loading DC + shop bill'],
            !$r['grn_id'] => ['unload', 'Unloading check'],
            !$r['qc_id'] || !$qcDone => ['qc', 'Quality check'],
            $r['grn_status'] === 'draft' => ['post', 'Post goods receipt'],
            default => ['done', 'Completed'],
        };
    }
    unset($r);
    return $rows;
}
function rd_task(array $f, string $title, string $sub, array $actions = [], string $link = ''): array {
    return ['id' => (int)$f['id'], 'ref' => $f['pr_number'], 'title' => $title, 'sub' => $sub, 'date' => $f['request_date'], 'amount' => $f['grand_total'] ?? null,
            'stage' => $f['stage'][1], 'actions' => $actions, 'link' => $link ?: 'purchase_flow.php?pr_id=' . (int)$f['id']];
}

try {
    if (!rd_tables($pdo)) erp_out(['status' => 'success', 'role' => '', 'sections' => [], 'note' => 'Run purchase_flow_migration.sql and roles_dashboard_migration.sql to switch on role dashboards.']);
    $roleName = (string)($_SESSION['admin_role_name'] ?? '');
    $key = role_access_key($roleName);
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) $key = 'super';
    $userDash = user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0));   // dashboards given by the Super Admin
    $want = $key === 'super' ? ['executive', 'manager', 'l1', 'admin', 'ceo', 'accounts'] : ($userDash ?: ($key && $key !== 'super' ? [$key] : []));
    $m1 = date('Y-m-01'); $today = date('Y-m-d'); $me = erp_user();
    $flows = rd_flows($pdo, "pr.status <> 'cancelled'");
    $sections = [];

    foreach ($want as $k) {
        $tasks = []; $kpis = [];
        if ($k === 'executive') {
            $mine = array_values(array_filter($flows, fn($f) => $key === 'super' || $f['created_by'] === $me));
            foreach ($mine as $f) if ($f['stage'][0] !== 'done' && $f['stage'][0] !== 'closed')
                $tasks[] = rd_task($f, $f['pr_number'], rd_items($pdo, (int)$f['id']), [], $f['stage'][0] === 'draft' ? 'purchase_requests.php?id=' . $f['id'] : '');
            $mineSql = $key === 'super' ? '' : ' AND created_by = ' . $pdo->quote($me);
            $raised = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE request_date >= ?{$mineSql}", [$m1]);
            $dec = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status IN ('approved','converted','manager_rejected','backend_rejected'){$mineSql}");
            $ok = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status IN ('approved','converted'){$mineSql}");
            $kpis = [rd_kpi('Requests raised this month', $raised), rd_kpi('Approved', $dec ? round($ok * 100 / $dec) : null, 'pct', "{$ok} of {$dec} decided"),
                     rd_kpi('Average time to full approval', rd_hours($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, backend_approved_at))/60 FROM purchase_requests WHERE backend_approved_at IS NOT NULL{$mineSql}"), 'h'),
                     rd_kpi('In progress', count($tasks), 'n', '', count($tasks) ? 'amber' : '')];
            $sections[] = ['key' => $k, 'title' => 'My purchase requests', 'role' => 'Valluvam Team Executive', 'new' => 'purchase_requests.php?new=1', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'manager') {
            foreach ($flows as $f) if ($f['stage'][0] === 'manager')
                $tasks[] = rd_task($f, $f['pr_number'] . ' · ' . ($f['requested_by'] ?: $f['created_by']), rd_items($pdo, (int)$f['id']),
                    [['label' => 'Approve', 'kind' => 'pr_manager_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'pr_manager_reject', 'reason' => true]], 'purchase_requests.php?id=' . $f['id']);
            $kpis = [rd_kpi('Waiting for my approval', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'),
                     rd_kpi('Approved this month', (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE manager_approved_at >= ?", [$m1])),
                     rd_kpi('Rejected (all time)', (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status = 'manager_rejected'")),
                     rd_kpi('Average decision time', rd_hours($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, manager_approved_at))/60 FROM purchase_requests WHERE manager_approved_at IS NOT NULL"), 'h')];
            $sections[] = ['key' => $k, 'title' => 'Requests to approve (Manager)', 'role' => 'Valluvam Team Manager', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'l1') {
            $labels = ['quotes' => 'Collect 3 shop quotations', 'transport' => 'Enter transport', 'docs' => 'Attach loading DC + shop bill', 'unload' => 'Unloading + quantity check', 'qc' => 'Quality check', 'post' => 'Post goods receipt'];
            foreach ($flows as $f) if (isset($labels[$f['stage'][0]]))
                $tasks[] = rd_task($f, $labels[$f['stage'][0]] . ' — ' . $f['pr_number'], ($f['supplier_name'] ? $f['supplier_name'] . ' · ' : '') . rd_items($pdo, (int)$f['id']));
            $appr = erp_rows($pdo, "SELECT f.pr_id, f.quote_submitted_at, pr.backend_approved_at, (SELECT COUNT(*) FROM pr_quotes q WHERE q.pr_id = f.pr_id) n,
                                           (SELECT MAX(grand_total) FROM pr_quotes q WHERE q.pr_id = f.pr_id) hi, (SELECT grand_total FROM pr_quotes q WHERE q.pr_id = f.pr_id AND q.is_selected = 1 LIMIT 1) chosen
                                    FROM purchase_flows f JOIN purchase_requests pr ON pr.id = f.pr_id WHERE f.quote_status = 'approved'");
            $three = count(array_filter($appr, fn($a) => $a['n'] >= 3));
            $saved = array_sum(array_map(fn($a) => max(0, (float)$a['hi'] - (float)$a['chosen']), $appr));
            $days = array_filter(array_map(fn($a) => $a['quote_submitted_at'] && $a['backend_approved_at'] ? (strtotime($a['quote_submitted_at']) - strtotime($a['backend_approved_at'])) / 86400 : null, $appr), fn($x) => $x !== null);
            $grns = erp_rows($pdo, "SELECT g.received_date, po.expected_delivery_date FROM purchase_flows f JOIN goods_receipts g ON g.id = f.grn_id JOIN purchase_orders po ON po.id = f.po_id WHERE po.expected_delivery_date IS NOT NULL");
            $onTime = count(array_filter($grns, fn($g) => $g['received_date'] <= $g['expected_delivery_date']));
            $checked = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_flows WHERE unload_check IS NOT NULL");
            $mismatch = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_flows WHERE unload_check IS NOT NULL AND unload_ok = 0");
            $kpis = [rd_kpi('My open tasks', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'),
                     rd_kpi('Purchases with 3 quotations', count($appr) ? round($three * 100 / count($appr)) : null, 'pct', "{$three} of " . count($appr)),
                     rd_kpi('Saved vs highest quotation', $saved, 'money', 'all approved shops', 'green'),
                     rd_kpi('Days: approval → shop sent', $days ? round(array_sum($days) / count($days), 1) : null, 'd'),
                     rd_kpi('On-time delivery', $grns ? round($onTime * 100 / count($grns)) : null, 'pct', "{$onTime} of " . count($grns)),
                     rd_kpi('Quantity mismatch at unloading', $checked ? round($mismatch * 100 / $checked) : null, 'pct', "{$mismatch} of {$checked}", $mismatch ? 'amber' : '')];
            $sections[] = ['key' => $k, 'title' => 'Sourcing & delivery tasks (L1)', 'role' => 'L1 (Sourcing)', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'admin') {
            foreach ($flows as $f) if ($f['stage'][0] === 'admin_pr')
                $tasks[] = rd_task($f, 'Final approval — ' . $f['pr_number'] . ' · ' . ($f['requested_by'] ?: $f['created_by']), rd_items($pdo, (int)$f['id']),
                    [['label' => 'Approve', 'kind' => 'pr_backend_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'pr_backend_reject', 'reason' => true]], 'purchase_requests.php?id=' . $f['id']);
            foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.summary, r.amount, r.entity_id, r.submitted_by, r.submitted_at, r.execution_error FROM approval_requests r
                                     WHERE r.module = 'pr_quotation' AND r.status IN ('submitted','under_review') ORDER BY r.id") as $a)
                $tasks[] = ['id' => (int)$a['entity_id'], 'ref' => $a['request_number'], 'title' => 'Shop choice — ' . $a['summary'], 'sub' => 'Sent by ' . $a['submitted_by'] . ($a['execution_error'] ? ' · last try failed: ' . $a['execution_error'] : ''),
                            'date' => substr((string)$a['submitted_at'], 0, 10), 'amount' => $a['amount'], 'stage' => 'Shop waiting for Admin', 'approval_id' => (int)$a['id'],
                            'actions' => [['label' => 'Approve shop & create PO', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'purchase_flow.php?pr_id=' . (int)$a['entity_id'] . '#card-quotes'];
            foreach (erp_rows($pdo, "SELECT po.id, po.po_number, po.po_date, po.grand_total, po.pr_id, s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                                     WHERE po.status = 'pending_approval' AND NOT EXISTS (SELECT 1 FROM approval_requests r WHERE r.module = 'po_ceo' AND r.request_key = po.id AND r.status IN ('submitted','under_review')) ORDER BY po.id") as $po)
                $tasks[] = ['id' => (int)$po['id'], 'ref' => $po['po_number'], 'title' => 'Approve purchase order — ' . $po['po_number'] . ' · ' . $po['supplier_name'], 'sub' => $po['pr_id'] ? 'From request #' . $po['pr_id'] : '', 'date' => $po['po_date'],
                            'amount' => $po['grand_total'], 'stage' => 'PO waiting for approval', 'po_id' => (int)$po['id'],
                            'actions' => [['label' => 'Approve PO', 'kind' => 'po_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'po_reject', 'reason' => true]], 'link' => 'purchase_orders.php?id=' . $po['id']];
            $poVal = (float)erp_val($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders WHERE approved_at >= ?", [$m1]);
            $kpis = [rd_kpi('Waiting for my approval', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'),
                     rd_kpi('Average final-approval time', rd_hours($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, manager_approved_at, backend_approved_at))/60 FROM purchase_requests WHERE backend_approved_at IS NOT NULL AND manager_approved_at IS NOT NULL"), 'h'),
                     rd_kpi('PO value approved this month', $poVal, 'money'),
                     rd_kpi('Waiting for the CEO', (int)erp_val($pdo, "SELECT COUNT(*) FROM approval_requests WHERE module = 'po_ceo' AND status IN ('submitted','under_review')"), 'n', 'POs above ₹' . number_format((float)erp_setting($pdo, 'ceo_po_limit', 0)))];
            $sections[] = ['key' => $k, 'title' => 'Approvals (Admin)', 'role' => 'Admin', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'ceo') {
            foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.summary, r.amount, r.entity_id, r.submitted_by, r.submitted_at FROM approval_requests r
                                     WHERE r.module = 'po_ceo' AND r.status IN ('submitted','under_review') ORDER BY r.id") as $a)
                $tasks[] = ['id' => (int)$a['entity_id'], 'ref' => $a['request_number'], 'title' => $a['summary'], 'sub' => 'Admin approved · sent by ' . $a['submitted_by'], 'date' => substr((string)$a['submitted_at'], 0, 10),
                            'amount' => $a['amount'], 'stage' => 'Waiting for CEO', 'approval_id' => (int)$a['id'],
                            'actions' => [['label' => 'Approve PO', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'purchase_orders.php?id=' . $a['entity_id']];
            require_once __DIR__ . '/erp_report_lib.php';
            $kp = [];
            try {
                $p = rep_pnl($pdo, $m1, $today);
                $kp[] = rd_kpi('Sales this month', $p['net_sales'], 'money', '', 'green');
                $kp[] = rd_kpi('Gross profit this month', $p['gross_profit'], 'money');
                $kp[] = rd_kpi('Net profit this month', $p['net_profit'], 'money', '', $p['net_profit'] < 0 ? 'red' : 'green');
                $kp[] = rd_kpi('Stock value', rep_valuation($pdo, $today)['total_value'], 'money');
                $kp[] = rd_kpi('Payable to suppliers', array_sum(array_column(rep_supplier_summary($pdo), 'outstanding')), 'money', '', 'amber');
            } catch (Throwable $e) { error_log('[role dash ceo] ' . $e->getMessage()); }
            $poVal = (float)erp_val($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders WHERE approved_at >= ?", [$m1]);
            $saved = (float)erp_val($pdo, "SELECT COALESCE(SUM(GREATEST(0, (SELECT MAX(grand_total) FROM pr_quotes q WHERE q.pr_id = f.pr_id) - (SELECT grand_total FROM pr_quotes q WHERE q.pr_id = f.pr_id AND q.is_selected = 1 LIMIT 1))),0)
                                           FROM purchase_flows f WHERE f.quote_status = 'approved'");
            $qcRec = (float)erp_val($pdo, "SELECT COALESCE(SUM(received_qty),0) FROM quality_check_items"); $qcRej = (float)erp_val($pdo, "SELECT COALESCE(SUM(rejected_qty),0) FROM quality_check_items");
            $kpis = array_merge([rd_kpi('POs waiting for me', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'), rd_kpi('Purchases approved this month', $poVal, 'money'),
                                 rd_kpi('Saved through 3 quotations', $saved, 'money', '', 'green'), rd_kpi('QC rejection', $qcRec > 0 ? round($qcRej * 100 / $qcRec, 1) : null, 'pct', 'of quantity checked')], $kp);
            $sections[] = ['key' => $k, 'title' => 'CEO approvals & company KPIs', 'role' => 'CEO', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'accounts') {
            foreach ($flows as $f) if ($f['stage'][0] === 'pay')
                $tasks[] = rd_task($f, ($f['payment_id'] ? 'Attach payment proof — ' : 'Pay — ') . $f['po_number'] . ' · ' . $f['supplier_name'], $f['pr_number'], [], 'purchase_flow.php?pr_id=' . (int)$f['id'] . '#card-payment');
            $wait = array_sum(array_map(fn($f) => (float)$f['grand_total'], array_filter($flows, fn($f) => $f['stage'][0] === 'pay' && !$f['payment_id'])));
            $billsDue = (float)erp_val($pdo, "SELECT COALESCE(SUM(pi.grand_total - COALESCE((SELECT SUM(amount) FROM purchase_payments pp WHERE pp.pinv_id = pi.id AND pp.status = 'completed'),0)),0)
                                              FROM purchase_invoices pi WHERE pi.status = 'posted' AND pi.due_date IS NOT NULL AND pi.due_date < ?", [$today]);
            $kpis = [rd_kpi('POs waiting for payment', $wait, 'money', count(array_filter($flows, fn($f) => $f['stage'][0] === 'pay' && !$f['payment_id'])) . ' PO(s)', $wait > 0 ? 'amber' : 'green'),
                     rd_kpi('Paid to suppliers this month', (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE status = 'completed' AND payment_date >= ?", [$m1]), 'money'),
                     rd_kpi('Payments without proof', (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_flows WHERE payment_id IS NOT NULL AND payment_proof_doc_id IS NULL"), 'n', '', ''),
                     rd_kpi('Overdue supplier bills', $billsDue, 'money', '', $billsDue > 0 ? 'red' : 'green'),
                     rd_kpi('Bank lines not reconciled', (int)erp_val($pdo, "SELECT COUNT(*) FROM bank_statement_lines WHERE status = 'unmatched'"), 'n')];
            $sections[] = ['key' => $k, 'title' => 'Payments (Accounts Team)', 'role' => 'Accounts Team', 'tasks' => $tasks, 'kpis' => $kpis];
        }
    }
    erp_out(['status' => 'success', 'role' => $key, 'role_name' => $roleName, 'limited' => $key !== 'super' && role_access_pages($roleName, $userDash) !== null, 'sections' => $sections]);
} catch (Throwable $e) {
    erp_db_error($e, 'role dashboard');
}
