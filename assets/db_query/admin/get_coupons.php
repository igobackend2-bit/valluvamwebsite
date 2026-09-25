<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM coupons ORDER BY id DESC");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'coupons' => $coupons]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'coupons' => []]);
        exit;
    }
    error_log("Error fetching coupons: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch coupons: ' . $e->getMessage()]);
}
