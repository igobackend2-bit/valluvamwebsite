<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// get_categories.php
require_once __DIR__ . '/../config.php'; // your PDO connection file

// ✅ Guard against missing 'action' param so a PHP notice never corrupts the JSON output
$action = $_GET['action'] ?? '';

if ($action === 'all_products') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_details ORDER BY id DESC");
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
} elseif ($action === 'product_search_shop') {

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
} else {
    // ✅ Always return valid JSON even for an unrecognized/missing action
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing action']);
}