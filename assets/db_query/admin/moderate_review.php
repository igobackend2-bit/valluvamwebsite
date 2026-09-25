<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['approve', 'delete'])) {
    echo json_encode(['status' => 'error', 'message' => 'id and a valid action are required']);
    exit;
}

try {
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE product_reviews SET is_approved = 1 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
        $stmt->execute([$id]);
    }
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error moderating review: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update review: ' . $e->getMessage()]);
}
