<?php
// ============================================================================
// Header notification bell (added 1 Oct 2026) — one feed per logged-in admin:
//   • every approval waiting for THIS user (Manager / Admin / CEO / others by permission)
//   • "your request was approved / rejected" for the person who sent it
//   • the next step after an approval (L1: collect quotations · Accounts: pay the PO)
//   • the existing alerts (stock, expiry, bills due …) the user may see
// Read marks reuse admin_notification_reads (no new table): approval items use
// negative ids so they never clash with admin_notifications ids.
//   -(id*4)   waiting for my approval     -(id*4+1) decision for the sender
//   -(id*4+2) next step after approval
// Read-only except the read marks. Needs no new database change.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';

erp_guard($pdo, null);
$action = (string)erp_input('action', 'feed');
$uid = (int)($_SESSION['admin_user_id'] ?? 0);

/** Dashboard keys this user works with (role + dashboards given by the Super Admin). */
function hn_keys(PDO $pdo, int $uid): array {
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) return ['super'];
    return array_values(array_unique(array_filter(array_merge([role_access_key((string)($_SESSION['admin_role_name'] ?? ''))], user_dash_keys($pdo, $uid)))));
}
/** Page that opens this approval. Dashboard approvals open the dashboard on that task. */
function hn_link(array $a): string {
    $onDash = ['purchase_request', 'purchase_request_final', 'pr_quotation', 'purchase_order', 'po_ceo'];
    if (in_array($a['module'], $onDash, true)) return 'index.php?focus=' . rawurlencode((string)$a['request_number']) . ',' . rawurlencode((string)$a['reference']) . '#roleDash';
    $map = ['purchase_invoice' => 'purchase_invoices.php?id=', 'stock_adjustment' => 'stock_adjustments.php?id=', 'stock_count' => 'stock_counts.php?id=', 'expense' => 'expenses.php?id=',
            'manual_journal' => 'journals.php?id=', 'po_amendment' => 'purchase_orders.php?id='];
    return isset($map[$a['module']]) ? $map[$a['module']] . (int)$a['entity_id'] : 'approvals.php?module=' . rawurlencode((string)$a['module']);
}
function hn_doc_link(array $a): string {
    return match ($a['module']) {
        'purchase_request', 'purchase_request_final', 'pr_quotation' => 'purchase_flow.php?pr_id=' . (int)$a['entity_id'],
        'purchase_order', 'po_ceo', 'po_amendment' => 'purchase_orders.php?id=' . (int)$a['entity_id'],
        default => hn_link($a),
    };
}
/** Is this user the one who approves the module? Purchase steps follow the role chain; others the approver permission. */
function hn_is_approver(PDO $pdo, array $keys, string $module, ?string $perm): bool {
    if (in_array('super', $keys, true)) return true;
    $chain = ['purchase_request' => 'manager', 'purchase_request_final' => 'admin', 'pr_quotation' => 'admin', 'purchase_order' => 'admin', 'po_ceo' => 'ceo'];
    if (isset($chain[$module])) return in_array($chain[$module], $keys, true);
    return erp_can($pdo, $perm ?: 'purchase.approve');
}

