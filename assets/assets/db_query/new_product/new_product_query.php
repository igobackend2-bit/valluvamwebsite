<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// require('C:/xampp/htdocs/valluvam/assets/db_query/config.php');
require_once __DIR__ . '/../config.php'; // your PDO connection file


$action = $_GET['action'];

if ($action == 'add_product') {

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
        $imageFileName = ''; // Initialize variable
        
        if (!empty($_FILES['image']['name'])) {

            // Use relative path that works on both Windows and Linux
            $uploadDir = __DIR__ . '/../../uploads/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Only file name (for database storage)
            $imageFileName = time() . '_' . basename($_FILES['image']['name']);

            // Full file system path (for upload only)
            $imagePath = $uploadDir . $imageFileName;

            // Upload
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                throw new Exception("Failed to upload image");
            }
        }

        if ($id) {
            // UPDATE
            if (!empty($imageFileName)) {
                $sql = "UPDATE product_details SET product_name=?, price=?, dis_price=?, category=?, quantity=?, rating=?, description=?, benefits=?, image=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$product_name, $price, $dis_price, $category, $quantity, $rating, $description, $benefits, $imageFileName, $id]);
            } else {
                $sql = "UPDATE product_details SET product_name=?, price=?, dis_price=?, category=?, quantity=?, rating=?, description=?, benefits=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$product_name, $price, $dis_price, $category, $quantity, $rating, $description, $benefits, $id]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            // INSERT
            $sql = "INSERT INTO product_details 
            (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $product_name,
                $price,
                $dis_price,
                $category,
                $quantity,
                $rating,
                $description,
                $benefits,
                $imageFileName   // ONLY FILE NAME (e.g., "1234567890_image.jpg")
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Product added successfully'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action == 'fetch_products') {
    try {

        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM product_details WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            exit;
        }

        $stmt = $pdo->query("SELECT * FROM product_details ORDER BY id DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['data' => [], 'error' => $e->getMessage()]);
    }
} elseif ($action == 'delete_products') {
    $id = $_POST['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM product_details WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Product deleted']);
    }
} elseif ($action == 'get_category') {
    try {
        $stmt = $pdo->prepare("SELECT category_name FROM product_category");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'categories' => $categories]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
