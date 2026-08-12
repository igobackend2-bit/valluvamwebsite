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

