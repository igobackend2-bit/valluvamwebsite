<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file


$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'No product ID']);
    exit;
}

// Fetch main product
$stmt = $pdo->prepare("SELECT id, product_name, price, dis_price, description, category, quantity, image, benefits, rating 
                       FROM product_details 
                       WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit;
}

// Fetch similar products (same category, exclude current product)
$stmt = $pdo->prepare("SELECT id, product_name, price, dis_price, image 
                       FROM product_details 
                       WHERE category = ? AND id != ? 
                       ORDER BY timestamp DESC LIMIT 5");
$stmt->execute([$product['category'], $id]);
$similar = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => [
        'product' => $product,
        'similar' => $similar
    ]
]);
