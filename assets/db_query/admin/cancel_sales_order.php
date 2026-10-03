<?php
// Cancels a sales order (status=cancelled). Never deletes.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();
require_permission($pdo, 'sales_orders.cancel');

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Sales order not found']);
        exit;
    }
    if ($order['status'] === 'cancelled') {
        echo json_encode(['status' => 'error', 'message' => 'This sales order is already cancelled']);
        exit;
    }

    // FIX (3 Oct 2026): goods already sent (dispatched DC) or money already received → cannot cancel here (use Sales Returns / credit note)
    $dcOut = $pdo->prepare("SELECT dc_number FROM delivery_challans WHERE sales_order_id = ? AND delivery_status IN ('dispatched','in_transit','delivered') LIMIT 1");
    $dcOut->execute([$id]);
    if ($dcNo = $dcOut->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => "Goods already dispatched on {$dcNo} — use Sales Returns instead of cancelling."]);
        exit;
    }
    $invPaid = $pdo->prepare("SELECT invoice_number FROM invoices WHERE sales_order_id = ? AND status != 'cancelled' AND (status = 'paid' OR amount_paid > 0) LIMIT 1");
    $invPaid->execute([$id]);
    if ($invNo = $invPaid->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => "Payment already received on {$invNo} — refund it with a credit note instead of cancelling."]);
        exit;
    }

    $upd = $pdo->prepare("UPDATE sales_orders SET status = 'cancelled', updated_by = ? WHERE id = ?");
    $upd->execute([$_SESSION['admin_username'] ?? 'Admin', $id]);

    // FIX (3 Oct 2026): the invoice made automatically for this order is cancelled with it (it was left "issued" before); draft DCs too
    $cInv = $pdo->prepare("SELECT id, invoice_number, status FROM invoices WHERE sales_order_id = ? AND status != 'cancelled'");
    $cInv->execute([$id]);
    $cancelledInv = [];
    foreach ($cInv->fetchAll(PDO::FETCH_ASSOC) as $iv) {
        $pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([$iv['id']]);
        log_audit($pdo, 'cancel', 'invoices', (int)$iv['id'], ['status' => $iv['status']], ['status' => 'cancelled', 'reason' => 'Sales order ' . $order['so_number'] . ' cancelled']);
        $cancelledInv[] = $iv['invoice_number'];
    }
    $pdo->prepare("UPDATE delivery_challans SET delivery_status = 'cancelled' WHERE sales_order_id = ? AND delivery_status IN ('draft','ready','loaded')")->execute([$id]);

    log_audit($pdo, 'cancel', 'sales_orders', $id, ['status' => $order['status']], ['status' => 'cancelled']);

    echo json_encode(['status' => 'success', 'cancelled_invoices' => $cancelledInv]);
} catch (PDOException $e) {
    error_log("Error cancelling sales order: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel sales order']);
}
