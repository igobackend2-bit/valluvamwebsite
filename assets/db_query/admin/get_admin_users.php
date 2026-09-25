<?php
// List admin users with their role name. Super-Admin-only (users.manage).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'users.manage');

try {
    $sql = "SELECT au.id, au.username, au.full_name, au.email, au.role_id, r.name AS role_name,
                   au.status, au.last_login_at, au.created_at
            FROM admin_users au
            LEFT JOIN admin_roles r ON r.id = au.role_id
            ORDER BY au.username ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'users' => $users]);
} catch (PDOException $e) {
    error_log("Error listing admin users: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'users' => []]);
}
