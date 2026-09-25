<?php
// Returns all roles, all permissions, and the current role -> permission
// matrix, for the Roles & Permissions card on admin_users.php.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'users.manage');

try {
    $roles = $pdo->query("SELECT id, name, description FROM admin_roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $permissions = $pdo->query("SELECT perm_key, description FROM admin_permissions ORDER BY perm_key ASC")->fetchAll(PDO::FETCH_ASSOC);
    $matrixRows = $pdo->query("SELECT role_id, perm_key FROM admin_role_permissions")->fetchAll(PDO::FETCH_ASSOC);

    $matrix = [];
    foreach ($matrixRows as $row) {
        $matrix[$row['role_id']][] = $row['perm_key'];
    }

    echo json_encode([
        'status' => 'success',
        'roles' => $roles,
        'permissions' => $permissions,
        'matrix' => $matrix
    ]);
} catch (PDOException $e) {
    error_log("Error loading roles/permissions: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'roles' => [], 'permissions' => [], 'matrix' => []]);
}
