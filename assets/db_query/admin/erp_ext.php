<?php
// ============================================================================
// Complete-ERP shared services (added 1 Oct 2026). Loaded by the new ERP APIs.
//   * settings + permission checks
//   * reusable APPROVAL engine (approval_policies / approval_requests / approval_actions)
//   * central ADMIN NOTIFICATIONS (admin_notifications)
//   * WAREHOUSE sub-ledger sync (warehouse_stock follows every stock movement)
//   * FEFO batch allocation (batch_allocations)
//   * document relations (a document on one transaction is visible from the related ones)
// Nothing in here changes an existing module; existing tables are only read,
// except the existing stock posting helpers in erp_helper.php.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/costing_engine.php';

// ---------------------------------------------------------------- guard / settings
/** Session + permission + the complete-ERP tables installed. */
function erpx_guard(PDO $pdo, ?string $perm = null) {
    erp_guard($pdo, $perm);
    static $ok = false;
    if (!$ok) {
        try { $pdo->query("SELECT 1 FROM approval_policies LIMIT 1"); }
        catch (PDOException $e) { erp_fail('The complete-ERP database tables are not installed yet. Run erp_complete_migration.sql once in HeidiSQL / phpMyAdmin.'); }
        $ok = true;
    }
}
/** true when the logged-in admin has the permission (Super Admin always). erp_helper.php may already define it. */
if (!function_exists('erp_can')) {
function erp_can(PDO $pdo, string $perm): bool {
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) return true;
    static $cache = [];
    if (!isset($cache[$perm])) {
        $cache[$perm] = (bool)erp_val($pdo, "SELECT 1 FROM admin_role_permissions WHERE role_id = ? AND perm_key = ?", [(int)($_SESSION['admin_role_id'] ?? 0), $perm]);
    }
    return $cache[$perm];
}
}
function erp_setting(PDO $pdo, string $key, $default = null) {
    try { $v = erp_val($pdo, "SELECT setting_value FROM admin_settings WHERE setting_key = ?", [$key]); }
    catch (PDOException $e) { return $default; }
    return $v === false || $v === null ? $default : $v;
}
function erp_setting_set(PDO $pdo, string $key, string $value, string $desc = '') {
    $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, description) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $value, $desc ?: null]);
}
/** Named lock so two browsers never run the same background sync at once. */
function erp_lock(PDO $pdo, string $name, int $wait = 5): bool { return (int)erp_val($pdo, "SELECT GET_LOCK(?, ?)", ['valluvam_' . $name, $wait]) === 1; }
function erp_unlock(PDO $pdo, string $name) { erp_val($pdo, "SELECT RELEASE_LOCK(?)", ['valluvam_' . $name]); }
function erp_cursor(PDO $pdo, string $name): ?int {
    $v = erp_val($pdo, "SELECT last_id FROM erp_sync_state WHERE name = ?", [$name]);
    return $v === false ? null : (int)$v;
}
function erp_cursor_set(PDO $pdo, string $name, int $id) {
    $pdo->prepare("INSERT INTO erp_sync_state (name, last_id) VALUES (?,?) ON DUPLICATE KEY UPDATE last_id = VALUES(last_id)")->execute([$name, $id]);
}
/** Server-side paging: returns [limit, offset, page]. */
function erp_page_args(int $default = 50): array {
    $per = max(10, min(500, (int)erp_input('per_page', $default)));
    $page = max(1, (int)erp_input('page', 1));
    return [$per, ($page - 1) * $per, $page];
}
function erp_warehouse_ok(PDO $pdo, int $id): int {
    if ($id <= 0 || !erp_val($pdo, "SELECT id FROM warehouses WHERE id = ?", [$id])) erp_invalid('Choose a valid warehouse.');
    return $id;
}

