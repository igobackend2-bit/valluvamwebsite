<?php
// ONE-TIME USE SCRIPT — adds the missing "Rice" row to product_category
// so it shows up in the Add Product category dropdown.
// Delete this file from the server immediately after running it once.

require_once __DIR__ . '/assets/db_query/config.php';

try {
    $check = $pdo->prepare("SELECT id FROM product_category WHERE category_name = ?");
    $check->execute(['Rice']);

    if ($check->fetch()) {
        echo "Rice already exists in product_category. Nothing to do. You can delete this file now.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO product_category (category_name, thumbnali, link) VALUES (?, ?, ?)");
        $stmt->execute(['Rice', 'rice.jpg', 'rice.php']);
        echo "Success: 'Rice' was added to product_category. Refresh the Add Product page and it will appear in the dropdown. You can delete this file now.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
