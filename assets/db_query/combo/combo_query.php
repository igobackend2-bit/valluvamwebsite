<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file
require_once __DIR__ . '/../size_group.php';   // FIX (3 Oct 2026)

$action = $_GET['action'];
 if ($action === 'combo_products') {

    try {
        $stmt = $pdo->prepare("SELECT * FROM product_details WHERE category = 'combo' ORDER BY id DESC");
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
} 
?>