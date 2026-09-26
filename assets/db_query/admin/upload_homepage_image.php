<?php
require_once __DIR__ . '/auth_helper.php';
require_admin_session();
header('Content-Type: application/json');
if (empty($_FILES['image']['tmp_name']) || @getimagesize($_FILES['image']['tmp_name']) === false) {
    echo json_encode(['status' => 'error', 'message' => 'Please choose a valid image file.']); exit;
}
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true) || $_FILES['image']['size'] > 8 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'Use a JPG, PNG, WEBP or GIF image up to 8 MB.']); exit;
}
$dir = dirname(__DIR__, 3) . '/images/homepage/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) { echo json_encode(['status'=>'error','message'=>'Upload folder is unavailable.']); exit; }
$name = 'homepage_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $name)) { echo json_encode(['status'=>'error','message'=>'Image upload failed.']); exit; }
echo json_encode(['status' => 'success', 'path' => 'images/homepage/' . $name]);
