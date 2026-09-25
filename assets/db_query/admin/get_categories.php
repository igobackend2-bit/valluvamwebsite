<?php
// List all product categories (same table the New Product form's Category
// dropdown and the homepage category slider both read from).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

try {
    $stmt = $pdo->query("SELECT id, category_name, thumbnali, link FROM product_category ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'categories' => $categories]);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'categories' => []]);
}
