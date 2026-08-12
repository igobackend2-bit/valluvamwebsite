<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=valluvamdb", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
