<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file

session_start();

// Validate required fields are present and not empty
$required_fields = ['email', 'username', 'password', 'phone'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(['status' => 'error', 'message' => ucfirst($field) . ' is required']);
        exit;
    }
}

$email = trim($_POST['email']);
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$phone = trim($_POST['phone']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

// Validate username (minimum 3 characters, alphanumeric and underscore only)
if (strlen($username) < 3) {
    echo json_encode(['status' => 'error', 'message' => 'Username must be at least 3 characters long']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['status' => 'error', 'message' => 'Username can only contain letters, numbers, and underscores']);
    exit;
}

// Validate password (minimum 6 characters)
if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long']);
    exit;
}

// Validate phone (basic validation - should be numeric and at least 10 digits)
if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number must be 10-15 digits']);
    exit;
}

// Check if email or username already exists
$check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$check->execute([$email, $username]);
if ($check->rowCount() > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email or username already exists']);
    exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user with validated data
$stmt = $pdo->prepare("INSERT INTO users (email, username, password, phone_number, created_at) VALUES (?, ?, ?, ?, NOW())");
if ($stmt->execute([$email, $username, $hashedPassword, $phone])) {
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Signup failed. Please try again.']);
}

