<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $approved = $_GET['approved'] ?? '';

    $sql = "SELECT r.id, r.product_id, r.reviewer_name, r.rating, r.review_text, r.is_approved, r.created_at,
                   p.product_name
            FROM product_reviews r
            LEFT JOIN product_details p ON p.id = r.product_id
            WHERE 1=1";
    $params = [];

    if ($approved !== '') {
        $sql .= " AND r.is_approved = ?";
        $params[] = (int)$approved;
    }

    $sql .= " ORDER BY r.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'reviews' => $reviews]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'reviews' => []]);
        exit;
    }
    error_log("Error fetching reviews: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch reviews: ' . $e->getMessage()]);
}
