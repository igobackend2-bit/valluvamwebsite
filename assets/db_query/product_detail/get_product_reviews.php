<?php
// Public read of approved reviews for one product — used on the product
// detail page. Only is_approved = 1 rows are ever returned here; pending/
// rejected reviews stay admin-only (see admin/reviews.php + get_reviews.php).
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$product_id = (int)($_GET['product_id'] ?? 0);
if (!$product_id) {
    echo json_encode(['status' => 'error', 'message' => 'Product is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, reviewer_name, rating, review_text, created_at
                            FROM product_reviews
                            WHERE product_id = ? AND is_approved = 1
                            ORDER BY id DESC");
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $avg = null;
    if (count($reviews) > 0) {
        $sum = 0;
        foreach ($reviews as $r) $sum += (int)$r['rating'];
        $avg = round($sum / count($reviews), 1);
    }

    echo json_encode(['status' => 'success', 'reviews' => $reviews, 'average_rating' => $avg, 'count' => count($reviews)]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'reviews' => [], 'average_rating' => null, 'count' => 0]);
        exit;
    }
    error_log("Error fetching product reviews: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not load reviews']);
}
