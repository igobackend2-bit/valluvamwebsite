<?php
// Create or update an accounts transaction. Both require accounts.create
// (there is no separate accounts.edit permission per this module's brief).
// Editing an existing transaction is only allowed while status = 'pending' —
// a completed transaction must be cancelled, not silently edited.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'accounts.create');

$id = $_POST['id'] ?? '';

$date          = trim($_POST['date'] ?? '');
$type          = trim($_POST['type'] ?? '');
$category      = trim($_POST['category'] ?? '');
$reference_type   = trim($_POST['reference_type'] ?? '');
$reference_number = trim($_POST['reference_number'] ?? '');
$party_name    = trim($_POST['party_name'] ?? '');
$amount        = $_POST['amount'] ?? '';
$payment_mode  = trim($_POST['payment_mode'] ?? 'cash');
$account       = trim($_POST['account'] ?? '');
$description   = trim($_POST['description'] ?? '');
$status        = trim($_POST['status'] ?? 'completed');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

$validTypes = ['income','expense','payment_received','payment_made','refund','adjustment'];
$validModes = ['cash','upi','bank_transfer','card','cheque','other'];
$validStatuses = ['completed','pending','cancelled'];

if (!$date || !in_array($type, $validTypes, true) || !$category || $amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'date, type, category and a positive amount are required']);
    exit;
}
if (!in_array($payment_mode, $validModes, true)) $payment_mode = 'cash';
if (!in_array($status, $validStatuses, true)) $status = 'completed';

try {
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM accounts_transactions WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old) {
            echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
            exit;
        }
        if ($old['status'] !== 'pending') {
            echo json_encode(['status' => 'error', 'message' => 'Only pending transactions can be edited. Cancel it instead of editing a completed transaction.']);
            exit;
        }

        $update = $pdo->prepare("UPDATE accounts_transactions SET date=?, type=?, category=?, reference_type=?, reference_number=?,
                                  party_name=?, amount=?, payment_mode=?, account=?, description=?, status=?, updated_by=? WHERE id=?");
        $update->execute([
            $date, $type, $category, $reference_type ?: null, $reference_number ?: null,
            $party_name ?: null, $amount, $payment_mode, $account ?: null, $description ?: null, $status, $adminUsername, $id
        ]);

        log_audit($pdo, 'update', 'accounts_transactions', $id, $old, $_POST);
        echo json_encode(['status' => 'success', 'id' => (int)$id]);
    } else {
        $transactionId = next_document_number($pdo, 'accounts_txn', 'TXN');

        $insert = $pdo->prepare("INSERT INTO accounts_transactions (transaction_id, date, type, category, reference_type,
                                  reference_number, party_name, amount, payment_mode, account, description, status, created_by)
                                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insert->execute([
            $transactionId, $date, $type, $category, $reference_type ?: null, $reference_number ?: null,
            $party_name ?: null, $amount, $payment_mode, $account ?: null, $description ?: null, $status, $adminUsername
        ]);
        $newId = $pdo->lastInsertId();

        log_audit($pdo, 'create', 'accounts_transactions', $newId, null, array_merge($_POST, ['transaction_id' => $transactionId]));
        echo json_encode(['status' => 'success', 'id' => (int)$newId, 'transaction_id' => $transactionId]);
    }
} catch (PDOException $e) {
    error_log("Error saving accounts transaction: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save transaction: ' . $e->getMessage()]);
}
