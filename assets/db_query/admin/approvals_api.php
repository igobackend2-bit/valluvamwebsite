<?php
// ============================================================================
// Approvals inbox + rules (added 1 Oct 2026) — one engine for every module:
// purchase requests / orders / amendments, bills, supplier payments, purchase
// and sales returns, stock adjustments / counts, expenses, manual journals.
// Approve = the stored request is replayed through the module's own API as the
// approver (so the module's own validation and posting run exactly once).
// ============================================================================
require_once __DIR__ . '/erp_ext.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
erpx_guard($pdo, $action === 'policies_save' ? 'approvals.manage' : 'approvals.view');
if (in_array($action, ['approve', 'reject', 'review', 'cancel', 'policies_save'], true) && !$isWrite) erp_fail('Invalid request method.');

const APR_ENDPOINTS = ['purchase_api.php', 'procurement_api.php', 'inventory_ops_api.php', 'warehouse_api.php', 'accounting_api.php', 'purchase_flow_api.php'];
const APR_LINKS = ['purchase_request' => 'purchase_requests.php?id=', 'purchase_request_final' => 'purchase_requests.php?id=', 'purchase_order' => 'purchase_orders.php?id=', 'po_amendment' => 'purchase_orders.php?id=',
                   'purchase_invoice' => 'purchase_invoices.php?id=', 'stock_adjustment' => 'stock_adjustments.php?id=', 'stock_count' => 'stock_counts.php?id=',
                   'expense' => 'expenses.php?id=', 'manual_journal' => 'journals.php?id=', 'purchase_payment' => 'purchase_payments.php', 'purchase_return' => 'purchase_returns.php',
                   'sales_return' => 'sales_returns.php', 'pr_quotation' => 'purchase_flow.php?pr_id='];

