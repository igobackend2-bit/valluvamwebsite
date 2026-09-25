<?php
// Records a payment against an invoice: increments amount_paid and flips
// status to partially_paid or paid. Transactional to avoid a race between
// two staff recording payment on the same invoice at once.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();
require_permission($pdo, 'invoices.create');

$id = (int)($_POST['id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$payment_mode = trim($_POST['payment_mode'] ?? '');

if (!$id || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'id and a positive amount are required']);
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        exit;
    }
    if ($invoice['status'] === 'cancelled') {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Cannot record payment on a cancelled invoice']);
        exit;
    }

    $newPaid = (float)$invoice['amount_paid'] + $amount;
    $newStatus = $newPaid >= (float)$invoice['grand_total'] ? 'paid' : 'partially_paid';
    if ($newPaid > (float)$invoice['grand_total']) {
        $newPaid = (float)$invoice['grand_total']; // never overshoot the total
    }

    $upd = $pdo->prepare("UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?");
    $upd->execute([$newPaid, $newStatus, $id]);

    $pdo->commit();

    log_audit($pdo, 'update', 'invoices', $id, ['amount_paid' => $invoice['amount_paid'], 'status' => $invoice['status']], ['amount_paid' => $newPaid, 'status' => $newStatus]);

    // ── Integration point: Accounts ────────────────────────────────────
    // accounts_transactions is owned by another agent and may not exist
    // yet. Attempt the insert outside the invoice's own transaction (which
    // has already committed) so a missing table never affects the payment
    // that was just recorded.
    try {
        $tx = $pdo->prepare("INSERT INTO accounts_transactions (type, reference_type, reference_number, amount, payment_mode, created_by, created_at)
                              VALUES ('payment_received', 'invoice', ?, ?, ?, ?, NOW())");
        $tx->execute([$invoice['invoice_number'], $amount, $payment_mode ?: null, $adminUsername]);
    } catch (PDOException $e) {
        error_log('accounts_transactions integration skipped (table not ready): ' . $e->getMessage());
    }

    echo json_encode(['status' => 'success', 'amount_paid' => $newPaid, 'invoice_status' => $newStatus]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error recording invoice payment: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to record payment: ' . $e->getMessage()]);
}
