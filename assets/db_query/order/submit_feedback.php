<?php
// Customer-facing post-delivery feedback submission. Shown on the Order
// Tracking page only for orders whose status is 'delivered'. One feedback
// per order (checked below). Feeds admin/feedback.php + get_feedback.php,
// which already read from order_feedback — this is the missing write side.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comments TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_order_feedback (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // INSERT below will surface a clear error if this genuinely failed.
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comments = trim($_POST['comments'] ?? '');

if (!$order_id) {
    echo json_encode(['status' => 'error', 'message' => 'Order is required']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a rating from 1 to 5 stars']);
    exit;
}

try {
    // Confirm this order belongs to the logged-in user (matched by email,
    // same as get_user_orders.php) and is actually delivered.
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $user_email = $user['email'] ?? ($_SESSION['email'] ?? null);

    if (!$user_email) {
        echo json_encode(['status' => 'error', 'message' => 'User email not found']);
        exit;
    }

    $orderStmt = $pdo->prepare("SELECT id, order_status FROM orders WHERE id = ? AND email = ?");
    $orderStmt->execute([$order_id, $user_email]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }
    if (($order['order_status'] ?? '') !== 'delivered') {
        echo json_encode(['status' => 'error', 'message' => 'Feedback is only available once the order is delivered']);
        exit;
    }

    $dup = $pdo->prepare("SELECT id FROM order_feedback WHERE order_id = ?");
    $dup->execute([$order_id]);
    if ($dup->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'You already submitted feedback for this order']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO order_feedback (order_id, rating, comments) VALUES (?, ?, ?)");
    $stmt->execute([$order_id, $rating, $comments]);

    echo json_encode(['status' => 'success', 'message' => 'Thank you for your feedback!']);
} catch (PDOException $e) {
    error_log("Error submitting feedback: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not submit feedback: ' . $e->getMessage()]);
}
