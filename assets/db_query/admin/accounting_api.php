<?php
// ============================================================================
// Accounting API (added 1 Oct 2026)
//   Settings & automatic posting · Chart of accounts · Journals (auto + manual
//   with approval, reversal) · General ledger · Trial balance · P&L and balance
//   sheet from the ledger · AP / AR ageing · Cash flow · GST summary ·
//   Bank & cash accounts · Bank statement import & reconciliation ·
//   Financial periods (close / reopen / closing entry) · Expenses (approval)
// The existing Accounts → Transactions cash book is not changed; posted
// expenses are written to it, and the journals read from it.
// ============================================================================
require_once __DIR__ . '/accounting_engine.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$perms = [
    'settings_save' => 'accounting.manage', 'sync' => 'accounting.view',
    'coa_save' => 'accounting.manage', 'coa_status' => 'accounting.manage',
    'jr_manual_save' => 'accounting.journal', 'jr_manual_submit' => 'accounting.journal', 'jr_manual_delete' => 'accounting.journal',
    'jr_manual_post' => 'accounting.approve', 'jr_manual_reject' => 'accounting.approve', 'jr_reverse' => 'accounting.approve',
    'bank_save' => 'accounting.manage', 'bank_status' => 'accounting.manage',
    'stmt_import' => 'bank.reconcile', 'stmt_auto_match' => 'bank.reconcile', 'stmt_match' => 'bank.reconcile', 'stmt_unmatch' => 'bank.reconcile', 'stmt_ignore' => 'bank.reconcile',
    'per_create' => 'accounting.manage', 'per_generate' => 'accounting.manage', 'per_close' => 'accounting.manage', 'per_reopen' => 'accounting.manage', 'per_closing_entry' => 'accounting.manage',
    'exp_list' => 'expense.manage', 'exp_get' => 'expense.manage', 'exp_accounts' => 'expense.manage', 'cashbook_expenses' => 'expense.manage',
    'exp_save' => 'expense.manage', 'exp_post' => 'expense.manage', 'exp_pay' => 'expense.manage', 'exp_cancel' => 'expense.manage', 'exp_reject' => 'expense.approve',
];
$readOnlyAllowed = ['exp_list', 'exp_get', 'exp_accounts', 'cashbook_expenses', 'sync'];
erpx_guard($pdo, $perms[$action] ?? 'accounting.view');
if (isset($perms[$action]) && !in_array($action, $readOnlyAllowed, true) && !$isWrite) erp_fail('Invalid request method.');

