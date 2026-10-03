<?php
// FIX (3 Oct 2026): money received later for a manual sale entered as pending / credit / partly paid.
// Same pattern as record_credit_sale_payment.php: the Accounts Team (or staff who can enter manual sales)
// records it; it goes to Transactions (payment_received · manual_sale) and the sale's payment status follows.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();
require_once __DIR__ . '/../../../admin/includes/role_access.php';
$accountsDash = role_access_key((string)($_SESSION['admin_role_name'] ?? '')) === 'accounts' || in_array('accounts', user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)), true);
if (!$accountsDash) require_permission($pdo, 'manual_sales.create');

$id = (int)($_POST['id'] ?? 0);
$amount = round((float)($_POST['amount'] ?? 0), 2);
$mode = trim($_POST['payment_mode'] ?? 'cash');
$ref = mb_substr(trim($_POST['reference'] ?? ''), 0, 100);
if (!in_array($mode, ['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'], true)) $mode = 'other';
if (!$id || $amount <= 0) { echo json_encode(['status' => 'error', 'message' => 'Enter the amount received.']); exit; }
if ($mode !== 'cash' && $ref === '') { echo json_encode(['status' => 'error', 'message' => 'Enter the UTR / reference number.']); exit; }
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT * FROM manual_sales WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $sale = $st->fetch(PDO::FETCH_ASSOC);
    if (!$sale) { $pdo->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Manual sale not found']); exit; }
    if ((int)$sale['stock_deducted'] !== 1) { $pdo->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Confirm the sale (deduct stock) first.']); exit; }
    if ($sale['payment_status'] === 'paid') { $pdo->rollBack(); echo json_encode(['status' => 'error', 'message' => 'This sale is already fully paid.']); exit; }
    $rc = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM accounts_transactions WHERE type = 'payment_received' AND status = 'completed' AND reference_type = 'manual_sale' AND reference_number = ?");
    $rc->execute([$sale['sale_number']]);
    $received = (float)$rc->fetchColumn();
    $due = round((float)$sale['grand_total'] - $received, 2);
    if ($amount > $due + 0.005) { $pdo->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Only ₹' . number_format($due, 2) . ' is due on ' . $sale['sale_number'] . '.']); exit; }

    $pdo->prepare("INSERT INTO accounts_transactions (transaction_id, date, type, category, reference_type, reference_number, party_name, amount, payment_mode, description, status, created_by, created_at)
                   VALUES (?, CURDATE(), 'payment_received', 'Manual Sale Payment', 'manual_sale', ?, ?, ?, ?, ?, 'completed', ?, NOW())")
        ->execute([next_document_number($pdo, 'accounts_txn', 'TXN'), $sale['sale_number'], $sale['customer_name'] ?: null, $amount, $mode,
                   'Payment for manual sale ' . $sale['sale_number'] . ($ref ? ' · ' . $ref : ''), $adminUsername]);
    $newStatus = $received + $amount + 0.005 >= (float)$sale['grand_total'] ? 'paid' : 'partially_paid';
    $pdo->prepare("UPDATE manual_sales SET payment_status = ? WHERE id = ?")->execute([$newStatus, $id]);
    $pdo->commit();

    log_audit($pdo, 'update', 'manual_sales', $id, ['payment_status' => $sale['payment_status'], 'received' => $received], ['payment_status' => $newStatus, 'received' => $received + $amount, 'ref' => $ref]);
    echo json_encode(['status' => 'success', 'payment_status' => $newStatus, 'balance_due' => round($due - $amount, 2),
                      'message' => '₹' . number_format($amount, 2) . ' received for ' . $sale['sale_number'] . ($newStatus === 'paid' ? ' — fully paid.' : ' — ₹' . number_format($due - $amount, 2) . ' still due.')]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error recording manual sale payment: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to record payment.']);
}
