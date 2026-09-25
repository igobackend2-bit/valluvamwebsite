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

// New multi-user login: check admin_users + admin_roles first.
try {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.password_hash, u.full_name, u.status, u.role_id, r.name AS role_name
                            FROM admin_users u JOIN admin_roles r ON r.id = u.role_id
                            WHERE u.username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['status'] !== 'active') {
            echo json_encode(['status' => 'error', 'message' => 'This account is inactive. Contact a Super Admin.']);
            exit;
        }
        if (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id']   = (int)$user['id'];
            $_SESSION['admin_username']  = $user['username'];
            $_SESSION['admin_full_name'] = $user['full_name'] ?: $user['username'];
            $_SESSION['admin_role_id']   = (int)$user['role_id'];
            $_SESSION['admin_role_name'] = $user['role_name'];

            $upd = $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?");
            $upd->execute([$user['id']]);

            echo json_encode(['status' => 'success', 'message' => 'Login successful']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        }
        exit;
    }
} catch (PDOException $e) {
    // admin_users table doesn't exist yet (foundation SQL not run) — fall
    // through to the legacy single-admin check below so the site doesn't
    // lock everyone out mid-migration.
    if (strpos($e->getMessage(), "doesn't exist") === false) {
        error_log('Admin login error: ' . $e->getMessage());
    }
}

// ── Legacy fallback (pre-multi-user): env-based single admin account ──────
require_once __DIR__ . '/../load_env.php';
$admin_username = getenv('ADMIN_USERNAME') ?: 'admin';
$admin_password = getenv('ADMIN_PASSWORD') ?: 'admin123';

if (hash_equals($admin_username, $username) && hash_equals($admin_password, $password)) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username']  = $username;
    $_SESSION['admin_full_name'] = $username;
    $_SESSION['admin_role_id']   = 1; // treat legacy login as Super Admin
    $_SESSION['admin_role_name'] = 'Super Admin';
    echo json_encode(['status' => 'success', 'message' => 'Login successful']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
}
