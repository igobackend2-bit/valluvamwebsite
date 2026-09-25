<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error deleting coupon: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete coupon: ' . $e->getMessage()]);
}
