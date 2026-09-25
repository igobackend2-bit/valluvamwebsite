<?php
// Replaces a role's permission set (except Super Admin, role_id 1, which
// always has every permission and cannot be edited here).
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'users.manage');

$role_id = (int)($_POST['role_id'] ?? 0);
$permKeysRaw = $_POST['perm_keys'] ?? [];
$permKeys = is_array($permKeysRaw) ? array_values(array_unique(array_map('strval', $permKeysRaw))) : [];

if (!$role_id) {
    echo json_encode(['status' => 'error', 'message' => 'role_id is required']);
    exit;
}

if ($role_id === 1) {
    echo json_encode(['status' => 'error', 'message' => "Super Admin's permissions can't be changed — it always has full access."]);
    exit;
}

try {
    $roleCheck = $pdo->prepare("SELECT id, name FROM admin_roles WHERE id = ?");
    $roleCheck->execute([$role_id]);
    $role = $roleCheck->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        echo json_encode(['status' => 'error', 'message' => 'Role not found']);
        exit;
    }

    $oldStmt = $pdo->prepare("SELECT perm_key FROM admin_role_permissions WHERE role_id = ?");
    $oldStmt->execute([$role_id]);
    $oldKeys = $oldStmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();

    $del = $pdo->prepare("DELETE FROM admin_role_permissions WHERE role_id = ?");
    $del->execute([$role_id]);

    if (!empty($permKeys)) {
        $ins = $pdo->prepare("INSERT INTO admin_role_permissions (role_id, perm_key) VALUES (?, ?)");
        foreach ($permKeys as $key) {
            if ($key === '') continue;
            $ins->execute([$role_id, $key]);
        }
    }

    $pdo->commit();

    log_audit($pdo, 'update', 'admin_role_permissions', $role_id, $oldKeys, $permKeys);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving role permissions: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save permissions: ' . $e->getMessage()]);
}
