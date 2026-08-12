<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$product_id = $_POST['product_id'] ?? 0;

if (!$product_id) {
    echo json_encode(['status' => 'error', 'message' => 'Product ID is required']);
    exit;
}

try {
    // Get product image path before deleting
    $stmt = $pdo->prepare("SELECT image FROM product_details WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }
    
    // Delete product
    $deleteStmt = $pdo->prepare("DELETE FROM product_details WHERE id = ?");
    $deleteStmt->execute([$product_id]);
    
    if ($deleteStmt->rowCount() > 0) {
        // Optionally delete the image file
        if (!empty($product['image'])) {
            $imagePath = __DIR__ . '/../../uploads/' . $product['image'];
            if (file_exists($imagePath)) {
                @unlink($imagePath); // Suppress errors if file doesn't exist
            }
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete product']);
    }
} catch (PDOException $e) {
    error_log("Error deleting product: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete product: ' . $e->getMessage()]);
}

