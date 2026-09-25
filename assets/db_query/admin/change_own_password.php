<?php
// Self-service password change. Any logged-in admin can change their OWN
// password — this does not require users.manage, only a valid session.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$old_password = (string)($_POST['old_password'] ?? '');
$new_password = (string)($_POST['new_password'] ?? '');

if ($old_password === '' || $new_password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Current and new password are both required']);
    exit;
}
if (strlen($new_password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'New password must be at least 6 characters']);
    exit;
}

$userId = $_SESSION['admin_user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired — please log in again.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($old_password, $row['password_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        exit;
    }

    $newHash = password_hash($new_password, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
    $upd->execute([$newHash, $userId]);

    log_audit($pdo, 'update', 'admin_users', $userId, ['password' => '(changed by self)'], ['password' => '(changed by self)']);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error changing own password: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to change password: ' . $e->getMessage()]);
}
