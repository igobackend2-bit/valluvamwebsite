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
    // Check if stock column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM product_details LIKE 'stock'");
    $hasStock = $checkColumn->rowCount() > 0;
    
    $search = $_GET['search'] ?? '';
    
    // Build SELECT query based on available columns
    $sql = "SELECT id, product_name, category, price, dis_price, image";
    
    if ($hasStock) {
        $sql .= ", stock";
    } else {
        $sql .= ", NULL as stock";
    }
    
    $sql .= " FROM product_details WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (product_name LIKE ? OR category LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all products have stock value set
    foreach ($products as &$product) {
        if (!isset($product['stock']) || $product['stock'] === null) {
            $product['stock'] = 'N/A';
        }
    }
    
    echo json_encode(['status' => 'success', 'products' => $products]);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch products: ' . $e->getMessage()]);
}

