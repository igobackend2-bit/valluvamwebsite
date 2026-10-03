<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
// FIX (3 Oct 2026): only the teams that have the Account Requests page (Executive, Manager, Super Admin) — it only checked "logged in" before
require_once __DIR__ . '/page_guard.php';
require_page_access('account_requests.php');

$id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? '';
$admin_notes = trim($_POST['admin_notes'] ?? '');

if (!$id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'id and status are required']);
    exit;
}

$valid_statuses = ['approved', 'rejected', 'completed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

try {
    $adminUsername = $_SESSION['admin_username'] ?? 'Admin';
    $stmt = $pdo->prepare("UPDATE account_requests SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $admin_notes ?: null, $adminUsername, $id]);
    // FIX (3 Oct 2026): the customer gets the decision in their Inbox (My Profile → Inbox / Account Requests)
    try {
        $rq = $pdo->prepare("SELECT user_id, request_type FROM account_requests WHERE id = ?"); $rq->execute([$id]); $rq = $rq->fetch(PDO::FETCH_ASSOC);
        if ($rq) {
            $what = ['account_deletion' => 'account deletion', 'data_export' => 'data copy', 'email_change' => 'email change', 'wholesale_upgrade' => 'wholesale account', 'reactivation' => 'account reactivation'][$rq['request_type']] ?? 'account';
            $pdo->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'system', ?, ?, 'profile.php')")
                ->execute([(int)$rq['user_id'], 'Your ' . $what . ' request was ' . $status, $admin_notes !== '' ? $admin_notes : 'Your request has been ' . $status . '. See My Profile → Account Requests.']);
        }
    } catch (PDOException $e) { error_log('[account request notify] ' . $e->getMessage()); }

    echo json_encode(['status' => 'success', 'message' => 'Request updated']);
} catch (PDOException $e) {
    error_log("Error resolving account request: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to resolve request: ' . $e->getMessage()]);
}
