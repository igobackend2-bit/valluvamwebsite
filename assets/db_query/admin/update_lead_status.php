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

if (!$id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'id and status are required']);
    exit;
}

$valid_statuses = ['new', 'contacted', 'closed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

try {
    try {
        $pdo->exec("ALTER TABLE contacts ADD COLUMN lead_status VARCHAR(20) NOT NULL DEFAULT 'new'");
    } catch (PDOException $e) {
        // Column already exists — fine.
    }

    $stmt = $pdo->prepare("UPDATE contacts SET lead_status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['status' => 'success', 'message' => 'Lead status updated']);
} catch (PDOException $e) {
    error_log("Error updating lead status: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update lead status: ' . $e->getMessage()]);
}
