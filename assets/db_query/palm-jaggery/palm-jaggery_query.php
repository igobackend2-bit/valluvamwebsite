<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file
require_once __DIR__ . '/../size_group.php';   // FIX (3 Oct 2026)

$action = $_GET['action'];
 if ($_GET['action'] === 'palmjaggery_products') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_details WHERE category = 'Palm Jaggery' ORDER BY id DESC");
        $stmt->execute();
        $products = vp_group_sizes($stmt->fetchAll(PDO::FETCH_ASSOC));   // FIX (3 Oct 2026): one card per product, sizes picked on the product page

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
}elseif ($action === 'product_search_palmjaggery') {

    $query = trim($_GET['query'] ?? '');

    try {
        if ($query !== "") {

            // category added to the SELECT (additive) so search-result cards can
            // build the correct "/{category}/{slug}" product URL.
            $sql = "SELECT id, product_name, price, dis_price, quantity, image, category
                    FROM product_details
                    WHERE product_name LIKE :query
                       OR description LIKE :query
                       OR category LIKE :query";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['query' => "%$query%"]);
            $results = vp_group_sizes($stmt->fetchAll(PDO::FETCH_ASSOC));   // FIX (3 Oct 2026): one card per product

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
