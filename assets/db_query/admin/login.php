<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
    exit;
}

// Admin credentials
// SECURITY FIX: set ADMIN_USERNAME / ADMIN_PASSWORD in the server's .env file.
// The old hard-coded values are only a fallback until those are set.
require_once __DIR__ . '/../load_env.php';
$admin_username = getenv('ADMIN_USERNAME') ?: 'admin';
$admin_password = getenv('ADMIN_PASSWORD') ?: 'admin123';

// Verify credentials
if (hash_equals($admin_username, $username) && hash_equals($admin_password, $password)) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    echo json_encode(['status' => 'success', 'message' => 'Login successful']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
}

