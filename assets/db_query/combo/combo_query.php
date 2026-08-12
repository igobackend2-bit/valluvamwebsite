<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file

$action = $_GET['action'];
 if ($action === 'combo_products') {

    try {
        $stmt = $pdo->prepare("SELECT * FROM product_details WHERE category = 'combo' ORDER BY id DESC");
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
} 
?>