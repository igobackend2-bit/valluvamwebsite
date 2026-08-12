<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file
$action = $_GET['action'] ?? '';


if ($action == 'category_slider') {
    try {
        $stmt = $pdo->prepare("SELECT category_name,thumbnali, link FROM product_category");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $categories]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action == 'product_catelog') {
    try {
        $stmt = $pdo->query("
            SELECT id, product_name, price, dis_price, category, image, quantity
            FROM product_details
            WHERE image IS NOT NULL
              AND TRIM(image) <> ''
            ORDER BY id DESC
            LIMIT 8
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $products
        ]);
        exit;
    } catch (PDOException $d) {
        echo json_encode([
            'status' => 'error',
            'message' => $d->getMessage()
        ]);
    }
} elseif ($action === 'product_search') {
    $query = trim($_GET['query'] ?? '');

    try {
        if ($query !== "") {
            $sql = "SELECT id, product_name, price, dis_price, quantity, image 
                    FROM product_details 
                    WHERE product_name LIKE :query 
                       OR description LIKE :query 
                       OR category LIKE :query";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['query' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($results) {
                echo json_encode(['status' => 'success','data' => $results]);
            } else {
                echo json_encode(['status' => 'not_found','message' => 'No products found']);
            }
        } else {
            echo json_encode(['status' => 'empty','message' => 'Type something to search...']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error','message' => $e->getMessage()]);
    }
    exit;
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action'
    ]);
    exit;
}

