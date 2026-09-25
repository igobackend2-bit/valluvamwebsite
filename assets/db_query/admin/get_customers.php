<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $search = $_GET['search'] ?? '';

    // Orders don't carry a user_id column (checkout is guest-style, keyed
    // by email), so the order count is a best-effort match on email —
    // same approach already used elsewhere in this codebase for joining
    // customer-facing data without a formal foreign key.
    $sql = "SELECT u.id, u.username, u.email, u.phone_number, u.created_at,
                   (SELECT COUNT(*) FROM orders o WHERE o.email = u.email) AS order_count
            FROM users u WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY u.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'customers' => $customers]);
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch customers: ' . $e->getMessage()]);
}
