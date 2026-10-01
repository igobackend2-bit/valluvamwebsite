<?php
// Records a repayment against an outstanding credit sale: increments
// amount_paid, flips status, and adds a row to credit_sale_payments so the
// full repayment history is visible. Same shape as record_invoice_payment.php.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/credit_sale_helper.php';

require_admin_session();
// FIX (1 Oct 2026): the Accounts Team records customer payments (money in) without being able to create sales documents.
require_once __DIR__ . '/../../../admin/includes/role_access.php';
$accountsDash = role_access_key((string)($_SESSION['admin_role_name'] ?? '')) === 'accounts' || in_array('accounts', user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)), true);
if (!$accountsDash) require_permission($pdo, 'credit_sales.create');

ensure_credit_sale_tables($pdo);

$id = (int)($_POST['id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$payment_mode = trim($_POST['payment_mode'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$id || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'id and a positive amount are required']);
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM credit_sales WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Credit sale not found']);
        exit;
    }
    if ($sale['status'] === 'paid') {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'This credit sale is already fully paid']);
        exit;
    }

    $newPaid = (float)$sale['amount_paid'] + $amount;
    $newStatus = $newPaid >= (float)$sale['grand_total'] ? 'paid' : 'partially_paid';
    if ($newPaid > (float)$sale['grand_total']) {
        $newPaid = (float)$sale['grand_total']; // never overshoot the total
    }

    $pdo->prepare("UPDATE credit_sales SET amount_paid = ?, status = ? WHERE id = ?")->execute([$newPaid, $newStatus, $id]);

    $pdo->prepare("INSERT INTO credit_sale_payments (credit_sale_id, amount, payment_mode, notes, created_by) VALUES (?,?,?,?,?)")
        ->execute([$id, $amount, $payment_mode ?: null, $notes ?: null, $adminUsername]);

    $pdo->commit();

    log_audit($pdo, 'update', 'credit_sales', $id, ['amount_paid' => $sale['amount_paid'], 'status' => $sale['status']], ['amount_paid' => $newPaid, 'status' => $newStatus]);

    // ── Integration point: Accounts (best-effort, same pattern as invoices) ──
    try {
        // FIX (30 Sep 2026): same missing transaction_id/date/category as invoices — payments never reached Transactions.
        $tx = $pdo->prepare("INSERT INTO accounts_transactions (transaction_id, date, type, category, reference_type, reference_number, party_name, amount, payment_mode, status, created_by, created_at)
                              VALUES (?, CURDATE(), 'payment_received', 'Credit Sale Payment', 'credit_sale', ?, ?, ?, ?, 'completed', ?, NOW())");
        $tx->execute([next_document_number($pdo, 'accounts_txn', 'TXN'), $sale['credit_number'], $sale['customer_name'] ?? null, $amount, in_array($payment_mode, ['cash', 'upi', 'bank_transfer', 'card', 'cheque'], true) ? $payment_mode : 'other', $adminUsername]);
    } catch (PDOException $e) {
        error_log('accounts_transactions integration skipped (table not ready): ' . $e->getMessage());
    }

    // FIX (1 Oct 2026): the second 'status' key overwrote 'success', so the page said "Could not record payment" after saving it (risk of paying in twice)
    echo json_encode(['status' => 'success', 'amount_paid' => $newPaid, 'sale_status' => $newStatus, 'balance_due' => round((float)$sale['grand_total'] - $newPaid, 2)]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error recording credit sale payment: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to record payment: ' . $e->getMessage()]);
}
