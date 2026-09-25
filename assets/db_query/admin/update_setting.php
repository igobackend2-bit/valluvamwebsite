<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$key = $_POST['key'] ?? '';
$value = $_POST['value'] ?? '';

if (!$key) {
    echo json_encode(['status' => 'error', 'message' => 'key is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error updating setting: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update setting: ' . $e->getMessage()]);
}
