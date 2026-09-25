<?php
// Lightweight product list for the Sales Flow line-item selects (Sales
// Order, Delivery Challan, Manual Sale, Invoice). Read-only access to
// product_details (explicitly allowed for FK lookups).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

try {
    $stmt = $pdo->query("SELECT id, product_name, price, dis_price, stock FROM product_details ORDER BY product_name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'products' => $products]);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'products' => []]);
}