try {
    switch ($action) {
        case 'list':
            $w = []; $p = [];
            $st = (string)erp_input('status', 'open');
            if ($st === 'open') $w[] = "r.status IN ('submitted','under_review')";
            elseif ($st !== '') { $w[] = 'r.status = ?'; $p[] = $st; }
            if ($m = erp_input('module')) { $w[] = 'r.module = ?'; $p[] = $m; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'DATE(r.submitted_at) >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'DATE(r.submitted_at) <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(r.request_number LIKE ? OR r.reference LIKE ? OR r.summary LIKE ? OR r.submitted_by LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
            [$lim, $off] = erp_page_args(50);
            $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
            $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM approval_requests r" . $where, $p);
            $rows = erp_rows($pdo, "SELECT r.id, r.request_number, r.module, r.entity_id, r.reference, r.summary, r.amount, r.status, r.submitted_by, r.submitted_at, r.reviewed_by,
                                           r.decided_by, r.decided_at, r.remarks, r.executed_at, r.execution_error, p.label, p.approver_perm
                                    FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module" . $where . " ORDER BY r.id DESC LIMIT {$lim} OFFSET {$off}", $p);
            foreach ($rows as &$r) {
                $r['can_decide'] = in_array($r['status'], ['submitted', 'under_review'], true) && erp_can($pdo, $r['approver_perm'] ?: 'purchase.approve');
                $r['link'] = isset(APR_LINKS[$r['module']]) ? APR_LINKS[$r['module']] . (substr(APR_LINKS[$r['module']], -1) === '=' ? $r['entity_id'] : '') : null;
            }
            unset($r);
            $counts = erp_rows($pdo, "SELECT module, COUNT(*) n FROM approval_requests WHERE status IN ('submitted','under_review') GROUP BY module");
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => $total, 'open_by_module' => $counts,
                     'modules' => erp_rows($pdo, "SELECT module, label FROM approval_policies ORDER BY label")]);

        case 'get':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT r.*, p.label, p.approver_perm FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module WHERE r.id = ?", [$id]);
            if (!$r) erp_fail('Approval request not found.');
            $r['actions'] = erp_rows($pdo, "SELECT action, by_user, remarks, created_at FROM approval_actions WHERE request_id = ? ORDER BY id", [$id]);
            $payload = json_decode((string)$r['approve_payload'], true) ?: [];
            // readable view of the stored request (item lines decoded)
            foreach ($payload as $k => $v) if (is_string($v) && ($v[0] ?? '') === '[') { $dec = json_decode($v, true); if (is_array($dec)) $payload[$k] = $dec; }
            unset($r['approve_payload'], $r['reject_payload']);
            $r['request'] = $payload;
            $r['can_decide'] = in_array($r['status'], ['submitted', 'under_review'], true) && erp_can($pdo, $r['approver_perm'] ?: 'purchase.approve');
            $r['link'] = isset(APR_LINKS[$r['module']]) ? APR_LINKS[$r['module']] . (substr(APR_LINKS[$r['module']], -1) === '=' ? $r['entity_id'] : '') : null;
            erp_out(['status' => 'success', 'record' => $r]);

        case 'review':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT r.*, p.approver_perm FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module WHERE r.id = ?", [$id]);
            if (!$r || $r['status'] !== 'submitted') erp_invalid('Only submitted requests can be taken for review.');
            if (!erp_can($pdo, $r['approver_perm'])) erp_fail('You do not have permission to review this.', 403);
            $pdo->prepare("UPDATE approval_requests SET status = 'under_review', reviewed_by = ? WHERE id = ?")->execute([erp_user(), $id]);
            $pdo->prepare("INSERT INTO approval_actions (request_id, action, by_user, remarks) VALUES (?, 'review', ?, ?)")->execute([$id, erp_user(), erp_input('remarks') ?: null]);
            erp_out(['status' => 'success', 'message' => "{$r['request_number']} is under review by " . erp_user() . '.']);

        case 'cancel':
            // the person who submitted (or an approver) withdraws a request
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT r.*, p.approver_perm FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module WHERE r.id = ?", [$id]);
            if (!$r || !in_array($r['status'], ['submitted', 'under_review'], true)) erp_invalid('Only open requests can be withdrawn.');
            if ($r['submitted_by'] !== erp_user() && !erp_can($pdo, $r['approver_perm'])) erp_fail('Only the requester or an approver can withdraw this.', 403);
            $pdo->prepare("UPDATE approval_requests SET status = 'cancelled', decided_by = ?, decided_at = NOW(), remarks = ? WHERE id = ?")->execute([erp_user(), erp_input('remarks') ?: 'Withdrawn', $id]);
            $pdo->prepare("INSERT INTO approval_actions (request_id, action, by_user, remarks) VALUES (?, 'cancel', ?, ?)")->execute([$id, erp_user(), erp_input('remarks') ?: null]);
            log_audit($pdo, 'cancel', 'approval_requests', $id, ['status' => $r['status']], ['status' => 'cancelled']);
            erp_out(['status' => 'success', 'message' => "{$r['request_number']} withdrawn."]);

        case 'reject':
        case 'approve':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT r.*, p.approver_perm FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module WHERE r.id = ?", [$id]);
            if (!$r || !in_array($r['status'], ['submitted', 'under_review'], true)) erp_invalid('This request is no longer waiting for a decision.');
            if (!erp_can($pdo, $r['approver_perm'] ?: 'purchase.approve')) erp_fail('You do not have permission to decide this.', 403);
            $remarks = trim((string)erp_input('remarks', ''));
            if ($action === 'reject' && $remarks === '') erp_invalid('Give a reason for rejecting.');
            $payload = json_decode((string)($action === 'approve' ? $r['approve_payload'] : $r['reject_payload']), true);
            log_audit($pdo, $action, 'approval_requests', $id, ['status' => $r['status']], ['module' => $r['module'], 'reference' => $r['reference'], 'remarks' => $remarks]);
            if (!$payload) {
                // nothing to replay (e.g. a rejection without a module action) — just record the decision
                apr_close($pdo, $r['module'], $r['request_key'], $action === 'approve' ? 'approved' : 'rejected', $remarks ?: null);
                erp_out(['status' => 'success', 'message' => "{$r['request_number']} " . ($action === 'approve' ? 'approved' : 'rejected') . '.']);
            }
            $endpoint = (string)($payload['_endpoint'] ?? $r['endpoint']);
            unset($payload['_endpoint']);
            if (!in_array($endpoint, APR_ENDPOINTS, true)) erp_fail('This request cannot be replayed (unknown module).');
            if ($action === 'reject') {
                // record the rejection first (the module's reject action closes it too; harmless if not)
                apr_close($pdo, $r['module'], $r['request_key'], 'rejected', $remarks);
            }
            // Replay the stored request through the module's own API, as the approver.
            $_POST = array_merge($payload, ['_approval_id' => $id, '_approval_remarks' => $remarks]);
            $_GET = [];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $GLOBALS['erp_replay_request'] = $r;
            register_shutdown_function(function () use ($pdo, $r, $action) {
                // an 'approve' replay that failed leaves the request open with the error, so it can be fixed and approved again
                if ($action !== 'approve') return;
                try {
                    $now = erp_row($pdo, "SELECT status, execution_error FROM approval_requests WHERE id = ?", [$r['id']]);
                    if ($now && in_array($now['status'], ['submitted', 'under_review'], true) && !$now['execution_error'])
                        $pdo->prepare("UPDATE approval_requests SET execution_error = 'The module did not confirm posting — check the document.' WHERE id = ?")->execute([$r['id']]);
                } catch (Throwable $e) {}
            });
            require __DIR__ . '/' . $endpoint;   // outputs the module's JSON answer and exits
            exit;

        case 'policies':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT * FROM approval_policies ORDER BY inherent DESC, label"),
                     'can_manage' => erp_can($pdo, 'approvals.manage')]);

        case 'policies_save':
            $rows = erp_json_input('rules');
            $pdo->beginTransaction();
            foreach ($rows as $x) {
                $p = apr_policy($pdo, (string)($x['module'] ?? ''));
                if (!$p) continue;
                $enabled = (int)$p['inherent'] ? 1 : (!empty($x['enabled']) && $x['enabled'] !== '0' ? 1 : 0);
                $min = erp_m(erp_num($x['min_amount'] ?? 0, 'Minimum amount'));
                $pdo->prepare("UPDATE approval_policies SET enabled = ?, min_amount = ?, updated_by = ? WHERE module = ?")->execute([$enabled, (int)$p['inherent'] ? 0 : $min, erp_user(), $p['module']]);
            }
            $pdo->commit();
            log_audit($pdo, 'update', 'approval_policies', null, null, $rows);
            erp_out(['status' => 'success', 'message' => 'Approval rules saved.']);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'approvals');
}
