<?php
// Toggle an admin user's status between active/inactive. Admin users are
// never hard-deleted — this preserves created_by/audit history integrity.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'users.manage');

$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $sel = $pdo->prepare("SELECT id, username, status FROM admin_users WHERE id = ?");
    $sel->execute([$id]);
    $user = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Admin user not found']);
        exit;
    }

    if ((int)$user['id'] === (int)($_SESSION['admin_user_id'] ?? 0)) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot deactivate your own account']);
        exit;
    }

    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';

    $stmt = $pdo->prepare("UPDATE admin_users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);

    log_audit($pdo, 'update', 'admin_users', $id, ['status' => $user['status']], ['status' => $newStatus]);

    echo json_encode(['status' => 'success', 'new_status' => $newStatus]);
} catch (PDOException $e) {
    error_log("Error toggling admin user status: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status: ' . $e->getMessage()]);
}