// ---------------------------------------------------------------- APPROVALS
function apr_policy(PDO $pdo, string $module): ?array {
    return erp_row($pdo, "SELECT * FROM approval_policies WHERE module = ?", [$module]);
}
function apr_is_approver(PDO $pdo, string $module): bool {
    $p = apr_policy($pdo, $module);
    return erp_can($pdo, $p['approver_perm'] ?? 'purchase.approve');
}
/** Opens (or refreshes) the approval request for a document. Returns its number. */
function apr_open(PDO $pdo, string $module, string $key, ?int $entityId, ?string $ref, string $summary, ?float $amount, string $endpoint, array $approvePayload, ?array $rejectPayload = null): string {
    $open = erp_row($pdo, "SELECT id, request_number FROM approval_requests WHERE module = ? AND request_key = ? AND status IN ('submitted','under_review') ORDER BY id DESC LIMIT 1", [$module, $key]);
    if ($open) {
        $pdo->prepare("UPDATE approval_requests SET reference = ?, summary = ?, amount = ?, endpoint = ?, approve_payload = ?, reject_payload = ?, submitted_by = ?, submitted_at = NOW() WHERE id = ?")
            ->execute([$ref, mb_substr($summary, 0, 255), $amount, $endpoint, json_encode($approvePayload), $rejectPayload ? json_encode($rejectPayload) : null, erp_user(), $open['id']]);
        $pdo->prepare("INSERT INTO approval_actions (request_id, action, by_user, remarks) VALUES (?, 'resubmit', ?, NULL)")->execute([$open['id'], erp_user()]);
        return $open['request_number'];
    }
    $num = next_document_number($pdo, 'approval', 'APR');
    $pdo->prepare("INSERT INTO approval_requests (request_number, module, request_key, entity_id, reference, summary, amount, status, endpoint, approve_payload, reject_payload, submitted_by, submitted_at)
                   VALUES (?,?,?,?,?,?,?, 'submitted', ?,?,?,?, NOW())")
        ->execute([$num, $module, $key, $entityId, $ref, mb_substr($summary, 0, 255), $amount, $endpoint, json_encode($approvePayload), $rejectPayload ? json_encode($rejectPayload) : null, erp_user()]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO approval_actions (request_id, action, by_user) VALUES (?, 'submit', ?)")->execute([$id, erp_user()]);
    return $num;
}
/** Closes the open request(s) of a document with a decision. Safe to call when none exists. */
function apr_close(PDO $pdo, string $module, string $key, string $decision, ?string $remarks = null) {
    try {
        $rows = erp_rows($pdo, "SELECT id FROM approval_requests WHERE module = ? AND request_key = ? AND status IN ('submitted','under_review','draft')", [$module, $key]);
        foreach ($rows as $r) {
            $pdo->prepare("UPDATE approval_requests SET status = ?, decided_by = ?, decided_at = NOW(), remarks = ?, executed_at = IF(? = 'approved', NOW(), executed_at) WHERE id = ?")
                ->execute([$decision, erp_user(), $remarks ? mb_substr($remarks, 0, 255) : null, $decision, $r['id']]);
            $pdo->prepare("INSERT INTO approval_actions (request_id, action, by_user, remarks) VALUES (?,?,?,?)")->execute([$r['id'], $decision === 'approved' ? 'approve' : ($decision === 'rejected' ? 'reject' : 'cancel'), erp_user(), $remarks]);
        }
    } catch (PDOException $e) { error_log('[ERP approvals] ' . $e->getMessage()); }
}
/** true when the rule for this module is on and the amount reaches its limit. */
function apr_applies(PDO $pdo, string $module, float $amount): bool {
    $p = apr_policy($pdo, $module);
    return $p && ((int)$p['enabled'] || (int)$p['inherent']) && $amount + 0.0001 >= (float)$p['min_amount'];
}
/** An approver posts directly: the approval is recorded, and marked executed once the call succeeds. */
function apr_record_direct(PDO $pdo, string $module, string $key, float $amount, string $summary, ?string $ref, string $endpoint, ?int $entityId) {
    $existing = erp_val($pdo, "SELECT id FROM approval_requests WHERE module = ? AND request_key = ? AND status IN ('submitted','under_review') LIMIT 1", [$module, $key]);
    if (!$existing) {
        $num = next_document_number($pdo, 'approval', 'APR');
        $pdo->prepare("INSERT INTO approval_requests (request_number, module, request_key, entity_id, reference, summary, amount, status, endpoint, submitted_by, submitted_at, decided_by, decided_at, remarks)
                       VALUES (?,?,?,?,?,?,?, 'approved', ?, ?, NOW(), ?, NOW(), 'Posted directly by an approver')")
            ->execute([$num, $module, $key, $entityId, $ref, mb_substr($summary, 0, 255), $amount, $endpoint, erp_user(), erp_user()]);
        $GLOBALS['erp_apr_exec'] = (int)$pdo->lastInsertId();
    } else {
        apr_close($pdo, $module, $key, 'approved', erp_input('_approval_remarks') ?: null);
        $pdo->prepare("UPDATE approval_requests SET executed_at = NULL WHERE id = ?")->execute([$existing]);
        $GLOBALS['erp_apr_exec'] = (int)$existing;
    }
    ob_start();
    register_shutdown_function(function () use ($pdo) {
        $out = ob_get_contents();
        $res = json_decode((string)$out, true);
        $id = $GLOBALS['erp_apr_exec'] ?? null;
        if ($id) {
            try {
                if (($res['status'] ?? '') === 'success') $pdo->prepare("UPDATE approval_requests SET executed_at = NOW(), execution_error = NULL WHERE id = ?")->execute([$id]);
                else $pdo->prepare("UPDATE approval_requests SET executed_at = NULL, execution_error = ? WHERE id = ?")->execute([mb_substr((string)($res['message'] ?? 'Posting failed'), 0, 255), $id]);
            } catch (Throwable $e) { error_log('[ERP approvals] ' . $e->getMessage()); }
        }
        if (ob_get_level()) ob_end_flush();
    });
}
/**
 * For posting actions WITHOUT a draft (supplier payment, purchase return, sales return):
 * rule off / below limit → proceed; approver → proceed and record; anyone else → the exact
 * request is stored as an approval request and this call stops with "sent for approval".
 * The approver's "Approve" in Approvals replays the same request. Call BEFORE the transaction.
 */
function apr_gate(PDO $pdo, string $module, string $key, float $amount, string $summary, ?string $ref, string $endpoint, array $payload, ?int $entityId = null) {
    if (!apr_applies($pdo, $module, $amount)) return;
    if (apr_is_approver($pdo, $module)) { apr_record_direct($pdo, $module, $key, $amount, $summary, $ref, $endpoint, $entityId); return; }
    $num = apr_open($pdo, $module, $key, $entityId, $ref, $summary, $amount, $endpoint, $payload);
    log_audit($pdo, 'submit', 'approval_requests', $num, null, ['module' => $module, 'reference' => $ref, 'amount' => $amount]);
    erp_out(['status' => 'success', 'pending_approval' => true, 'approval_number' => $num,
             'message' => "Sent for approval ({$num}). It will be posted when an approver approves it in Approvals."]);
}
/** For posting actions WITH a draft (bills, expenses): returns true when the caller must save a draft and open a request instead of posting. */
function apr_intercept(PDO $pdo, string $module, string $key, float $amount, string $summary, ?string $ref, string $endpoint, ?int $entityId = null): bool {
    if (!apr_applies($pdo, $module, $amount)) return false;
    if (apr_is_approver($pdo, $module)) { apr_record_direct($pdo, $module, $key, $amount, $summary, $ref, $endpoint, $entityId); return false; }
    return true;
}
/** Complete-ERP tables installed? (hooks in the first-release APIs stay silent until the migration is run) */
function erpx_installed(PDO $pdo): bool {
    static $ok = null;
    if ($ok === null) { try { $pdo->query("SELECT 1 FROM approval_policies LIMIT 1"); $ok = true; } catch (PDOException $e) { $ok = false; } }
    return $ok;
}
/** The request payload as the gate sees it (everything posted except internal keys). */
function apr_payload(): array {
    $p = $_POST;
    unset($p['_approval_id'], $p['_approval_remarks']);
    return $p;
}

// ---------------------------------------------------------------- NOTIFICATIONS
/** Recomputes the alert list (at most every 5 minutes unless $force). */
function ntf_refresh(PDO $pdo, bool $force = false) {
    $last = (int)erp_setting($pdo, 'erp_ntf_last_run', 0);
    if (!$force && time() - $last < 300) return;
    if (!erp_lock($pdo, 'ntf', 0)) return;
    try {
        erp_setting_set($pdo, 'erp_ntf_last_run', (string)time(), 'Last time admin alerts were refreshed');
        $alerts = [];
        $add = function ($key, $type, $sev, $title, $msg, $link, $perm = null) use (&$alerts) { $alerts[$key] = compact('type', 'sev', 'title', 'msg', 'link', 'perm'); };
        $today = date('Y-m-d');

        foreach (erp_rows($pdo, "SELECT r.module, p.label, p.approver_perm, COUNT(*) n FROM approval_requests r LEFT JOIN approval_policies p ON p.module = r.module
                                 WHERE r.status IN ('submitted','under_review') GROUP BY r.module, p.label, p.approver_perm") as $r)
            $add('approvals:' . $r['module'], 'approval', 'warning', "{$r['n']} waiting for approval — " . ($r['label'] ?: $r['module']), 'Open Approvals to approve or reject.', 'approvals.php?module=' . urlencode($r['module']), $r['approver_perm']);

        $out = (int)erp_val($pdo, "SELECT COUNT(*) FROM product_details WHERE stock = 0");
        $low = (int)erp_val($pdo, "SELECT COUNT(*) FROM product_details WHERE stock > 0 AND stock < 20");
        if ($out) $add('stock:out', 'stock', 'critical', "{$out} product(s) out of stock", 'Restock or mark them unavailable.', 'inventory_overview.php', 'inventory.view');
        if ($low) $add('stock:low', 'stock', 'warning', "{$low} product(s) low on stock (below 20)", 'Raise a purchase request.', 'inventory_overview.php', 'inventory.view');
        $rawLow = (int)erp_val($pdo, "SELECT COUNT(*) FROM raw_materials WHERE status = 'active' AND reorder_level IS NOT NULL AND stock <= reorder_level");
        if ($rawLow) $add('stock:raw_low', 'stock', 'warning', "{$rawLow} raw material(s) at or below reorder level", '', 'raw_materials.php', 'raw_materials.manage');

        fefo_sync($pdo);
        $days = (int)erp_setting($pdo, 'erp_expiry_alert_days', 30);
        $bq = batch_remaining_rows($pdo);
        $expired = 0; $near = 0;
        foreach ($bq as $b) {
            if ($b['remaining'] <= 0.0005 || !$b['expiry_date']) continue;
            if ($b['expiry_date'] < $today) $expired++; elseif ($b['expiry_date'] <= date('Y-m-d', strtotime("+{$days} days"))) $near++;
        }
        if ($expired) $add('expiry:expired', 'expiry', 'critical', "{$expired} batch(es) EXPIRED with stock left", 'Move them to the expired bucket so they are not sold.', 'batches.php?status=expired', 'warehouse.view');
        if ($near) $add('expiry:near', 'expiry', 'warning', "{$near} batch(es) expire within {$days} days", 'Sell these first (FEFO).', 'batches.php?status=expiring', 'warehouse.view');

        $bills = erp_rows($pdo, "SELECT pi.id, pi.due_date, pi.grand_total,
                                   pi.grand_total - COALESCE((SELECT SUM(amount) FROM purchase_payments pp WHERE pp.pinv_id = pi.id AND pp.status = 'completed'),0)
                                   - COALESCE((SELECT SUM(total_value - refund_received) FROM purchase_returns r WHERE r.pinv_id = pi.id AND r.status = 'posted' AND r.settlement IN ('credit_note','refund')),0) AS bal
                                 FROM purchase_invoices pi WHERE pi.status = 'posted' AND pi.due_date IS NOT NULL");
        $od = 0; $soon = 0; $odAmt = 0;
        foreach ($bills as $b) {
            if ($b['bal'] <= 0.005) continue;
            if ($b['due_date'] < $today) { $od++; $odAmt += $b['bal']; } elseif ($b['due_date'] <= date('Y-m-d', strtotime('+7 days'))) $soon++;
        }
        if ($od) $add('ap:overdue', 'payable', 'critical', "{$od} supplier bill(s) overdue (₹" . number_format($odAmt, 2) . ')', 'Pay or agree new dates.', 'purchase_invoices.php?status=overdue', 'purchase_payment.create');
        if ($soon) $add('ap:due_soon', 'payable', 'warning', "{$soon} supplier bill(s) due in the next 7 days", '', 'purchase_invoices.php?status=unpaid', 'purchase_payment.create');

        $arOd = (int)erp_val($pdo, "SELECT COUNT(*) FROM invoices WHERE status NOT IN ('draft','cancelled','paid') AND due_date IS NOT NULL AND due_date < ? AND grand_total - COALESCE(amount_paid,0) > 0.005", [$today]);
        if ($arOd) $add('ar:overdue', 'receivable', 'critical', "{$arOd} customer invoice(s) overdue", 'Follow up for payment.', 'receivables.php', 'invoices.view');
        $csOld = (int)erp_val($pdo, "SELECT COUNT(*) FROM credit_sales WHERE status <> 'paid' AND sale_date < ?", [date('Y-m-d', strtotime('-30 days'))]);
        if ($csOld) $add('ar:credit_old', 'receivable', 'warning', "{$csOld} credit sale(s) unpaid for over 30 days", '', 'receivables.php', 'credit_sales.view');

        $late = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('approved','partially_received') AND expected_delivery_date IS NOT NULL AND expected_delivery_date < ?", [$today]);
        if ($late) $add('po:late', 'purchase', 'warning', "{$late} purchase order(s) past the expected delivery date", 'Goods not fully received yet.', 'purchase_orders.php?status=open', 'purchase.view');
        $draftGrn = (int)erp_val($pdo, "SELECT COUNT(*) FROM goods_receipts WHERE status = 'draft' AND created_at < ?", [date('Y-m-d H:i:s', strtotime('-1 day'))]);
        if ($draftGrn) $add('grn:draft', 'purchase', 'info', "{$draftGrn} goods receipt(s) still in draft", 'Stock is not added until they are posted.', 'goods_receipts.php?status=draft', 'grn.create');
        $qc = (int)erp_val($pdo, "SELECT COUNT(*) FROM quality_checks WHERE status = 'pending'");
        if ($qc) $add('qc:pending', 'quality', 'warning', "{$qc} quality check(s) pending", 'Goods wait in quarantine until checked.', 'quality_checks.php?status=pending', 'qc.manage');
        $unbilled = (int)erp_val($pdo, "SELECT COUNT(DISTINCT g.id) FROM goods_receipts g JOIN goods_receipt_items gi ON gi.grn_id = g.id
                                        WHERE g.status = 'posted' AND g.received_date < ? AND gi.accepted_qty > COALESCE((SELECT SUM(pii.quantity) FROM purchase_invoice_items pii
                                              JOIN purchase_invoices x ON x.id = pii.pinv_id WHERE pii.grn_item_id = gi.id AND x.status <> 'cancelled'),0) + 0.0005", [date('Y-m-d', strtotime('-7 days'))]);
        if ($unbilled) $add('grn:unbilled', 'purchase', 'warning', "{$unbilled} goods receipt(s) older than 7 days without a supplier bill", 'Enter the purchase invoice so the payable is recorded.', 'purchase_invoices.php', 'purchase_invoice.create');
        $ship = (int)erp_val($pdo, "SELECT COUNT(*) FROM inbound_shipments WHERE status IN ('planned','in_transit') AND expected_arrival IS NOT NULL AND expected_arrival < ?", [$today]);
        if ($ship) $add('ship:late', 'transport', 'info', "{$ship} incoming shipment(s) overdue", '', 'shipments.php', 'shipment.manage');
        $rej = (float)erp_val($pdo, "SELECT COALESCE(SUM(quantity),0) FROM warehouse_stock WHERE bucket IN ('rejected','damaged','expired') AND quantity > 0");
        if ($rej > 0) $add('stock:quarantine', 'stock', 'info', erp_q($rej) . ' unit(s) in rejected / damaged / expired stock', 'Return, dispose or restore them.', 'warehouse_stock.php', 'warehouse.view');
        $transit = (int)erp_val($pdo, "SELECT COUNT(*) FROM stock_transfers WHERE status = 'in_transit' AND sent_at < ?", [date('Y-m-d H:i:s', strtotime('-3 days'))]);
        if ($transit) $add('transfer:open', 'stock', 'warning', "{$transit} stock transfer(s) in transit for more than 3 days", '', 'stock_transfers.php', 'warehouse.transfer');
        try {
            $susp = (float)erp_val($pdo, "SELECT COALESCE(SUM(l.credit - l.debit),0) FROM journal_lines l JOIN journal_entries j ON j.id = l.journal_id JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'suspense' AND j.status IN ('posted','reversed')");
            if (abs($susp) > 0.005) $add('gl:suspense', 'accounting', 'warning', 'Suspense account balance ₹' . number_format($susp, 2), 'Review unclassified cash-book entries.', 'general_ledger.php?account=suspense', 'accounting.view');
        } catch (PDOException $e) {}

        // upsert current alerts, resolve the rest
        $keep = [];
        foreach ($alerts as $k => $a) {
            $cur = erp_row($pdo, "SELECT id, title, status FROM admin_notifications WHERE alert_key = ?", [$k]);
            if ($cur) {
                if ($cur['title'] !== $a['title'] || $cur['status'] !== 'open') $pdo->prepare("DELETE FROM admin_notification_reads WHERE notification_id = ?")->execute([$cur['id']]);
                $pdo->prepare("UPDATE admin_notifications SET type=?, severity=?, title=?, message=?, link=?, target_perm=?, status='open', last_seen_at=NOW(), resolved_at=NULL WHERE id=?")
                    ->execute([$a['type'], $a['sev'], $a['title'], $a['msg'], $a['link'], $a['perm'], $cur['id']]);
            } else {
                $pdo->prepare("INSERT INTO admin_notifications (alert_key, type, severity, title, message, link, target_perm) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$k, $a['type'], $a['sev'], $a['title'], $a['msg'], $a['link'], $a['perm']]);
            }
            $keep[] = $k;
        }
        $in = $keep ? implode(',', array_fill(0, count($keep), '?')) : "''";
        $pdo->prepare("UPDATE admin_notifications SET status = 'resolved', resolved_at = NOW() WHERE status = 'open' AND alert_key NOT IN ($in)")->execute($keep);
    } finally { erp_unlock($pdo, 'ntf'); }
}
/** Open notifications this admin may see. */
function ntf_visible(PDO $pdo, bool $onlyOpen = true): array {
    $rows = erp_rows($pdo, "SELECT n.*, (SELECT 1 FROM admin_notification_reads r WHERE r.notification_id = n.id AND r.admin_user_id = ?) AS is_read
                            FROM admin_notifications n" . ($onlyOpen ? " WHERE n.status = 'open'" : '') . " ORDER BY n.status = 'open' DESC, FIELD(n.severity,'critical','warning','info'), n.last_seen_at DESC LIMIT 200",
                     [(int)($_SESSION['admin_user_id'] ?? 0)]);
    return array_values(array_filter($rows, function ($n) use ($pdo) { return !$n['target_perm'] || erp_can($pdo, $n['target_perm']); }));
}

// ---------------------------------------------------------------- WAREHOUSE SUB-LEDGER
function wh_default(PDO $pdo): int {
    $id = (int)erp_setting($pdo, 'erp_default_warehouse', 0);
    if ($id && erp_val($pdo, "SELECT id FROM warehouses WHERE id = ?", [$id])) return $id;
    return (int)(erp_val($pdo, "SELECT MIN(id) FROM warehouses") ?: 1);
}
/** Adds a signed quantity to one warehouse bucket and writes the move row. */
function wh_apply(PDO $pdo, string $type, int $itemId, int $wh, string $bucket, float $qty, string $source, ?int $movementId, ?string $refType, ?string $refNo, string $reason): bool {
    if (abs($qty) < 0.0005) return false;
    try {
        $pdo->prepare("INSERT INTO warehouse_stock_moves (item_type, item_id, warehouse_id, bucket, quantity, source, source_movement_id, reference_type, reference_number, reason, created_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$type, $itemId, $wh, $bucket, erp_q($qty), $source, $movementId, $refType, $refNo, mb_substr($reason, 0, 255), erp_user()]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return false;   // already applied (idempotent)
        throw $e;
    }
    $pdo->prepare("INSERT INTO warehouse_stock (item_type, item_id, warehouse_id, bucket, quantity) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)")
        ->execute([$type, $itemId, $wh, $bucket, erp_q($qty)]);
    return true;
}
/**
 * Brings warehouse_stock up to date with the stock ledgers. First run: current stock
 * of every item is placed in the default warehouse (or the warehouse of its last movement)
 * as the opening allocation. Later runs apply each new ledger row to its warehouse.
 * Also moves GRN-rejected qty into the 'rejected' bucket and sales-return damaged qty
 * into the 'damaged' bucket. Idempotent.
 */
function wh_sync(PDO $pdo) {
    if (!erp_lock($pdo, 'wh', 10)) return;
    try {
        $def = wh_default($pdo);
        $whs = array_map('intval', array_column(erp_rows($pdo, "SELECT id FROM warehouses"), 'id'));
        $valid = function ($w) use ($whs, $def) { $w = (int)$w; return in_array($w, $whs, true) ? $w : $def; };
        foreach ([['product', 'stock_movements', 'product_id', 'product_details'], ['raw_material', 'raw_material_movements', 'raw_material_id', 'raw_materials']] as [$type, $tbl, $col, $master]) {
            $cur = erp_cursor($pdo, 'wh_' . $type);
            $pdo->beginTransaction();
            if ($cur === null) {
                $max = (int)erp_val($pdo, "SELECT COALESCE(MAX(id),0) FROM {$tbl}");
                foreach (erp_rows($pdo, "SELECT m.id, m.stock, (SELECT x.warehouse_id FROM {$tbl} x WHERE x.{$col} = m.id ORDER BY x.id DESC LIMIT 1) AS wh FROM {$master} m WHERE m.stock <> 0") as $r)
                    wh_apply($pdo, $type, (int)$r['id'], $valid($r['wh'] ?: $def), 'available', (float)$r['stock'], 'opening', null, 'opening', null, 'Opening allocation (stock when warehouse tracking started)');
                erp_cursor_set($pdo, 'wh_' . $type, $max);
            } else {
                $rows = erp_rows($pdo, "SELECT id, {$col} AS item_id, warehouse_id, previous_stock, new_stock, reference_type, reference_number, movement_type FROM {$tbl} WHERE id > ? ORDER BY id LIMIT 5000", [$cur]);
                foreach ($rows as $m) {
                    $d = (float)$m['new_stock'] - (float)$m['previous_stock'];
                    wh_apply($pdo, $type, (int)$m['item_id'], $valid($m['warehouse_id']), 'available', $d, 'ledger', (int)$m['id'], $m['reference_type'], $m['reference_number'], (string)$m['movement_type']);
                    $cur = (int)$m['id'];
                }
                erp_cursor_set($pdo, 'wh_' . $type, $cur);
            }
            $pdo->commit();
        }
        // rejected goods at GRN → 'rejected' bucket (physically held until returned / destroyed)
        $pdo->beginTransaction();
        foreach (erp_rows($pdo, "SELECT g.grn_number, g.warehouse_id, gi.item_type, gi.item_id, SUM(gi.rejected_qty) q FROM goods_receipts g JOIN goods_receipt_items gi ON gi.grn_id = g.id
                                 WHERE g.status = 'posted' AND gi.rejected_qty > 0
                                   AND NOT EXISTS (SELECT 1 FROM warehouse_stock_moves m WHERE m.source = 'bucket' AND m.reference_type = 'grn_rejected' AND m.reference_number = g.grn_number
                                                   AND m.item_type = gi.item_type AND m.item_id = gi.item_id)
                                 GROUP BY g.grn_number, g.warehouse_id, gi.item_type, gi.item_id") as $r)
            wh_apply($pdo, $r['item_type'], (int)$r['item_id'], $valid($r['warehouse_id']), 'rejected', (float)$r['q'], 'bucket', null, 'grn_rejected', $r['grn_number'], 'Rejected at goods receipt / QC');
        // damaged goods from sales returns → 'damaged' bucket
        foreach (erp_rows($pdo, "SELECT r.return_number, r.warehouse_id, i.product_id, SUM(i.damaged_qty) q FROM sales_returns r JOIN sales_return_items i ON i.return_id = r.id
                                 WHERE r.status = 'posted' AND i.damaged_qty > 0
                                   AND NOT EXISTS (SELECT 1 FROM warehouse_stock_moves m WHERE m.source = 'bucket' AND m.reference_type = 'sales_return_damaged' AND m.reference_number = r.return_number AND m.item_id = i.product_id)
                                 GROUP BY r.return_number, r.warehouse_id, i.product_id") as $r)
            wh_apply($pdo, 'product', (int)$r['product_id'], $valid($r['warehouse_id']), 'damaged', (float)$r['q'], 'bucket', null, 'sales_return_damaged', $r['return_number'], 'Damaged / unsellable goods from a sales return');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally { erp_unlock($pdo, 'wh'); }
}
function wh_qty(PDO $pdo, string $type, int $itemId, int $wh, string $bucket = 'available'): float {
    return (float)(erp_val($pdo, "SELECT quantity FROM warehouse_stock WHERE item_type = ? AND item_id = ? AND warehouse_id = ? AND bucket = ?", [$type, $itemId, $wh, $bucket]) ?: 0);
}

// ---------------------------------------------------------------- FEFO BATCH ALLOCATION
/**
 * Registers batches from the existing Stock In module (lines with a batch number, not
 * created by a GRN / repack / sales return) and assigns every new stock OUTFLOW to
 * batches — earliest expiry first (FEFO), then oldest receipt. Purchase returns and
 * warehouse transfers are skipped (returns already reduce their own batch; transfers
 * don't change which batch the goods belong to). Idempotent.
 */
function fefo_sync(PDO $pdo) {
    if (!erp_lock($pdo, 'fefo', 10)) return;
    try {
        $pdo->beginTransaction();
        // batches from the existing Stock In module
        foreach (erp_rows($pdo, "SELECT sii.id, sii.product_id, sii.quantity, sii.batch_number, sii.manufacturing_date, sii.expiry_date, sii.purchase_rate, si.stock_in_date, si.warehouse_id, si.supplier_id
                                 FROM stock_in_items sii JOIN stock_ins si ON si.id = sii.stock_in_id
                                 WHERE si.status = 'completed' AND (sii.batch_number IS NOT NULL AND sii.batch_number <> '' OR sii.expiry_date IS NOT NULL)
                                   AND si.id NOT IN (SELECT stock_in_id FROM goods_receipts WHERE stock_in_id IS NOT NULL)
                                   AND si.id NOT IN (SELECT stock_in_id FROM repack_jobs WHERE stock_in_id IS NOT NULL)
                                   AND si.id NOT IN (SELECT stock_in_id FROM sales_returns WHERE stock_in_id IS NOT NULL)
                                   AND NOT EXISTS (SELECT 1 FROM inventory_batches b WHERE b.source_type = 'stock_in' AND b.source_item_id = sii.id)") as $r) {
            $pdo->prepare("INSERT INTO inventory_batches (item_type, item_id, warehouse_id, batch_number, manufacturing_date, expiry_date, supplier_id, source_type, source_id, source_item_id, received_date, qty_received, unit_cost)
                           VALUES ('product', ?,?,?,?,?,?, 'stock_in', NULL, ?,?,?,?)")
                ->execute([$r['product_id'], $r['warehouse_id'], $r['batch_number'] ?: null, $r['manufacturing_date'], $r['expiry_date'], $r['supplier_id'], $r['id'], $r['stock_in_date'], $r['quantity'], $r['purchase_rate'] ?: 0]);
        }
        foreach ([['product', 'stock_movements', 'product_id'], ['raw_material', 'raw_material_movements', 'raw_material_id']] as [$type, $tbl, $col]) {
            $cur = erp_cursor($pdo, 'fefo_' . $type) ?? 0;
            $rows = erp_rows($pdo, "SELECT id, {$col} AS item_id, previous_stock, new_stock, COALESCE(reference_type,'') rt, created_at FROM {$tbl} WHERE id > ? ORDER BY id LIMIT 5000", [$cur]);
            foreach ($rows as $m) {
                $cur = (int)$m['id'];
                $out = (float)$m['previous_stock'] - (float)$m['new_stock'];
                if ($out <= 0.0005 || in_array($m['rt'], ['purchase_return', 'stock_transfer'], true)) continue;
                if (erp_val($pdo, "SELECT 1 FROM batch_allocations WHERE item_type = ? AND movement_id = ? LIMIT 1", [$type, $m['id']])) continue; // manual allocation exists
                $batches = erp_rows($pdo, "SELECT b.id, b.qty_received - b.qty_returned - COALESCE((SELECT SUM(a.quantity) FROM batch_allocations a WHERE a.batch_id = b.id),0) AS rem
                                           FROM inventory_batches b WHERE b.item_type = ? AND b.item_id = ? AND b.received_date <= ?
                                           ORDER BY b.expiry_date IS NULL, b.expiry_date, b.received_date, b.id", [$type, $m['item_id'], substr($m['created_at'], 0, 10)]);
                foreach ($batches as $b) {
                    if ($out <= 0.0005) break;
                    $rem = (float)$b['rem'];
                    if ($rem <= 0.0005) continue;
                    $take = min($rem, $out);
                    $pdo->prepare("INSERT IGNORE INTO batch_allocations (batch_id, item_type, item_id, movement_id, quantity, allocation_type) VALUES (?,?,?,?,?, 'fefo')")
                        ->execute([$b['id'], $type, $m['item_id'], $m['id'], erp_q($take)]);
                    $out -= $take;
                }
            }
            erp_cursor_set($pdo, 'fefo_' . $type, $cur);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally { erp_unlock($pdo, 'fefo'); }
}
/** Every batch with its remaining quantity (received − returned − allocated). */
function batch_remaining_rows(PDO $pdo, array $filter = []): array {
    $w = ['1=1']; $p = [];
    if (!empty($filter['item_type'])) { $w[] = 'b.item_type = ?'; $p[] = $filter['item_type']; }
    if (!empty($filter['item_id'])) { $w[] = 'b.item_id = ?'; $p[] = (int)$filter['item_id']; }
    if (!empty($filter['batch_id'])) { $w[] = 'b.id = ?'; $p[] = (int)$filter['batch_id']; }
    $rows = erp_rows($pdo, "SELECT b.*, s.supplier_name, w.name AS warehouse_name,
                                   COALESCE((SELECT SUM(a.quantity) FROM batch_allocations a WHERE a.batch_id = b.id),0) AS allocated
                            FROM inventory_batches b LEFT JOIN suppliers s ON s.id = b.supplier_id LEFT JOIN warehouses w ON w.id = b.warehouse_id
                            WHERE " . implode(' AND ', $w) . " ORDER BY b.expiry_date IS NULL, b.expiry_date, b.received_date, b.id", $p);
    foreach ($rows as &$r) $r['remaining'] = erp_q(max(0, $r['qty_received'] - $r['qty_returned'] - $r['allocated']));
    unset($r);
    return $rows;
}

// ---------------------------------------------------------------- DOCUMENT RELATIONS
/** Entity types that can carry documents: [label, page link, permission to see its documents]. */
function doc_entities(): array {
    return [
        'purchase_request' => ['Purchase request', 'purchase_requests.php?id=', 'purchase.view'],
        'rfq' => ['RFQ', 'rfqs.php?id=', 'purchase.view'],
        'quotation' => ['Supplier quotation', 'rfqs.php?quote=', 'purchase.view'],
        'purchase_order' => ['Purchase order', 'purchase_orders.php?id=', 'purchase.view'],
        'shipment' => ['Transport', 'shipments.php?id=', 'purchase.view'],
        'grn' => ['Goods receipt', 'goods_receipts.php?id=', 'purchase.view'],
        'qc' => ['Quality check', 'quality_checks.php?id=', 'purchase.view'],
        'purchase_invoice' => ['Purchase bill', 'purchase_invoices.php?id=', 'purchase.view'],
        'purchase_payment' => ['Supplier payment', 'purchase_payments.php?id=', 'purchase.view'],
        'purchase_return' => ['Purchase return', 'purchase_returns.php?id=', 'purchase.view'],
        'debit_note' => ['Debit note', 'debit_notes.php?id=', 'purchase.view'],
        'supplier' => ['Supplier', 'supplier_360.php?id=', 'suppliers.view'],
        'product' => ['Product', 'transaction_trace.php?product_id=', 'inventory.view'],
        'raw_material' => ['Raw material', 'raw_materials.php?id=', 'inventory.view'],
        'repack' => ['Repacking job', 'repacking.php?id=', 'inventory.view'],
        'batch' => ['Batch', 'batches.php?id=', 'warehouse.view'],
        'stock_transfer' => ['Stock transfer', 'stock_transfers.php?id=', 'warehouse.view'],
        'stock_count' => ['Stock count', 'stock_counts.php?id=', 'warehouse.view'],
        'stock_adjustment' => ['Stock adjustment', 'stock_adjustments.php?id=', 'inventory.view'],
        'sales_order' => ['Sales order', 'sales_orders.php?id=', 'sales_orders.view'],
        'invoice' => ['Sales invoice', 'invoices.php?id=', 'invoices.view'],
        'website_order' => ['Website order', 'orders.php?id=', 'customer.view'],
        'manual_sale' => ['Manual sale', 'manual_sales.php?id=', 'customer.view'],
        'credit_sale' => ['Credit sale', 'credit_sale.php?id=', 'credit_sales.view'],
        'sales_return' => ['Sales return', 'sales_returns.php?id=', 'customer.view'],
        'credit_note' => ['Credit note', 'credit_notes.php?id=', 'customer.view'],
        'customer_payment' => ['Customer payment', 'accounts.php?id=', 'accounting.view'],
        'expense' => ['Expense', 'expenses.php?id=', 'expense.manage'],
        'journal' => ['Journal entry', 'journals.php?id=', 'accounting.view'],
        'bank_account' => ['Bank account', 'bank_accounts.php?id=', 'accounting.view'],
        'company' => ['Company / general', 'documents.php?entity=company', 'documents.view'],
        // stock lifecycle proofs (2 Oct 2026)
        'consignment' => ['Consignment (loading / transport / unloading / QC)', 'stock_flow.php?id=', 'stockflow.view'],
        'stock_issue' => ['Stock out', 'stock_operations.php?tab=issue&id=', 'stockflow.view'],
        'stock_damage' => ['Damage report', 'stock_operations.php?tab=damage&id=', 'stockflow.view'],
        'return_receipt' => ['Sales return receiving / QC', 'stock_operations.php?tab=returns&id=', 'stockflow.view'],
        'opening_stock' => ['Opening stock', 'stock_operations.php?tab=opening&id=', 'stockflow.view'],
        'return_dispatch' => ['Purchase return dispatch', 'stock_operations.php?tab=dispatch&id=', 'stockflow.view'],
        'stock_audit' => ['Monthly stock audit', 'stock_audit.php?id=', 'stockflow.view'],
        'stock_handover' => ['Executive stock handover', 'stock_handover.php?id=', 'stockflow.view'],
        'waste_record' => ['Waste record', 'waste.php?id=', 'inventory.view'],   // FIX (3 Oct 2026): proof photo of wastage (Manager → Admin approval)
    ];
}
function doc_categories(): array {
    return ['PURCHASE_ORDER' => 'Purchase order', 'QUOTATION' => 'Quotation', 'SUPPLIER_INVOICE' => 'Supplier invoice / bill', 'SUPPLIER_PAYMENT_PROOF' => 'Supplier payment proof',
            'TRANSPORT_RECEIPT' => 'Transport receipt / LR', 'E_WAY_BILL' => 'E-way bill', 'GRN' => 'GRN / delivery challan', 'QC_DOCUMENT' => 'QC document / photo',
            'PRODUCT_DOCUMENT' => 'Product document', 'BATCH_DOCUMENT' => 'Batch document / certificate', 'SALES_INVOICE' => 'Sales invoice',
            'CUSTOMER_PAYMENT' => 'Customer payment proof', 'CUSTOMER_DOCUMENT' => 'Customer document', 'SALES_RETURN' => 'Sales return', 'CREDIT_DEBIT_NOTE' => 'Credit / debit note',
            'REFUND_PROOF' => 'Refund proof', 'EXPENSE_RECEIPT' => 'Expense receipt', 'BANK_DOCUMENT' => 'Bank document / statement', 'TAX_DOCUMENT' => 'Tax document',
            // stock lifecycle proofs (2 Oct 2026)
            'LOADING_PHOTO' => 'Loading photo', 'WEIGHT_MACHINE_PHOTO' => 'Weight machine photo', 'PACKING_LIST' => 'Packing list', 'UNLOADING_PHOTO' => 'Unloading photo',
            'COURIER_RECEIPT' => 'Courier receipt', 'TRACKING_PROOF' => 'Tracking proof', 'PHYSICAL_STOCK_PHOTO' => 'Physical stock photo', 'RACK_PHOTO' => 'Rack photo',
            'COUNTING_SHEET' => 'Counting sheet', 'SIGNED_DOCUMENT' => 'Signed document', 'DAMAGE_PHOTO' => 'Damage photo', 'HANDOVER_COPY' => 'Handover copy',
            'OTHER' => 'Other'];
}
/** Old doc_type (first release) → category. */
function doc_type_category(string $t): string {
    $m = ['vendor_invoice' => 'SUPPLIER_INVOICE', 'purchase_receipt' => 'GRN', 'delivery_challan' => 'GRN', 'eway_bill' => 'E_WAY_BILL', 'qc_document' => 'QC_DOCUMENT',
          'payment_proof' => 'SUPPLIER_PAYMENT_PROOF', 'credit_note' => 'CREDIT_DEBIT_NOTE', 'expense_receipt' => 'EXPENSE_RECEIPT'];
    return $m[$t] ?? 'OTHER';
}
/** Category → closest value of the existing erp_documents.doc_type enum. */
function doc_category_type(string $c): string {
    $m = ['SUPPLIER_INVOICE' => 'vendor_invoice', 'GRN' => 'purchase_receipt', 'E_WAY_BILL' => 'eway_bill', 'QC_DOCUMENT' => 'qc_document', 'SUPPLIER_PAYMENT_PROOF' => 'payment_proof',
          'CUSTOMER_PAYMENT' => 'payment_proof', 'REFUND_PROOF' => 'payment_proof', 'CREDIT_DEBIT_NOTE' => 'credit_note', 'EXPENSE_RECEIPT' => 'expense_receipt', 'TRANSPORT_RECEIPT' => 'delivery_challan'];
    return $m[$c] ?? 'other';
}
/**
 * All entities linked to one business document, so its documents show everywhere in the chain.
 * Purchase chain: RFQ ↔ quotation ↔ PO ↔ transport ↔ GRN ↔ QC ↔ bill ↔ payment ↔ return ↔ debit note.
 * Sales chain: sales order ↔ invoice ↔ sales return ↔ credit note. Expense ↔ transport.
 * Returns [[type, id], ...] including the document itself.
 */
function doc_related(PDO $pdo, string $type, int $id): array {
    $set = [];
    $put = function ($t, $i) use (&$set) { if ($i) $set[$t . ':' . (int)$i] = [$t, (int)$i]; };
    $put($type, $id);
    $ids = function ($sql, $p) use ($pdo) { return array_map('intval', array_column(erp_rows($pdo, $sql, $p), 'id')); };
    $purchase = ['rfq', 'quotation', 'purchase_order', 'shipment', 'grn', 'qc', 'purchase_invoice', 'purchase_payment', 'purchase_return', 'debit_note'];
    if (in_array($type, $purchase, true)) {
        $po = []; $grn = []; $inv = [];
        switch ($type) {
            case 'rfq': $po = $ids("SELECT po_id AS id FROM procurement_links WHERE rfq_id = ?", [$id]); break;
            case 'quotation': $po = $ids("SELECT po_id AS id FROM procurement_links WHERE quotation_id = ?", [$id]); break;
            case 'purchase_order': $po = [$id]; break;
            case 'shipment': $r = erp_row($pdo, "SELECT po_id, grn_id FROM inbound_shipments WHERE id = ?", [$id]); if ($r) { $po = array_filter([(int)$r['po_id']]); $grn = array_filter([(int)$r['grn_id']]); } break;
            case 'grn': $grn = [$id]; break;
            case 'qc': $grn = $ids("SELECT grn_id AS id FROM quality_checks WHERE id = ?", [$id]); break;
            case 'purchase_invoice': $inv = [$id]; break;
            case 'purchase_payment': $inv = $ids("SELECT pinv_id AS id FROM purchase_payments WHERE id = ? AND pinv_id IS NOT NULL", [$id]); break;
            case 'purchase_return': $r = erp_row($pdo, "SELECT grn_id, pinv_id, po_id FROM purchase_returns WHERE id = ?", [$id]); if ($r) { $grn = array_filter([(int)$r['grn_id']]); $inv = array_filter([(int)$r['pinv_id']]); $po = array_filter([(int)$r['po_id']]); } break;
            case 'debit_note': $r = erp_row($pdo, "SELECT r.id, r.grn_id, r.pinv_id, r.po_id FROM debit_notes d JOIN purchase_returns r ON r.id = d.purchase_return_id WHERE d.id = ?", [$id]); if ($r) { $put('purchase_return', $r['id']); $grn = array_filter([(int)$r['grn_id']]); $inv = array_filter([(int)$r['pinv_id']]); $po = array_filter([(int)$r['po_id']]); } break;
        }
        foreach ($inv as $i) { $r = erp_row($pdo, "SELECT po_id, grn_id FROM purchase_invoices WHERE id = ?", [$i]); if ($r) { if ($r['po_id']) $po[] = (int)$r['po_id']; if ($r['grn_id']) $grn[] = (int)$r['grn_id']; } }
        foreach ($grn as $g) { $p = erp_val($pdo, "SELECT po_id FROM goods_receipts WHERE id = ?", [$g]); if ($p) $po[] = (int)$p; }
        $po = array_values(array_unique(array_filter($po)));
        foreach ($po as $p) {
            $put('purchase_order', $p);
            foreach ($ids("SELECT id FROM goods_receipts WHERE po_id = ?", [$p]) as $g) $grn[] = $g;
            foreach ($ids("SELECT id FROM purchase_invoices WHERE po_id = ?", [$p]) as $i) $inv[] = $i;
            foreach ($ids("SELECT id FROM inbound_shipments WHERE po_id = ?", [$p]) as $s) $put('shipment', $s);
            $l = erp_row($pdo, "SELECT rfq_id, quotation_id FROM procurement_links WHERE po_id = ?", [$p]);
            if ($l) { $put('rfq', $l['rfq_id']); $put('quotation', $l['quotation_id']); }
            $pr = erp_val($pdo, "SELECT pr_id FROM purchase_orders WHERE id = ?", [$p]); if ($pr) $put('purchase_request', $pr);
        }
        foreach (array_unique($grn) as $g) {
            $put('grn', $g);
            foreach ($ids("SELECT id FROM quality_checks WHERE grn_id = ?", [$g]) as $q) $put('qc', $q);
            foreach ($ids("SELECT id FROM inbound_shipments WHERE grn_id = ?", [$g]) as $s) $put('shipment', $s);
            foreach ($ids("SELECT id FROM purchase_invoices WHERE grn_id = ?", [$g]) as $i) $inv[] = $i;
            foreach ($ids("SELECT id FROM purchase_returns WHERE grn_id = ?", [$g]) as $r) $put('purchase_return', $r);
        }
        foreach (array_unique($inv) as $i) {
            $put('purchase_invoice', $i);
            foreach ($ids("SELECT id FROM purchase_payments WHERE pinv_id = ?", [$i]) as $x) $put('purchase_payment', $x);
            foreach ($ids("SELECT id FROM purchase_returns WHERE pinv_id = ?", [$i]) as $x) $put('purchase_return', $x);
        }
        foreach ($set as [$t, $i]) if ($t === 'purchase_return') foreach ($ids("SELECT id FROM debit_notes WHERE purchase_return_id = ?", [$i]) as $d) $put('debit_note', $d);
        foreach ($set as [$t, $i]) if ($t === 'shipment') foreach ($ids("SELECT id FROM expenses WHERE shipment_id = ?", [$i]) as $e) $put('expense', $e);
    } elseif (in_array($type, ['sales_order', 'invoice'], true)) {
        $so = $type === 'sales_order' ? $id : (int)erp_val($pdo, "SELECT sales_order_id FROM invoices WHERE id = ?", [$id]);
        if ($so) { $put('sales_order', $so); foreach ($ids("SELECT id FROM invoices WHERE sales_order_id = ?", [$so]) as $i) $put('invoice', $i); }
        foreach ($set as [$t, $i]) if ($t === 'invoice') foreach ($ids("SELECT id FROM sales_returns WHERE source_type = 'invoice' AND source_id = ?", [$i]) as $r) $put('sales_return', $r);
    } elseif (in_array($type, ['website_order', 'manual_sale', 'credit_sale'], true)) {
        foreach ($ids("SELECT id FROM sales_returns WHERE source_type = ? AND source_id = ?", [$type, $id]) as $r) $put('sales_return', $r);
    } elseif ($type === 'sales_return' || $type === 'credit_note') {
        $rid = $type === 'sales_return' ? $id : (int)erp_val($pdo, "SELECT sales_return_id FROM credit_notes WHERE id = ?", [$id]);
        $r = erp_row($pdo, "SELECT id, source_type, source_id FROM sales_returns WHERE id = ?", [$rid]);
        if ($r) { $put('sales_return', $r['id']); $put($r['source_type'], $r['source_id']); foreach ($ids("SELECT id FROM credit_notes WHERE sales_return_id = ?", [$r['id']]) as $c) $put('credit_note', $c); }
    } elseif ($type === 'expense') {
        $s = erp_val($pdo, "SELECT shipment_id FROM expenses WHERE id = ?", [$id]); if ($s) $put('shipment', $s);
    } elseif ($type === 'consignment') {   // stock lifecycle (2 Oct 2026)
        try { $c = erp_row($pdo, "SELECT po_id, grn_id, qc_id, shipment_id FROM sf_consignments WHERE id = ?", [$id]); } catch (PDOException $e) { $c = null; }
        if ($c) { $put('purchase_order', $c['po_id']); $put('grn', $c['grn_id']); $put('qc', $c['qc_id']); $put('shipment', $c['shipment_id']); }
    }
    if (in_array($type, $purchase, true)) {   // consignments of the same purchase orders (stock lifecycle, 2 Oct 2026)
        try { foreach ($set as [$t, $i]) if ($t === 'purchase_order') foreach ($ids("SELECT id FROM sf_consignments WHERE po_id = ?", [$i]) as $c) $put('consignment', $c); } catch (PDOException $e) {}
    }
    return array_values($set);
}
/** Human label (document number) for an entity. */
function doc_entity_label(PDO $pdo, string $type, int $id): string {
    $q = [
        'purchase_request' => "SELECT pr_number FROM purchase_requests WHERE id = ?", 'rfq' => "SELECT rfq_number FROM rfqs WHERE id = ?",
        'quotation' => "SELECT quote_number FROM supplier_quotations WHERE id = ?", 'purchase_order' => "SELECT po_number FROM purchase_orders WHERE id = ?",
        'shipment' => "SELECT shipment_number FROM inbound_shipments WHERE id = ?", 'grn' => "SELECT grn_number FROM goods_receipts WHERE id = ?",
        'qc' => "SELECT qc_number FROM quality_checks WHERE id = ?", 'purchase_invoice' => "SELECT CONCAT(pinv_number,' / ',supplier_invoice_no) FROM purchase_invoices WHERE id = ?",
        'purchase_payment' => "SELECT payment_number FROM purchase_payments WHERE id = ?", 'purchase_return' => "SELECT return_number FROM purchase_returns WHERE id = ?",
        'debit_note' => "SELECT dn_number FROM debit_notes WHERE id = ?", 'supplier' => "SELECT supplier_name FROM suppliers WHERE id = ?",
        'product' => "SELECT product_name FROM product_details WHERE id = ?", 'raw_material' => "SELECT name FROM raw_materials WHERE id = ?",
        'batch' => "SELECT COALESCE(batch_number, CONCAT('Batch #', id)) FROM inventory_batches WHERE id = ?", 'stock_transfer' => "SELECT transfer_number FROM stock_transfers WHERE id = ?",
        'stock_count' => "SELECT count_number FROM stock_counts WHERE id = ?", 'stock_adjustment' => "SELECT adj_number FROM stock_adjustments WHERE id = ?",
        'sales_order' => "SELECT so_number FROM sales_orders WHERE id = ?", 'invoice' => "SELECT invoice_number FROM invoices WHERE id = ?",
        'website_order' => "SELECT receipt FROM orders WHERE id = ?", 'manual_sale' => "SELECT sale_number FROM manual_sales WHERE id = ?",
        'credit_sale' => "SELECT credit_number FROM credit_sales WHERE id = ?", 'sales_return' => "SELECT return_number FROM sales_returns WHERE id = ?",
        'credit_note' => "SELECT cn_number FROM credit_notes WHERE id = ?", 'customer_payment' => "SELECT transaction_id FROM accounts_transactions WHERE id = ?",
        'expense' => "SELECT expense_number FROM expenses WHERE id = ?", 'journal' => "SELECT journal_number FROM journal_entries WHERE id = ?",
        'bank_account' => "SELECT name FROM bank_accounts WHERE id = ?",
        'consignment' => "SELECT consignment_number FROM sf_consignments WHERE id = ?", 'stock_issue' => "SELECT issue_number FROM sf_stock_issues WHERE id = ?",
        'stock_damage' => "SELECT damage_number FROM sf_damage_reports WHERE id = ?", 'return_receipt' => "SELECT receipt_number FROM sf_return_receipts WHERE id = ?",
        'opening_stock' => "SELECT opening_number FROM sf_opening_stock WHERE id = ?", 'return_dispatch' => "SELECT dispatch_number FROM sf_return_dispatches WHERE id = ?",
        'stock_audit' => "SELECT audit_number FROM sf_audits WHERE id = ?", 'stock_handover' => "SELECT handover_number FROM sf_handovers WHERE id = ?",
        'waste_record' => "SELECT waste_id FROM waste_records WHERE id = ?",   // FIX (3 Oct 2026)
    ];
    if ($type === 'company') return 'Company';
    if (!isset($q[$type])) return $type . ' #' . $id;
    try { $v = erp_val($pdo, $q[$type], [$id]); } catch (PDOException $e) { $v = false; }
    return $v === false || $v === null ? ('#' . $id . ' (not found)') : (string)$v;
}
/** Finds an entity by its document number (for uploading from the Documents page). */
function doc_entity_find(PDO $pdo, string $type, string $number): ?int {
    $q = [
        'purchase_request' => "SELECT id FROM purchase_requests WHERE pr_number = ?", 'rfq' => "SELECT id FROM rfqs WHERE rfq_number = ?",
        'quotation' => "SELECT id FROM supplier_quotations WHERE quote_number = ?", 'purchase_order' => "SELECT id FROM purchase_orders WHERE po_number = ?",
        'shipment' => "SELECT id FROM inbound_shipments WHERE shipment_number = ?", 'grn' => "SELECT id FROM goods_receipts WHERE grn_number = ?",
        'qc' => "SELECT id FROM quality_checks WHERE qc_number = ?", 'purchase_invoice' => "SELECT id FROM purchase_invoices WHERE pinv_number = ? OR supplier_invoice_no = ? LIMIT 1",
        'purchase_payment' => "SELECT id FROM purchase_payments WHERE payment_number = ?", 'purchase_return' => "SELECT id FROM purchase_returns WHERE return_number = ?",
        'debit_note' => "SELECT id FROM debit_notes WHERE dn_number = ?", 'supplier' => "SELECT id FROM suppliers WHERE supplier_name = ? OR id = ? LIMIT 1",
        'product' => "SELECT id FROM product_details WHERE id = ? OR product_name = ? LIMIT 1", 'raw_material' => "SELECT id FROM raw_materials WHERE code = ? OR name = ? LIMIT 1",
        'batch' => "SELECT id FROM inventory_batches WHERE batch_number = ? OR id = ? ORDER BY id DESC LIMIT 1", 'stock_transfer' => "SELECT id FROM stock_transfers WHERE transfer_number = ?",
        'stock_count' => "SELECT id FROM stock_counts WHERE count_number = ?", 'stock_adjustment' => "SELECT id FROM stock_adjustments WHERE adj_number = ?",
        'sales_order' => "SELECT id FROM sales_orders WHERE so_number = ?", 'invoice' => "SELECT id FROM invoices WHERE invoice_number = ?",
        'website_order' => "SELECT id FROM orders WHERE receipt = ? OR id = ? LIMIT 1", 'manual_sale' => "SELECT id FROM manual_sales WHERE sale_number = ?",
        'credit_sale' => "SELECT id FROM credit_sales WHERE credit_number = ?", 'sales_return' => "SELECT id FROM sales_returns WHERE return_number = ?",
        'credit_note' => "SELECT id FROM credit_notes WHERE cn_number = ?", 'customer_payment' => "SELECT id FROM accounts_transactions WHERE transaction_id = ?",
        'expense' => "SELECT id FROM expenses WHERE expense_number = ?", 'journal' => "SELECT id FROM journal_entries WHERE journal_number = ?",
        'bank_account' => "SELECT id FROM bank_accounts WHERE name = ?",
        'consignment' => "SELECT id FROM sf_consignments WHERE consignment_number = ?", 'stock_issue' => "SELECT id FROM sf_stock_issues WHERE issue_number = ?",
        'stock_damage' => "SELECT id FROM sf_damage_reports WHERE damage_number = ?", 'return_receipt' => "SELECT id FROM sf_return_receipts WHERE receipt_number = ?",
        'opening_stock' => "SELECT id FROM sf_opening_stock WHERE opening_number = ?", 'return_dispatch' => "SELECT id FROM sf_return_dispatches WHERE dispatch_number = ?",
        'stock_audit' => "SELECT id FROM sf_audits WHERE audit_number = ?", 'stock_handover' => "SELECT id FROM sf_handovers WHERE handover_number = ?",
        'waste_record' => "SELECT id FROM waste_records WHERE waste_id = ?",   // FIX (3 Oct 2026)
    ];
    if ($type === 'company') return 0;
    if (!isset($q[$type])) return null;
    $n = substr_count($q[$type], '?');
    $v = erp_val($pdo, $q[$type], array_fill(0, $n, trim($number)));
    return $v === false || $v === null ? null : (int)$v;
}

// ---------------------------------------------------------------- DEBIT NOTES
/** Issues the debit note for a posted purchase return (once). Returns its number or null. */
function dn_issue_for_return(PDO $pdo, int $returnId): ?string {
    $r = erp_row($pdo, "SELECT * FROM purchase_returns WHERE id = ? AND status = 'posted'", [$returnId]);
    if (!$r || erp_val($pdo, "SELECT id FROM debit_notes WHERE purchase_return_id = ?", [$returnId])) return null;
    $num = next_document_number($pdo, 'debit_note', 'DN');
    try {
        $pdo->prepare("INSERT INTO debit_notes (dn_number, purchase_return_id, supplier_id, pinv_id, dn_date, total, status, created_by) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$num, $returnId, $r['supplier_id'], $r['pinv_id'], $r['return_date'], $r['total_value'], $r['settlement'] === 'refund' && (float)$r['refund_received'] >= (float)$r['total_value'] - 0.005 ? 'refunded' : 'issued', erp_user()]);
    } catch (PDOException $e) { if ($e->getCode() === '23000') return null; throw $e; }
    log_audit($pdo, 'create', 'debit_notes', (int)$pdo->lastInsertId(), null, ['dn_number' => $num, 'return' => $r['return_number']]);
    return $num;
}

/** Issues the credit note for a posted sales return (refund or credit note settlement), once. */
function cn_issue_for_return(PDO $pdo, int $returnId): ?string {
    $r = erp_row($pdo, "SELECT * FROM sales_returns WHERE id = ? AND status = 'posted'", [$returnId]);
    if (!$r || $r['settlement'] === 'replacement' || erp_val($pdo, "SELECT id FROM credit_notes WHERE sales_return_id = ?", [$returnId])) return null;
    $num = next_document_number($pdo, 'credit_note', 'CN');
    try {
        $pdo->prepare("INSERT INTO credit_notes (cn_number, sales_return_id, customer_name, customer_mobile, source_type, source_id, cn_date, total, refunded, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$num, $returnId, $r['customer_name'], $r['customer_mobile'], $r['source_type'], $r['source_id'], $r['return_date'], $r['refund_amount'],
                       $r['settlement'] === 'refund' ? $r['refund_amount'] : 0, $r['settlement'] === 'refund' ? 'refunded' : 'issued', erp_user()]);
    } catch (PDOException $e) { if ($e->getCode() === '23000') return null; throw $e; }
    log_audit($pdo, 'create', 'credit_notes', (int)$pdo->lastInsertId(), null, ['cn_number' => $num, 'return' => $r['return_number']]);
    return $num;
}
/**
 * QC hook for goods receipts (called by purchase_api grn_save / grn_post):
 *  pending QC → blocked; completed QC → the lines are the QC result (client lines ignored on post, edit blocked);
 *  "QC required" setting on → posting needs a completed QC.
 */
function qc_grn_hook(PDO $pdo, string $action, int $grnId) {
    if (!erpx_installed($pdo)) return;
    $qc = $grnId ? erp_row($pdo, "SELECT id, qc_number, status FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$grnId]) : null;
    if ($qc && $qc['status'] === 'pending') erp_invalid("Quality check {$qc['qc_number']} is still pending — complete it first (the goods stay in quarantine).");
    if ($qc) {
        if ($action === 'grn_save') erp_invalid("Quality check {$qc['qc_number']} is complete — the quantities are locked. Post the goods receipt, or cancel the quality check to change them.");
        $lines = [];
        foreach (erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ?", [$grnId]) as $gi)
            $lines[] = ['po_item_id' => $gi['po_item_id'], 'item_type' => $gi['item_type'], 'item_id' => $gi['item_id'], 'received_qty' => $gi['received_qty'], 'rejected_qty' => $gi['rejected_qty'],
                        'rejection_reason' => $gi['rejection_reason'], 'batch_number' => $gi['batch_number'], 'lot_number' => $gi['lot_number'], 'manufacturing_date' => $gi['manufacturing_date'],
                        'expiry_date' => $gi['expiry_date'], 'qc_status' => $gi['qc_status'], 'rate' => $gi['rate']];
        $_POST['items'] = json_encode($lines);
        return;
    }
    if ($action === 'grn_post' && erp_setting($pdo, 'erp_qc_required', '0') === '1')
        erp_invalid('Quality check is required before goods are added to stock: save the goods receipt as draft and create a quality check.');
}
