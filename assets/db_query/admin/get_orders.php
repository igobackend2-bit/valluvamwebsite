<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // First, check if order_status column exists and create it if it doesn't
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN order_status VARCHAR(50) DEFAULT 'ordered'");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            // Some other error, but we'll continue anyway
        }
    }
    
    $status = $_GET['status'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    
    // Build query - check if order_status column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
    $hasOrderStatus = $checkColumn->rowCount() > 0;
    
    $sql = "
        SELECT 
            o.id,
            o.receipt,
            o.first_name,
            o.last_name,
            o.email,
            o.phone,
            o.state,
            o.city,
            o.street_address,
            o.apartment,
            o.postcode,
            o.amount,
            o.payment_method,
            o.payment_status";
    
    if ($hasOrderStatus) {
        $sql .= ", COALESCE(o.order_status, 'ordered') as order_status";
    } else {
        $sql .= ", 'ordered' as order_status";
    }
    
    $sql .= ", COALESCE(o.created_at, NOW()) as created_at
        FROM orders o
    ";
    
    $params = [];
    if (!empty($status) && $hasOrderStatus) {
        $sql .= " WHERE COALESCE(o.order_status, 'ordered') = ?";
        $params[] = $status;
    } elseif (!empty($status)) {
        // If status filter is requested but column doesn't exist, return empty or all
        if ($status === 'ordered') {
            // Return all orders as 'ordered' if filtering for ordered
        } else {
            // Return empty for other statuses
            echo json_encode(['status' => 'success', 'orders' => []]);
            exit;
        }
    }
    
    $sql .= " ORDER BY o.id DESC";
    
    // LIMIT cannot use prepared statement parameters, so we need to validate and use directly
    if ($limit > 0) {
        $limit = (int)$limit; // Ensure it's an integer
        $sql .= " LIMIT " . $limit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all orders have order_status set
    foreach ($orders as &$order) {
        if (!isset($order['order_status']) || empty($order['order_status'])) {
            $order['order_status'] = 'ordered';
        }
    }
    
    echo json_encode(['status' => 'success', 'orders' => $orders]);
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch orders: ' . $e->getMessage()]);
}

