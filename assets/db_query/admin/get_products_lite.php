<?php
// Lightweight product picker used by Stock In / Stock Out line-item forms.
// Read-only access to product_details (allowed: FK lookups are read-only).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

try {
    // Homepage selection needs only the existing product ID and name. Do not
    // require inventory-only columns here: older live databases may not have
    // the same stock column used by the ERP screens.
    $stmt = $pdo->query("SELECT id, product_name FROM product_details ORDER BY product_name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'products' => $products]);
} catch (PDOException $e) {
    error_log("Error fetching products for picker: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'products' => []]);
}
