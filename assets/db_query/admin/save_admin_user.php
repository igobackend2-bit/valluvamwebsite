<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'users.manage');

$id        = $_POST['id'] ?? '';
$username  = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$role_id   = (int)($_POST['role_id'] ?? 0);
$status    = $_POST['status'] ?? 'active';
$password  = (string)($_POST['password'] ?? '');

if (!$username || !$role_id) {
    echo json_encode(['status' => 'error', 'message' => 'Username and role are required']);
    exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

// Password is required on create, optional on edit (blank keeps current).
if (!$id && $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Password is required for a new admin user']);
    exit;
}
if ($password !== '' && strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    if ($id) {
        $sel = $pdo->prepare("SELECT id, username, full_name, email, role_id, status FROM admin_users WHERE id = ?");
        $sel->execute([$id]);
        $old = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
            echo json_encode(['status' => 'error', 'message' => 'Admin user not found']);
            exit;
        }

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, full_name = ?, email = ?, role_id = ?, status = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$username, $full_name ?: null, $email ?: null, $role_id, $status, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, full_name = ?, email = ?, role_id = ?, status = ? WHERE id = ?");
            $stmt->execute([$username, $full_name ?: null, $email ?: null, $role_id, $status, $id]);
        }

        log_audit($pdo, 'update', 'admin_users', $id, $old, [
            'username' => $username, 'full_name' => $full_name, 'email' => $email,
            'role_id' => $role_id, 'status' => $status
        ]);
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, full_name, email, role_id, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $full_name ?: null, $email ?: null, $role_id, $status]);
        $newId = $pdo->lastInsertId();

        log_audit($pdo, 'create', 'admin_users', $newId, null, [
            'username' => $username, 'full_name' => $full_name, 'email' => $email,
            'role_id' => $role_id, 'status' => $status
        ]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['status' => 'error', 'message' => 'That username already exists']);
        exit;
    }
    error_log("Error saving admin user: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save admin user: ' . $e->getMessage()]);
}
