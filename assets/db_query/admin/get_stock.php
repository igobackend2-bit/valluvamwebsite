<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    try {
        $pdo->exec("ALTER TABLE product_details ADD COLUMN stock INT NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists — fine.
    }

    $search = $_GET['search'] ?? '';
    $sql = "SELECT id, product_name, category, stock FROM product_details WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (product_name LIKE ? OR category LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY stock ASC, product_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'products' => $products]);
} catch (PDOException $e) {
    error_log("Error fetching stock: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch stock: ' . $e->getMessage()]);
}
