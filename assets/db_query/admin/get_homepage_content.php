<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_admin_session();
header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['homepage_content_v1']);
    $raw = $stmt->fetchColumn();
    $content = $raw ? json_decode($raw, true) : [];
    echo json_encode(['status' => 'success', 'content' => is_array($content) ? $content : []]);
} catch (PDOException $e) {
    error_log('Homepage content read failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not load homepage content.']);
}