function hn_feed(PDO $pdo, int $uid): array {
    $keys = hn_keys($pdo, $uid); $me = erp_user(); $items = [];
    $labels = [];
    foreach (erp_rows($pdo, "SELECT module, label, approver_perm FROM approval_policies") as $p) $labels[$p['module']] = $p;
    $lab = fn($m) => $labels[$m]['label'] ?? ucwords(str_replace('_', ' ', $m));
    // 1. waiting for my approval
    foreach (erp_rows($pdo, "SELECT id, request_number, module, entity_id, request_key, reference, summary, amount, submitted_by, submitted_at FROM approval_requests
                             WHERE status IN ('submitted','under_review') ORDER BY submitted_at DESC, id DESC LIMIT 100") as $a) {
        if (!hn_is_approver($pdo, $keys, $a['module'], $labels[$a['module']]['approver_perm'] ?? null)) continue;
        $items[] = ['id' => -((int)$a['id'] * 4), 'kind' => 'approval', 'severity' => 'warning', 'title' => 'Approve: ' . $lab($a['module']),
                    'message' => $a['summary'] . ($a['amount'] !== null ? ' · ₹' . number_format((float)$a['amount'], 2) : '') . ' · sent by ' . $a['submitted_by'],
                    'link' => hn_link($a), 'at' => $a['submitted_at']];
    }
    // 2. decisions on what I sent, and 3. next step after an approval (last 7 days)
    $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    foreach (erp_rows($pdo, "SELECT r.id, r.request_number, r.module, r.entity_id, r.request_key, r.reference, r.summary, r.amount, r.status, r.submitted_by, r.decided_by, r.decided_at, r.remarks,
                                    (SELECT COUNT(*) FROM approval_requests c WHERE c.module = 'po_ceo' AND c.request_key = r.request_key) AS ceo_rows
                             FROM approval_requests r WHERE r.status IN ('approved','rejected') AND r.decided_at >= ? ORDER BY r.decided_at DESC, r.id DESC LIMIT 100", [$since]) as $a) {
        $ok = $a['status'] === 'approved';
        if ($a['submitted_by'] === $me && $a['decided_by'] !== $me)
            $items[] = ['id' => -((int)$a['id'] * 4 + 1), 'kind' => 'decision', 'severity' => $ok ? 'info' : 'critical', 'title' => ($ok ? 'Approved: ' : 'Rejected: ') . $lab($a['module']),
                        'message' => $a['summary'] . ' · by ' . $a['decided_by'] . ($a['remarks'] ? ' — ' . $a['remarks'] : ''), 'link' => hn_doc_link($a), 'at' => $a['decided_at']];
        if (!$ok) continue;
        $next = null;
        if ($a['module'] === 'purchase_request_final' && (in_array('l1', $keys, true) || in_array('super', $keys, true))
            && in_array((string)erp_val($pdo, "SELECT COALESCE((SELECT quote_status FROM purchase_flows WHERE pr_id = ?), 'collecting')", [(int)$a['entity_id']]), ['collecting', 'rejected'], true))   // only while quotations are still to be collected
            $next = ['Collect 3 shop quotations', 'purchase_flow.php?pr_id=' . (int)$a['entity_id'] . '#card-quotes'];
        if ((($a['module'] === 'purchase_order' && !(int)$a['ceo_rows']) || $a['module'] === 'po_ceo') && (in_array('accounts', $keys, true) || in_array('super', $keys, true))) {
            $fl = erp_rows($pdo, "SELECT pr_id, payment_id FROM purchase_flows WHERE po_id = ? LIMIT 1", [(int)$a['entity_id']])[0] ?? null;
            $pr = (int)($fl['pr_id'] ?? 0);
            if ($fl && !$fl['payment_id'])   // purchase-flow PO not paid yet
            $next = ['PO approved — pay the supplier', $pr ? 'purchase_flow.php?pr_id=' . $pr . '#card-payment' : 'purchase_orders.php?id=' . (int)$a['entity_id']];
        }
        if ($next) $items[] = ['id' => -((int)$a['id'] * 4 + 2), 'kind' => 'task', 'severity' => 'warning', 'title' => $next[0], 'message' => $a['summary'] . ' · approved by ' . $a['decided_by'], 'link' => $next[1], 'at' => $a['decided_at']];
    }
    // 3b. waste records reported, for whoever approves waste (1 Oct 2026). Read id -(id*4+3): never clashes with the approval ids.
    if (in_array('super', $keys, true) || erp_can($pdo, 'waste.approve')) {
        try {
            foreach (erp_rows($pdo, "SELECT w.id, w.waste_id, w.date, w.reason, w.quantity, w.unit, w.created_by, w.created_at, p.product_name FROM waste_records w
                                     LEFT JOIN product_details p ON p.id = w.product_id WHERE w.status = 'reported' ORDER BY w.id DESC LIMIT 30") as $w)
                $items[] = ['id' => -((int)$w['id'] * 4 + 3), 'kind' => 'approval', 'severity' => 'warning', 'title' => 'Approve: waste record ' . $w['waste_id'],
                            'message' => trim(($w['product_name'] ? $w['product_name'] . ' · ' : '') . ($w['quantity'] ? $w['quantity'] . ' ' . $w['unit'] . ' · ' : '') . $w['reason'] . ' · reported by ' . $w['created_by']),
                            'link' => (in_array('manager', $keys, true) ? 'index.php?focus=' . rawurlencode((string)$w['waste_id']) . '#roleDash' : 'waste.php'), 'at' => $w['created_at']];
        } catch (PDOException $e) { /* waste table not installed */ }
    }
    // 4. existing alerts (not the approval summaries — those are listed one by one above)
    try {
        ntf_refresh($pdo);
        foreach (ntf_visible($pdo) as $n) if ($n['type'] !== 'approval')
            $items[] = ['id' => (int)$n['id'], 'kind' => $n['type'], 'severity' => $n['severity'], 'title' => $n['title'], 'message' => (string)$n['message'], 'link' => $n['link'] ?: 'notifications.php', 'at' => $n['last_seen_at'], 'read' => (bool)$n['is_read']];
    } catch (Throwable $e) { error_log('[header ntf alerts] ' . $e->getMessage()); }
    // page-limited roles: drop items whose page the user cannot open
    $allowed = role_access_pages((string)($_SESSION['admin_role_name'] ?? ''), user_dash_keys($pdo, $uid));
    if ($allowed !== null && !in_array('super', $keys, true))
        $items = array_values(array_filter($items, fn($i) => in_array(strtok(basename((string)$i['link']), '?#'), $allowed, true)));
    // read marks
    $neg = array_values(array_filter(array_column($items, 'id'), fn($i) => $i < 0));
    $read = [];
    if ($neg) { $st = $pdo->prepare("SELECT notification_id FROM admin_notification_reads WHERE admin_user_id = ? AND notification_id IN (" . implode(',', array_fill(0, count($neg), '?')) . ")");
                $st->execute(array_merge([$uid], $neg)); $read = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))); }
    foreach ($items as &$i) if ($i['id'] < 0) $i['read'] = isset($read[$i['id']]);
    unset($i);
    $rank = fn($i) => $i['id'] < 0 ? 0 : 1;   // approvals and purchase steps above the stock / bill alerts
    usort($items, fn($x, $y) => ((int)$x['read'] <=> (int)$y['read']) ?: ($rank($x) <=> $rank($y)) ?: strcmp((string)$y['at'], (string)$x['at']));   // unread first, newest first
    return $items;
}

try {
    try { $pdo->query("SELECT 1 FROM approval_requests LIMIT 1"); $pdo->query("SELECT 1 FROM admin_notification_reads LIMIT 1"); }
    catch (PDOException $e) { erp_out(['status' => 'success', 'items' => [], 'unread' => 0]); }
    switch ($action) {
        case 'feed':
            $items = hn_feed($pdo, $uid);
            erp_out(['status' => 'success', 'user' => $uid, 'unread' => count(array_filter($items, fn($i) => !$i['read'])), 'items' => array_slice($items, 0, 60)]);
        case 'read':
        case 'read_all':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            $allowed = array_column(hn_feed($pdo, $uid), 'id');   // only items this user can see
            $ids = $action === 'read' ? array_intersect([(int)erp_input('id')], $allowed) : $allowed;
            $ins = $pdo->prepare("INSERT IGNORE INTO admin_notification_reads (notification_id, admin_user_id) VALUES (?,?)");
            foreach ($ids as $id) if ($id) $ins->execute([(int)$id, $uid]);
            erp_out(['status' => 'success']);
        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'header notifications');
}
