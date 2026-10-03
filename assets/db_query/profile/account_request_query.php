<?php
// FIX (3 Oct 2026): customers raise account requests from My Profile (Admin → Customers → Account Requests).
//   GET  action=list                       → my requests with status and the admin's reply
//   POST action=create  request_type, details
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) { echo json_encode(['status' => 'error', 'message' => 'Please log in.']); exit; }
const VP_REQ_TYPES = ['account_deletion' => 'Delete my account', 'data_export' => 'Send me a copy of my data', 'email_change' => 'Change my email',
                      'wholesale_upgrade' => 'Wholesale / B2B account', 'reactivation' => 'Reactivate my account', 'other' => 'Other'];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_type VARCHAR(30) NOT NULL,
        details TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        admin_notes VARCHAR(500) NULL,
        resolved_by VARCHAR(100) NULL,
        resolved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ar_user (user_id), KEY idx_ar_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    if ($action === 'create') {
        $type = (string)($_POST['request_type'] ?? '');
        $details = trim(mb_substr((string)($_POST['details'] ?? ''), 0, 2000));
        if (!isset(VP_REQ_TYPES[$type])) { echo json_encode(['status' => 'error', 'message' => 'Choose what you need.']); exit; }
        if (in_array($type, ['email_change', 'wholesale_upgrade', 'other'], true) && $details === '') { echo json_encode(['status' => 'error', 'message' => 'Please add the details.']); exit; }
        $dup = $pdo->prepare("SELECT id FROM account_requests WHERE user_id = ? AND request_type = ? AND status = 'pending' LIMIT 1");
        $dup->execute([$userId, $type]);
        if ($dup->fetchColumn()) { echo json_encode(['status' => 'error', 'message' => 'You already have this request open — we will get back to you soon.']); exit; }
        $pdo->prepare("INSERT INTO account_requests (user_id, request_type, details, status) VALUES (?, ?, ?, 'pending')")->execute([$userId, $type, $details ?: null]);
        echo json_encode(['status' => 'success', 'message' => 'Request sent. Our team will review it and reply in your Inbox.']);
        exit;
    }
    $st = $pdo->prepare("SELECT id, request_type, details, status, admin_notes, created_at, resolved_at FROM account_requests WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    $st->execute([$userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) $r['type_label'] = VP_REQ_TYPES[$r['request_type']] ?? $r['request_type'];
    unset($r);
    echo json_encode(['status' => 'success', 'requests' => $rows, 'types' => VP_REQ_TYPES]);
} catch (PDOException $e) {
    error_log('[account_request_query] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not load your requests. Please try again.']);
}
