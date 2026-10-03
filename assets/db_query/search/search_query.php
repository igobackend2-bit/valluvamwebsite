<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; // your PDO connection file
require_once __DIR__ . '/../size_group.php';   // FIX (3 Oct 2026)


$response = ['status' => 'error', 'data' => []];

if (isset($_GET['query'])) {
    $query = trim($_GET['query']);

    if ($query !== "") {
        // category added to the SELECT (additive) so search-result cards can
        // build the correct "/{category}/{slug}" product URL.
        $sql = "SELECT id, product_name, price, dis_price, quantity, image, category
                FROM product_details
                WHERE product_name LIKE :query";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['query' => "%$query%"]);
        $results = vp_group_sizes($stmt->fetchAll(PDO::FETCH_ASSOC));   // FIX (3 Oct 2026): one card per product

        if ($results) {
            $response['status'] = 'success';
            $response['data'] = $results;
        } else {
            $response['status'] = 'not_found';
            $response['message'] = 'No products found';
        }
    } else {
        $response['status'] = 'empty';
        $response['message'] = 'Type something to search...';
    }
}

echo json_encode($response);
exit;
