<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit;
}

try {
    // Get user email from session or users table
    $user_email = $_SESSION['email'] ?? null;

    if (!$user_email) {
        // Fetch user email from users table
        $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user['email'] ?? null;
    }

    if (!$user_email) {
        echo json_encode(['status' => 'error', 'message' => 'User email not found. Please contact support.']);
        exit;
    }

    // Fetch all orders for the user (by email)
    // Start with basic columns that should definitely exist
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
        o.payment_status,
        o.created_at,
        o.order_status
    FROM orders o
    WHERE o.email = ?
    ORDER BY o.id DESC
";


    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_email]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo json_encode(['status' => 'success', 'orders' => []]);
        exit;
    }

    // Fetch order items for each order and add default values
    foreach ($orders as &$order) {
        // Set default order_status if not in DB
        if (!isset($order['order_status'])) {
            $order['order_status'] = 'ordered';
        }

        // Set default created_at if not in DB
        if (!isset($order['created_at'])) {
            $order['created_at'] = date('Y-m-d H:i:s');
        }

        // Fetch order items
        try {
            $itemStmt = $pdo->prepare("
                SELECT 
                    oi.id,
                    oi.product_id,
                    oi.quantity,
                    oi.price,
                    p.product_name,
                    p.image,
                    p.category
                FROM order_items oi
                JOIN product_details p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemStmt->execute([$order['id']]);
            $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching order items for order {$order['id']}: " . $e->getMessage());
            $order['items'] = []; // Set empty array if items can't be fetched
        }
    }

    echo json_encode(['status' => 'success', 'orders' => $orders]);
} catch (PDOException $e) {
    error_log("Error fetching user orders: " . $e->getMessage());
    // Return detailed error for debugging
    $errorMessage = 'Failed to fetch orders: ' . $e->getMessage();
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'debug_info' => [
            'error' => $e->getMessage(),
            'user_id' => $user_id,
            'user_email' => $user_email ?? 'not found',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Exception $e) {
    error_log("Error fetching user orders: " . $e->getMessage());
    $errorMessage = 'Failed to fetch orders: ' . $e->getMessage();
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'debug_info' => $e->getMessage()
    ]);
}
