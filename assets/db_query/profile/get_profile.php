<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

// ── GET: Fetch profile ────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT id, username, email, phone_number, full_name, gender, dob, avatar_url, created_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    echo json_encode(['status' => 'success', 'user' => $user]);
    exit;
}

// ── POST: Update profile ──────────────────────────────────────
if ($method === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $gender    = trim($_POST['gender'] ?? '');
    $dob       = trim($_POST['dob'] ?? '');

    $allowed_genders = ['male', 'female', 'other', 'prefer_not_to_say'];
    if ($gender && !in_array($gender, $allowed_genders)) $gender = null;
    if (!$dob) $dob = null;

    $stmt = $pdo->prepare("
        UPDATE users SET full_name = ?, phone_number = ?, gender = ?, dob = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$full_name ?: null, $phone ?: null, $gender, $dob, $user_id]);

    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
