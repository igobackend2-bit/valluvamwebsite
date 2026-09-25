<?php
// Cancels a transaction (status -> cancelled). Does not delete the row —
// keeps the audit trail intact.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'accounts.create');

$id = (int)($_POST['id'] ?? 0);
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM accounts_transactions WHERE id = ?");
    $stmt->execute([$id]);
    $txn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$txn) {
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    if ($txn['status'] === 'cancelled') {
        echo json_encode(['status' => 'error', 'message' => 'Transaction is already cancelled']);
        exit;
    }

    $update = $pdo->prepare("UPDATE accounts_transactions SET status = 'cancelled', updated_by = ? WHERE id = ?");
    $update->execute([$adminUsername, $id]);

    log_audit($pdo, 'cancel', 'accounts_transactions', $id, ['status' => $txn['status']], ['status' => 'cancelled']);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error cancelling accounts transaction: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel transaction: ' . $e->getMessage()]);
}
