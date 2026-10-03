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
                                   f.grn_id, f.qc_id, f.po_id, f.quote_submitted_by, f.courier_name, f.tracking_number, f.vehicle_number, f.driver_name, f.driver_phone, po.po_number, po.status AS po_status, po.grand_total, po.expected_delivery_date, s.supplier_name,
                                   (SELECT COUNT(*) FROM pr_quotes q WHERE q.pr_id = pr.id) AS quote_count, qc.status AS qc_status, g.status AS grn_status,
                                   (SELECT COUNT(*) FROM approval_requests ar WHERE ar.module = 'pr_quotation_mgr' AND ar.request_key = pr.id AND ar.status IN ('submitted','under_review')) AS shop_mgr_open
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
            ($r['quote_status'] ?? 'collecting') !== 'approved' => (($r['quote_status'] ?? '') === 'submitted' ? ((int)$r['shop_mgr_open'] ? ['mgr_shop', 'Shop waiting for Manager'] : ['admin_shop', 'Shop waiting for Admin']) : ['quotes', 'Collect 3 quotations']),   // FIX (2 Oct 2026): Manager → Admin
            !$r['po_number'] => ['po_missing', 'PO not created'],
            !$poOk => ['admin_po', 'PO waiting for approval'],
            !($r['payment_id'] && $r['payment_proof_doc_id']) => ['pay', 'Waiting for payment'],
            // FIX (2 Oct 2026): clear loading / unloading stage names for every dashboard
            !$r['delivery_mode'] => ['transport', 'Waiting for loading (transport)'],
            !($r['loading_dc_doc_id'] && $r['shop_bill_doc_id']) => ['docs', 'Loading — attach DC + shop bill'],
            !$r['grn_id'] => ['unload', 'In transit — waiting for unloading'],
            !$r['qc_id'] || !$qcDone => ['qc', 'Quality check — Executive to check & report'],
            $r['grn_status'] === 'draft' => ['post', 'QC done — Executive to add to stock (Stock In sheet)'],
            default => ['done', 'Added to inventory — completed'],   // FIX (2 Oct 2026)
        };
    }
    unset($r);
    // FIX (2 Oct 2026): show how the goods are coming (courier + tracking number / own vehicle + driver) on every later stage
    foreach ($rows as &$r) if ($r['delivery_mode'] && in_array($r['stage'][0], ['docs', 'unload', 'qc', 'post'], true))
        $r['stage'][1] .= ' · ' . ($r['delivery_mode'] === 'courier' ? 'Courier ' . trim(($r['courier_name'] ?? '') . ($r['tracking_number'] ? ' · tracking ' . $r['tracking_number'] : ''))
                                                                  : 'Own vehicle ' . trim(($r['vehicle_number'] ?? '') . ($r['driver_name'] ? ' · driver ' . $r['driver_name'] . ($r['driver_phone'] ? ' ' . $r['driver_phone'] : '') : '')));
    unset($r);
    return $rows;
}
/** FIX (2 Oct 2026): monthly audits with the external auditor's sign-off (or waiting for it). */
function rd_signoffs_installed(PDO $pdo): bool { static $ok = null; if ($ok === null) { try { $pdo->query("SELECT 1 FROM sf_audit_signoffs LIMIT 1"); $ok = true; } catch (PDOException $e) { $ok = false; } } return $ok; }
function rd_audit_list(PDO $pdo, string $title): array {
    $rows = [];
    $sign = rd_signoffs_installed($pdo);
    foreach (erp_rows($pdo, "SELECT a.id, a.audit_number, a.audit_month, a.status, w.name AS warehouse" . ($sign ? ", s.auditor_name, s.auditor_org, s.auditor_type, s.qc_result, s.signed_at, s.qc_findings, s.report_doc_id, rd.original_name AS report_name" : '') . "
                             FROM sf_audits a JOIN warehouses w ON w.id = a.warehouse_id" . ($sign ? " LEFT JOIN sf_audit_signoffs s ON s.audit_id = a.id LEFT JOIN erp_documents rd ON rd.id = s.report_doc_id" : '') . "
                             WHERE a.status <> 'cancelled' ORDER BY a.audit_month DESC, a.id DESC LIMIT 12") as $a) {
        $signed = $sign && !empty($a['signed_at']);
        $rows[] = ['aud' => $a['audit_number'] . ' · ' . $a['audit_month'], 'loc' => $a['warehouse'],
                   'who' => $signed ? $a['auditor_name'] . ($a['auditor_org'] ? ' (' . $a['auditor_org'] . ')' : '') . ($a['auditor_type'] === 'internal' ? ' · internal' : '') : '—',
                   'res' => $signed ? ucfirst(str_replace('_', ' ', $a['qc_result'])) : 'Not signed', 'what' => $signed ? mb_substr((string)$a['qc_findings'], 0, 120) : ucwords(str_replace('_', ' ', $a['status'])),
                   'date' => $signed ? substr($a['signed_at'], 0, 10) : null,
                   'proof' => $signed && $a['report_doc_id'] ? ['href' => '../assets/db_query/admin/erp_docs.php?action=download&id=' . (int)$a['report_doc_id'], 'text' => $a['report_name']] : null,
                   'link' => 'stock_audit.php?id=' . (int)$a['id']];
    }
    return ['key' => 'audits', 'title' => $title, 'empty' => 'No monthly audit yet.', 'more' => 'stock_audit.php',
            'cols' => [['k' => 'aud', 'l' => 'Audit / month'], ['k' => 'loc', 'l' => 'Location'], ['k' => 'who', 'l' => 'Auditor (signed)'], ['k' => 'res', 'l' => 'Quality result', 'f' => 'status'],
                       ['k' => 'what', 'l' => 'Findings'], ['k' => 'date', 'l' => 'Signed on', 'f' => 'date'], ['k' => 'proof', 'l' => 'Report file', 'f' => 'file']], 'rows' => $rows];
}
function rd_task(array $f, string $title, string $sub, array $actions = [], string $link = ''): array {
    return ['id' => (int)$f['id'], 'ref' => $f['pr_number'], 'title' => $title, 'sub' => $sub, 'date' => $f['request_date'], 'amount' => $f['grand_total'] ?? null,
            'stage' => $f['stage'][1], 'actions' => $actions, 'link' => $link ?: 'purchase_flow.php?pr_id=' . (int)$f['id']];
}
/* ---- Lists for the Admin and CEO dashboards (added 1 Oct 2026) ---- */
/** Who approves each module: the purchase steps by their role, other modules by the roles holding the approver permission. */
function rd_approvers(PDO $pdo): array {
    $fixed = ['purchase_request' => 'Valluvam Team Manager', 'purchase_request_final' => 'Admin', 'pr_quotation' => 'Admin', 'purchase_order' => 'Admin', 'po_ceo' => 'CEO', 'pr_quotation_mgr' => 'Valluvam Team Manager', 'transport_charge_mgr' => 'Valluvam Team Manager', 'transport_charge' => 'Admin'];
    $out = [];
    try {
        foreach (erp_rows($pdo, "SELECT p.module, p.label, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles FROM approval_policies p
                                 LEFT JOIN admin_role_permissions rp ON rp.perm_key = p.approver_perm LEFT JOIN admin_roles r ON r.id = rp.role_id AND r.id <> 1
                                 GROUP BY p.module, p.label") as $p)
            $out[$p['module']] = ['label' => $p['label'], 'who' => $fixed[$p['module']] ?? ($p['roles'] ?: 'Super Admin')];
    } catch (PDOException $e) { error_log('[role dash approvers] ' . $e->getMessage()); }
    return $out;
}
function rd_link(array $a): string {
    $id = (int)$a['entity_id'];
    return match ($a['module']) {
        'purchase_request', 'purchase_request_final', 'pr_quotation', 'pr_quotation_mgr' => 'purchase_flow.php?pr_id=' . $id,
        'purchase_order', 'po_ceo', 'po_amendment' => 'purchase_orders.php?id=' . $id,
        default => 'approvals.php',
    };
}
function rd_lists(PDO $pdo, array $flows, bool $ceo): array {
    $who = rd_approvers($pdo); $lists = [];
    // 1. everything waiting for approval, and who must approve it
    $rows = [];
    foreach (erp_rows($pdo, "SELECT id, request_number, module, entity_id, reference, summary, amount, submitted_by, submitted_at FROM approval_requests
                             WHERE status IN ('submitted','under_review') ORDER BY submitted_at, id LIMIT 50") as $a)
        $rows[] = ['type' => $who[$a['module']]['label'] ?? ucwords(str_replace('_', ' ', $a['module'])), 'ref' => $a['reference'] ?: $a['request_number'], 'what' => $a['summary'],
                   'amount' => $a['amount'], 'by' => $a['submitted_by'], 'since' => $a['submitted_at'], 'who' => $who[$a['module']]['who'] ?? 'Super Admin', 'link' => rd_link($a)];
    $lists[] = ['key' => 'waiting', 'title' => 'Waiting for approval — and who approves', 'empty' => 'No approvals are waiting.',
                'cols' => [['k' => 'type', 'l' => 'Approval'], ['k' => 'ref', 'l' => 'Reference'], ['k' => 'what', 'l' => 'Details'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'money'],
                           ['k' => 'by', 'l' => 'Sent by'], ['k' => 'since', 'l' => 'Waiting since', 'f' => 'date'], ['k' => 'who', 'l' => 'Waiting for', 'f' => 'badge']], 'rows' => $rows];
    // 2. who approved / rejected what
    $rows = [];
    foreach (erp_rows($pdo, "SELECT id, request_number, module, entity_id, reference, summary, amount, status, submitted_by, decided_by, decided_at, remarks FROM approval_requests
                             WHERE status IN ('approved','rejected') ORDER BY decided_at DESC, id DESC LIMIT 15") as $a)
        $rows[] = ['type' => $who[$a['module']]['label'] ?? ucwords(str_replace('_', ' ', $a['module'])), 'ref' => $a['reference'] ?: $a['request_number'], 'what' => $a['summary'],
                   'amount' => $a['amount'], 'by' => $a['submitted_by'], 'dec' => $a['decided_by'], 'st' => ucfirst($a['status']), 'at' => $a['decided_at'], 'rem' => $a['remarks'], 'link' => rd_link($a)];
    $lists[] = ['key' => 'history', 'title' => 'Approval history — who approved or rejected', 'empty' => 'No decisions yet.',
                'cols' => [['k' => 'type', 'l' => 'Approval'], ['k' => 'ref', 'l' => 'Reference'], ['k' => 'what', 'l' => 'Details'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'money'],
                           ['k' => 'by', 'l' => 'Sent by'], ['k' => 'dec', 'l' => 'Decided by'], ['k' => 'st', 'l' => 'Result', 'f' => 'status'], ['k' => 'at', 'l' => 'On', 'f' => 'date'], ['k' => 'rem', 'l' => 'Remarks']], 'rows' => $rows];
    // 3. purchase pipeline: how many requests are at each step
    $stages = [];
    foreach ($flows as $f) { $s = $f['stage'][1]; $stages[$s] ??= ['stage' => $s, 'n' => 0, 'amount' => 0]; $stages[$s]['n']++; $stages[$s]['amount'] += (float)($f['grand_total'] ?? 0); }
    $lists[] = ['key' => 'pipeline', 'title' => 'Purchase pipeline — requests at each step', 'empty' => 'No purchase requests yet.',
                'cols' => [['k' => 'stage', 'l' => 'Step'], ['k' => 'n', 'l' => 'Requests', 'f' => 'n'], ['k' => 'amount', 'l' => 'PO value', 'f' => 'money']], 'rows' => array_values($stages)];
    // 4. transaction history (money in / out)
    if ($ceo) {
        $rows = [];
        try {
            foreach (erp_rows($pdo, "SELECT transaction_id, date, type, category, party_name, reference_number, amount, payment_mode, status FROM accounts_transactions ORDER BY date DESC, id DESC LIMIT 15") as $t)
                $rows[] = ['date' => $t['date'], 'txn' => $t['transaction_id'], 'type' => ucwords(str_replace('_', ' ', $t['type'])), 'cat' => $t['category'], 'party' => $t['party_name'],
                           'ref' => $t['reference_number'], 'amount' => in_array($t['type'], ['expense', 'payment_made', 'refund'], true) ? -abs((float)$t['amount']) : (float)$t['amount'],
                           'mode' => ucwords(str_replace('_', ' ', (string)$t['payment_mode'])), 'st' => ucfirst((string)$t['status'])];
        } catch (PDOException $e) { error_log('[role dash txns] ' . $e->getMessage()); }
        $lists[] = ['key' => 'txns', 'title' => 'Latest transactions (money in + / money out −)', 'empty' => 'No transactions yet.',
                    'cols' => [['k' => 'date', 'l' => 'Date', 'f' => 'date'], ['k' => 'txn', 'l' => 'Txn no.'], ['k' => 'type', 'l' => 'Type'], ['k' => 'cat', 'l' => 'Category'], ['k' => 'party', 'l' => 'Party'],
                               ['k' => 'ref', 'l' => 'Reference'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'signed'], ['k' => 'mode', 'l' => 'Mode'], ['k' => 'st', 'l' => 'Status', 'f' => 'status']], 'rows' => $rows];
    }
    // links only to pages this user may open (Executive / Manager are page-limited)
    $ok = role_access_pages((string)($_SESSION['admin_role_name'] ?? ''), user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)));
    $can = fn($l) => $ok === null || (int)($_SESSION['admin_role_id'] ?? 0) === 1 || in_array(strtok(basename((string)$l), '?#'), $ok, true);
    foreach ($lists as &$l) {
        if (in_array($l['key'], ['waiting', 'history'], true) && $can('approvals.php')) $l['more'] = 'approvals.php';
        foreach ($l['rows'] as &$r) if (!empty($r['link']) && !$can($r['link'])) unset($r['link']);
        unset($r);
    }
    unset($l);
    return $lists;
}

try {
    if (!rd_tables($pdo)) erp_out(['status' => 'success', 'role' => '', 'sections' => [], 'note' => 'Run purchase_flow_migration.sql and roles_dashboard_migration.sql to switch on role dashboards.']);
    $roleName = (string)($_SESSION['admin_role_name'] ?? '');
    $key = role_access_key($roleName);
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) $key = 'super';
    $userDash = user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0));   // dashboards given by the Super Admin
    $want = $key === 'super' ? ['executive', 'manager', 'l1', 'admin', 'ceo', 'accounts', 'auditor'] : ($userDash ?: ($key && $key !== 'super' ? [$key] : []));
    $m1 = date('Y-m-01'); $today = date('Y-m-d'); $me = erp_user();
    $flows = rd_flows($pdo, "pr.status <> 'cancelled'");
    $sections = [];

    foreach ($want as $k) {
        $tasks = []; $kpis = [];
        if ($k === 'executive') {
            $mine = array_values(array_filter($flows, fn($f) => $key === 'super' || $f['created_by'] === $me));
            // FIX (2 Oct 2026): after unloading the Executive checks the quality, then adds the goods to stock (Stock In: download sheet → upload) — for every purchase
            foreach ($flows as $f) if (in_array($f['stage'][0], ['qc', 'post'], true))
                $tasks[] = rd_task($f, ($f['stage'][0] === 'qc' ? 'Check the quality & write the report — ' : 'Add to stock — download the sheet, upload it in Stock In — ') . $f['pr_number'] . ($f['supplier_name'] ? ' · ' . $f['supplier_name'] : ''),
                                   rd_items($pdo, (int)$f['id']), [], $f['stage'][0] === 'qc' ? 'purchase_flow.php?pr_id=' . (int)$f['id'] . '#card-qc' : 'stock_in.php#grnPanel');
            $mine = array_values(array_filter($mine, fn($f) => !in_array($f['stage'][0], ['qc', 'post'], true)));
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
            // FIX (2 Oct 2026): courier / transport charges waiting for the Manager
            try {
                foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.summary, r.amount, r.submitted_by, r.submitted_at, c.pr_id FROM approval_requests r JOIN pf_transport_charges c ON CONCAT('tc', c.id) = r.request_key
                                         WHERE r.module = 'transport_charge_mgr' AND r.status IN ('submitted','under_review') ORDER BY r.id") as $a)
                    $tasks[] = ['id' => (int)$a['pr_id'], 'ref' => $a['request_number'], 'title' => 'Courier / transport charge — ' . $a['summary'], 'sub' => 'Raised by ' . $a['submitted_by'] . ' · check the bill in the purchase flow',
                                'date' => substr((string)$a['submitted_at'], 0, 10), 'amount' => $a['amount'], 'stage' => 'Charge waiting for Manager', 'approval_id' => (int)$a['id'],
                                'actions' => [['label' => 'Approve', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'purchase_flow.php?pr_id=' . (int)$a['pr_id'] . '#card-tcharge'];
            } catch (PDOException $e) { /* transport_charge_migration.sql not run */ }
            // FIX (2 Oct 2026): shop choice (3 quotations) waits for the Manager first, then the Admin
            foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.summary, r.amount, r.entity_id, r.submitted_by, r.submitted_at, r.execution_error FROM approval_requests r
                                     WHERE r.module = 'pr_quotation_mgr' AND r.status IN ('submitted','under_review') ORDER BY r.id") as $a)
                $tasks[] = ['id' => (int)$a['entity_id'], 'ref' => $a['request_number'], 'title' => 'Shop choice — ' . $a['summary'], 'sub' => 'Sent by ' . $a['submitted_by'] . ' (L1) · after you it goes to the Admin',
                            'date' => substr((string)$a['submitted_at'], 0, 10), 'amount' => $a['amount'], 'stage' => 'Shop waiting for Manager', 'approval_id' => (int)$a['id'],
                            'actions' => [['label' => 'Approve shop', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'purchase_flow.php?pr_id=' . (int)$a['entity_id'] . '#card-quotes'];
            // stock adjustments / counts / returns / PO changes waiting for the Manager (approver permission; never the accounts approvals) (1 Oct 2026)
            foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.module, r.entity_id, r.reference, r.summary, r.amount, r.submitted_by, r.submitted_at, p.label, p.approver_perm
                                     FROM approval_requests r JOIN approval_policies p ON p.module = r.module
                                     WHERE r.status IN ('submitted','under_review') AND r.module IN ('stock_adjustment','stock_count','sales_return','purchase_return','po_amendment') ORDER BY r.submitted_at, r.id") as $a)
                if ($key === 'super' || erp_can($pdo, (string)$a['approver_perm']))
                    $tasks[] = ['id' => (int)$a['entity_id'], 'ref' => $a['request_number'], 'title' => $a['label'] . ' — ' . ($a['reference'] ?: $a['request_number']), 'sub' => $a['summary'] . ' · sent by ' . $a['submitted_by'],
                                'date' => substr((string)$a['submitted_at'], 0, 10), 'amount' => $a['amount'], 'stage' => 'Waiting for Manager', 'approval_id' => (int)$a['id'],
                                'actions' => [['label' => 'Approve', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'approvals.php?module=' . rawurlencode($a['module'])];
            // waste records reported by the team wait for the Manager's approval (1 Oct 2026)
            try {
                foreach (erp_rows($pdo, "SELECT w.id, w.waste_id, w.date, w.waste_type, w.quantity, w.unit, w.reason, w.estimated_value, w.created_by, p.product_name
                                         FROM waste_records w LEFT JOIN product_details p ON p.id = w.product_id WHERE w.status = 'reported' ORDER BY w.date, w.id LIMIT 50") as $w)
                    $tasks[] = ['id' => (int)$w['id'], 'ref' => $w['waste_id'], 'title' => 'Waste ' . $w['waste_id'] . ' · ' . ($w['product_name'] ?: ucwords(str_replace('_', ' ', $w['waste_type']))),
                                'sub' => trim(($w['quantity'] ? $w['quantity'] . ' ' . $w['unit'] . ' · ' : '') . $w['reason'] . ' · reported by ' . $w['created_by']), 'date' => $w['date'],
                                'amount' => $w['estimated_value'], 'stage' => 'Waste waiting for approval', 'waste_id' => (int)$w['id'],
                                'actions' => [['label' => 'Approve', 'kind' => 'waste_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'waste_reject', 'reason' => true]], 'link' => 'waste.php'];
            } catch (PDOException $e) { /* waste table not installed */ }
            $kpis = [rd_kpi('Waiting for my approval', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'),
                     rd_kpi('Approved this month', (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE manager_approved_at >= ?", [$m1])),
                     rd_kpi('Rejected (all time)', (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status = 'manager_rejected'")),
                     rd_kpi('Average decision time', rd_hours($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, manager_approved_at))/60 FROM purchase_requests WHERE manager_approved_at IS NOT NULL"), 'h')];
            $sections[] = ['key' => $k, 'title' => 'Requests to approve (Manager)', 'role' => 'Valluvam Team Manager', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'l1') {
            $labels = ['quotes' => 'Collect 3 shop quotations', 'transport' => 'Buy the goods — enter transport', 'docs' => 'Buy the goods — attach loading DC + shop bill', 'unload' => 'Unload at the warehouse — check the quantity'];   // + unloading by the same L1 (2 Oct 2026)   // FIX (2 Oct 2026): L1 gets the quotation; the same person buys
            foreach ($flows as $f) if (isset($labels[$f['stage'][0]]) && ($f['stage'][0] === 'quotes' || $key === 'super' || $k !== 'l1' || ($f['quote_submitted_by'] ?? '') === erp_user()))
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
            // FIX (2 Oct 2026): courier / transport charges waiting for the Admin
            try {
                foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.summary, r.amount, r.submitted_by, r.submitted_at, c.pr_id FROM approval_requests r JOIN pf_transport_charges c ON CONCAT('tc', c.id) = r.request_key
                                         WHERE r.module = 'transport_charge' AND r.status IN ('submitted','under_review') ORDER BY r.id") as $a)
                    $tasks[] = ['id' => (int)$a['pr_id'], 'ref' => $a['request_number'], 'title' => 'Courier / transport charge — ' . $a['summary'], 'sub' => 'Raised by ' . $a['submitted_by'] . ' · check the bill in the purchase flow',
                                'date' => substr((string)$a['submitted_at'], 0, 10), 'amount' => $a['amount'], 'stage' => 'Charge waiting for Admin', 'approval_id' => (int)$a['id'],
                                'actions' => [['label' => 'Approve', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'purchase_flow.php?pr_id=' . (int)$a['pr_id'] . '#card-tcharge'];
            } catch (PDOException $e) { /* transport_charge_migration.sql not run */ }
            $poVal = (float)erp_val($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders WHERE approved_at >= ?", [$m1]);
            $kpis = [rd_kpi('Waiting for my approval', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'),
                     rd_kpi('Average final-approval time', rd_hours($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, manager_approved_at, backend_approved_at))/60 FROM purchase_requests WHERE backend_approved_at IS NOT NULL AND manager_approved_at IS NOT NULL"), 'h'),
                     rd_kpi('PO value approved this month', $poVal, 'money'),
                     rd_kpi('Waiting for the CEO', (int)erp_val($pdo, "SELECT COUNT(*) FROM approval_requests WHERE module = 'po_ceo' AND status IN ('submitted','under_review')"), 'n', 'POs above ₹' . number_format((float)erp_setting($pdo, 'ceo_po_limit', 0)))];
            $sections[] = ['key' => $k, 'title' => 'Approvals (Admin)', 'role' => 'Admin', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        // FIX (2 Oct 2026): External Auditor — monthly stock audits waiting for the auditor's QC check, report and signature
        if ($k === 'auditor') {
            try {
                $signedOk = rd_signoffs_installed($pdo);
                foreach (erp_rows($pdo, "SELECT a.id, a.audit_number, a.audit_month, a.audit_date, a.status, w.name AS warehouse FROM sf_audits a JOIN warehouses w ON w.id = a.warehouse_id
                                         WHERE a.status IN ('count_completed','verification_pending','approved')" . ($signedOk ? " AND NOT EXISTS (SELECT 1 FROM sf_audit_signoffs s WHERE s.audit_id = a.id)" : '') . " ORDER BY a.audit_date, a.id LIMIT 50") as $a)
                    $tasks[] = ['id' => (int)$a['id'], 'ref' => $a['audit_number'], 'title' => 'Audit & quality check — ' . $a['audit_number'] . ' · ' . $a['warehouse'] . ' · ' . $a['audit_month'],
                                'sub' => 'Check the stock and the quality, write the report and sign', 'date' => $a['audit_date'], 'amount' => null, 'stage' => 'Waiting for your signature', 'actions' => [], 'link' => 'stock_audit.php?id=' . (int)$a['id']];
                $signedMonth = $signedOk ? (int)erp_val($pdo, "SELECT COUNT(*) FROM sf_audit_signoffs WHERE signed_at >= ?", [$m1]) : 0;
                $issues = $signedOk ? (int)erp_val($pdo, "SELECT COUNT(*) FROM sf_audit_signoffs WHERE qc_result <> 'satisfactory' AND signed_at >= ?", [$m1]) : 0;
                $kpis = [rd_kpi('Audits waiting for me', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'), rd_kpi('Signed this month', $signedMonth),
                         rd_kpi('Quality issues found this month', $issues, 'n', 'needs improvement / unsatisfactory', $issues ? 'red' : 'green')];
                $lists = [rd_audit_list($pdo, 'My signed audit reports')];
                $sections[] = ['key' => $k, 'title' => 'Monthly stock audit & quality check (External Auditor)', 'role' => 'External Auditor', 'tasks' => $tasks, 'kpis' => $kpis, 'lists' => $lists];
            } catch (PDOException $e) { error_log('[role dash auditor] ' . $e->getMessage()); }
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
                $kp[] = rd_kpi('Stock value', rep_valuation($pdo, $today)['total_value'], 'money', 'goods received and posted');
                // owed (bills not yet paid) and advances (paid before the bill) shown apart, so the card is never a negative "payable"
                $outs = array_map('floatval', array_column(rep_supplier_summary($pdo), 'outstanding'));
                $kp[] = rd_kpi('Owed to suppliers', array_sum(array_filter($outs, fn($v) => $v > 0)), 'money', 'bills not yet paid', 'amber');
                $kp[] = rd_kpi('Advance paid, bill pending', abs(array_sum(array_filter($outs, fn($v) => $v < 0))), 'money', 'paid before the shop bill was entered');
            } catch (Throwable $e) { error_log('[role dash ceo] ' . $e->getMessage()); }
            $poVal = (float)erp_val($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders WHERE approved_at >= ?", [$m1]);
            $saved = (float)erp_val($pdo, "SELECT COALESCE(SUM(GREATEST(0, (SELECT MAX(grand_total) FROM pr_quotes q WHERE q.pr_id = f.pr_id) - (SELECT grand_total FROM pr_quotes q WHERE q.pr_id = f.pr_id AND q.is_selected = 1 LIMIT 1))),0)
                                           FROM purchase_flows f WHERE f.quote_status = 'approved'");
            $qcRec = (float)erp_val($pdo, "SELECT COALESCE(SUM(received_qty),0) FROM quality_check_items"); $qcRej = (float)erp_val($pdo, "SELECT COALESCE(SUM(rejected_qty),0) FROM quality_check_items");
            $kpis = array_merge([rd_kpi('POs waiting for me', count($tasks), 'n', '', count($tasks) ? 'amber' : 'green'), rd_kpi('Purchases approved this month', $poVal, 'money'),
                                 rd_kpi('Saved through 3 quotations', $saved, 'money', '', 'green'), rd_kpi('QC rejection', $qcRec > 0 ? round($qcRej * 100 / $qcRec, 1) : null, 'pct', 'of quantity checked')], $kp);
            foreach ($kpis as $i => &$kv) $kv['group'] = $i < 4 ? 'Purchasing' : 'Money & stock';   // two labelled KPI rows on the dashboard
            unset($kv);
            $sections[] = ['key' => $k, 'title' => 'CEO approvals & company KPIs', 'role' => 'CEO', 'tasks' => $tasks, 'kpis' => $kpis];
        }
        if ($k === 'accounts') {
            foreach ($flows as $f) if ($f['stage'][0] === 'pay')
                $tasks[] = rd_task($f, ($f['payment_id'] ? 'Attach payment proof — ' : 'Pay (check PO, pay, attach proof) — ') . $f['po_number'] . ' · ' . $f['supplier_name'], $f['pr_number'], [], 'purchase_flow.php?pr_id=' . (int)$f['id'] . '#card-payment');
            // FIX (2 Oct 2026): approved courier / transport charges — check and pay to the courier's account
            try {
                foreach (erp_rows($pdo, "SELECT c.id, c.charge_number, c.pr_id, c.amount, c.payee_name, c.account_number, c.upi_id, c.status, c.raised_at, pr.pr_number FROM pf_transport_charges c JOIN purchase_requests pr ON pr.id = c.pr_id
                                         WHERE c.status IN ('approved','checked') ORDER BY c.id") as $c)
                    $tasks[] = ['id' => (int)$c['pr_id'], 'ref' => $c['charge_number'], 'title' => ($c['status'] === 'approved' ? 'Check courier charge — ' : 'Pay courier charge — ') . $c['charge_number'] . ' · ' . $c['payee_name'],
                                'sub' => $c['pr_number'] . ' · ' . ($c['account_number'] ? 'A/c ' . $c['account_number'] : 'UPI ' . $c['upi_id']), 'date' => substr((string)$c['raised_at'], 0, 10), 'amount' => $c['amount'],
                                'stage' => $c['status'] === 'approved' ? 'Approved — check it' : 'Checked — waiting for payment', 'actions' => [], 'link' => 'purchase_flow.php?pr_id=' . (int)$c['pr_id'] . '#card-tcharge'];
            } catch (PDOException $e) { /* not installed */ }
            $wait = array_sum(array_map(fn($f) => (float)$f['grand_total'], array_filter($flows, fn($f) => $f['stage'][0] === 'pay' && !$f['payment_id'])));
            $billsDue = (float)erp_val($pdo, "SELECT COALESCE(SUM(pi.grand_total - COALESCE((SELECT SUM(amount) FROM purchase_payments pp WHERE pp.pinv_id = pi.id AND pp.status = 'completed'),0)),0)
                                              FROM purchase_invoices pi WHERE pi.status = 'posted' AND pi.due_date IS NOT NULL AND pi.due_date < ?", [$today]);
            $kpis = [rd_kpi('POs waiting for payment', $wait, 'money', count(array_filter($flows, fn($f) => $f['stage'][0] === 'pay' && !$f['payment_id'])) . ' PO(s)', $wait > 0 ? 'amber' : 'green'),
                     rd_kpi('Paid to suppliers this month', (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE status = 'completed' AND payment_date >= ?", [$m1]), 'money'),
                     rd_kpi('Payments without proof', (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_flows WHERE payment_id IS NOT NULL AND payment_proof_doc_id IS NULL"), 'n', '', ''),
                     rd_kpi('Overdue supplier bills', $billsDue, 'money', '', $billsDue > 0 ? 'red' : 'green'),
                     rd_kpi('Bank lines not reconciled', (int)erp_val($pdo, "SELECT COUNT(*) FROM bank_statement_lines WHERE status = 'unmatched'"), 'n')];
            // every transaction reaches the accounts automatically — show the totals here too (1 Oct 2026)
            try {
                require_once __DIR__ . '/erp_report_lib.php';
                $kpis[] = rd_kpi('Expenses this month', (float)erp_val($pdo, "SELECT COALESCE(SUM(total),0) FROM expenses WHERE expense_date >= ? AND status IN ('approved','posted')", [$m1]), 'money');
                $kpis[] = rd_kpi('Customer receivables', rep_receivables($pdo)['total'], 'money', 'invoices + credit / manual sales not paid', 'amber');
                $kpis[] = rd_kpi('Money in this month', (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM accounts_transactions WHERE date >= ? AND status = 'completed' AND type IN ('income','payment_received')", [$m1]), 'money', '', 'green');
                $kpis[] = rd_kpi('Money out this month', (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM accounts_transactions WHERE date >= ? AND status = 'completed' AND type IN ('expense','payment_made','refund')", [$m1]), 'money');
            } catch (Throwable $e) { error_log('[role dash accounts] ' . $e->getMessage()); }
            // approvals that belong to Accounts: expenses, bills, supplier payments, journals (1 Oct 2026)
            foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.module, r.entity_id, r.reference, r.summary, r.amount, r.submitted_by, r.submitted_at, p.label, p.approver_perm
                                     FROM approval_requests r JOIN approval_policies p ON p.module = r.module
                                     WHERE r.status IN ('submitted','under_review') AND r.module IN ('expense','purchase_invoice','purchase_payment','manual_journal') ORDER BY r.submitted_at, r.id") as $a)
                if ($key === 'super' || erp_can($pdo, (string)$a['approver_perm']))
                    $tasks[] = ['id' => (int)$a['entity_id'], 'ref' => $a['request_number'], 'title' => $a['label'] . ' — ' . ($a['reference'] ?: $a['request_number']), 'sub' => $a['summary'] . ' · sent by ' . $a['submitted_by'],
                                'date' => substr((string)$a['submitted_at'], 0, 10), 'amount' => $a['amount'], 'stage' => 'Waiting for Accounts', 'approval_id' => (int)$a['id'],
                                'actions' => [['label' => 'Approve', 'kind' => 'apr_approve', 'primary' => true], ['label' => 'Reject', 'kind' => 'apr_reject', 'reason' => true]], 'link' => 'approvals.php?module=' . rawurlencode($a['module'])];
            $lists = [];
            // goods received but the shop bill is not entered — the payable is not in the books yet
            $rows = array_map(fn($g) => ['grn' => $g['grn_number'], 'date' => $g['received_date'], 'sup' => $g['supplier_name'], 'po' => $g['po_number'], 'link' => 'purchase_invoices.php'],
                erp_rows($pdo, "SELECT g.grn_number, g.received_date, s.supplier_name, po.po_number FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id
                                WHERE g.status = 'posted' AND NOT EXISTS (SELECT 1 FROM purchase_invoices pi WHERE pi.grn_id = g.id AND pi.status <> 'cancelled') ORDER BY g.received_date LIMIT 20"));
            $lists[] = ['key' => 'nobill', 'title' => 'Goods received — enter the shop bill', 'empty' => 'Every received purchase has its bill. ✓', 'more' => 'purchase_invoices.php',
                        'cols' => [['k' => 'grn', 'l' => 'Goods receipt'], ['k' => 'date', 'l' => 'Received', 'f' => 'date'], ['k' => 'sup', 'l' => 'Supplier'], ['k' => 'po', 'l' => 'PO']], 'rows' => $rows];
            // supplier bills still to pay, oldest due first
            $rows = [];
            foreach (erp_rows($pdo, "SELECT pi.id, pi.pinv_number, pi.supplier_invoice_no, pi.due_date, pi.grand_total, s.supplier_name,
                                            pi.grand_total - COALESCE((SELECT SUM(amount) FROM purchase_payments pp WHERE pp.pinv_id = pi.id AND pp.status = 'completed'),0) AS bal
                                     FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id WHERE pi.status = 'posted' ORDER BY pi.due_date IS NULL, pi.due_date LIMIT 60") as $b)
                if ($b['bal'] > 0.005 && count($rows) < 15)
                    $rows[] = ['bill' => $b['pinv_number'] . ($b['supplier_invoice_no'] ? ' · ' . $b['supplier_invoice_no'] : ''), 'sup' => $b['supplier_name'], 'due' => $b['due_date'],
                               'st' => $b['due_date'] && $b['due_date'] < $today ? 'Overdue' : 'Due', 'bal' => $b['bal'], 'link' => 'purchase_invoices.php?id=' . $b['id']];
            $lists[] = ['key' => 'billsdue', 'title' => 'Supplier bills to pay', 'empty' => 'No unpaid supplier bills. ✓', 'more' => 'purchase_payments.php',
                        'cols' => [['k' => 'bill', 'l' => 'Bill'], ['k' => 'sup', 'l' => 'Supplier'], ['k' => 'due', 'l' => 'Due', 'f' => 'date'], ['k' => 'st', 'l' => 'Status', 'f' => 'status'], ['k' => 'bal', 'l' => 'Balance', 'f' => 'money']], 'rows' => $rows];
            // money to collect from customers
            try {
                require_once __DIR__ . '/erp_report_lib.php';
                $docs = rep_receivables($pdo)['documents'];
                usort($docs, fn($x, $y) => strcmp((string)$x['date'], (string)$y['date']));
                $lnk = ['invoice' => 'invoices.php', 'credit_sale' => 'receivables.php', 'manual_sale' => 'receivables.php'];
                $lists[] = ['key' => 'recv', 'title' => 'Money to collect from customers (oldest first)', 'empty' => 'Nothing to collect. ✓', 'more' => 'receivables.php',
                            'cols' => [['k' => 'number', 'l' => 'Document'], ['k' => 'date', 'l' => 'Date', 'f' => 'date'], ['k' => 'customer_name', 'l' => 'Customer'], ['k' => 'customer_mobile', 'l' => 'Mobile'], ['k' => 'outstanding', 'l' => 'To collect', 'f' => 'money']],
                            'rows' => array_map(fn($d) => $d + ['link' => $lnk[$d['source']] ?? 'receivables.php'], array_slice($docs, 0, 15))];
            } catch (Throwable $e) { error_log('[role dash recv] ' . $e->getMessage()); }
            // every transaction reaches the accounts automatically — latest ones
            $rows = [];
            try {
                foreach (erp_rows($pdo, "SELECT transaction_id, date, type, category, party_name, reference_number, amount, payment_mode, status FROM accounts_transactions ORDER BY date DESC, id DESC LIMIT 15") as $t)
                    $rows[] = ['date' => $t['date'], 'txn' => $t['transaction_id'], 'type' => ucwords(str_replace('_', ' ', $t['type'])), 'cat' => $t['category'], 'party' => $t['party_name'], 'ref' => $t['reference_number'],
                               'amount' => in_array($t['type'], ['expense', 'payment_made', 'refund'], true) ? -abs((float)$t['amount']) : (float)$t['amount'], 'mode' => ucwords(str_replace('_', ' ', (string)$t['payment_mode'])), 'link' => 'accounts.php'];
            } catch (PDOException $e) {}
            $lists[] = ['key' => 'txns', 'title' => 'Latest transactions (money in + / money out −)', 'empty' => 'No transactions yet.', 'more' => 'accounts.php',
                        'cols' => [['k' => 'date', 'l' => 'Date', 'f' => 'date'], ['k' => 'txn', 'l' => 'Txn no.'], ['k' => 'type', 'l' => 'Type'], ['k' => 'cat', 'l' => 'Category'], ['k' => 'party', 'l' => 'Party'], ['k' => 'ref', 'l' => 'Reference'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'signed'], ['k' => 'mode', 'l' => 'Mode']], 'rows' => $rows];
            $sections[] = ['key' => $k, 'title' => 'Payments & accounts (Accounts Team)', 'role' => 'Accounts Team', 'tasks' => $tasks, 'kpis' => $kpis, 'lists' => $lists];
        }
    }
    // approvals waiting / approval history / pipeline lists — shown once, on the first of these sections (1 Oct 2026).
    // Executive and Manager see them too; only the CEO also sees the money transactions.
    foreach (['ceo', 'admin', 'manager', 'executive'] as $lk) {
        $ix = array_search($lk, array_column($sections, 'key'), true);
        if ($ix !== false) {
            $sections[$ix]['lists'] = rd_lists($pdo, $flows, $lk === 'ceo');
            // purchase orders already paid, with the payment proof — Executive (own requests), Manager, Admin, CEO (1 Oct 2026)
            try {
                $own = $lk === 'executive' && $key !== 'super';
                $rows = [];
                foreach (erp_rows($pdo, "SELECT f.pr_id, pr.pr_number, pr.created_by, po.po_number, po.grand_total, s.supplier_name, pp.payment_number, pp.payment_date, pp.amount, pp.payment_mode, pp.reference_number,
                                                pp.created_by AS paid_by, d.id AS doc_id, d.original_name
                                         FROM purchase_flows f JOIN purchase_requests pr ON pr.id = f.pr_id JOIN purchase_payments pp ON pp.id = f.payment_id AND pp.status = 'completed'
                                         LEFT JOIN purchase_orders po ON po.id = f.po_id LEFT JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN erp_documents d ON d.id = f.payment_proof_doc_id
                                         " . ($own ? "WHERE pr.created_by = ? " : '') . "ORDER BY pp.payment_date DESC, pp.id DESC LIMIT 15", $own ? [$me] : []) as $x)
                    $rows[] = ['po' => $x['po_number'] . ' · ' . $x['pr_number'], 'sup' => $x['supplier_name'], 'date' => $x['payment_date'], 'amount' => $x['amount'],
                               'how' => ucwords(str_replace('_', ' ', (string)$x['payment_mode'])) . ($x['reference_number'] ? ' · ' . $x['reference_number'] : ''), 'by' => $x['paid_by'],
                               'st' => (float)$x['amount'] + 0.005 >= (float)$x['grand_total'] ? 'Paid' : 'Part paid',
                               'proof' => $x['doc_id'] ? ['href' => '../assets/db_query/admin/erp_docs.php?action=download&id=' . (int)$x['doc_id'], 'text' => $x['original_name']] : null,
                               'link' => 'purchase_flow.php?pr_id=' . (int)$x['pr_id'] . '#card-payment'];
                $sections[$ix]['lists'][] = ['key' => 'paid', 'title' => $own ? 'My purchases — paid, with payment proof' : 'Purchases paid — with payment proof', 'empty' => 'No purchase order has been paid yet.',
                                             'cols' => [['k' => 'po', 'l' => 'PO / request'], ['k' => 'sup', 'l' => 'Shop'], ['k' => 'date', 'l' => 'Paid on', 'f' => 'date'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'money'],
                                                        ['k' => 'how', 'l' => 'Mode / UTR'], ['k' => 'by', 'l' => 'Paid by'], ['k' => 'st', 'l' => 'Status', 'f' => 'status'], ['k' => 'proof', 'l' => 'Payment proof', 'f' => 'file']], 'rows' => $rows];
            } catch (PDOException $e) { error_log('[role dash paid] ' . $e->getMessage()); }
            // FIX (2 Oct 2026): how the goods are coming — courier + tracking number or own vehicle + driver (Executive: own requests; Manager, Admin, CEO: all)
            try {
                $rows = [];
                $pfx = true; try { $pdo->query("SELECT 1 FROM purchase_flow_extras LIMIT 1"); } catch (PDOException $e) { $pfx = false; }   // FIX (2 Oct 2026): unloading checker + signature
                foreach (erp_rows($pdo, "SELECT f.pr_id, pr.pr_number, po.po_number, s.supplier_name, f.delivery_mode, f.courier_name, f.tracking_number, f.vehicle_number, f.driver_name, f.driver_phone,
                                                f.dispatch_date, f.quote_submitted_by, f.grn_id, f.unload_check, cs.tracking_url" . ($pfx ? ", x.unload_checker_name, x.unload_signed_at, x.unload_signature_doc_id" : '') . "
                                         FROM purchase_flows f JOIN purchase_requests pr ON pr.id = f.pr_id LEFT JOIN purchase_orders po ON po.id = f.po_id LEFT JOIN suppliers s ON s.id = po.supplier_id
                                         LEFT JOIN courier_services cs ON cs.id = f.courier_service_id" . ($pfx ? " LEFT JOIN purchase_flow_extras x ON x.pr_id = f.pr_id" : '') . "
                                         WHERE f.delivery_mode IS NOT NULL" . ($own ? " AND pr.created_by = ?" : '') . " ORDER BY f.dispatch_date DESC, f.id DESC LIMIT 15", $own ? [$me] : []) as $x) {
                    $c = $x['delivery_mode'] === 'courier';
                    $rows[] = ['po' => $x['po_number'] . ' · ' . $x['pr_number'], 'sup' => $x['supplier_name'], 'how' => $c ? 'Courier · ' . ($x['courier_name'] ?: '') : 'Own vehicle · ' . ($x['vehicle_number'] ?: ''),
                               'track' => $c ? ($x['tracking_number'] ?: '—') : trim(($x['driver_name'] ?: '') . ' ' . ($x['driver_phone'] ?: '')),
                               'date' => $x['dispatch_date'], 'by' => $x['quote_submitted_by'], 'st' => $x['grn_id'] || $x['unload_check'] ? 'Unloaded' : 'In transit',
                               'chk' => !empty($x['unload_checker_name']) ? $x['unload_checker_name'] . ' · ' . substr((string)$x['unload_signed_at'], 0, 10) : '—',
                               'sig' => !empty($x['unload_signature_doc_id']) ? ['href' => '../assets/db_query/admin/erp_docs.php?action=download&id=' . (int)$x['unload_signature_doc_id'], 'text' => 'signature'] : null,
                               'link' => 'purchase_flow.php?pr_id=' . (int)$x['pr_id'] . '#card-transport'];
                }
                $sections[$ix]['lists'][] = ['key' => 'transit', 'title' => $own ? 'My purchases — transport & tracking' : 'Purchases on the way — transport & tracking', 'empty' => 'No goods dispatched yet.',
                                             'cols' => [['k' => 'po', 'l' => 'PO / request'], ['k' => 'sup', 'l' => 'Shop'], ['k' => 'how', 'l' => 'Transport'], ['k' => 'track', 'l' => 'Tracking no. / driver'],
                                                        ['k' => 'date', 'l' => 'Dispatched', 'f' => 'date'], ['k' => 'by', 'l' => 'Bought by (L1)'], ['k' => 'st', 'l' => 'Status', 'f' => 'status'],
                                                        ['k' => 'chk', 'l' => 'Unloading checked by'], ['k' => 'sig', 'l' => 'Signature', 'f' => 'file']], 'rows' => $rows];
            } catch (PDOException $e) { error_log('[role dash transit] ' . $e->getMessage()); }
            // FIX (2 Oct 2026): external auditor reports (monthly stock audit + quality check) — Manager, Admin, CEO
            if ($lk !== 'executive' || $key === 'super') { try { $sections[$ix]['lists'][] = rd_audit_list($pdo, 'External audit & quality reports (monthly)'); } catch (PDOException $e) { error_log('[role dash audits] ' . $e->getMessage()); } }
            // FIX (3 Oct 2026): sales orders — Executive (own), Manager and CEO (all): status, delivery (DC), invoice / payment
            if (in_array($lk, ['ceo', 'manager', 'executive'], true) && ($key === 'super' || erp_can($pdo, 'sales_orders.view'))) {
                try {
                    $ownSo = $lk === 'executive' && $key !== 'super';
                    $rows = [];
                    foreach (erp_rows($pdo, "SELECT so.id, so.so_number, so.order_date, so.customer_name, so.grand_total, so.status, so.created_by,
                                                    (SELECT GROUP_CONCAT(dc.dc_number SEPARATOR ', ') FROM delivery_challans dc WHERE dc.sales_order_id = so.id AND dc.delivery_status IN ('dispatched','in_transit','delivered')) AS dcs,
                                                    (SELECT CONCAT(inv.invoice_number, ' · ', inv.status) FROM invoices inv WHERE inv.sales_order_id = so.id AND inv.status != 'cancelled' ORDER BY inv.id LIMIT 1) AS inv
                                             FROM sales_orders so" . ($ownSo ? " WHERE so.created_by = ?" : '') . " ORDER BY so.id DESC LIMIT 15", $ownSo ? [$me] : []) as $x)
                        $rows[] = ['so' => $x['so_number'], 'date' => $x['order_date'], 'cust' => $x['customer_name'] ?: '—', 'amount' => $x['grand_total'],
                                   'st' => $x['status'] === 'confirmed' && !$x['dcs'] ? 'Confirmed — waiting for dispatch (DC)' : ucfirst(str_replace('_', ' ', $x['status'])),
                                   'dc' => $x['dcs'] ?: '—', 'inv' => str_replace('_', ' ', (string)($x['inv'] ?: '—')), 'by' => $x['created_by'], 'link' => 'sales_orders.php?id=' . (int)$x['id']];
                    $sections[$ix]['lists'][] = ['key' => 'sales', 'title' => $ownSo ? 'My sales orders — dispatch & invoice' : 'Sales orders — dispatch & invoice', 'empty' => 'No sales orders yet.', 'more' => 'sales_orders.php',
                                                 'cols' => [['k' => 'so', 'l' => 'SO #'], ['k' => 'date', 'l' => 'Date', 'f' => 'date'], ['k' => 'cust', 'l' => 'Customer'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'money'],
                                                            ['k' => 'st', 'l' => 'Status', 'f' => 'status'], ['k' => 'dc', 'l' => 'Delivery challan'], ['k' => 'inv', 'l' => 'Invoice'], ['k' => 'by', 'l' => 'Entered by']], 'rows' => $rows];
                } catch (PDOException $e) { error_log('[role dash sales] ' . $e->getMessage()); }
            }
            // FIX (3 Oct 2026): manual (counter) sales — Executive (own), Manager and CEO (all): stock confirmed?, payment and what is still due
            if (in_array($lk, ['ceo', 'manager', 'executive'], true) && ($key === 'super' || $lk === 'ceo' || erp_can($pdo, 'manual_sales.create'))) {
                try {
                    $ownMs = $lk === 'executive' && $key !== 'super';
                    $rows = [];
                    foreach (erp_rows($pdo, "SELECT m.id, m.sale_number, m.sales_date, m.customer_name, m.grand_total, m.payment_status, m.payment_mode, m.stock_deducted, m.created_by,
                                                    (SELECT COALESCE(SUM(t.amount),0) FROM accounts_transactions t WHERE t.type = 'payment_received' AND t.status = 'completed' AND t.reference_type = 'manual_sale' AND t.reference_number = m.sale_number) AS rcv
                                             FROM manual_sales m" . ($ownMs ? " WHERE m.created_by = ?" : '') . " ORDER BY m.id DESC LIMIT 15", $ownMs ? [$me] : []) as $x)
                        $rows[] = ['ms' => $x['sale_number'], 'date' => $x['sales_date'], 'cust' => $x['customer_name'] ?: 'Walk-in', 'amount' => $x['grand_total'],
                                   'stock' => (int)$x['stock_deducted'] === 1 ? 'Stock deducted' : 'Pending confirmation',
                                   'pay' => ucwords(str_replace('_', ' ', $x['payment_status'])) . ' · ' . ucwords(str_replace('_', ' ', $x['payment_mode'])),
                                   'due' => $x['payment_status'] === 'paid' ? 0 : max(0, (float)$x['grand_total'] - (float)$x['rcv']), 'by' => $x['created_by'], 'link' => 'manual_sales.php?id=' . (int)$x['id']];
                    $sections[$ix]['lists'][] = ['key' => 'manual', 'title' => $ownMs ? 'My manual sales — stock & payment' : 'Manual sales — stock & payment', 'empty' => 'No manual sales yet.', 'more' => 'manual_sales.php',
                                                 'cols' => [['k' => 'ms', 'l' => 'Sale #'], ['k' => 'date', 'l' => 'Date', 'f' => 'date'], ['k' => 'cust', 'l' => 'Customer'], ['k' => 'amount', 'l' => 'Amount', 'f' => 'money'],
                                                            ['k' => 'stock', 'l' => 'Stock', 'f' => 'status'], ['k' => 'pay', 'l' => 'Payment'], ['k' => 'due', 'l' => 'Due', 'f' => 'money'], ['k' => 'by', 'l' => 'Entered by']], 'rows' => $rows];
                } catch (PDOException $e) { error_log('[role dash manual] ' . $e->getMessage()); }
            }
            // FIX (3 Oct 2026): credit sales (goods now, pay later) — Executive (own), Manager and CEO (all): given?, paid, balance due
            if (in_array($lk, ['ceo', 'manager', 'executive'], true) && ($key === 'super' || erp_can($pdo, 'credit_sales.view'))) {
                try {
                    $ownCs = $lk === 'executive' && $key !== 'super';
                    $rows = [];
                    foreach (erp_rows($pdo, "SELECT id, credit_number, sale_date, customer_name, customer_mobile, grand_total, amount_paid, status, stock_deducted, created_by FROM credit_sales"
                                             . ($ownCs ? " WHERE created_by = ?" : '') . " ORDER BY (status = 'paid'), id DESC LIMIT 15", $ownCs ? [$me] : []) as $x)
                        $rows[] = ['cr' => $x['credit_number'], 'date' => $x['sale_date'], 'cust' => trim($x['customer_name'] . ' ' . ($x['customer_mobile'] ?: '')), 'amount' => $x['grand_total'],
                                   'paid' => $x['amount_paid'], 'due' => max(0, (float)$x['grand_total'] - (float)$x['amount_paid']),
                                   'st' => (int)$x['stock_deducted'] === 1 ? ucfirst(str_replace('_', ' ', $x['status'])) : 'Pending confirmation', 'by' => $x['created_by'], 'link' => 'credit_sale.php?id=' . (int)$x['id']];
                    $sections[$ix]['lists'][] = ['key' => 'credit', 'title' => $ownCs ? 'My credit sales — paid & balance due' : 'Credit sales — paid & balance due', 'empty' => 'No credit sales yet.', 'more' => 'credit_sale.php',
                                                 'cols' => [['k' => 'cr', 'l' => 'Credit #'], ['k' => 'date', 'l' => 'Date', 'f' => 'date'], ['k' => 'cust', 'l' => 'Customer'], ['k' => 'amount', 'l' => 'Total', 'f' => 'money'],
                                                            ['k' => 'paid', 'l' => 'Paid', 'f' => 'money'], ['k' => 'due', 'l' => 'Balance due', 'f' => 'money'], ['k' => 'st', 'l' => 'Status', 'f' => 'status'], ['k' => 'by', 'l' => 'Entered by']], 'rows' => $rows];
                } catch (PDOException $e) { error_log('[role dash credit] ' . $e->getMessage()); }
            }
            // team activity from the audit trail — Manager and CEO (1 Oct 2026)
            if (in_array($lk, ['ceo', 'manager'], true) && ($key === 'super' || erp_can($pdo, 'audit_logs.view'))) {
                try {
                    $rows = array_map(fn($a) => ['at' => $a['created_at'], 'who' => $a['username'] ?: '—', 'what' => ucfirst((string)$a['action']), 'module' => ucwords(str_replace('_', ' ', (string)$a['module'])), 'rec' => $a['record_id']],
                                      erp_rows($pdo, "SELECT username, action, module, record_id, created_at FROM audit_logs ORDER BY id DESC LIMIT 20"));
                    $sections[$ix]['lists'][] = ['key' => 'audit', 'title' => 'Team activity — audit trail (latest 20)', 'empty' => 'No activity yet.', 'more' => 'audit_logs.php',
                                                 'cols' => [['k' => 'at', 'l' => 'When', 'f' => 'dt'], ['k' => 'who', 'l' => 'User'], ['k' => 'what', 'l' => 'Action'], ['k' => 'module', 'l' => 'Module'], ['k' => 'rec', 'l' => 'Record']], 'rows' => $rows];
                } catch (PDOException $e) { /* audit table missing */ }
            }
            break;
        }
    }
    erp_out(['status' => 'success', 'role' => $key, 'role_name' => $roleName, 'limited' => $key !== 'super' && role_access_pages($roleName, $userDash) !== null, 'sections' => $sections]);
} catch (Throwable $e) {
    erp_db_error($e, 'role dashboard');
}
