<?php
session_start();
ini_set('display_errors', 1);
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
}
