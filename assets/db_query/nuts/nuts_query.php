<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file

$action = $_GET['action'];
if ($action === 'nuts_products') {

    try {
        $stmt = $pdo->prepare("SELECT * FROM product_details WHERE category = 'nuts' ORDER BY id DESC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $products
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'DB Error: ' . $e->getMessage()
        ]);
    }
}   elseif ($action === 'product_search_nuts') {

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
                echo json_encode(['status' => 'success', 'data' => $results]);
            } else {
                echo json_encode(['status' => 'not_found']);
            }
        } else {
            echo json_encode(['status' => 'empty']);
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}