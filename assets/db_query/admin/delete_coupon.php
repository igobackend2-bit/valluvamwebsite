<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
// FIX (3 Oct 2026): only the teams that have the Coupons page (Executive, Manager, Super Admin) — it only checked "logged in" before
require_once __DIR__ . '/page_guard.php';
require_page_access('coupons.php');

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
