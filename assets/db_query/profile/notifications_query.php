<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Login required']); exit; }
$user_id = (int)$_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

// GET – list notifications
if ($method === 'GET') {
    $only_unread = isset($_GET['unread']) ? 1 : null;
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($only_unread) $sql .= " AND is_read = 0";
    $sql .= " ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // unread count
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $cnt->execute([$user_id]);
    echo json_encode(['status'=>'success','notifications'=>$notifs,'unread_count'=>(int)$cnt->fetchColumn()]);
    exit;
}

// POST – mark read / mark all read
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id,$user_id]);
        echo json_encode(['status'=>'success']);
    } elseif ($action === 'mark_all_read') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user_id]);
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Unknown action']);
    }
    exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid request']);
