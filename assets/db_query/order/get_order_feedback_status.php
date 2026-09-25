<?php
// Tells the Order Tracking page which of the logged-in user's own orders
// already have feedback submitted, so it can show "Thanks!" instead of the
// feedback form again for those. Session-based, no params.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit;
}

try {
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $user_email = $user['email'] ?? ($_SESSION['email'] ?? null);

    if (!$user_email) {
        echo json_encode(['status' => 'success', 'order_ids' => []]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT f.order_id
                            FROM order_feedback f
                            JOIN orders o ON o.id = f.order_id
                            WHERE o.email = ?");
    $stmt->execute([$user_email]);
    $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'order_id'));

    echo json_encode(['status' => 'success', 'order_ids' => $ids]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'order_ids' => []]);
        exit;
    }
    error_log("Error fetching feedback status: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not load feedback status']);
}
