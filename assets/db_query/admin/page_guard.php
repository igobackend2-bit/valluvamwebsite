<?php
// FIX (3 Oct 2026): product / category / homepage changes only for roles that have that page in their menu
// (role_access.php: Executive, Manager, Super Admin, + dashboards a Super Admin gave the user).
// Before this, these endpoints only checked "logged in", so any team (L1, Auditor, Accounts) could change or delete products.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';

function vp_page_allowed(string $page): bool {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) return false;
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) return true;   // Super Admin
    $pdo = $GLOBALS['pdo'] ?? null;
    $dash = $pdo instanceof PDO ? user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)) : [];
    $pages = role_access_pages((string)($_SESSION['admin_role_name'] ?? ''), $dash);
    return $pages === null || in_array($page, $pages, true);
}
function require_page_access(string $page): void {
    if (vp_page_allowed($page)) return;
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to do this.']);
    exit;
}
