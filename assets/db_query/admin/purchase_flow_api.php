<?php
// ============================================================================
// Guided purchase flow (added 1 Oct 2026)
//   Purchase request (Manager → Backend approval)
//   → 3 shop quotations, lowest price / fastest delivery highlighted
//   → "which shop" approval (Approvals inbox)
//   → purchase order created from the approved shop (bank details kept) → PO approval
//   → payment with proof → transport (internal vehicle / courier + tracking)
//   → loading DC, unloading DC, shop bill (handwritten / system)
//   → unloading quantity check → goods receipt (draft) → quality check
// This file only keeps the flow's own records (purchase_flows, pr_quotes …).
// Purchase orders, payments, goods receipts and quality checks are still made
// by their own modules (purchase_api.php / procurement_api.php), unchanged.
// Needs purchase_flow_migration.sql.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/pq_lib.php';
require_once __DIR__ . '/pf_lib.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$writes = ['quotes_save', 'quotes_submit', 'quotes_approve', 'quotes_reject', 'quotes_retry_po', 'quotes_mgr_approve', 'quotes_mgr_reject', 'po_check', 'pay_link', 'transport_save', 'doc_link', 'unload_save', 'grn_link', 'qc_link'];
$perms = ['quotes_approve' => 'purchase.backend_approve', 'quotes_reject' => 'purchase.backend_approve', 'quotes_retry_po' => 'purchase.backend_approve',
          'quotes_mgr_approve' => 'purchase.manager_approve', 'quotes_mgr_reject' => 'purchase.manager_approve'];   // FIX (2 Oct 2026): shop choice — Manager first, then Admin
/** FIX (2 Oct 2026): dashboard key of the logged-in role (executive, manager, l1, admin, ceo, accounts). */
function pf_role_key(): string {
    $f = __DIR__ . '/../../../admin/includes/role_access.php';
    if (!function_exists('role_access_key') && is_file($f)) require_once $f;
    return function_exists('role_access_key') ? role_access_key((string)($_SESSION['admin_role_name'] ?? '')) : '';
}
/** FIX (2 Oct 2026): after the shop is approved, only the L1 who sent the quotation buys (transport, loading DC, shop bill). */
function pf_buyer_guard(?array $f) {
    if (($_SESSION['admin_role_name'] ?? '') !== 'L1 (Sourcing)' || !$f || empty($f['quote_submitted_by'])) return;
    if ($f['quote_submitted_by'] !== erp_user()) erp_invalid("Only {$f['quote_submitted_by']} (who got the quotation) buys this purchase.");
}
/** FIX (2 Oct 2026): Accounts "PO checked" step before payment (needs pr_po_check_migration.sql; skipped when not installed). */
function pf_has_po_check(PDO $pdo): bool {
    static $ok = null;
    if ($ok === null) { try { $ok = (bool)$pdo->query("SHOW COLUMNS FROM purchase_flows LIKE 'po_checked_at'")->fetch(); } catch (Throwable $e) { $ok = false; } }
    return $ok;
}
/** FIX (2 Oct 2026): the shop choice waits for the Manager first when the 'pr_quotation_mgr' rule is installed (pr_quote_manager_migration.sql). */
function pf_mgr_open(PDO $pdo, int $prId): ?array {
    try { return erp_row($pdo, "SELECT * FROM approval_requests WHERE module = 'pr_quotation_mgr' AND request_key = ? AND status IN ('submitted','under_review') ORDER BY id DESC LIMIT 1", [(string)$prId]); }
    catch (Throwable $e) { return null; }
}
erp_guard($pdo, $perms[$action] ?? 'purchase.view');
if (in_array($action, $writes, true) && !$isWrite) erp_fail('Invalid request method.');
const PQ_PR = ['q' => 'pr_quotes', 'i' => 'pr_quote_items', 'fk' => 'pr_id'];
const PF_DOC_SLOTS = ['payment_proof' => ['payment_proof_doc_id', 'SUPPLIER_PAYMENT_PROOF'], 'courier_proof' => ['courier_proof_doc_id', 'TRANSPORT_RECEIPT'],
                      'loading_dc' => ['loading_dc_doc_id', 'GRN'], 'unloading_dc' => ['unloading_dc_doc_id', 'GRN'], 'shop_bill' => ['shop_bill_doc_id', 'SUPPLIER_INVOICE']];

