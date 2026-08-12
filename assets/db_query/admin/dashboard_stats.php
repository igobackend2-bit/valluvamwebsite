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
    
    // Check if order_status column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
    $hasOrderStatus = $checkColumn->rowCount() > 0;
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Pending orders (ordered, packed, couriered)
    if ($hasOrderStatus) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE COALESCE(order_status, 'ordered') IN ('ordered', 'packed', 'couriered')");
    } else {
        // If column doesn't exist, all orders are considered pending
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    }
    $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Delivered orders
    if ($hasOrderStatus) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE COALESCE(order_status, 'ordered') = 'delivered'");
    } else {
        // If column doesn't exist, no delivered orders yet
        $stmt = $pdo->query("SELECT 0 as total");
    }
    $delivered_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM product_details");
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_orders' => (int)$total_orders,
            'pending_orders' => (int)$pending_orders,
            'delivered_orders' => (int)$delivered_orders,
            'total_products' => (int)$total_products
        ]
    ]);
} catch (PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch statistics']);
}

