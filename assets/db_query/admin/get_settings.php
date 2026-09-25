<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, description FROM admin_settings ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'settings' => $settings]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'settings' => []]);
        exit;
    }
    error_log("Error fetching settings: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch settings: ' . $e->getMessage()]);
}
