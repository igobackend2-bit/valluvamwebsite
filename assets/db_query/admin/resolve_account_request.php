<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? '';
$admin_notes = trim($_POST['admin_notes'] ?? '');

if (!$id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'id and status are required']);
    exit;
}

$valid_statuses = ['approved', 'rejected', 'completed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

try {
    $adminUsername = $_SESSION['admin_username'] ?? 'Admin';
    $stmt = $pdo->prepare("UPDATE account_requests SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $admin_notes ?: null, $adminUsername, $id]);

    echo json_encode(['status' => 'success', 'message' => 'Request updated']);
} catch (PDOException $e) {
    error_log("Error resolving account request: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to resolve request: ' . $e->getMessage()]);
}
