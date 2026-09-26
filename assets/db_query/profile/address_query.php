<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Login required']); exit; }
$user_id = (int)$_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

// GET – list addresses
if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM saved_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt->execute([$user_id]);
    echo json_encode(['status'=>'success','addresses'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// POST – add / update
if ($method === 'POST') {
    $action    = $_POST['action'] ?? 'add';
    $addr_id   = (int)($_POST['id'] ?? 0);
    $label     = trim($_POST['label'] ?? 'Home');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $street    = trim($_POST['street'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $state     = trim($_POST['state'] ?? '');
    $postcode  = trim($_POST['postcode'] ?? '');
    $is_default = (int)($_POST['is_default'] ?? 0);

    if (!$full_name || !$phone || !$street || !$city || !$state || !$postcode) {
        echo json_encode(['status'=>'error','message'=>'All required fields must be filled']);
        exit;
    }

    if ($is_default) {
        $pdo->prepare("UPDATE saved_addresses SET is_default=0 WHERE user_id=?")->execute([$user_id]);
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO saved_addresses (user_id,label,full_name,phone,street,apartment,city,state,postcode,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user_id,$label,$full_name,$phone,$street,$apartment,$city,$state,$postcode,$is_default]);
        echo json_encode(['status'=>'success','message'=>'Address saved','id'=>$pdo->lastInsertId()]);
    } elseif ($action === 'update' && $addr_id) {
        $stmt = $pdo->prepare("UPDATE saved_addresses SET label=?,full_name=?,phone=?,street=?,apartment=?,city=?,state=?,postcode=?,is_default=? WHERE id=? AND user_id=?");
        $stmt->execute([$label,$full_name,$phone,$street,$apartment,$city,$state,$postcode,$is_default,$addr_id,$user_id]);
        echo json_encode(['status'=>'success','message'=>'Address updated']);
    } elseif ($action === 'delete' && $addr_id) {
        $pdo->prepare("DELETE FROM saved_addresses WHERE id=? AND user_id=?")->execute([$addr_id,$user_id]);
        echo json_encode(['status'=>'success','message'=>'Address deleted']);
    } elseif ($action === 'set_default' && $addr_id) {
        $pdo->prepare("UPDATE saved_addresses SET is_default=0 WHERE user_id=?")->execute([$user_id]);
        $pdo->prepare("UPDATE saved_addresses SET is_default=1 WHERE id=? AND user_id=?")->execute([$addr_id,$user_id]);
        echo json_encode(['status'=>'success','message'=>'Default address updated']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Invalid action']);
    }
    exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid request']);
