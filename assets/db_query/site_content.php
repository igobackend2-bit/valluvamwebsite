<?php
// Public, read-only homepage configuration. It deliberately exposes only
// content fields, never admin data or customer/order information.
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
try {
    $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['homepage_content_v1']);
    $content = json_decode((string)$stmt->fetchColumn(), true);
    echo json_encode(['status' => 'success', 'content' => is_array($content) ? $content : []]);
} catch (PDOException $e) {
    // The website must continue to render its current hard-coded defaults if
    // the existing settings table is unavailable on an older deployment.
    echo json_encode(['status' => 'success', 'content' => []]);
}
