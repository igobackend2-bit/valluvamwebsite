<?php
// Records a repayment against an outstanding credit sale: increments
// amount_paid, flips status, and adds a row to credit_sale_payments so the
// full repayment history is visible. Same shape as record_invoice_payment.php.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/credit_sale_helper.php';

require_admin_session();
require_permission($pdo, 'credit_sales.create');

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
        $tx = $pdo->prepare("INSERT INTO accounts_transactions (type, reference_type, reference_number, amount, payment_mode, created_by, created_at)
                              VALUES ('payment_received', 'credit_sale', ?, ?, ?, ?, NOW())");
        $tx->execute([$sale['credit_number'], $amount, $payment_mode ?: null, $adminUsername]);
    } catch (PDOException $e) {
        error_log('accounts_transactions integration skipped (table not ready): ' . $e->getMessage());
    }

    echo json_encode(['status' => 'success', 'amount_paid' => $newPaid, 'status' => $newStatus, 'balance_due' => round((float)$sale['grand_total'] - $newPaid, 2)]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error recording credit sale payment: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to record payment: ' . $e->getMessage()]);
}
