<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Same auth style as the existing get_orders.php / orders.php (pre-ERP code).
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            oi.id,
            oi.product_id,
            oi.quantity,
            oi.price,
            (oi.quantity * oi.price) AS line_total,
            p.product_name,
            p.image,
            p.category
        FROM order_items oi
        LEFT JOIN product_details p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'items' => $items]);
} catch (PDOException $e) {
    error_log("Error fetching order items for order {$order_id}: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch order items']);
}
