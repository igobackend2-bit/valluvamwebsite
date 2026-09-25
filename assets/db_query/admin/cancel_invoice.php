<?php
// Cancels an invoice (status=cancelled). Never deletes.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();
require_permission($pdo, 'invoices.cancel');

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        exit;
    }
    if ($invoice['status'] === 'cancelled') {
        echo json_encode(['status' => 'error', 'message' => 'This invoice is already cancelled']);
        exit;
    }
    if ($invoice['status'] === 'paid') {
        echo json_encode(['status' => 'error', 'message' => 'A fully paid invoice cannot be cancelled']);
        exit;
    }

    $upd = $pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?");
    $upd->execute([$id]);

    log_audit($pdo, 'cancel', 'invoices', $id, ['status' => $invoice['status']], ['status' => 'cancelled']);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error cancelling invoice: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel invoice']);
}
