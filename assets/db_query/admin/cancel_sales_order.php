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

    $upd = $pdo->prepare("UPDATE sales_orders SET status = 'cancelled', updated_by = ? WHERE id = ?");
    $upd->execute([$_SESSION['admin_username'] ?? 'Admin', $id]);

    log_audit($pdo, 'cancel', 'sales_orders', $id, ['status' => $order['status']], ['status' => 'cancelled']);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error cancelling sales order: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel sales order']);
}
