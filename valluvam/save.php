<?php
header('Content-Type: application/json');
require_once __DIR__ . '/assets/db_query/config.php'; // your PDO connection

try {
    $id = $_POST['id'] ?? '';
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    $dis_price = $_POST['dis_price'];
    $category = $_POST['category'];
    $quantity = $_POST['quantity'];
    $rating = $_POST['rating'];
    $description = $_POST['description'];
    $benefits = $_POST['benefits'];
    $imagePath = '';

    // Handle image upload if present
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $imagePath = $uploadDir . time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
    }

    if ($id) {
        // UPDATE
        if ($imagePath) {
            $sql = "UPDATE products SET product_name=?, price=?, dis_price=?, category=?, quantity=?, rating=?, description=?, benefits=?, image=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$product_name, $price, $dis_price, $category, $quantity, $rating, $description, $benefits, $imagePath, $id]);
        } else {
            $sql = "UPDATE products SET product_name=?, price=?, dis_price=?, category=?, quantity=?, rating=?, description=?, benefits=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$product_name, $price, $dis_price, $category, $quantity, $rating, $description, $benefits, $id]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
    } else {
        // INSERT
        $sql = "INSERT INTO products (product_name, price, dis_price, category, quantity, rating, description, benefits, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_name, $price, $dis_price, $category, $quantity, $rating, $description, $benefits, $imagePath]);
        echo json_encode(['status' => 'success', 'message' => 'Product added successfully']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
