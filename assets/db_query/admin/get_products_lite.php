<?php
// Lightweight product picker used by Stock In / Stock Out line-item forms.
// Read-only access to product_details (allowed: FK lookups are read-only).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

try {
    // NOTE: product_details has no real `sku` column. Synthesize a stable
    // display SKU from the id instead of adding a new column.
    $stmt = $pdo->query("SELECT id, product_name, CONCAT('PRD-', id) AS sku, stock FROM product_details ORDER BY product_name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'products' => $products]);
} catch (PDOException $e) {
    error_log("Error fetching products for picker: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'products' => []]);
}