/** Requesters (Valluvam Team Executive) see only their own requests in the flow. */
function pf_sees_all(PDO $pdo): bool {
    foreach (['purchase.manager_approve', 'purchase.backend_approve', 'flow.source', 'purchase_payment.create', 'po.ceo_approve'] as $p) if (erp_can($pdo, $p)) return true;
    return false;
}
function pf_need_any(PDO $pdo, array $perms, string $what) {
    foreach ($perms as $p) if (erp_can($pdo, $p)) return;
    erp_fail("You do not have permission to {$what}.", 403);
}
function pf_flow(PDO $pdo, int $prId, bool $create = false): ?array {
    $f = erp_row($pdo, "SELECT * FROM purchase_flows WHERE pr_id = ?", [$prId]);
    if (!$f && $create) {
        $pdo->prepare("INSERT IGNORE INTO purchase_flows (pr_id, created_by) VALUES (?, ?)")->execute([$prId, erp_user()]);
        $f = erp_row($pdo, "SELECT * FROM purchase_flows WHERE pr_id = ?", [$prId]);
    }
    return $f;
}
function pf_pr(PDO $pdo, int $prId): array {
    $pr = erp_row($pdo, "SELECT pr.*, w.name AS warehouse_name FROM purchase_requests pr LEFT JOIN warehouses w ON w.id = pr.warehouse_id WHERE pr.id = ?", [$prId]);
    if (!$pr) erp_invalid('Purchase request not found.');
    return $pr;
}
function pf_set(PDO $pdo, int $prId, array $cols) {
    $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($cols)));
    $pdo->prepare("UPDATE purchase_flows SET {$sets} WHERE pr_id = ?")->execute(array_merge(array_values($cols), [$prId]));
}
function pf_po_for(PDO $pdo, array $flow): ?array {
    if (empty($flow['po_id'])) return null;
    return erp_row($pdo, "SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?", [$flow['po_id']]);
}
function pf_supplier_cols(PDO $pdo): array {
    static $c = null;
    if ($c === null) { $c = []; foreach ($pdo->query("SHOW COLUMNS FROM suppliers")->fetchAll(PDO::FETCH_ASSOC) as $r) $c[$r['Field']] = true; }
    return $c;
}
/** Finds the supplier of a quotation or creates it (with contact and bank details). Fills only empty fields of an existing supplier. */
function pf_supplier_from_quote(PDO $pdo, array $q): int {
    $cols = pf_supplier_cols($pdo);
    $map = ['mobile' => $q['mobile'], 'email' => $q['email'], 'gst_number' => $q['gst_number'], 'owner_name' => $q['contact_person'], 'account_holder_name' => $q['account_holder_name'],
            'bank_name' => $q['bank_name'], 'bank_account_number' => $q['bank_account_number'], 'bank_ifsc' => $q['bank_ifsc'], 'upi_id' => $q['upi_id'], 'payment_terms' => $q['payment_terms']];
    $map = array_filter($map, fn($v, $k) => isset($cols[$k]) && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
    $s = null;
    if (!empty($q['supplier_id'])) $s = erp_row($pdo, "SELECT * FROM suppliers WHERE id = ?", [$q['supplier_id']]);
    if (!$s) $s = erp_row($pdo, "SELECT * FROM suppliers WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(?)) LIMIT 1", [$q['supplier_name']]);
    if ($s) {
        $fill = array_filter($map, fn($v, $k) => trim((string)($s[$k] ?? '')) === '', ARRAY_FILTER_USE_BOTH);
        if ($fill) {
            $pdo->prepare("UPDATE suppliers SET " . implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($fill))) . " WHERE id = ?")->execute(array_merge(array_values($fill), [$s['id']]));
            log_audit($pdo, 'update', 'suppliers', $s['id'], null, ['filled_from_quotation' => array_keys($fill)]);
        }
        if ($s['status'] !== 'active') erp_invalid("Supplier {$s['supplier_name']} is inactive. Activate it in Suppliers first.");
        return (int)$s['id'];
    }
    $fields = array_merge(['supplier_name' => $q['supplier_name'], 'status' => 'active'], $map);
    $pdo->prepare("INSERT INTO suppliers (" . implode(', ', array_keys($fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")")->execute(array_values($fields));
    $id = (int)$pdo->lastInsertId();
    log_audit($pdo, 'create', 'suppliers', $id, null, ['supplier_name' => $q['supplier_name'], 'from' => 'purchase flow quotation']);
    return $id;
}
function pf_doc_ok(PDO $pdo, int $docId, array $entities): bool {
    foreach ($entities as [$type, $id]) {
        if ($id && erp_val($pdo, "SELECT id FROM erp_documents WHERE id = ? AND entity_type = ? AND entity_id = ?", [$docId, $type, $id])) return true;
    }
    return false;
}
function pf_doc_info(PDO $pdo, $docId): ?array {
    if (!$docId) return null;
    return erp_row($pdo, "SELECT id, original_name, created_at, uploaded_by FROM erp_documents WHERE id = ?", [$docId]);
}
/** Step list with status for the tracker. */
function pf_steps(PDO $pdo, array $pr, ?array $f, ?array $po, array $ctx): array {
    $st = [];
    $prDone = in_array($pr['status'], ['approved', 'converted'], true);
    $prBad = in_array($pr['status'], ['manager_rejected', 'backend_rejected', 'rejected', 'cancelled'], true);
    $st[] = ['key' => 'request', 'label' => 'Purchase request', 'state' => $prDone ? 'done' : ($prBad ? 'blocked' : 'current'),
             'note' => ['draft' => 'Draft — submit it', 'submitted' => 'Waiting for Manager', 'manager_approved' => 'Waiting for Backend', 'approved' => 'Approved', 'converted' => 'Approved',
                        'manager_rejected' => 'Rejected by Manager', 'backend_rejected' => 'Rejected by Backend', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'][$pr['status']] ?? $pr['status']];
    $qs = $f['quote_status'] ?? 'collecting';
    $st[] = ['key' => 'quotes', 'label' => 'Shop quotations (3)', 'state' => !$prDone ? 'todo' : ($qs === 'approved' ? 'done' : ($qs === 'submitted' ? 'waiting' : 'current')),
             'note' => $qs === 'approved' ? 'Shop approved' : ($qs === 'submitted' ? (pf_mgr_open($pdo, (int)$pr['id']) ? 'Waiting for Manager approval' : 'Waiting for Admin approval') : ($qs === 'rejected' ? 'Rejected — change and resend' : $ctx['quote_count'] . ' of 3 collected'))];
    $poSt = $po['status'] ?? null;
    $poDone = in_array($poSt, ['approved', 'partially_received', 'fully_received', 'closed'], true);
    $st[] = ['key' => 'po', 'label' => 'Purchase order', 'state' => !$po ? ($qs === 'approved' ? 'current' : 'todo') : ($poDone ? 'done' : ($poSt === 'cancelled' ? 'blocked' : 'waiting')),
             'note' => $po ? $po['po_number'] . ' · ' . str_replace('_', ' ', $poSt) : ($qs === 'approved' ? 'Not created — create it' : '')];
    $paid = $ctx['paid'];
    $payDone = $f && $f['payment_id'] && $f['payment_proof_doc_id'];
    $st[] = ['key' => 'payment', 'label' => 'Payment + proof', 'state' => !$poDone ? 'todo' : ($payDone ? 'done' : 'current'),
             'note' => $payDone ? '₹' . number_format($paid, 2) . ' paid · proof attached' : ($poDone ? ($f && $f['payment_id'] ? 'Proof missing' : (pf_has_po_check($pdo) && empty($f['po_checked_at']) ? 'Accounts to check the PO' : (pf_has_po_check($pdo) ? 'PO checked — waiting for payment' : 'Waiting for payment'))) : '')];   // FIX (2 Oct 2026): PO checked step
    $tDone = $f && $f['delivery_mode'] && ($f['delivery_mode'] === 'internal' ? ($f['driver_name'] && $f['driver_phone']) : ($f['tracking_number'] && $f['courier_proof_doc_id']));
    $st[] = ['key' => 'transport', 'label' => 'Transport', 'state' => !$poDone ? 'todo' : ($tDone ? 'done' : 'current'),
             'note' => $f && $f['delivery_mode'] ? ($f['delivery_mode'] === 'courier' ? 'Courier · ' . ($f['courier_name'] ?: '') . ($f['tracking_number'] ? ' · ' . $f['tracking_number'] : '') . (!$f['courier_proof_doc_id'] ? ' · proof missing' : '') : 'Internal · ' . ($f['vehicle_number'] ?: $f['driver_name'] ?: '')) : ''];
    $dDone = $f && $f['loading_dc_doc_id'] && $f['shop_bill_doc_id'];
    $st[] = ['key' => 'docs', 'label' => 'Loading DC + shop bill', 'state' => !$poDone ? 'todo' : ($dDone ? 'done' : 'current'),
             'note' => $f ? trim(($f['loading_dc_doc_id'] ? 'Loading DC ✓ ' : 'Loading DC missing ') . ($f['shop_bill_doc_id'] ? '· Bill ✓ (' . ($f['shop_bill_type'] ?: '') . ')' : '· Bill missing')) : ''];
    $uDone = $f && $f['unload_check'] && $f['unloading_dc_doc_id'] && $f['grn_id'];
    $st[] = ['key' => 'unload', 'label' => 'Unloading + quantity check', 'state' => !$poDone ? 'todo' : ($uDone ? ($f['unload_ok'] ? 'done' : 'warn') : 'current'),
             'note' => $f && $f['unload_check'] ? ($f['unload_ok'] ? 'Quantity correct' : 'Quantity / damage difference') . ($f['unloading_dc_doc_id'] ? '' : ' · unloading DC missing') . ($f['grn_id'] ? ' · GRN ' . ($ctx['grn']['grn_number'] ?? '') : '') : ''];
    $qc = $ctx['qc'];
    $qcDone = $qc && in_array($qc['status'], ['passed', 'partially_passed', 'rejected'], true);
    $st[] = ['key' => 'qc', 'label' => 'Quality check', 'state' => !($f && $f['grn_id']) ? 'todo' : ($qcDone ? ($qc['status'] === 'passed' ? 'done' : 'warn') : 'current'),
             'note' => $qc ? $qc['qc_number'] . ' · ' . str_replace('_', ' ', $qc['status']) : ''];
    return $st;
}

try {
    if (!pf_installed($pdo)) erp_invalid('The purchase flow is not installed yet. Run purchase_flow_migration.sql once in HeidiSQL.');
    switch ($action) {
        // ------------------------------------------------------------------ overview
        case 'list':
            $w = ["pr.status IN ('submitted','manager_approved','approved','converted')"]; $p = [];
            if (($q = trim((string)erp_input('q', ''))) !== '') { $w[] = "(pr.pr_number LIKE ? OR po.po_number LIKE ? OR s.supplier_name LIKE ?)"; array_push($p, "%$q%", "%$q%", "%$q%"); }
            if (erp_input('all') === '1') $w = $q !== '' ? [end($w)] : [];
            if (!pf_sees_all($pdo)) { $w[] = 'pr.created_by = ?'; $p[] = erp_user(); }
            $rows = erp_rows($pdo, "SELECT pr.id, pr.pr_number, pr.request_date, pr.required_by, pr.status AS pr_status, pr.requested_by, f.quote_status, f.payment_id, f.payment_proof_doc_id,
                                           f.delivery_mode, f.tracking_number, f.unload_ok, f.grn_id, f.qc_id, po.po_number, po.status AS po_status, po.grand_total, s.supplier_name,
                                           (SELECT COUNT(*) FROM pr_quotes q WHERE q.pr_id = pr.id) AS quote_count, qc.status AS qc_status
                                    FROM purchase_requests pr LEFT JOIN purchase_flows f ON f.pr_id = pr.id LEFT JOIN purchase_orders po ON po.id = f.po_id
                                    LEFT JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN quality_checks qc ON qc.id = f.qc_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY pr.id DESC LIMIT 300", $p);
            foreach ($rows as &$r) {
                $poDone = in_array($r['po_status'], ['approved', 'partially_received', 'fully_received', 'closed'], true);
                $r['stage'] = !in_array($r['pr_status'], ['approved', 'converted'], true) ? 'Request approval'
                    : ($r['quote_status'] !== 'approved' ? ($r['quote_status'] === 'submitted' ? 'Quotation approval' : 'Collect quotations')
                    : (!$r['po_number'] ? 'Create PO' : (!$poDone ? 'PO approval'
                    : (!($r['payment_id'] && $r['payment_proof_doc_id']) ? 'Waiting for payment'
                    : (!$r['delivery_mode'] ? 'Transport' : (!$r['grn_id'] ? 'Unloading check'
                    : (!in_array($r['qc_status'], ['passed', 'partially_passed', 'rejected'], true) ? 'Quality check' : 'Completed')))))));
            }
            unset($r);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'get':
            $prId = (int)erp_input('pr_id');
            $pr = pf_pr($pdo, $prId);
            if (!pf_sees_all($pdo) && $pr['created_by'] !== erp_user()) erp_fail('You can view only your own purchase requests.', 403);
            $pr['items'] = pf_attach_units($pdo, erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_request_items WHERE pr_id = ? ORDER BY id", [$prId])));
            $f = pf_flow($pdo, $prId);
            $quotes = pq_load($pdo, PQ_PR, $prId);
            $po = $f ? pf_po_for($pdo, $f) : null;
            $poItems = $po ? erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$po['id']])) : [];
            $sup = $po ? erp_row($pdo, "SELECT * FROM suppliers WHERE id = ?", [$po['supplier_id']]) : null;
            $payment = $f && $f['payment_id'] ? erp_row($pdo, "SELECT id, payment_number, payment_date, amount, payment_mode, reference_number, status FROM purchase_payments WHERE id = ?", [$f['payment_id']]) : null;
            $grn = $f && $f['grn_id'] ? erp_row($pdo, "SELECT id, grn_number, status, received_date FROM goods_receipts WHERE id = ?", [$f['grn_id']]) : null;
            $qc = $f && $f['qc_id'] ? erp_row($pdo, "SELECT id, qc_number, status, completed_at FROM quality_checks WHERE id = ?", [$f['qc_id']]) : null;
            if (!$qc && $grn) $qc = erp_row($pdo, "SELECT id, qc_number, status, completed_at FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$grn['id']]);
            $apr = erp_row($pdo, "SELECT r.id, r.request_number, r.status, r.submitted_by, r.submitted_at, r.decided_by, r.decided_at, r.remarks, r.execution_error
                                  FROM approval_requests r WHERE r.module = 'pr_quotation' AND r.request_key = ? ORDER BY r.id DESC LIMIT 1", [(string)$prId]);
            $aprStage = 'admin';   // FIX (2 Oct 2026): Manager stage of the shop choice
            if (($f['quote_status'] ?? '') === 'submitted' && ($m = pf_mgr_open($pdo, $prId))) { $apr = array_intersect_key($m, array_flip(['id', 'request_number', 'status', 'submitted_by', 'submitted_at', 'decided_by', 'decided_at', 'remarks', 'execution_error'])); $aprStage = 'manager'; }
            $docs = [];
            foreach (PF_DOC_SLOTS as $slot => [$col]) $docs[$slot] = $f ? pf_doc_info($pdo, $f[$col]) : null;
            $paid = $payment && $payment['status'] === 'completed' ? (float)$payment['amount'] : 0;
            $safeSup = $sup ? array_intersect_key($sup, array_flip(['id', 'supplier_name', 'mobile', 'email', 'gst_number', 'owner_name', 'account_holder_name', 'bank_name', 'bank_account_number', 'bank_ifsc', 'upi_id', 'payment_terms', 'bank_details'])) : null;
            erp_out(['status' => 'success', 'pr' => $pr, 'flow' => $f, 'quotes' => $quotes, 'po' => $po ? array_merge($po, ['items' => $poItems]) : null, 'supplier' => $safeSup,
                     'payment' => $payment, 'grn' => $grn, 'qc' => $qc, 'approval' => $apr, 'approval_stage' => $aprStage, 'po_check' => pf_has_po_check($pdo), 'can_mgr' => erp_can($pdo, 'purchase.manager_approve'), 'docs' => $docs,
                     'couriers' => erp_rows($pdo, "SELECT id, name, tracking_url FROM courier_services WHERE is_active = 1 ORDER BY sort_order, name"),
                     'can' => ['quotes' => erp_can($pdo, 'flow.source'), 'approve' => erp_can($pdo, 'purchase.backend_approve'),
                               'pay' => erp_can($pdo, 'purchase_payment.create'), 'transport' => erp_can($pdo, 'flow.source'),
                               'receive' => erp_can($pdo, 'flow.source') && erp_can($pdo, 'grn.create'), 'qc' => erp_can($pdo, 'qc.manage') && (erp_can($pdo, 'flow.source') || in_array(pf_role_key(), ['executive', 'manager', 'admin'], true) || (int)($_SESSION['admin_role_id'] ?? 0) === 1), 'docs' => erp_can($pdo, 'documents.upload')],
                     'steps' => pf_steps($pdo, $pr, $f, $po, ['quote_count' => count($quotes), 'paid' => $paid, 'grn' => $grn, 'qc' => $qc])]);

        // ------------------------------------------------------------------ quotations
        case 'quotes_save':
        case 'quotes_submit':
            pf_need_any($pdo, ['flow.source'], 'collect shop quotations (L1 sourcing)');
            $prId = (int)erp_input('pr_id');
            $pr = pf_pr($pdo, $prId);
            if ($pr['status'] !== 'approved') erp_invalid('Quotations are collected after the request is approved by Manager and Backend.');
            $f = pf_flow($pdo, $prId, true);
            if (in_array($f['quote_status'], ['submitted', 'approved'], true)) erp_invalid($f['quote_status'] === 'approved' ? 'The shop is already approved.' : 'The quotations are waiting for approval. Withdraw them in Approvals to change them.');
            $selected = (int)erp_input('selected_slot', 0);
            $clean = pq_clean($pdo, erp_json_input('quotes'), $selected);
            $reason = trim((string)erp_input('fewer_reason', ''));
            if ($action === 'quotes_submit') {
                if (!$clean) erp_invalid('Enter the shop quotations first.');
                $sel = array_values(array_filter($clean, fn($c) => $c['is_selected']));
                if (!$sel) erp_invalid('Choose the shop you want to buy from ("Use this quotation").');
                if (!array_filter($sel[0]['items'], fn($i) => $i[1])) erp_invalid('The chosen quotation has no product matched to your items.');
                if (count($clean) < 3 && $reason === '') erp_invalid('Collect 3 shop quotations, or give the reason why fewer shops were asked.');
            }
            $pdo->beginTransaction();
            pq_store($pdo, PQ_PR, $prId, $clean);
            pf_set($pdo, $prId, ['selected_slot' => $selected ?: null, 'fewer_quotes_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null, 'quote_status' => 'collecting']);
            $pdo->commit();
            if ($action === 'quotes_save') erp_out(['status' => 'success', 'message' => count($clean) . ' quotation(s) saved for ' . $pr['pr_number'] . '.']);
            $chosen = array_values(array_filter($clean, fn($c) => $c['is_selected']))[0];
            $lowest = min(array_map(fn($c) => $c['grand_total'], $clean));
            $summary = "{$pr['pr_number']}: buy from {$chosen['supplier_name']} ₹" . number_format($chosen['grand_total'], 2) . ' (' . count($clean) . ' quotations'
                     . ($chosen['grand_total'] > $lowest + 0.004 ? ', ₹' . number_format($chosen['grand_total'] - $lowest, 2) . ' above the lowest' : ', lowest price') . ')';
            $mgrStep = ($mp = apr_policy($pdo, 'pr_quotation_mgr')) && (int)$mp['enabled'];   // FIX (2 Oct 2026): Manager approves the shop first, then Admin
            $num = $mgrStep
                ? apr_open($pdo, 'pr_quotation_mgr', (string)$prId, $prId, $pr['pr_number'], $summary, $chosen['grand_total'], 'purchase_flow_api.php',
                           ['action' => 'quotes_mgr_approve', 'pr_id' => $prId], ['action' => 'quotes_mgr_reject', 'pr_id' => $prId])
                : apr_open($pdo, 'pr_quotation', (string)$prId, $prId, $pr['pr_number'], $summary, $chosen['grand_total'], 'purchase_flow_api.php',
                            ['action' => 'quotes_approve', 'pr_id' => $prId], ['action' => 'quotes_reject', 'pr_id' => $prId]);
            pf_set($pdo, $prId, ['quote_status' => 'submitted', 'quote_submitted_by' => erp_user(), 'quote_submitted_at' => date('Y-m-d H:i:s'), 'quote_remarks' => null]);
            log_audit($pdo, 'update', 'purchase_flows', $prId, null, ['quotes_submitted' => count($clean), 'chosen' => $chosen['supplier_name'], 'approval' => $num]);
            erp_out(['status' => 'success', 'message' => "Sent for " . ($mgrStep ? 'Manager' : 'Admin') . " approval ({$num}): {$chosen['supplier_name']}."]);

        // FIX (2 Oct 2026): Manager decision on the shop choice — approve sends it on to the Admin, reject sends it back to L1
        case 'quotes_mgr_approve':
        case 'quotes_mgr_reject':
            $prId = (int)erp_input('pr_id');
            $pr = pf_pr($pdo, $prId);
            $f = pf_flow($pdo, $prId);
            if (!$f || $f['quote_status'] !== 'submitted') erp_invalid('These quotations are not waiting for approval.');
            $open = pf_mgr_open($pdo, $prId);
            if (!$open && $action === 'quotes_mgr_approve') erp_invalid('These quotations are not waiting for the Manager.');   // a rejection from Approvals closes the request before this runs
            if (!$open && erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'pr_quotation' AND request_key = ? AND status IN ('submitted','under_review')", [(string)$prId])) erp_invalid('The Manager has already approved — it is with the Admin now.');
            $rem = erp_input('_approval_remarks') ?: erp_input('remarks') ?: null;
            if ($action === 'quotes_mgr_reject') {
                pf_set($pdo, $prId, ['quote_status' => 'rejected', 'quote_decided_by' => erp_user(), 'quote_decided_at' => date('Y-m-d H:i:s'), 'quote_remarks' => $rem ? mb_substr('Manager: ' . $rem, 0, 255) : 'Rejected by Manager']);
                apr_close($pdo, 'pr_quotation_mgr', (string)$prId, 'rejected', $rem);
                log_audit($pdo, 'reject', 'purchase_flows', $prId, null, ['shop_choice' => 'rejected by manager', 'remarks' => $rem]);
                erp_out(['status' => 'success', 'message' => 'Quotation rejected by the Manager — it goes back to L1 for changes.']);
            }
            apr_close($pdo, 'pr_quotation_mgr', (string)$prId, 'approved', $rem);
            $num = apr_open($pdo, 'pr_quotation', (string)$prId, $prId, $pr['pr_number'], mb_substr($open['summary'] . ' · Manager approved: ' . erp_user(), 0, 255), $open['amount'] !== null ? (float)$open['amount'] : null,
                            'purchase_flow_api.php', ['action' => 'quotes_approve', 'pr_id' => $prId], ['action' => 'quotes_reject', 'pr_id' => $prId]);
            log_audit($pdo, 'approve', 'purchase_flows', $prId, null, ['shop_choice' => 'approved by manager', 'sent_to_admin' => $num]);
            erp_out(['status' => 'success', 'message' => "Shop approved by the Manager — sent to the Admin ({$num})."]);

        case 'quotes_reject':
            $prId = (int)erp_input('pr_id');
            $f = pf_flow($pdo, $prId);
            if (!$f || $f['quote_status'] !== 'submitted') erp_invalid('These quotations are not waiting for approval.');
            $rem = erp_input('_approval_remarks') ?: erp_input('remarks') ?: null;
            pf_set($pdo, $prId, ['quote_status' => 'rejected', 'quote_decided_by' => erp_user(), 'quote_decided_at' => date('Y-m-d H:i:s'), 'quote_remarks' => $rem ? mb_substr($rem, 0, 255) : null]);
            apr_close($pdo, 'pr_quotation', (string)$prId, 'rejected', $rem);
            apr_close($pdo, 'pr_quotation_mgr', (string)$prId, 'rejected', $rem);   // FIX (2 Oct 2026)
            erp_out(['status' => 'success', 'message' => 'Quotation rejected — it goes back for changes.']);

        case 'quotes_approve':
        case 'quotes_retry_po':
            // Approve the chosen shop and create the purchase order through purchase_api.php (po_submit → PO approval).
            $prId = (int)erp_input('pr_id');
            $pr = pf_pr($pdo, $prId);
            $f = pf_flow($pdo, $prId);
            if ($action === 'quotes_approve' && (!$f || $f['quote_status'] !== 'submitted')) erp_invalid('These quotations are not waiting for approval.');
            if ($action === 'quotes_approve' && pf_mgr_open($pdo, $prId)) erp_invalid('The Manager has not approved this shop yet — it reaches the Admin after the Manager.');   // FIX (2 Oct 2026)
            if ($action === 'quotes_retry_po' && (!$f || $f['quote_status'] !== 'approved' || $f['po_id'])) erp_invalid('A purchase order can be created only for an approved shop without a PO.');
            if (!in_array($pr['status'], ['approved', 'converted'], true)) erp_invalid('The purchase request is no longer approved.');
            $quotes = pq_load($pdo, PQ_PR, $prId);
            $chosen = null; foreach ($quotes as $q) if ((int)$q['is_selected']) $chosen = $q;
            if (!$chosen) erp_invalid('No shop is chosen.');
            $lines = [];
            foreach ($chosen['items'] as $i) {
                if (!$i['item_type'] || !$i['item_id'] || (float)$i['rate'] <= 0) continue;
                $lines[] = ['item_type' => $i['item_type'], 'item_id' => (int)$i['item_id'], 'quantity' => (float)$i['quantity'], 'rate' => (float)$i['rate'], 'discount_amount' => 0, 'tax_percent' => (float)$i['tax_percent']];
            }
            if (!$lines) erp_invalid('The chosen quotation has no priced product lines.');
            $pdo->beginTransaction();
            $supplierId = pf_supplier_from_quote($pdo, $chosen);
            $pdo->commit();
            $poDate = date('Y-m-d');
            $exp = $chosen['delivery_days'] !== null ? date('Y-m-d', strtotime("+{$chosen['delivery_days']} days")) : ($pr['required_by'] && $pr['required_by'] >= $poDate ? $pr['required_by'] : null);
            $remarks = (string)(erp_input('_approval_remarks') ?: '');
            $GLOBALS['pf_ctx'] = ['pr' => $pr, 'chosen' => $chosen, 'quotes' => $quotes, 'remarks' => $remarks, 'approval' => $action === 'quotes_approve'];
            ob_start();
            register_shutdown_function(function () use ($pdo, $prId) {
                $out = ob_get_clean();
                $res = json_decode((string)$out, true);
                $ctx = $GLOBALS['pf_ctx'];
                if (($res['status'] ?? '') === 'success' && !empty($res['id'])) {
                    try {
                        $poId = (int)$res['id'];
                        $pdo->prepare("UPDATE purchase_orders SET pr_id = ? WHERE id = ? AND pr_id IS NULL")->execute([$prId, $poId]);
                        $pdo->prepare("UPDATE purchase_requests SET status = 'converted' WHERE id = ? AND status = 'approved'")->execute([$prId]);
                        $cols = ['po_id' => $poId];
                        if ($ctx['approval']) $cols += ['quote_status' => 'approved', 'quote_decided_by' => erp_user(), 'quote_decided_at' => date('Y-m-d H:i:s'), 'quote_remarks' => $ctx['remarks'] ? mb_substr($ctx['remarks'], 0, 255) : null];
                        pf_set($pdo, $prId, $cols);
                        if ($ctx['approval']) {
                            apr_close($pdo, 'pr_quotation', (string)$prId, 'approved', $ctx['remarks'] ?: null);
                            $pdo->prepare("UPDATE approval_requests SET execution_error = NULL WHERE module = 'pr_quotation' AND request_key = ?")->execute([(string)$prId]);
                        }
                        if (pq_table_exists($pdo, 'po_competitor_quotes')) {   // the PO shows the same comparison
                            $copy = array_map(function ($q) {
                                $q['items'] = array_map(fn($i) => [$i['item_type'] ?: null, $i['item_id'] ?: null, $i['item_name'], $i['quantity'], $i['unit'], $i['rate'], $i['tax_percent'], $i['line_total']], $q['items']);
                                return $q;
                            }, $ctx['quotes']);
                            pq_store($pdo, ['q' => 'po_competitor_quotes', 'i' => 'po_competitor_quote_items', 'fk' => 'po_id'], $poId, $copy);
                        }
                        log_audit($pdo, 'approve', 'purchase_flows', $prId, null, ['shop' => $ctx['chosen']['supplier_name'], 'po' => $res['po_number'] ?? $poId]);
                        $res['message'] = "Shop approved: {$ctx['chosen']['supplier_name']}. " . ($res['message'] ?? 'Purchase order created.') . ' It is now waiting for PO approval.';
                    } catch (Throwable $e) { error_log('[purchase flow] ' . $e->getMessage()); }
                } else {
                    $res = ['status' => 'error', 'message' => 'The purchase order could not be created: ' . ($res['message'] ?? 'unknown error') . ' — fix it and approve again.'];
                    if ($ctx['approval']) try { $pdo->prepare("UPDATE approval_requests SET execution_error = ? WHERE module = 'pr_quotation' AND request_key = ? AND status IN ('submitted','under_review')")->execute([mb_substr($res['message'], 0, 255), (string)$prId]); } catch (Throwable $e) {}
                }
                echo json_encode($res);
            });
            $_POST = ['action' => 'po_submit', 'supplier_id' => $supplierId, 'po_date' => $poDate, 'expected_delivery_date' => $exp ?: '', 'warehouse_id' => $pr['warehouse_id'] ?: 1,
                      'buyer' => erp_user(), 'other_charges' => (float)$chosen['freight'], 'items' => json_encode($lines),
                      'notes' => "From {$pr['pr_number']} — quotation of {$chosen['supplier_name']}" . ($chosen['delivery_days'] !== null ? " (delivery {$chosen['delivery_days']} days)" : '') . ($chosen['payment_terms'] ? ", terms: {$chosen['payment_terms']}" : '')];
            $_GET = [];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            require __DIR__ . '/purchase_api.php';   // the PO module itself creates the PO and opens its approval
            exit;

        // ------------------------------------------------------------------ payment
        // FIX (2 Oct 2026): Accounts Team checks the approved PO (supplier, bank details, amount) before paying
        case 'po_check':
            pf_need_any($pdo, ['purchase_payment.create'], 'check purchase orders for payment');
            if (!pf_has_po_check($pdo)) erp_invalid('Run pr_po_check_migration.sql once in HeidiSQL to switch on "PO checked".');
            $prId = (int)erp_input('pr_id');
            $f = pf_flow($pdo, $prId);
            $po = $f ? pf_po_for($pdo, $f) : null;
            if (!$po) erp_invalid('There is no purchase order yet.');
            if (!in_array($po['status'], ['approved', 'partially_received', 'fully_received', 'closed'], true)) erp_invalid('The purchase order is not approved yet.');
            if (!empty($f['po_checked_at'])) erp_invalid('This PO is already checked.');
            pf_set($pdo, $prId, ['po_checked_by' => erp_user(), 'po_checked_at' => date('Y-m-d H:i:s')]);
            log_audit($pdo, 'update', 'purchase_flows', $prId, null, ['po_checked' => $po['po_number']]);
            erp_out(['status' => 'success', 'message' => "{$po['po_number']} marked as checked — waiting for payment."]);

        case 'pay_link':
            pf_need_any($pdo, ['purchase_payment.create'], 'record supplier payments');
            $prId = (int)erp_input('pr_id');
            $f = pf_flow($pdo, $prId);
            $po = $f ? pf_po_for($pdo, $f) : null;
            if (!$po) erp_invalid('Create the purchase order first.');
            $payId = (int)erp_input('payment_id', 0);
            $docId = (int)erp_input('proof_doc_id', 0);
            $cols = [];
            if ($payId) {
                $pay = erp_row($pdo, "SELECT * FROM purchase_payments WHERE id = ?", [$payId]);
                if (!$pay || (int)$pay['supplier_id'] !== (int)$po['supplier_id']) erp_invalid('That payment is not for this supplier.');
                $cols['payment_id'] = $payId;
            }
            if ($docId) {
                if (!pf_doc_ok($pdo, $docId, [['purchase_order', (int)$po['id']], ['purchase_payment', $payId ?: (int)($f['payment_id'] ?? 0)]])) erp_invalid('The proof document is not attached to this PO or payment.');
                $cols['payment_proof_doc_id'] = $docId;
            }
            if (!$cols) erp_invalid('Nothing to save.');
            pf_set($pdo, $prId, $cols);
            erp_out(['status' => 'success', 'message' => 'Payment recorded in the purchase flow.']);

        // ------------------------------------------------------------------ transport
        case 'transport_save':
            pf_need_any($pdo, ['flow.source'], 'record transport (L1 sourcing)');
            $prId = (int)erp_input('pr_id');
            $f = pf_flow($pdo, $prId);
            pf_buyer_guard($f);   // FIX (2 Oct 2026)
            $po = $f ? pf_po_for($pdo, $f) : null;
            if (!$po) erp_invalid('Create the purchase order first.');
            $mode = (string)erp_input('delivery_mode');
            if (!in_array($mode, ['internal', 'courier'], true)) erp_invalid('Choose internal delivery or courier.');
            $phone = preg_replace('/[^0-9+]/', '', (string)erp_input('driver_phone', ''));
            if ($phone !== '' && !preg_match('/^\+?\d{10,13}$/', $phone)) erp_invalid('Enter a valid phone number for the driver / delivery person (10 digits).');
            $driver = mb_substr(trim((string)erp_input('driver_name', '')), 0, 150);
            $vehicle = strtoupper(mb_substr(trim((string)erp_input('vehicle_number', '')), 0, 30));
            $tracking = mb_substr(trim((string)erp_input('tracking_number', '')), 0, 80);
            $csId = (int)erp_input('courier_service_id', 0) ?: null;
            $cname = mb_substr(trim((string)erp_input('courier_name', '')), 0, 150);
            if ($mode === 'courier') {
                $cs = $csId ? erp_row($pdo, "SELECT * FROM courier_services WHERE id = ?", [$csId]) : null;
                if (!$cs) erp_invalid('Choose the courier service.');
                if (stripos($cs['name'], 'other') === 0) { if ($cname === '') erp_invalid('Type the courier service name.'); } else $cname = $cs['name'];
                if ($tracking === '') erp_invalid('Enter the courier tracking / AWB number.');
            } else {
                $csId = null; $cname = null;
                if ($driver === '' || $phone === '') erp_invalid('Enter the driver name and phone number.');
            }
            $dispatch = erp_date(erp_input('dispatch_date', '')) ?: null;
            $pdo->beginTransaction();
            $shipId = (int)($f['shipment_id'] ?? 0);
            $vals = [$po['id'], $po['supplier_id'], $mode === 'courier' ? $cname : $driver, $mode === 'courier' ? $cname : null, $mode === 'courier' ? 'courier' : 'own_vehicle',
                     $vehicle ?: null, $driver ?: null, $phone ?: null, $tracking ?: null, $dispatch];
            if ($shipId && erp_val($pdo, "SELECT id FROM inbound_shipments WHERE id = ? AND status <> 'cancelled'", [$shipId])) {
                $pdo->prepare("UPDATE inbound_shipments SET po_id=?, supplier_id=?, transporter_name=?, transport_company=?, transport_mode=?, vehicle_number=?, driver_name=?, driver_phone=?, consignment_number=?, dispatch_date=? WHERE id=?")
                    ->execute(array_merge($vals, [$shipId]));
            } else {
                $num = next_document_number($pdo, 'shipment', 'SHP');
                $pdo->prepare("INSERT INTO inbound_shipments (shipment_number, po_id, supplier_id, transporter_name, transport_company, transport_mode, vehicle_number, driver_name, driver_phone,
                               consignment_number, dispatch_date, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'in_transit', ?, ?)")
                    ->execute(array_merge([$num], $vals, ['From purchase flow', erp_user()]));
                $shipId = (int)$pdo->lastInsertId();
            }
            pf_set($pdo, $prId, ['delivery_mode' => $mode, 'courier_service_id' => $csId, 'courier_name' => $cname, 'tracking_number' => $tracking ?: null, 'driver_name' => $driver ?: null,
                                 'driver_phone' => $phone ?: null, 'vehicle_number' => $vehicle ?: null, 'dispatch_date' => $dispatch, 'shipment_id' => $shipId]);
            $pdo->commit();
            log_audit($pdo, 'update', 'purchase_flows', $prId, null, ['transport' => $mode, 'courier' => $cname, 'tracking' => $tracking, 'shipment_id' => $shipId]);
            erp_out(['status' => 'success', 'shipment_id' => $shipId, 'message' => 'Transport details saved' . ($mode === 'courier' ? " ({$cname} · {$tracking})." : '.')]);

        // ------------------------------------------------------------------ documents (attached to the PO, recorded per slot)
        case 'doc_link':
            $prId = (int)erp_input('pr_id');
            $slot = (string)erp_input('slot');
            if (!isset(PF_DOC_SLOTS[$slot])) erp_invalid('Unknown document type.');
            pf_need_any($pdo, $slot === 'payment_proof' ? ['purchase_payment.create'] : ['flow.source'], 'attach this document');
            $f = pf_flow($pdo, $prId);
            if ($slot !== 'payment_proof') pf_buyer_guard($f);   // FIX (2 Oct 2026)
            $po = $f ? pf_po_for($pdo, $f) : null;
            if (!$po) erp_invalid('Create the purchase order first.');
            $docId = (int)erp_input('doc_id');
            if (!pf_doc_ok($pdo, $docId, [['purchase_order', (int)$po['id']], ['purchase_payment', (int)($f['payment_id'] ?? 0)]])) erp_invalid('The document is not attached to this purchase order.');
            $cols = [PF_DOC_SLOTS[$slot][0] => $docId];
            if ($slot === 'shop_bill') {
                $type = (string)erp_input('bill_type');
                if (!in_array($type, ['handwritten', 'system'], true)) erp_invalid('Choose handwritten or system bill.');
                $cols['shop_bill_type'] = $type;
                $cols['shop_bill_number'] = mb_substr(trim((string)erp_input('bill_number', '')), 0, 60) ?: null;
            }
            pf_set($pdo, $prId, $cols);
            erp_out(['status' => 'success', 'message' => 'Document saved in the purchase flow.']);

        // ------------------------------------------------------------------ unloading check → GRN → QC
        case 'unload_save':
            pf_need_any($pdo, ['flow.source'], 'do the unloading check (L1 sourcing)');
            $prId = (int)erp_input('pr_id');
            $f = pf_flow($pdo, $prId);
            pf_buyer_guard($f);   // FIX (2 Oct 2026): the L1 who bought also unloads + checks the quantity
            $po = $f ? pf_po_for($pdo, $f) : null;
            if (!$po) erp_invalid('Create the purchase order first.');
            if (!in_array($po['status'], ['approved', 'partially_received', 'fully_received'], true)) erp_invalid('The purchase order must be approved before goods are received.');
            $poItems = []; foreach (erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ?", [$po['id']]) as $pi) $poItems[(int)$pi['id']] = $pi;
            $lines = []; $ok = true;
            foreach (erp_json_input('lines') as $l) {
                $pid = (int)($l['po_item_id'] ?? 0);
                if (!isset($poItems[$pid])) erp_invalid('A line does not belong to this purchase order.');
                $ordered = (float)$poItems[$pid]['quantity'];
                $rec = erp_q(erp_num($l['received_qty'] ?? 0, 'Received quantity'));
                $dmg = erp_q(erp_num($l['damaged_qty'] ?? 0, 'Damaged quantity'));
                if ($dmg > $rec) erp_invalid('Damaged quantity cannot be more than the received quantity.');
                $match = abs($rec - $ordered) < 0.0005 && $dmg == 0;
                if (!$match) $ok = false;
                $lines[] = ['po_item_id' => $pid, 'ordered' => $ordered, 'received' => $rec, 'damaged' => $dmg, 'match' => $match, 'note' => mb_substr(trim((string)($l['note'] ?? '')), 0, 200)];
            }
            if (!$lines) erp_invalid('Enter the unloaded quantities.');
            if (!$ok && trim((string)erp_input('remarks', '')) === '') erp_invalid('The quantity does not match the order — write what was short, extra or damaged.');
            pf_set($pdo, $prId, ['unload_check' => json_encode(['lines' => $lines, 'remarks' => mb_substr(trim((string)erp_input('remarks', '')), 0, 300)]), 'unload_ok' => $ok ? 1 : 0,
                                 'unload_checked_by' => erp_user(), 'unload_checked_at' => date('Y-m-d H:i:s')]);
            log_audit($pdo, 'update', 'purchase_flows', $prId, null, ['unload_ok' => $ok, 'lines' => $lines]);
            erp_out(['status' => 'success', 'ok' => $ok, 'message' => $ok ? 'Unloading checked — quantity is correct.' : 'Unloading checked — differences recorded.']);

        case 'grn_link':
        case 'qc_link':
            pf_need_any($pdo, $action === 'qc_link' ? ['flow.source', 'qc.manage'] : ['flow.source'], 'link this record');   // FIX (2 Oct 2026): Executive does the quality check
            $prId = (int)erp_input('pr_id');
            $f = pf_flow($pdo, $prId);
            $po = $f ? pf_po_for($pdo, $f) : null;
            if (!$po) erp_invalid('Create the purchase order first.');
            if ($action === 'grn_link') {
                $gid = (int)erp_input('grn_id');
                if (!erp_val($pdo, "SELECT id FROM goods_receipts WHERE id = ? AND po_id = ?", [$gid, $po['id']])) erp_invalid('That goods receipt is not for this purchase order.');
                pf_set($pdo, $prId, ['grn_id' => $gid]);
            } else {
                $qid = (int)erp_input('qc_id');
                if (!$f['grn_id'] || !erp_val($pdo, "SELECT id FROM quality_checks WHERE id = ? AND grn_id = ?", [$qid, $f['grn_id']])) erp_invalid('That quality check is not for this goods receipt.');
                pf_set($pdo, $prId, ['qc_id' => $qid]);
            }
            erp_out(['status' => 'success', 'message' => 'Linked.']);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'purchase flow');
}
