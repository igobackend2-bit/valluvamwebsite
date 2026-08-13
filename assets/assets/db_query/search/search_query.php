<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; // your PDO connection file


$response = ['status' => 'error', 'data' => []];

if (isset($_GET['query'])) {
    $query = trim($_GET['query']);

    if ($query !== "") {
        $sql = "SELECT id, product_name, price, dis_price, quantity, image 
                FROM product_details 
                WHERE product_name LIKE :query";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['query' => "%$query%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
