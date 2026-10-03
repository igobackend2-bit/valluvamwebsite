<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$order_id = $_POST['order_id'] ?? 0;
$status = $_POST['status'] ?? '';

if (!$order_id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID and status are required']);
    exit;
}

// FIX (3 Oct 2026): who may change website orders, cancelling (stock goes back), COD cash collected, unpaid online orders
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';
$ordAccounts = role_access_key((string)($_SESSION['admin_role_name'] ?? '')) === 'accounts' || in_array('accounts', user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)), true);
$ordUser = $_SESSION['admin_username'] ?? 'Admin';
$ordStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$ordStmt->execute([(int)$order_id]);
$ord = $ordStmt->fetch(PDO::FETCH_ASSOC);
if (!$ord) { echo json_encode(['status' => 'error', 'message' => 'Order not found']); exit; }
$ordCur = $ord['order_status'] ?: 'ordered';

// COD: cash collected on delivery → payment paid (Accounts posts it as "COD collected")
if ($status === 'cod_paid') {
    if (!$ordAccounts) require_permission($pdo, 'sales.fulfilment');
    if (strtoupper((string)$ord['payment_method']) !== 'COD') { echo json_encode(['status' => 'error', 'message' => 'Only cash-on-delivery orders are marked paid here.']); exit; }
    if ($ord['payment_status'] === 'paid') { echo json_encode(['status' => 'error', 'message' => 'Already marked paid.']); exit; }
    if ($ordCur === 'cancelled') { echo json_encode(['status' => 'error', 'message' => 'This order is cancelled.']); exit; }
    $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$ord['id']]);
    log_audit($pdo, 'update', 'orders', (int)$ord['id'], ['payment_status' => $ord['payment_status']], ['payment_status' => 'paid', 'note' => 'COD cash collected']);
    echo json_encode(['status' => 'success', 'message' => "{$ord['receipt']}: COD cash collected — marked paid."]);
    exit;
}

require_permission($pdo, 'sales.fulfilment');
if ($ordCur === 'cancelled') { echo json_encode(['status' => 'error', 'message' => 'This order is cancelled — its status cannot be changed.']); exit; }
$ordStockOut = strtoupper((string)$ord['payment_method']) === 'COD' || $ord['payment_status'] === 'paid';   // stock is taken when a COD order is placed / an online order is paid
if (!$ordStockOut && $status !== 'cancelled') { echo json_encode(['status' => 'error', 'message' => 'Online payment not received for this order — do not pack or send it.']); exit; }

if ($status === 'cancelled') {
    if (in_array($ordCur, ['couriered', 'delivered'], true)) { echo json_encode(['status' => 'error', 'message' => 'Already sent to the customer — use Sales Returns when the goods come back.']); exit; }
    try {
        $pdo->beginTransaction();
        $back = [];
        // what was taken out for this order (stock ledger), else the order lines when stock was taken before the ledger existed
        $mv = $pdo->prepare("SELECT product_id, SUM(-quantity) q FROM stock_movements WHERE movement_type = 'stock_out' AND reference_type = 'website_order' AND reference_number = ? GROUP BY product_id");
        $mv->execute([$ord['receipt']]);
        foreach ($mv->fetchAll(PDO::FETCH_ASSOC) as $m) if ((int)$m['q'] > 0) $back[(int)$m['product_id']] = (int)$m['q'];
        if (!$back && $ordStockOut) {
            $it = $pdo->prepare("SELECT product_id, SUM(quantity) q FROM order_items WHERE order_id = ? AND product_id IS NOT NULL GROUP BY product_id");
            $it->execute([$ord['id']]);
            foreach ($it->fetchAll(PDO::FETCH_ASSOC) as $m) if ((int)$m['q'] > 0) $back[(int)$m['product_id']] = (int)$m['q'];
        }
        $lock = $pdo->prepare("SELECT stock FROM product_details WHERE id = ? FOR UPDATE");
        foreach ($back as $pid => $q) {
            $lock->execute([$pid]);
            $cur = $lock->fetchColumn();
            if ($cur === false) continue;
            $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?")->execute([(int)$cur + $q, $pid]);
            $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id, quantity, previous_stock, new_stock, reference_type, reference_number, reason, created_by, created_at)
                           VALUES ('return', ?, ?, 1, ?, ?, ?, 'website_order_cancel', ?, ?, ?, NOW())")
                ->execute([$pid, 'PRD-' . $pid, $q, (int)$cur, (int)$cur + $q, $ord['receipt'], 'Website order ' . $ord['receipt'] . ' cancelled — back to stock', $ordUser]);
        }
        $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?")->execute([$ord['id']]);
        $pdo->commit();
        log_audit($pdo, 'cancel', 'orders', (int)$ord['id'], ['order_status' => $ordCur], ['order_status' => 'cancelled', 'stock_back' => $back]);
        $n = array_sum($back);
        echo json_encode(['status' => 'success', 'message' => "{$ord['receipt']} cancelled." . ($n ? " {$n} item(s) put back in stock." : '') . ($ord['payment_status'] === 'paid' && strtoupper((string)$ord['payment_method']) !== 'COD' ? ' Refund the online payment in Razorpay.' : '')]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Error cancelling order: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to cancel the order.']);
    }
    exit;
}

$valid_statuses = ['ordered', 'packed', 'couriered', 'delivered'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

try {
    // Check if order_status column exists, if not we might need to add it
    // For now, try to update it
    $sql = "UPDATE orders SET order_status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $order_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Order status updated successfully']);
    } else {
        // If update didn't work, the column might not exist
        // Try to add the column first (if it doesn't exist)
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN order_status VARCHAR(50) DEFAULT 'ordered'");
            // Try update again
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $order_id]);
            echo json_encode(['status' => 'success', 'message' => 'Order status updated successfully']);
        } catch (PDOException $e) {
            // Column might already exist or other error
            echo json_encode(['status' => 'error', 'message' => 'Failed to update order status. Please check if order_status column exists in orders table.']);
        }
    }
} catch (PDOException $e) {
    error_log("Error updating order status: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update order status: ' . $e->getMessage()]);
}

