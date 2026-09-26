<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");


require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_logged_in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'CartCount') {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) AS cart_count 
                           FROM cart 
                           WHERE user_id = ? AND status='pending'");
    $stmt->execute([$user_id]);
    $count = (int)$stmt->fetchColumn();

    echo json_encode(["status" => "success", "count" => $count]);
    exit;
} elseif ($action === 'HeaderCounts') {
    // Cart Count
    $stmtC = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ? AND status='pending'");
    $stmtC->execute([$user_id]);
    $cart = (int)$stmtC->fetchColumn();

    // Wishlist Count
    $stmtW = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $stmtW->execute([$user_id]);
    $wishlist = (int)$stmtW->fetchColumn();

    // Notification Count
    $stmtN = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtN->execute([$user_id]);
    $notif = (int)$stmtN->fetchColumn();

    echo json_encode(["status" => "success", "cart" => $cart, "wishlist" => $wishlist, "notif" => $notif]);
    exit;
}
