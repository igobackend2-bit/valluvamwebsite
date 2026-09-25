<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT f.id, f.order_id, f.rating, f.comments, f.created_at,
                   o.receipt, o.first_name, o.last_name
            FROM order_feedback f
            LEFT JOIN orders o ON o.id = f.order_id
            ORDER BY f.id DESC";

    $stmt = $pdo->query($sql);
    $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'feedback' => $feedback]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'feedback' => []]);
        exit;
    }
    error_log("Error fetching feedback: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch feedback: ' . $e->getMessage()]);
}
