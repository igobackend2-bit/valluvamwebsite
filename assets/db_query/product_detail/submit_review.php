<?php
// Customer-facing review submission for a product. Reviews go live immediately
// (is_approved = 1 on insert) — admin's Approve/Reject/Delete on the Reviews
// page (admin/reviews.php) is after-the-fact moderation, per the agreed flow.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Defensive schema creation — same pattern used elsewhere in this codebase
// (e.g. credit_sale_helper.php): never DROP/MODIFY, only create-if-missing.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        reviewer_name VARCHAR(150) DEFAULT NULL,
        rating TINYINT NOT NULL,
        review_text TEXT DEFAULT NULL,
        is_approved TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // If this fails, the INSERT below will fail too and report a clear error.
}

$product_id = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');
$reviewer_name = trim($_POST['reviewer_name'] ?? '');

if (!$product_id) {
    echo json_encode(['status' => 'error', 'message' => 'Product is required']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a rating from 1 to 5 stars']);
    exit;
}
if ($review_text === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please write a short review']);
    exit;
}
if ($reviewer_name === '') {
    $reviewer_name = 'Anonymous';
}
// Prefer the logged-in user's name if available and no name was typed in.
if ($reviewer_name === 'Anonymous' && !empty($_SESSION['user_id'])) {
    try {
        $u = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $u->execute([$_SESSION['user_id']]);
        $row = $u->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['first_name'])) {
            $reviewer_name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
        }
    } catch (PDOException $e) { /* users table shape may differ — keep 'Anonymous' */ }
}

try {
    $check = $pdo->prepare("SELECT id FROM product_details WHERE id = ?");
    $check->execute([$product_id]);
    if (!$check->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, reviewer_name, rating, review_text, is_approved)
                            VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$product_id, $reviewer_name, $rating, $review_text]);

    echo json_encode(['status' => 'success', 'message' => 'Thank you for your review!']);
} catch (PDOException $e) {
    error_log("Error submitting review: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not submit review: ' . $e->getMessage()]);
}
