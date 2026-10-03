<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
// FIX (3 Oct 2026): only the teams that have the Account Requests page (Executive, Manager, Super Admin) — it only checked "logged in" before
require_once __DIR__ . '/page_guard.php';
require_page_access('account_requests.php');

try {
    $status = $_GET['status'] ?? '';

    $sql = "SELECT r.id, r.request_type, r.details, r.status, r.admin_notes, r.created_at,
                   u.username, u.email
            FROM account_requests r
            JOIN users u ON u.id = r.user_id
            WHERE 1=1";
    $params = [];

    if (!empty($status)) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY r.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'requests' => $requests]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'requests' => []]);
        exit;
    }
    error_log("Error fetching account requests: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch account requests: ' . $e->getMessage()]);
}