function acc_d($v, string $def): string { return erp_date($v) ?: $def; }
function acc_fy_start(): string { $y = (int)date('Y'); if ((int)date('n') < 4) $y--; return "{$y}-04-01"; }
/** Signed balance of one account (normal side) up to a date. */
function acc_balance_of(PDO $pdo, int $accId, string $to, ?string $from = null): float {
    $a = erp_row($pdo, "SELECT * FROM chart_of_accounts WHERE id = ?", [$accId]);
    $r = erp_row($pdo, "SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c FROM journal_lines l JOIN journal_entries j ON j.id = l.journal_id
                        WHERE l.account_id = ? AND j.status IN ('posted','reversed') AND j.entry_date <= ?" . ($from ? " AND j.entry_date >= ?" : ''), $from ? [$accId, $to, $from] : [$accId, $to]);
    return acc_signed($a, (float)$r['d'], (float)$r['c']);
}
function acc_manual_lines(PDO $pdo, array $lines): array {
    $out = []; $dr = 0; $cr = 0;
    foreach ($lines as $l) {
        $acc = (int)($l['account_id'] ?? 0);
        if (!$acc) continue;
        $a = erp_row($pdo, "SELECT id, is_active, name FROM chart_of_accounts WHERE id = ?", [$acc]);
        if (!$a || !(int)$a['is_active']) erp_invalid('An account in the lines is missing or inactive.');
        $d = erp_m(erp_num($l['debit'] ?? 0, 'Debit')); $c = erp_m(erp_num($l['credit'] ?? 0, 'Credit'));
        if ($d > 0 && $c > 0) erp_invalid("{$a['name']}: a line is either a debit or a credit, not both.");
        if ($d <= 0 && $c <= 0) continue;
        $dr += $d; $cr += $c;
        $out[] = ['acc' => $acc, 'dr' => $d, 'cr' => $c, 'memo' => mb_substr(trim((string)($l['memo'] ?? '')), 0, 255) ?: null, 'party_name' => mb_substr(trim((string)($l['party_name'] ?? '')), 0, 150) ?: null,
                  'party_type' => trim((string)($l['party_type'] ?? '')) ?: null];
    }
    if (count($out) < 2) erp_invalid('A journal needs at least two lines.');
    if (abs(erp_m($dr) - erp_m($cr)) >= 0.005) erp_invalid('Total debit (₹' . number_format($dr, 2) . ') must equal total credit (₹' . number_format($cr, 2) . ').');
    return $out;
}
function acc_in_closed(PDO $pdo, string $date): bool {
    return (bool)erp_val($pdo, "SELECT 1 FROM financial_periods WHERE status = 'closed' AND ? BETWEEN start_date AND end_date", [$date]);
}
function exp_txn_sync(PDO $pdo, array $e, ?string $bankName) {
    // mirror the expense in the existing cash book (Accounts → Transactions): completed when paid, pending while unpaid
    $status = $e['payment_status'] === 'paid' ? 'completed' : 'pending';
    if ($e['accounts_transaction_id']) {
        $pdo->prepare("UPDATE accounts_transactions SET status = ?, payment_mode = ?, account = ?, updated_by = ? WHERE id = ?")
            ->execute([$status, in_array($e['payment_mode'], ['cash', 'upi', 'bank_transfer', 'card', 'cheque'], true) ? $e['payment_mode'] : 'other', $bankName, erp_user(), $e['accounts_transaction_id']]);
        return (int)$e['accounts_transaction_id'];
    }
    $id = erp_cash_entry($pdo, 'expense', $e['category'], (float)$e['total'], $e['expense_date'], (string)$e['payment_mode'], $e['payee'], 'expense', $e['expense_number'],
                         mb_substr(($e['description'] ?: $e['category']) . ((float)$e['tax_amount'] > 0 ? ' (incl. GST ₹' . number_format($e['tax_amount'], 2) . ')' : ''), 0, 255), $bankName);
    if ($status === 'pending') $pdo->prepare("UPDATE accounts_transactions SET status = 'pending' WHERE id = ?")->execute([$id]);
    return $id;
}

try {
    switch ($action) {
        // ============================================================ SETTINGS / SYNC
        case 'settings':
            $jr = (int)erp_val($pdo, "SELECT COUNT(*) FROM journal_entries");
            erp_out(['status' => 'success', 'gl_start_date' => acc_start($pdo), 'suggested_start' => date('Y-m-01'), 'journals' => $jr,
                     'online_payment_account' => erp_setting($pdo, 'erp_online_payment_account', ''), 'tax_in_cost' => erp_tax_in_cost($pdo),
                     'banks' => erp_rows($pdo, "SELECT id, name, account_type FROM bank_accounts WHERE status = 'active' ORDER BY is_default DESC, name"),
                     'last_sync' => (int)erp_setting($pdo, 'erp_gl_last_sync', 0) ?: null]);

        case 'settings_save':
            $start = erp_date(erp_input('gl_start_date'));
            $cur = acc_start($pdo);
            if ($start && $start !== $cur) {
                if ((int)erp_val($pdo, "SELECT COUNT(*) FROM journal_entries WHERE is_manual = 0")) erp_invalid('Automatic journals are already posted from ' . $cur . '. The start date can no longer change (reverse entries with a manual journal if needed).');
                erp_setting_set($pdo, 'erp_gl_start_date', $start, 'Journals are posted automatically for records from this date');
            }
            $online = trim((string)erp_input('online_payment_account', ''));
            if ($online !== '' && !erp_val($pdo, "SELECT id FROM bank_accounts WHERE name = ?", [$online])) erp_invalid('Choose one of the bank accounts for online (Razorpay) payments.');
            erp_setting_set($pdo, 'erp_online_payment_account', $online, 'Bank account that receives website online payments');
            log_audit($pdo, 'update', 'settings', 'accounting', ['gl_start_date' => $cur], ['gl_start_date' => $start ?: $cur, 'online_payment_account' => $online]);
            $res = acc_start($pdo) ? acc_sync($pdo, true) : null;
            erp_out(['status' => 'success', 'message' => 'Accounting settings saved.' . ($res && !empty($res['posted']) ? ' Journals posted: ' . array_sum($res['posted']) . '.' : ''), 'sync' => $res]);

        case 'sync':
            if (!acc_start($pdo)) erp_out(['status' => 'success', 'enabled' => false, 'message' => 'Set the ledger start date in Accounting settings first.']);
            $res = acc_sync($pdo, erp_input('force') === '1');
            $n = isset($res['posted']) ? array_sum($res['posted']) : 0;
            erp_out(['status' => 'success', 'enabled' => true, 'result' => $res, 'message' => !empty($res['skipped']) ? 'Journals are up to date.' : "{$n} journal(s) posted."]);

        // ============================================================ CHART OF ACCOUNTS
        case 'coa_list':
            if (acc_start($pdo)) acc_sync($pdo);
            $to = acc_d(erp_input('as_of'), date('Y-m-d'));
            $bal = acc_balances($pdo, null, $to);
            $rows = erp_rows($pdo, "SELECT a.*, p.code AS parent_code FROM chart_of_accounts a LEFT JOIN chart_of_accounts p ON p.id = a.parent_id ORDER BY a.code");
            foreach ($rows as &$a) { [$d, $c] = $bal[(int)$a['id']] ?? [0, 0]; $a['debit'] = erp_m($d); $a['credit'] = erp_m($c); $a['balance'] = acc_signed($a, $d, $c); }
            unset($a);
            erp_out(['status' => 'success', 'rows' => $rows, 'as_of' => $to]);

        case 'coa_save':
            $id = (int)erp_input('id', 0);
            $name = trim((string)erp_input('name', ''));
            if ($name === '') erp_invalid('Enter the account name.');
            $code = trim((string)erp_input('code', ''));
            if (!preg_match('/^[0-9A-Z\-\.]{2,20}$/', $code)) erp_invalid('Account code: 2–20 digits / capital letters (e.g. 6140).');
            $type = (string)erp_input('account_type');
            if (!in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) erp_invalid('Choose the account type.');
            $sub = trim((string)erp_input('sub_type', '')) ?: ($type === 'expense' ? 'operating' : null);
            $parent = (int)erp_input('parent_id', 0) ?: null;
            if (erp_val($pdo, "SELECT id FROM chart_of_accounts WHERE code = ? AND id <> ?", [$code, $id])) erp_invalid("Code {$code} is already used.");
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM chart_of_accounts WHERE id = ?", [$id]);
                if (!$old) erp_invalid('Account not found.');
                if ((int)$old['is_system'] && ($old['account_type'] !== $type || $old['code'] !== $code)) erp_invalid('System accounts keep their code and type (only the name / description can change).');
                $used = (int)erp_val($pdo, "SELECT COUNT(*) FROM journal_lines WHERE account_id = ?", [$id]);
                if ($used && $old['account_type'] !== $type) erp_invalid('The account already has entries — its type cannot change.');
                $pdo->prepare("UPDATE chart_of_accounts SET code = ?, name = ?, account_type = ?, sub_type = ?, parent_id = ?, description = ? WHERE id = ?")
                    ->execute([$code, $name, $type, $sub, $parent, erp_input('description') ?: null, $id]);
            } else {
                $pdo->prepare("INSERT INTO chart_of_accounts (code, name, account_type, sub_type, parent_id, description) VALUES (?,?,?,?,?,?)")->execute([$code, $name, $type, $sub, $parent, erp_input('description') ?: null]);
                $id = (int)$pdo->lastInsertId();
            }
            log_audit($pdo, 'save', 'chart_of_accounts', $id, $old ?? null, ['code' => $code, 'name' => $name, 'type' => $type]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "Account {$code} {$name} saved."]);

        case 'coa_status':
            $id = (int)erp_input('id');
            $a = erp_row($pdo, "SELECT * FROM chart_of_accounts WHERE id = ?", [$id]);
            if (!$a) erp_invalid('Account not found.');
            if ((int)$a['is_system'] && erp_input('active') !== '1') erp_invalid('System accounts are used by automatic journals and cannot be deactivated.');
            $pdo->prepare("UPDATE chart_of_accounts SET is_active = ? WHERE id = ?")->execute([erp_input('active') === '1' ? 1 : 0, $id]);
            log_audit($pdo, 'update', 'chart_of_accounts', $id, ['is_active' => $a['is_active']], ['is_active' => erp_input('active')]);
            erp_out(['status' => 'success', 'message' => 'Account ' . (erp_input('active') === '1' ? 'activated.' : 'deactivated.')]);

        // ============================================================ JOURNALS
        case 'jr_list':
            if (acc_start($pdo)) acc_sync($pdo);
            $w = ['1=1']; $p = [];
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'j.entry_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'j.entry_date <= ?'; $p[] = $d; }
            if ($s = erp_input('status')) { $w[] = 'j.status = ?'; $p[] = $s; }
            if ($m = erp_input('module')) { $w[] = 'j.source_module = ?'; $p[] = $m; }
            if (erp_input('manual') === '1') $w[] = 'j.is_manual = 1';
            if ($acc = (int)erp_input('account_id', 0)) { $w[] = 'EXISTS (SELECT 1 FROM journal_lines x WHERE x.journal_id = j.id AND x.account_id = ?)'; $p[] = $acc; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(j.journal_number LIKE ? OR j.source_ref LIKE ? OR j.narration LIKE ? OR EXISTS (SELECT 1 FROM journal_lines x WHERE x.journal_id = j.id AND x.party_name LIKE ?))'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
            [$lim, $off] = erp_page_args(50);
            $where = implode(' AND ', $w);
            $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM journal_entries j WHERE {$where}", $p);
            $rows = erp_rows($pdo, "SELECT j.* FROM journal_entries j WHERE {$where} ORDER BY j.entry_date DESC, j.id DESC LIMIT {$lim} OFFSET {$off}", $p);
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => $total]);

        case 'jr_get':
            $id = (int)erp_input('id');
            $j = erp_row($pdo, "SELECT * FROM journal_entries WHERE id = ?", [$id]);
            if (!$j) erp_fail('Journal not found.');
            $j['lines'] = erp_rows($pdo, "SELECT l.*, a.code, a.name AS account_name, a.account_type FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE l.journal_id = ? ORDER BY l.id", [$id]);
            $j['related'] = erp_rows($pdo, "SELECT id, journal_number, event, entry_date, status, total FROM journal_entries WHERE source_type = ? AND source_id = ? AND id <> ? ORDER BY id", [$j['source_type'], $j['source_id'], $id]);
            $links = ['purchase_invoice' => 'purchase_invoices.php?id=', 'purchase_payment' => 'purchase_payments.php?id=', 'purchase_return' => 'purchase_returns.php?id=', 'invoice' => 'invoices.php?id=',
                      'manual_sale' => 'manual_sales.php?id=', 'credit_sale' => 'credit_sale.php?id=', 'website_order' => 'orders.php?id=', 'sales_return' => 'sales_returns.php?id=', 'expense' => 'expenses.php?id=',
                      'cashbook' => 'accounts.php?id=', 'shipment_payment' => 'shipments.php', 'supplier_opening' => 'supplier_360.php?id=', 'inventory_day' => 'stock_valuation.php', 'inventory_opening' => 'stock_valuation.php'];
            $j['source_link'] = isset($links[$j['source_type']]) ? $links[$j['source_type']] . (substr($links[$j['source_type']], -1) === '=' ? $j['source_id'] : '') : null;
            $j['approvals'] = $j['is_manual'] ? erp_rows($pdo, "SELECT request_number, status, submitted_by, submitted_at, decided_by, decided_at, remarks FROM approval_requests WHERE module = 'manual_journal' AND entity_id = ? ORDER BY id DESC", [$id]) : [];
            erp_out(['status' => 'success', 'record' => $j]);

        case 'jr_manual_save':
        case 'jr_manual_submit':
            $id = (int)erp_input('id', 0);
            $date = erp_date(erp_input('entry_date'), true);
            if (acc_in_closed($pdo, $date)) erp_invalid('That date is in a closed period — reopen the period or use a later date.');
            $narr = trim((string)erp_input('narration', ''));
            if ($narr === '') erp_invalid('Enter the narration (what this entry is for).');
            $lines = acc_manual_lines($pdo, erp_json_input('lines'));
            $total = erp_m(array_sum(array_column($lines, 'dr')));
            $status = $action === 'jr_manual_submit' ? 'submitted' : 'draft';
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM journal_entries WHERE id = ? AND is_manual = 1 FOR UPDATE", [$id]);
                if (!$old || !in_array($old['status'], ['draft', 'submitted'], true)) erp_invalid('Only draft manual journals can be edited.');
                $pdo->prepare("UPDATE journal_entries SET entry_date = ?, narration = ?, source_ref = ?, total = ?, status = ? WHERE id = ?")->execute([$date, mb_substr($narr, 0, 255), erp_input('reference') ?: null, $total, $status, $id]);
                $pdo->prepare("DELETE FROM journal_lines WHERE journal_id = ?")->execute([$id]);
                $num = $old['journal_number'];
            } else {
                $num = next_document_number($pdo, 'journal', 'JV');
                $pdo->prepare("INSERT INTO journal_entries (journal_number, entry_date, source_module, source_type, source_id, event, source_ref, narration, total, status, is_manual, created_by)
                               VALUES (?,?, 'manual', 'manual', ?, 'post', ?,?,?,?, 1, ?)")
                    ->execute([$num, $date, $num, erp_input('reference') ?: null, mb_substr($narr, 0, 255), $total, $status, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO journal_lines (journal_id, account_id, debit, credit, party_type, party_name, memo) VALUES (?,?,?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute([$id, $l['acc'], $l['dr'], $l['cr'], $l['party_type'], $l['party_name'], $l['memo']]);
            if ($status === 'submitted') apr_open($pdo, 'manual_journal', (string)$id, $id, $num, "Manual journal {$num}: {$narr}", $total, 'accounting_api.php', ['action' => 'jr_manual_post', 'id' => $id], ['action' => 'jr_manual_reject', 'id' => $id]);
            $pdo->commit();
            log_audit($pdo, $status === 'submitted' ? 'submit' : 'save', 'journal_entries', $id, null, ['journal_number' => $num, 'total' => $total, 'lines' => $lines]);
            if ($status === 'submitted' && erp_can($pdo, 'accounting.approve') && erp_input('post_now') === '1') { $action = 'jr_manual_post'; $_POST['id'] = $id; }
            else erp_out(['status' => 'success', 'id' => $id, 'message' => $status === 'submitted' ? "{$num} sent for approval." : "{$num} saved as draft."]);
            // fall through (approver posting immediately)
        case 'jr_manual_post':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $j = erp_row($pdo, "SELECT * FROM journal_entries WHERE id = ? AND is_manual = 1 FOR UPDATE", [$id]);
            if (!$j || !in_array($j['status'], ['submitted', 'draft'], true)) erp_invalid('Only draft or submitted manual journals can be posted.');
            if (acc_in_closed($pdo, $j['entry_date'])) erp_invalid('The entry date is in a closed period.');
            $t = erp_row($pdo, "SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c, COUNT(*) n FROM journal_lines WHERE journal_id = ?", [$id]);
            if ((int)$t['n'] < 2 || abs(erp_m($t['d']) - erp_m($t['c'])) >= 0.005) erp_invalid('The journal does not balance.');
            $pdo->prepare("UPDATE journal_entries SET status = 'posted', approved_by = ?, posted_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
            apr_close($pdo, 'manual_journal', (string)$id, 'approved', erp_input('_approval_remarks') ?: null);
            $pdo->commit();
            log_audit($pdo, 'approve', 'journal_entries', $id, ['status' => $j['status']], ['status' => 'posted']);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$j['journal_number']} posted to the ledger."]);

        case 'jr_manual_reject':
        case 'jr_manual_delete':
            $id = (int)erp_input('id');
            $j = erp_row($pdo, "SELECT * FROM journal_entries WHERE id = ? AND is_manual = 1", [$id]);
            if (!$j || !in_array($j['status'], ['draft', 'submitted'], true)) erp_invalid('Only unposted manual journals can be rejected or cancelled.');
            $pdo->prepare("UPDATE journal_entries SET status = ? WHERE id = ?")->execute([$action === 'jr_manual_reject' ? 'draft' : 'cancelled', $id]);
            apr_close($pdo, 'manual_journal', (string)$id, $action === 'jr_manual_reject' ? 'rejected' : 'cancelled', erp_input('_approval_remarks') ?: erp_input('reason') ?: null);
            log_audit($pdo, $action === 'jr_manual_reject' ? 'reject' : 'cancel', 'journal_entries', $id, ['status' => $j['status']], null);
            erp_out(['status' => 'success', 'message' => $action === 'jr_manual_reject' ? "{$j['journal_number']} returned to draft." : "{$j['journal_number']} cancelled (never posted)."]);

        case 'jr_reverse':
            $id = (int)erp_input('id');
            $j = erp_row($pdo, "SELECT * FROM journal_entries WHERE id = ?", [$id]);
            if (!$j || $j['status'] !== 'posted') erp_invalid('Only posted journals can be reversed.');
            if (!(int)$j['is_manual']) erp_invalid('Automatic journals follow their source document — cancel the document (bill, payment, invoice…) and the reversal is posted automatically.');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason for the reversal.');
            $date = erp_date(erp_input('date')) ?: date('Y-m-d');
            if (acc_in_closed($pdo, $date)) erp_invalid('The reversal date is in a closed period.');
            $rid = jr_reverse($pdo, $id, 'reversal', $date, "Reversal of {$j['journal_number']}: {$reason}");
            if (!$rid) erp_invalid('This journal is already reversed.');
            log_audit($pdo, 'reverse', 'journal_entries', $id, ['status' => 'posted'], ['reversal_journal' => $rid, 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$j['journal_number']} reversed."]);

        // ============================================================ LEDGER REPORTS
        case 'gl':
            if (acc_start($pdo)) acc_sync($pdo);
            $acc = (int)erp_input('account_id', 0);
            if (!$acc && ($key = erp_input('account'))) $acc = (int)erp_val($pdo, "SELECT id FROM chart_of_accounts WHERE system_key = ? OR code = ?", [$key, $key]);
            $a = erp_row($pdo, "SELECT * FROM chart_of_accounts WHERE id = ?", [$acc]);
            if (!$a) erp_fail('Choose an account.');
            $from = acc_d(erp_input('date_from'), acc_start($pdo) ?: date('Y-m-01'));
            $to = acc_d(erp_input('date_to'), date('Y-m-d'));
            $open = acc_balance_of($pdo, $acc, date('Y-m-d', strtotime($from . ' -1 day')));
            $w = "l.account_id = ? AND j.status IN ('posted','reversed') AND j.entry_date BETWEEN ? AND ?"; $p = [$acc, $from, $to];
            if ($party = trim((string)erp_input('party', ''))) { $w .= ' AND l.party_name LIKE ?'; $p[] = "%$party%"; }
            $rows = erp_rows($pdo, "SELECT j.id AS journal_id, j.journal_number, j.entry_date, j.source_type, j.source_id, j.source_ref, j.narration, j.status, l.debit, l.credit, l.party_name, l.memo, l.channel
                                    FROM journal_lines l JOIN journal_entries j ON j.id = l.journal_id WHERE {$w} ORDER BY j.entry_date, j.id, l.id LIMIT 5000", $p);
            $bal = $open; $sign = in_array($a['account_type'], ['asset', 'expense'], true) ? 1 : -1;
            foreach ($rows as &$r) { $bal = erp_m($bal + $sign * ((float)$r['debit'] - (float)$r['credit'])); $r['balance'] = $bal; }
            unset($r);
            erp_out(['status' => 'success', 'account' => $a, 'from' => $from, 'to' => $to, 'opening' => $open, 'closing' => $bal, 'rows' => $rows,
                     'totals' => ['debit' => erp_m(array_sum(array_column($rows, 'debit'))), 'credit' => erp_m(array_sum(array_column($rows, 'credit')))]]);

        case 'trial_balance':
            if (acc_start($pdo)) acc_sync($pdo);
            $to = acc_d(erp_input('as_of'), date('Y-m-d'));
            $bal = acc_balances($pdo, null, $to);
            $out = []; $td = 0; $tc = 0;
            foreach (acc_accounts($pdo) as $id => $a) {
                [$d, $c] = $bal[$id] ?? [0, 0];
                if (abs($d) < 0.005 && abs($c) < 0.005) continue;
                $net = erp_m($d - $c);
                $out[] = ['id' => $id, 'code' => $a['code'], 'name' => $a['name'], 'account_type' => $a['account_type'], 'debit' => $net > 0 ? $net : 0, 'credit' => $net < 0 ? -$net : 0];
                if ($net > 0) $td += $net; else $tc += -$net;
            }
            erp_out(['status' => 'success', 'as_of' => $to, 'rows' => $out, 'total_debit' => erp_m($td), 'total_credit' => erp_m($tc), 'balanced' => abs($td - $tc) < 0.01]);

        case 'pnl_gl':
            if (acc_start($pdo)) acc_sync($pdo);
            $from = acc_d(erp_input('date_from'), date('Y-m-01'));
            $to = acc_d(erp_input('date_to'), date('Y-m-d'));
            $bal = acc_balances($pdo, $from, $to);
            $g = ['sales' => [], 'other_income' => [], 'cogs' => [], 'operating' => []];
            foreach (acc_accounts($pdo) as $id => $a) {
                if (!in_array($a['account_type'], ['income', 'expense'], true)) continue;
                [$d, $c] = $bal[$id] ?? [0, 0];
                $v = acc_signed($a, $d, $c);
                if (abs($v) < 0.005) continue;
                $grp = $a['account_type'] === 'income' ? ($a['sub_type'] === 'sales' ? 'sales' : 'other_income') : ($a['sub_type'] === 'cogs' ? 'cogs' : 'operating');
                $g[$grp][] = ['id' => $id, 'code' => $a['code'], 'name' => $a['name'], 'amount' => $v];
            }
            $sum = function ($k) use ($g) { return erp_m(array_sum(array_column($g[$k], 'amount'))); };
            $net = $sum('sales'); $gp = erp_m($net - $sum('cogs')); $np = erp_m($gp - $sum('operating') + $sum('other_income'));
            erp_out(['status' => 'success', 'from' => $from, 'to' => $to, 'groups' => $g, 'net_sales' => $net, 'cogs' => $sum('cogs'), 'gross_profit' => $gp,
                     'operating_expenses' => $sum('operating'), 'other_income' => $sum('other_income'), 'net_profit' => $np]);

        case 'balance_sheet':
            if (acc_start($pdo)) acc_sync($pdo);
            $to = acc_d(erp_input('as_of'), date('Y-m-d'));
            $bal = acc_balances($pdo, null, $to);
            $g = ['asset' => [], 'liability' => [], 'equity' => []]; $earn = 0.0;
            foreach (acc_accounts($pdo) as $id => $a) {
                [$d, $c] = $bal[$id] ?? [0, 0];
                $v = acc_signed($a, $d, $c);
                if (in_array($a['account_type'], ['income', 'expense'], true)) { $earn += $a['account_type'] === 'income' ? $v : -$v; continue; }
                if (abs($v) < 0.005) continue;
                $g[$a['account_type']][] = ['id' => $id, 'code' => $a['code'], 'name' => $a['name'], 'amount' => $v];
            }
            $g['equity'][] = ['id' => null, 'code' => '', 'name' => 'Profit / loss to date (not yet closed)', 'amount' => erp_m($earn)];
            $tA = erp_m(array_sum(array_column($g['asset'], 'amount'))); $tL = erp_m(array_sum(array_column($g['liability'], 'amount'))); $tE = erp_m(array_sum(array_column($g['equity'], 'amount')));
            erp_out(['status' => 'success', 'as_of' => $to, 'groups' => $g, 'total_assets' => $tA, 'total_liabilities' => $tL, 'total_equity' => $tE, 'balanced' => abs($tA - $tL - $tE) < 0.01]);

        case 'ap_aging':
            $asOf = acc_d(erp_input('as_of'), date('Y-m-d'));
            $rows = [];
            foreach (erp_rows($pdo, "SELECT pi.*, s.supplier_name, sp.credit_days FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id LEFT JOIN supplier_profiles sp ON sp.supplier_id = pi.supplier_id
                                     WHERE pi.status = 'posted' AND pi.invoice_date <= ?", [$asOf]) as $b) {
                $paid = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE pinv_id = ? AND status = 'completed' AND payment_date <= ?", [$b['id'], $asOf]);
                $cr = (float)erp_val($pdo, "SELECT COALESCE(SUM(total_value - refund_received),0) FROM purchase_returns WHERE pinv_id = ? AND status = 'posted' AND settlement IN ('credit_note','refund') AND return_date <= ?", [$b['id'], $asOf]);
                $bal = erp_m((float)$b['grand_total'] - $paid - $cr);
                if ($bal <= 0.005) continue;
                $due = $b['due_date'] ?: ($b['credit_days'] ? date('Y-m-d', strtotime($b['invoice_date'] . ' +' . (int)$b['credit_days'] . ' days')) : $b['invoice_date']);
                $days = (int)floor((strtotime($asOf) - strtotime($due)) / 86400);
                $rows[] = ['supplier_id' => (int)$b['supplier_id'], 'supplier_name' => $b['supplier_name'], 'document' => $b['supplier_invoice_no'], 'pinv_number' => $b['pinv_number'], 'id' => (int)$b['id'],
                           'date' => $b['invoice_date'], 'due_date' => $due, 'days_overdue' => max(0, $days), 'balance' => $bal,
                           'bucket' => $days <= 0 ? 'current' : ($days <= 30 ? 'd1_30' : ($days <= 60 ? 'd31_60' : ($days <= 90 ? 'd61_90' : 'd90p')))];
            }
            // opening balances and advances (payments without a bill)
            foreach (erp_rows($pdo, "SELECT s.id, s.supplier_name, COALESCE(sp.opening_balance,0) ob,
                                            (SELECT COALESCE(SUM(amount),0) FROM purchase_payments pp WHERE pp.supplier_id = s.id AND pp.pinv_id IS NULL AND pp.status = 'completed' AND pp.payment_date <= ?) adv
                                     FROM suppliers s LEFT JOIN supplier_profiles sp ON sp.supplier_id = s.id", [$asOf]) as $s) {
                $v = erp_m((float)$s['ob'] - (float)$s['adv']);
                if (abs($v) >= 0.005) $rows[] = ['supplier_id' => (int)$s['id'], 'supplier_name' => $s['supplier_name'], 'document' => (float)$s['ob'] ? 'Opening balance / advances' : 'Advance paid',
                                                  'pinv_number' => null, 'id' => null, 'date' => null, 'due_date' => null, 'days_overdue' => 0, 'balance' => $v, 'bucket' => 'current'];
            }
            erp_out(['status' => 'success', 'as_of' => $asOf, 'rows' => $rows] + acc_aging_summary($rows, 'supplier_name'));

        case 'ar_aging':
            $asOf = acc_d(erp_input('as_of'), date('Y-m-d'));
            $rows = [];
            $push = function ($cust, $mobile, $doc, $link, $date, $due, $bal) use (&$rows, $asOf) {
                if ($bal <= 0.005) return;
                $days = (int)floor((strtotime($asOf) - strtotime($due ?: $date)) / 86400);
                $rows[] = ['customer' => $cust ?: 'Walk-in', 'mobile' => $mobile, 'document' => $doc, 'link' => $link, 'date' => $date, 'due_date' => $due ?: $date, 'days_overdue' => max(0, $days), 'balance' => erp_m($bal),
                           'bucket' => $days <= 0 ? 'current' : ($days <= 30 ? 'd1_30' : ($days <= 60 ? 'd31_60' : ($days <= 90 ? 'd61_90' : 'd90p')))];
            };
            foreach (erp_rows($pdo, "SELECT id, invoice_number, invoice_date, due_date, customer_name, customer_mobile, grand_total, amount_paid FROM invoices WHERE status NOT IN ('draft','cancelled') AND invoice_date <= ?", [$asOf]) as $i)
                $push($i['customer_name'], $i['customer_mobile'], $i['invoice_number'], 'invoices.php?id=' . $i['id'], $i['invoice_date'], $i['due_date'], (float)$i['grand_total'] - (float)$i['amount_paid']);
            foreach (erp_rows($pdo, "SELECT id, credit_number, sale_date, customer_name, customer_mobile, grand_total, amount_paid FROM credit_sales WHERE sale_date <= ?", [$asOf]) as $c)
                $push($c['customer_name'], $c['customer_mobile'], $c['credit_number'], 'credit_sale.php?id=' . $c['id'], $c['sale_date'], null, (float)$c['grand_total'] - (float)$c['amount_paid']);
            foreach (erp_rows($pdo, "SELECT id, sale_number, sales_date, customer_name, customer_mobile, grand_total FROM manual_sales WHERE payment_status IN ('pending','credit') AND sales_date <= ?", [$asOf]) as $m)
                $push($m['customer_name'], $m['customer_mobile'], $m['sale_number'], 'manual_sales.php?id=' . $m['id'], $m['sales_date'], null, (float)$m['grand_total']);
            foreach (erp_rows($pdo, "SELECT id, receipt, created_at, first_name, last_name, phone, amount FROM orders WHERE UPPER(payment_method) = 'COD' AND payment_status <> 'paid' AND COALESCE(order_status,'') <> 'cancelled' AND DATE(created_at) <= ?", [$asOf]) as $o)
                $push(trim($o['first_name'] . ' ' . $o['last_name']), $o['phone'], $o['receipt'] . ' (COD)', 'orders.php?id=' . $o['id'], substr($o['created_at'], 0, 10), null, (float)$o['amount']);
            // credit notes given reduce what the customer owes
            foreach (erp_rows($pdo, "SELECT cn_number, cn_date, customer_name, customer_mobile, total FROM credit_notes WHERE status = 'issued' AND cn_date <= ?", [$asOf]) as $c)
                $rows[] = ['customer' => $c['customer_name'] ?: 'Walk-in', 'mobile' => $c['customer_mobile'], 'document' => $c['cn_number'] . ' (credit note)', 'link' => 'credit_notes.php', 'date' => $c['cn_date'],
                           'due_date' => $c['cn_date'], 'days_overdue' => 0, 'balance' => -erp_m($c['total']), 'bucket' => 'current'];
            erp_out(['status' => 'success', 'as_of' => $asOf, 'rows' => $rows] + acc_aging_summary($rows, 'customer'));

        case 'cash_flow':
            if (acc_start($pdo)) acc_sync($pdo);
            $from = acc_d(erp_input('date_from'), date('Y-m-01'));
            $to = acc_d(erp_input('date_to'), date('Y-m-d'));
            $money = array_map('intval', array_column(erp_rows($pdo, "SELECT id FROM chart_of_accounts WHERE sub_type IN ('cash','bank')"), 'id'));
            if (!$money) erp_fail('No cash / bank accounts.');
            $in = implode(',', $money);
            $open = 0.0; foreach ($money as $m) $open += acc_balance_of($pdo, $m, date('Y-m-d', strtotime($from . ' -1 day')));
            $cats = [];
            $label = function ($a) {
                if (!$a) return ['operating', 'Other'];
                switch ($a['system_key']) {
                    case 'ar': case 'sales': case 'sales_returns': return ['operating', 'Received from customers'];
                    case 'ap': case 'grni': case 'freight_payable': case 'purchase_expense': return ['operating', 'Paid to suppliers / transport'];
                    case 'gst_input': case 'gst_output': return ['operating', 'GST'];
                    case 'capital': case 'obe': case 'retained': return ['financing', 'Owner / capital'];
                    case 'fixed_assets': return ['investing', 'Fixed assets'];
                    case 'suspense': return ['operating', 'Unclassified (suspense)'];
                    case 'other_income': return ['operating', 'Other income'];
                }
                if ($a['account_type'] === 'expense') return ['operating', 'Expenses paid'];
                if ($a['account_type'] === 'equity') return ['financing', 'Owner / capital'];
                if ($a['sub_type'] === 'fixed') return ['investing', 'Fixed assets'];
                return ['operating', 'Other'];
            };
            foreach (erp_rows($pdo, "SELECT j.id, SUM(CASE WHEN l.account_id IN ($in) THEN l.debit - l.credit ELSE 0 END) cash FROM journal_entries j JOIN journal_lines l ON l.journal_id = j.id
                                     WHERE j.status IN ('posted','reversed') AND j.entry_date BETWEEN ? AND ? GROUP BY j.id HAVING ABS(cash) >= 0.005", [$from, $to]) as $j) {
                $other = erp_row($pdo, "SELECT a.* FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE l.journal_id = ? AND l.account_id NOT IN ($in) ORDER BY (l.debit + l.credit) DESC LIMIT 1", [$j['id']]);
                [$sec, $lab] = $label($other);
                $k = $sec . '|' . $lab;
                if (!isset($cats[$k])) $cats[$k] = ['section' => $sec, 'label' => $lab, 'in' => 0.0, 'out' => 0.0];
                if ($j['cash'] > 0) $cats[$k]['in'] += (float)$j['cash']; else $cats[$k]['out'] += -(float)$j['cash'];
            }
            $net = 0.0;
            foreach ($cats as &$c) { $c['in'] = erp_m($c['in']); $c['out'] = erp_m($c['out']); $c['net'] = erp_m($c['in'] - $c['out']); $net += $c['net']; }
            unset($c);
            $closing = 0.0; foreach ($money as $m) $closing += acc_balance_of($pdo, $m, $to);
            erp_out(['status' => 'success', 'from' => $from, 'to' => $to, 'opening' => erp_m($open), 'lines' => array_values($cats), 'net_change' => erp_m($net), 'closing' => erp_m($closing),
                     'by_account' => array_map(function ($m) use ($pdo, $from, $to) { $a = erp_row($pdo, "SELECT code, name FROM chart_of_accounts WHERE id = ?", [$m]);
                         return $a + ['opening' => acc_balance_of($pdo, $m, date('Y-m-d', strtotime($from . ' -1 day'))), 'closing' => acc_balance_of($pdo, $m, $to)]; }, $money)]);

        case 'gst':
            $from = acc_d(erp_input('date_from'), date('Y-m-01'));
            $to = acc_d(erp_input('date_to'), date('Y-m-d'));
            $taxInCost = erp_tax_in_cost($pdo);
            $docs = [];
            foreach (erp_rows($pdo, "SELECT invoice_date d, invoice_number n, customer_name party, grand_total - tax_amount taxable, tax_amount tax FROM invoices WHERE status NOT IN ('draft','cancelled') AND invoice_date BETWEEN ? AND ?", [$from, $to]) as $r) $docs[] = $r + ['kind' => 'output', 'type' => 'Sales invoice'];
            foreach (erp_rows($pdo, "SELECT sales_date d, sale_number n, customer_name party, grand_total - total_tax taxable, total_tax tax FROM manual_sales WHERE sales_date BETWEEN ? AND ?", [$from, $to]) as $r) $docs[] = $r + ['kind' => 'output', 'type' => 'Manual sale'];
            foreach (erp_rows($pdo, "SELECT sale_date d, credit_number n, customer_name party, grand_total - total_tax taxable, total_tax tax FROM credit_sales WHERE sale_date BETWEEN ? AND ?", [$from, $to]) as $r) $docs[] = $r + ['kind' => 'output', 'type' => 'Credit sale'];
            foreach (erp_rows($pdo, "SELECT pi.invoice_date d, pi.supplier_invoice_no n, s.supplier_name party, pi.subtotal - pi.discount_total taxable, pi.tax_total tax, s.gst_number gstin
                                     FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id WHERE pi.status = 'posted' AND pi.invoice_date BETWEEN ? AND ?", [$from, $to]) as $r) $docs[] = $r + ['kind' => 'input', 'type' => 'Purchase bill'];
            foreach (erp_rows($pdo, "SELECT expense_date d, expense_number n, payee party, amount taxable, tax_amount tax FROM expenses WHERE status = 'posted' AND tax_amount > 0 AND expense_date BETWEEN ? AND ?", [$from, $to]) as $r) $docs[] = $r + ['kind' => 'input', 'type' => 'Expense'];
            $months = [];
            foreach ($docs as $d) {
                $m = substr($d['d'], 0, 7);
                if (!isset($months[$m])) $months[$m] = ['month' => $m, 'output_taxable' => 0, 'output_tax' => 0, 'input_taxable' => 0, 'input_tax' => 0];
                $months[$m][$d['kind'] . '_taxable'] += (float)$d['taxable']; $months[$m][$d['kind'] . '_tax'] += (float)$d['tax'];
            }
            ksort($months);
            foreach ($months as &$m) { foreach (['output_taxable', 'output_tax', 'input_taxable', 'input_tax'] as $k) $m[$k] = erp_m($m[$k]); $m['net_payable'] = erp_m($m['output_tax'] - ($taxInCost ? 0 : $m['input_tax'])); }
            unset($m);
            $out = erp_m(array_sum(array_column($months, 'output_tax'))); $inp = erp_m(array_sum(array_column($months, 'input_tax')));
            erp_out(['status' => 'success', 'from' => $from, 'to' => $to, 'months' => array_values($months), 'documents' => $docs, 'output_tax' => $out, 'input_tax' => $inp,
                     'net_payable' => erp_m($out - ($taxInCost ? 0 : $inp)), 'tax_in_cost' => $taxInCost,
                     'notes' => ['Website orders have no GST split in the existing data, so they are not in this summary.', $taxInCost ? 'Purchase GST is set as NOT claimable (added to stock cost), so input tax is shown for information only.' : 'Purchase GST is treated as input tax credit.', 'This is a working summary — file returns from your GST software / accountant.']]);

        // ============================================================ BANK & CASH
        case 'bank_list':
            if (acc_start($pdo)) acc_sync($pdo);
            $rows = erp_rows($pdo, "SELECT b.*, a.code, a.name AS account_name FROM bank_accounts b JOIN chart_of_accounts a ON a.id = b.coa_account_id ORDER BY b.is_default DESC, b.name");
            foreach ($rows as &$b) {
                $b['book_balance'] = acc_balance_of($pdo, (int)$b['coa_account_id'], date('Y-m-d'));
                $b['unmatched_lines'] = (int)erp_val($pdo, "SELECT COUNT(*) FROM bank_statement_lines WHERE bank_account_id = ? AND status = 'unmatched'", [$b['id']]);
                $b['last_statement'] = erp_val($pdo, "SELECT MAX(txn_date) FROM bank_statement_lines WHERE bank_account_id = ?", [$b['id']]) ?: null;
            }
            unset($b);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'bank_save':
            $id = (int)erp_input('id', 0);
            $name = trim((string)erp_input('name', ''));
            if ($name === '') erp_invalid('Enter a name (e.g. "HDFC current account").');
            $type = in_array(erp_input('account_type'), ['cash', 'bank', 'wallet'], true) ? erp_input('account_type') : 'bank';
            $last4 = substr(preg_replace('/\D/', '', (string)erp_input('account_number', '')), -4) ?: null;
            $ifsc = strtoupper(trim((string)erp_input('ifsc', '')));
            if ($ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) erp_invalid('IFSC looks wrong (format: ABCD0123456).');
            $modes = array_values(array_intersect(['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'], array_map('trim', explode(',', (string)erp_input('payment_modes', '')))));
            if (erp_val($pdo, "SELECT id FROM bank_accounts WHERE name = ? AND id <> ?", [$name, $id])) erp_invalid('That name is already used.');
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM bank_accounts WHERE id = ?", [$id]);
                if (!$old) erp_invalid('Account not found.');
                $pdo->prepare("UPDATE bank_accounts SET name = ?, bank_name = ?, account_last4 = COALESCE(?, account_last4), ifsc = ?, payment_modes = ? WHERE id = ?")
                    ->execute([$name, erp_input('bank_name') ?: null, $last4, $ifsc ?: null, implode(',', $modes) ?: null, $id]);
                $pdo->prepare("UPDATE chart_of_accounts SET name = ? WHERE id = ? AND is_system = 0")->execute([$name, $old['coa_account_id']]);
            } else {
                $parentKey = $type === 'cash' ? 'cash' : 'bank';
                $parent = erp_row($pdo, "SELECT * FROM chart_of_accounts WHERE system_key = ?", [$parentKey]);
                $n = 1; while (erp_val($pdo, "SELECT id FROM chart_of_accounts WHERE code = ?", [$parent['code'] . '-' . $n])) $n++;
                $pdo->prepare("INSERT INTO chart_of_accounts (code, name, account_type, sub_type, parent_id) VALUES (?,?, 'asset', ?, ?)")->execute([$parent['code'] . '-' . $n, $name, $type === 'cash' ? 'cash' : 'bank', $parent['id']]);
                $coa = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO bank_accounts (name, account_type, coa_account_id, bank_name, account_last4, ifsc, payment_modes) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name, $type, $coa, erp_input('bank_name') ?: null, $last4, $ifsc ?: null, implode(',', $modes) ?: null]);
                $id = (int)$pdo->lastInsertId();
            }
            if (erp_input('is_default') === '1') { $pdo->prepare("UPDATE bank_accounts SET is_default = (id = ?)")->execute([$id]); }
            $pdo->commit();
            log_audit($pdo, 'save', 'bank_accounts', $id, null, ['name' => $name, 'type' => $type, 'modes' => $modes]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$name} saved. Only the last 4 digits of the account number are stored."]);

        case 'bank_status':
            $id = (int)erp_input('id');
            $b = erp_row($pdo, "SELECT * FROM bank_accounts WHERE id = ?", [$id]);
            if (!$b) erp_invalid('Account not found.');
            if ((int)$b['is_default'] && erp_input('active') !== '1') erp_invalid('Make another account the default first.');
            $pdo->prepare("UPDATE bank_accounts SET status = ? WHERE id = ?")->execute([erp_input('active') === '1' ? 'active' : 'inactive', $id]);
            log_audit($pdo, 'update', 'bank_accounts', $id, ['status' => $b['status']], ['status' => erp_input('active') === '1' ? 'active' : 'inactive']);
            erp_out(['status' => 'success', 'message' => 'Saved.']);

        // ============================================================ BANK RECONCILIATION
        case 'stmt_import':
            $bid = (int)erp_input('bank_account_id');
            $b = erp_row($pdo, "SELECT * FROM bank_accounts WHERE id = ?", [$bid]);
            if (!$b) erp_invalid('Choose the bank account.');
            $rows = erp_json_input('rows');
            if (!$rows) erp_invalid('No statement lines found in the file.');
            if (count($rows) > 5000) erp_invalid('Import at most 5,000 lines at a time.');
            $batch = date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $added = 0; $dup = 0; $seen = [];
            $pdo->beginTransaction();
            foreach ($rows as $i => $r) {
                $d = erp_date($r['date'] ?? '');
                if (!$d) erp_invalid('Line ' . ($i + 1) . ': date must be YYYY-MM-DD.');
                $amt = erp_m(erp_num($r['amount'] ?? 0, 'Line ' . ($i + 1) . ' amount', true, true));
                if (abs($amt) < 0.005) continue;
                $desc = mb_substr(trim((string)($r['description'] ?? '')), 0, 255); $ref = mb_substr(trim((string)($r['reference'] ?? '')), 0, 100);
                $base = $bid . '|' . $d . '|' . $amt . '|' . $ref . '|' . $desc;
                $seen[$base] = ($seen[$base] ?? 0) + 1;                 // identical lines inside one file stay separate
                $hash = sha1($base . '|' . $seen[$base]);
                $bal = ($r['balance'] ?? '') === '' ? null : erp_m(erp_num($r['balance'], 'Balance', true, true));
                try {
                    $pdo->prepare("INSERT INTO bank_statement_lines (bank_account_id, txn_date, description, reference, amount, balance, line_hash, import_batch) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$bid, $d, $desc ?: null, $ref ?: null, $amt, $bal, $hash, $batch]);
                    $added++;
                } catch (PDOException $e) { if ($e->getCode() === '23000') { $dup++; continue; } throw $e; }
            }
            $pdo->commit();
            log_audit($pdo, 'import', 'bank_statement_lines', $bid, null, ['batch' => $batch, 'added' => $added, 'duplicates' => $dup]);
            erp_out(['status' => 'success', 'message' => "{$added} line(s) imported" . ($dup ? ", {$dup} already imported earlier (skipped)" : '') . '. Run "Auto-match" next.']);

        case 'stmt_list':
            $bid = (int)erp_input('bank_account_id');
            $b = erp_row($pdo, "SELECT * FROM bank_accounts WHERE id = ?", [$bid]);
            if (!$b) erp_fail('Choose the bank account.');
            if (acc_start($pdo)) acc_sync($pdo);
            $from = acc_d(erp_input('date_from'), date('Y-m-01', strtotime('-2 months')));
            $to = acc_d(erp_input('date_to'), date('Y-m-d'));
            $st = (string)erp_input('status', '');
            $lines = erp_rows($pdo, "SELECT s.*, j.journal_number, j.narration AS matched_narration FROM bank_statement_lines s LEFT JOIN journal_lines l ON l.id = s.matched_journal_line_id
                                     LEFT JOIN journal_entries j ON j.id = l.journal_id WHERE s.bank_account_id = ? AND s.txn_date BETWEEN ? AND ?" . ($st ? ' AND s.status = ?' : '') . " ORDER BY s.txn_date, s.id",
                                     $st ? [$bid, $from, $to, $st] : [$bid, $from, $to]);
            $book = erp_rows($pdo, "SELECT l.id, l.debit, l.credit, l.party_name, l.memo, j.journal_number, j.entry_date, j.narration, j.source_ref,
                                           (SELECT s.id FROM bank_statement_lines s WHERE s.matched_journal_line_id = l.id LIMIT 1) AS statement_line_id
                                    FROM journal_lines l JOIN journal_entries j ON j.id = l.journal_id
                                    WHERE l.account_id = ? AND j.status IN ('posted','reversed') AND j.entry_date BETWEEN ? AND ? ORDER BY j.entry_date, l.id", [$b['coa_account_id'], $from, $to]);
            $stBal = erp_val($pdo, "SELECT balance FROM bank_statement_lines WHERE bank_account_id = ? AND balance IS NOT NULL AND txn_date <= ? ORDER BY txn_date DESC, id DESC LIMIT 1", [$bid, $to]);
            $bookBal = acc_balance_of($pdo, (int)$b['coa_account_id'], $to);
            $unBook = array_values(array_filter($book, function ($x) { return !$x['statement_line_id']; }));
            $inTransit = erp_m(array_sum(array_map(function ($x) { return (float)$x['debit'] - (float)$x['credit']; }, $unBook)));
            $unStmt = erp_m(array_sum(array_map(function ($x) { return $x['status'] === 'unmatched' ? (float)$x['amount'] : 0; }, $lines)));
            erp_out(['status' => 'success', 'account' => $b, 'from' => $from, 'to' => $to, 'statement_lines' => $lines, 'book_lines' => $book,
                     'summary' => ['book_balance' => $bookBal, 'statement_balance' => $stBal === false || $stBal === null ? null : (float)$stBal,
                                   'book_not_in_statement' => $inTransit, 'statement_not_in_book' => $unStmt,
                                   'reconciled_difference' => $stBal === false || $stBal === null ? null : erp_m($bookBal - $inTransit + $unStmt - (float)$stBal)]]);

        case 'stmt_auto_match':
            $bid = (int)erp_input('bank_account_id');
            $b = erp_row($pdo, "SELECT * FROM bank_accounts WHERE id = ?", [$bid]);
            if (!$b) erp_invalid('Choose the bank account.');
            if (acc_start($pdo)) acc_sync($pdo, true);
            $n = 0;
            $pdo->beginTransaction();
            foreach (erp_rows($pdo, "SELECT * FROM bank_statement_lines WHERE bank_account_id = ? AND status = 'unmatched' ORDER BY txn_date, id", [$bid]) as $s) {
                $amt = (float)$s['amount'];
                $cands = erp_rows($pdo, "SELECT l.id, j.entry_date, j.source_ref, j.narration, l.memo FROM journal_lines l JOIN journal_entries j ON j.id = l.journal_id
                                         WHERE l.account_id = ? AND j.status IN ('posted','reversed') AND " . ($amt > 0 ? 'l.debit = ?' : 'l.credit = ?') . "
                                           AND j.entry_date BETWEEN DATE_SUB(?, INTERVAL 5 DAY) AND DATE_ADD(?, INTERVAL 5 DAY)
                                           AND NOT EXISTS (SELECT 1 FROM bank_statement_lines x WHERE x.matched_journal_line_id = l.id)
                                         ORDER BY ABS(DATEDIFF(j.entry_date, ?)), l.id", [$b['coa_account_id'], abs($amt), $s['txn_date'], $s['txn_date'], $s['txn_date']]);
                if (!$cands) continue;
                $pick = null;
                if ($s['reference']) foreach ($cands as $c) if (stripos($c['source_ref'] . ' ' . $c['narration'] . ' ' . $c['memo'], $s['reference']) !== false) { $pick = $c; break; }
                if (!$pick && count($cands) === 1) $pick = $cands[0];
                if (!$pick) continue;   // several possible entries → leave for manual matching
                $pdo->prepare("UPDATE bank_statement_lines SET matched_journal_line_id = ?, status = 'matched', matched_by = ?, matched_at = NOW() WHERE id = ? AND status = 'unmatched'")->execute([$pick['id'], 'auto', $s['id']]);
                $n++;
            }
            $pdo->commit();
            log_audit($pdo, 'auto_match', 'bank_statement_lines', $bid, null, ['matched' => $n]);
            erp_out(['status' => 'success', 'message' => "{$n} line(s) matched automatically (same amount, within 5 days, unique or same reference)."]);

        case 'stmt_match':
            $s = erp_row($pdo, "SELECT s.*, b.coa_account_id FROM bank_statement_lines s JOIN bank_accounts b ON b.id = s.bank_account_id WHERE s.id = ?", [(int)erp_input('line_id')]);
            if (!$s || $s['status'] !== 'unmatched') erp_invalid('That statement line is not open.');
            $l = erp_row($pdo, "SELECT * FROM journal_lines WHERE id = ?", [(int)erp_input('journal_line_id')]);
            if (!$l || (int)$l['account_id'] !== (int)$s['coa_account_id']) erp_invalid('Choose a ledger entry of the same bank account.');
            if (erp_val($pdo, "SELECT id FROM bank_statement_lines WHERE matched_journal_line_id = ?", [$l['id']])) erp_invalid('That ledger entry is already matched.');
            $bookAmt = (float)$l['debit'] - (float)$l['credit'];
            if (abs($bookAmt - (float)$s['amount']) >= 0.005) erp_invalid('The amounts differ (statement ₹' . number_format($s['amount'], 2) . ', ledger ₹' . number_format($bookAmt, 2) . ').');
            $pdo->prepare("UPDATE bank_statement_lines SET matched_journal_line_id = ?, status = 'matched', matched_by = ?, matched_at = NOW() WHERE id = ?")->execute([$l['id'], erp_user(), $s['id']]);
            log_audit($pdo, 'match', 'bank_statement_lines', $s['id'], null, ['journal_line' => $l['id']]);
            erp_out(['status' => 'success', 'message' => 'Matched.']);

        case 'stmt_unmatch':
        case 'stmt_ignore':
            $s = erp_row($pdo, "SELECT * FROM bank_statement_lines WHERE id = ?", [(int)erp_input('line_id')]);
            if (!$s) erp_invalid('Line not found.');
            if ($action === 'stmt_ignore' && trim((string)erp_input('reason', '')) === '') erp_invalid('Give a reason for ignoring this line.');
            $pdo->prepare("UPDATE bank_statement_lines SET matched_journal_line_id = NULL, status = ?, matched_by = ?, matched_at = NOW(), description = IF(? = '', description, CONCAT(COALESCE(description,''), ' [ignored: ', ?, ']')) WHERE id = ?")
                ->execute([$action === 'stmt_ignore' ? 'ignored' : 'unmatched', erp_user(), (string)erp_input('reason', ''), (string)erp_input('reason', ''), $s['id']]);
            log_audit($pdo, $action === 'stmt_ignore' ? 'ignore' : 'unmatch', 'bank_statement_lines', $s['id'], ['status' => $s['status']], ['reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => $action === 'stmt_ignore' ? 'Line ignored.' : 'Match removed.']);

        // ============================================================ PERIODS
        case 'per_list':
            $rows = erp_rows($pdo, "SELECT p.*, (SELECT COUNT(*) FROM journal_entries j WHERE j.entry_date BETWEEN p.start_date AND p.end_date AND j.status IN ('posted','reversed')) AS journals,
                                           (SELECT COUNT(*) FROM journal_entries j WHERE j.entry_date BETWEEN p.start_date AND p.end_date AND j.is_manual = 1 AND j.status IN ('draft','submitted')) AS unposted
                                    FROM financial_periods p ORDER BY p.start_date DESC");
            erp_out(['status' => 'success', 'rows' => $rows, 'fy_start' => acc_fy_start()]);

        case 'per_create':
        case 'per_generate':
            $made = 0;
            $list = [];
            if ($action === 'per_generate') {
                $s = erp_date(erp_input('fy_start'), true);
                for ($i = 0; $i < 12; $i++) { $a = date('Y-m-01', strtotime("$s +$i month")); $list[] = [date('M Y', strtotime($a)), $a, date('Y-m-t', strtotime($a))]; }
            } else {
                $a = erp_date(erp_input('start_date'), true); $b = erp_date(erp_input('end_date'), true);
                if ($b < $a) erp_invalid('The end date is before the start date.');
                $list[] = [trim((string)erp_input('name', '')) ?: "$a to $b", $a, $b];
            }
            foreach ($list as [$n, $a, $b]) {
                if (erp_val($pdo, "SELECT id FROM financial_periods WHERE start_date <= ? AND end_date >= ?", [$b, $a])) { if ($action === 'per_create') erp_invalid('It overlaps an existing period.'); continue; }
                $pdo->prepare("INSERT INTO financial_periods (name, start_date, end_date) VALUES (?,?,?)")->execute([$n, $a, $b]);
                $made++;
            }
            log_audit($pdo, 'create', 'financial_periods', null, null, ['periods' => $made]);
            erp_out(['status' => 'success', 'message' => "{$made} period(s) created."]);

        case 'per_close':
        case 'per_reopen':
            $id = (int)erp_input('id');
            $p = erp_row($pdo, "SELECT * FROM financial_periods WHERE id = ?", [$id]);
            if (!$p) erp_invalid('Period not found.');
            if ($action === 'per_close') {
                if ($p['status'] === 'closed') erp_invalid('Already closed.');
                $open = (int)erp_val($pdo, "SELECT COUNT(*) FROM journal_entries WHERE entry_date BETWEEN ? AND ? AND is_manual = 1 AND status IN ('draft','submitted')", [$p['start_date'], $p['end_date']]);
                if ($open) erp_invalid("{$open} manual journal(s) in this period are not posted yet — post or cancel them first.");
                if (acc_start($pdo)) acc_sync($pdo, true);   // post everything that belongs to the period before closing it
                $pdo->prepare("UPDATE financial_periods SET status = 'closed', closed_by = ?, closed_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
                $msg = "{$p['name']} closed. Late entries for it will be dated the first open day after it.";
            } else {
                if ($p['status'] !== 'closed') erp_invalid('The period is open.');
                if (trim((string)erp_input('reason', '')) === '') erp_invalid('Give a reason for reopening.');
                $pdo->prepare("UPDATE financial_periods SET status = 'open', closed_by = NULL, closed_at = NULL WHERE id = ?")->execute([$id]);
                $msg = "{$p['name']} reopened.";
            }
            log_audit($pdo, $action === 'per_close' ? 'close' : 'reopen', 'financial_periods', $id, ['status' => $p['status']], ['reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => $msg]);

        case 'per_closing_entry':
            // moves the period's income and expense balances into retained earnings (once per period)
            $id = (int)erp_input('id');
            $p = erp_row($pdo, "SELECT * FROM financial_periods WHERE id = ?", [$id]);
            if (!$p) erp_invalid('Period not found.');
            if (acc_start($pdo)) acc_sync($pdo, true);
            $bal = acc_balances($pdo, $p['start_date'], $p['end_date']);
            $lines = []; $net = 0.0;
            foreach (acc_accounts($pdo) as $aid => $a) {
                if (!in_array($a['account_type'], ['income', 'expense'], true)) continue;
                [$d, $c] = $bal[$aid] ?? [0, 0];
                $diff = erp_m($d - $c);
                if (abs($diff) < 0.005) continue;
                $lines[] = ['acc' => $aid, 'dr' => $diff < 0 ? -$diff : 0, 'cr' => $diff > 0 ? $diff : 0, 'memo' => 'Close ' . $a['name']];
                $net += $diff;   // debit-balance total (expenses minus income)
            }
            if (!$lines) erp_invalid('No income or expense balances in this period.');
            $lines[] = ['acc' => acc_id($pdo, 'retained'), 'dr' => $net > 0 ? erp_m($net) : 0, 'cr' => $net < 0 ? erp_m(-$net) : 0, 'memo' => $net > 0 ? 'Loss for the period' : 'Profit for the period'];
            $jid = jr_post($pdo, 'closing', 'period_closing', (string)$id, 'post', $p['end_date'], $p['name'], "Closing entry {$p['name']} — profit & loss to retained earnings", $lines, ['keep_date' => true, 'strict' => true]);
            if (!$jid) erp_invalid('The closing entry for this period is already posted.');
            log_audit($pdo, 'closing_entry', 'financial_periods', $id, null, ['journal_id' => $jid, 'net' => -$net]);
            erp_out(['status' => 'success', 'message' => 'Closing entry posted: ' . ($net < 0 ? 'profit' : 'loss') . ' ₹' . number_format(abs($net), 2) . ' moved to retained earnings.']);

        // ============================================================ EXPENSES
        case 'exp_accounts':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT id, code, name, sub_type FROM chart_of_accounts WHERE account_type = 'expense' AND is_active = 1 AND COALESCE(sub_type,'operating') IN ('operating') ORDER BY code"),
                     'banks' => erp_rows($pdo, "SELECT id, name, account_type, payment_modes FROM bank_accounts WHERE status = 'active' ORDER BY is_default DESC, name"),
                     'policy' => apr_policy($pdo, 'expense'), 'is_approver' => apr_is_approver($pdo, 'expense')]);

        case 'exp_list':
            $w = ['1=1']; $p = [];
            if ($s = erp_input('status')) { $w[] = 'e.status = ?'; $p[] = $s; }
            if ($s = erp_input('payment_status')) { $w[] = 'e.payment_status = ?'; $p[] = $s; }
            if ($a = (int)erp_input('account_id', 0)) { $w[] = 'e.account_id = ?'; $p[] = $a; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'e.expense_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'e.expense_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(e.expense_number LIKE ? OR e.payee LIKE ? OR e.description LIKE ? OR e.reference_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
            [$lim, $off] = erp_page_args(50);
            $where = implode(' AND ', $w);
            $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM expenses e WHERE {$where}", $p);
            $sum = erp_row($pdo, "SELECT COALESCE(SUM(amount),0) a, COALESCE(SUM(tax_amount),0) t, COALESCE(SUM(total),0) g FROM expenses e WHERE {$where} AND e.status = 'posted'", $p);
            $rows = erp_rows($pdo, "SELECT e.*, a.code AS account_code, b.name AS bank_name FROM expenses e JOIN chart_of_accounts a ON a.id = e.account_id LEFT JOIN bank_accounts b ON b.id = e.bank_account_id
                                    WHERE {$where} ORDER BY e.expense_date DESC, e.id DESC LIMIT {$lim} OFFSET {$off}", $p);
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => $total, 'posted_totals' => ['amount' => erp_m($sum['a']), 'tax' => erp_m($sum['t']), 'total' => erp_m($sum['g'])]]);

        case 'exp_get':
            $id = (int)erp_input('id');
            $e = erp_row($pdo, "SELECT e.*, a.code AS account_code, a.name AS account_name, b.name AS bank_name, t.transaction_id AS txn_number, s.shipment_number, sup.supplier_name
                                FROM expenses e JOIN chart_of_accounts a ON a.id = e.account_id LEFT JOIN bank_accounts b ON b.id = e.bank_account_id
                                LEFT JOIN accounts_transactions t ON t.id = e.accounts_transaction_id LEFT JOIN inbound_shipments s ON s.id = e.shipment_id LEFT JOIN suppliers sup ON sup.id = e.supplier_id WHERE e.id = ?", [$id]);
            if (!$e) erp_fail('Expense not found.');
            $e['journals'] = erp_rows($pdo, "SELECT id, journal_number, event, entry_date, status, total FROM journal_entries WHERE source_type = 'expense' AND source_id = ?", [$id]);
            $e['approvals'] = erp_rows($pdo, "SELECT request_number, status, submitted_by, submitted_at, decided_by, decided_at, remarks FROM approval_requests WHERE module = 'expense' AND entity_id = ? ORDER BY id DESC", [$id]);
            erp_out(['status' => 'success', 'record' => $e]);

        case 'exp_save':
        case 'exp_post':
            $id = (int)erp_input('id', 0);
            $acc = erp_row($pdo, "SELECT * FROM chart_of_accounts WHERE id = ? AND account_type = 'expense' AND is_active = 1", [(int)erp_input('account_id')]);
            if (!$acc) erp_invalid('Choose the expense category.');
            $date = erp_date(erp_input('expense_date'), true);
            if ($date > date('Y-m-d')) erp_invalid('The expense date cannot be in the future.');
            $amount = erp_m(erp_num(erp_input('amount'), 'Amount', false));
            $tax = erp_m(erp_num(erp_input('tax_amount', 0), 'GST amount'));
            $total = erp_m($amount + $tax);
            $paid = erp_input('payment_status', 'paid') === 'unpaid' ? 'unpaid' : 'paid';
            $mode = in_array(erp_input('payment_mode'), ['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'], true) ? erp_input('payment_mode') : 'cash';
            $bank = (int)erp_input('bank_account_id', 0) ?: null;
            if ($bank && !erp_val($pdo, "SELECT id FROM bank_accounts WHERE id = ? AND status = 'active'", [$bank])) erp_invalid('Choose an active bank / cash account.');
            $ref = trim((string)erp_input('reference_number', ''));
            if ($paid === 'paid' && in_array($mode, ['bank_transfer', 'upi', 'cheque'], true) && $ref === '') erp_invalid('Enter the reference / UTR / cheque number.');
            if ($ref !== '' && erp_val($pdo, "SELECT id FROM expenses WHERE reference_number = ? AND payment_mode = ? AND status NOT IN ('cancelled','rejected') AND id <> ?", [$ref, $mode, $id])) erp_invalid("Reference {$ref} is already used on another expense.");
            $payee = trim((string)erp_input('payee', ''));
            $sup = (int)erp_input('supplier_id', 0) ?: null;
            $shipment = (int)erp_input('shipment_id', 0) ?: null;
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM expenses WHERE id = ? FOR UPDATE", [$id]);
                if (!$old || !in_array($old['status'], ['draft', 'submitted', 'rejected'], true)) erp_invalid('Only draft expenses can be edited.');
                $shipment = $old['shipment_id'];
                $pdo->prepare("UPDATE expenses SET expense_date=?, account_id=?, category=?, payee=?, supplier_id=?, amount=?, tax_amount=?, total=?, payment_status=?, payment_mode=?, bank_account_id=?, reference_number=?,
                               paid_date=?, description=? WHERE id=?")
                    ->execute([$date, $acc['id'], $acc['name'], $payee ?: null, $sup, $amount, $tax, $total, $paid, $mode, $bank, $ref ?: null, $paid === 'paid' ? $date : null, erp_input('description') ?: null, $id]);
                $num = $old['expense_number'];
            } else {
                $num = next_document_number($pdo, 'expense', 'EXP');
                $pdo->prepare("INSERT INTO expenses (expense_number, expense_date, account_id, category, payee, supplier_id, amount, tax_amount, total, payment_status, payment_mode, bank_account_id, reference_number,
                               paid_date, description, shipment_id, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?)")
                    ->execute([$num, $date, $acc['id'], $acc['name'], $payee ?: null, $sup, $amount, $tax, $total, $paid, $mode, $bank, $ref ?: null, $paid === 'paid' ? $date : null, erp_input('description') ?: null, $shipment, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
            $msg = "{$num} saved as draft.";
            if ($action === 'exp_post') {
                if (apr_intercept($pdo, 'expense', (string)$id, $total, "Expense {$num}: {$acc['name']}" . ($payee ? " — {$payee}" : '') . ' ₹' . number_format($total, 2), $num, 'accounting_api.php', $id)) {
                    $pdo->prepare("UPDATE expenses SET status = 'submitted' WHERE id = ?")->execute([$id]);
                    $apr = apr_open($pdo, 'expense', (string)$id, $id, $num, "Expense {$num}: {$acc['name']}" . ($payee ? " — {$payee}" : '') . ' ₹' . number_format($total, 2), $total, 'accounting_api.php',
                                    array_merge(apr_payload(), ['action' => 'exp_post', 'id' => $id]), ['action' => 'exp_reject', 'id' => $id]);
                    log_audit($pdo, 'submit', 'expenses', $id, null, ['expense_number' => $num, 'total' => $total]);
                    erp_out(['status' => 'success', 'id' => $id, 'pending_approval' => true, 'message' => "{$num} sent for approval ({$apr})."]);
                }
                $pdo->beginTransaction();
                $e = erp_row($pdo, "SELECT * FROM expenses WHERE id = ? FOR UPDATE", [$id]);
                $bankName = $e['bank_account_id'] ? erp_val($pdo, "SELECT name FROM bank_accounts WHERE id = ?", [$e['bank_account_id']]) : null;
                $txn = exp_txn_sync($pdo, $e, $bankName ?: null);
                $pdo->prepare("UPDATE expenses SET status = 'posted', accounts_transaction_id = ?, approved_by = COALESCE(approved_by, ?), approved_at = COALESCE(approved_at, NOW()), posted_by = ?, posted_at = NOW() WHERE id = ?")
                    ->execute([$txn, erp_user(), erp_user(), $id]);
                apr_close($pdo, 'expense', (string)$id, 'approved', erp_input('_approval_remarks') ?: null);
                $pdo->commit();
                if (acc_start($pdo)) acc_sync($pdo, true);
                $msg = "{$num} posted" . ($paid === 'paid' ? ' (paid — also in Transactions).' : ' as UNPAID (payable). Record the payment when you pay it.');
            }
            log_audit($pdo, $action === 'exp_post' ? 'post' : 'save', 'expenses', $id, $old ?? null, ['expense_number' => $num, 'account' => $acc['name'], 'total' => $total, 'payment' => $paid]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => $msg]);

        case 'exp_reject':
            $id = (int)erp_input('id');
            $e = erp_row($pdo, "SELECT * FROM expenses WHERE id = ?", [$id]);
            if (!$e || $e['status'] !== 'submitted') erp_invalid('Only expenses waiting for approval can be rejected.');
            $pdo->prepare("UPDATE expenses SET status = 'rejected', cancel_reason = ? WHERE id = ?")->execute([erp_input('_approval_remarks') ?: erp_input('reason') ?: null, $id]);
            apr_close($pdo, 'expense', (string)$id, 'rejected', erp_input('_approval_remarks') ?: erp_input('reason') ?: null);
            log_audit($pdo, 'reject', 'expenses', $id, ['status' => 'submitted'], ['status' => 'rejected']);
            erp_out(['status' => 'success', 'message' => "{$e['expense_number']} rejected — it can be corrected and sent again."]);

        case 'exp_pay':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $e = erp_row($pdo, "SELECT * FROM expenses WHERE id = ? FOR UPDATE", [$id]);
            if (!$e || $e['status'] !== 'posted' || $e['payment_status'] !== 'unpaid') erp_invalid('Only posted, unpaid expenses can be paid.');
            $mode = in_array(erp_input('payment_mode'), ['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'], true) ? erp_input('payment_mode') : 'bank_transfer';
            $ref = trim((string)erp_input('reference_number', ''));
            if (in_array($mode, ['bank_transfer', 'upi', 'cheque'], true) && $ref === '') erp_invalid('Enter the reference / UTR / cheque number.');
            $date = erp_date(erp_input('paid_date'), true);
            if ($date < $e['expense_date']) erp_invalid('Payment date cannot be before the expense date.');
            $bank = (int)erp_input('bank_account_id', 0) ?: null;
            $pdo->prepare("UPDATE expenses SET payment_status = 'paid', paid_date = ?, payment_mode = ?, reference_number = ?, bank_account_id = ? WHERE id = ?")->execute([$date, $mode, $ref ?: null, $bank, $id]);
            $e = erp_row($pdo, "SELECT * FROM expenses WHERE id = ?", [$id]);
            exp_txn_sync($pdo, $e, $bank ? erp_val($pdo, "SELECT name FROM bank_accounts WHERE id = ?", [$bank]) : null);
            $pdo->commit();
            if (acc_start($pdo)) acc_sync($pdo, true);
            log_audit($pdo, 'payment', 'expenses', $id, ['payment_status' => 'unpaid'], ['payment_status' => 'paid', 'date' => $date, 'mode' => $mode, 'reference' => $ref]);
            erp_out(['status' => 'success', 'message' => "{$e['expense_number']} marked paid."]);

        case 'exp_cancel':
            $id = (int)erp_input('id');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason.');
            $pdo->beginTransaction();
            $e = erp_row($pdo, "SELECT * FROM expenses WHERE id = ? FOR UPDATE", [$id]);
            if (!$e || in_array($e['status'], ['cancelled'], true)) erp_invalid('Already cancelled.');
            $pdo->prepare("UPDATE expenses SET status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$reason, $id]);
            erp_cancel_cash_entry($pdo, $e['accounts_transaction_id'] ? (int)$e['accounts_transaction_id'] : null);
            if ($e['status'] === 'submitted') apr_close($pdo, 'expense', (string)$id, 'cancelled', $reason);
            $pdo->commit();
            if (acc_start($pdo)) acc_sync($pdo, true);
            log_audit($pdo, 'cancel', 'expenses', $id, ['status' => $e['status']], ['status' => 'cancelled', 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$e['expense_number']} cancelled" . ($e['status'] === 'posted' ? ' — the Transactions entry is cancelled and the journal reversed.' : '.')]);

        case 'cashbook_expenses':
            // expense rows entered directly in the existing Transactions page (read-only here)
            $w = ["t.type = 'expense'", "COALESCE(t.reference_type,'') <> 'expense'"]; $p = [];
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 't.date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 't.date <= ?'; $p[] = $d; }
            $rows = erp_rows($pdo, "SELECT t.id, t.transaction_id, t.date, t.category, t.party_name, t.amount, t.payment_mode, t.description, t.status FROM accounts_transactions t WHERE " . implode(' AND ', $w) . " ORDER BY t.date DESC, t.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'accounting');
}

/** Bucket totals + per-party summary for ageing reports. */
function acc_aging_summary(array $rows, string $partyKey): array {
    $b = ['current' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90p' => 0];
    $party = [];
    foreach ($rows as $r) {
        $b[$r['bucket']] += $r['balance'];
        $k = $r[$partyKey];
        if (!isset($party[$k])) $party[$k] = ['party' => $k] + array_fill_keys(array_keys($b), 0) + ['total' => 0];
        $party[$k][$r['bucket']] += $r['balance']; $party[$k]['total'] += $r['balance'];
    }
    foreach ($b as $k => $v) $b[$k] = erp_m($v);
    foreach ($party as &$p) foreach ($p as $k => $v) if ($k !== 'party') $p[$k] = erp_m($v);
    unset($p);
    usort($party, function ($x, $y) { return $y['total'] <=> $x['total']; });
    return ['buckets' => $b, 'total' => erp_m(array_sum($b)), 'by_party' => array_values($party)];
}
