<?php
// Kept under /admin so it shares the exact same PHP session scope as the
// authenticated admin pages on hosting environments with restrictive session
// cookie paths.
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../assets/db_query/config.php';
require_once __DIR__ . '/../assets/db_query/admin/auth_helper.php';

$action = $_REQUEST['action'] ?? '';
if ($action === 'get') {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['homepage_content_v1']);
        $content = json_decode((string)$stmt->fetchColumn(), true);
        echo json_encode(['status' => 'success', 'content' => is_array($content) ? $content : []]);
    } catch (PDOException $e) {
        error_log('Homepage CMS read failed: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Could not load homepage content.']);
    }
    exit;
}
if ($action === 'products') {
    try {
        $stmt = $pdo->query('SELECT id, product_name FROM product_details ORDER BY product_name ASC');
        echo json_encode(['status' => 'success', 'products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'success', 'products' => []]);
    }
    exit;
}
if ($action === 'upload') {
    if (empty($_FILES['image']['tmp_name']) || @getimagesize($_FILES['image']['tmp_name']) === false) { echo json_encode(['status'=>'error','message'=>'Please choose a valid image file.']); exit; }
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true) || $_FILES['image']['size'] > 8 * 1024 * 1024) { echo json_encode(['status'=>'error','message'=>'Use a JPG, PNG, WEBP or GIF image up to 8 MB.']); exit; }
    $dir = __DIR__ . '/../images/homepage/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { echo json_encode(['status'=>'error','message'=>'Upload folder is unavailable.']); exit; }
    $name = 'homepage_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $name)) { echo json_encode(['status'=>'error','message'=>'Image upload failed.']); exit; }
    echo json_encode(['status'=>'success','path'=>'images/homepage/' . $name]);
    exit;
}
if ($action === 'save') {
    $input = json_decode($_POST['content'] ?? '', true);
    if (!is_array($input)) { echo json_encode(['status'=>'error','message'=>'Invalid content payload.']); exit; }
    $allowed = ['hero','categories','best_sellers','why','healthy','did_you_know','traditional','story','gifting','curated_combos','everyday','trending','reviews','marketplaces','wholesale','private_label','kitchen','newsletter','final_cta','footer'];
    $sections = [];
    foreach (($input['sections'] ?? []) as $key => $s) {
        if (!in_array($key, $allowed, true) || !is_array($s)) continue;
        $sections[$key] = ['enabled'=>!empty($s['enabled']),'order'=>max(0,(int)($s['order']??0)),'heading'=>trim((string)($s['heading']??'')),'description'=>trim((string)($s['description']??'')),'image'=>trim((string)($s['image']??'')),'cta_text'=>trim((string)($s['cta_text']??'')),'cta_url'=>trim((string)($s['cta_url']??''))];
    }
    $collections = [];
    foreach (($input['collections'] ?? []) as $key => $ids) if (in_array($key, ['best_sellers','healthy','traditional','gifting','everyday','trending'], true)) $collections[$key] = array_values(array_unique(array_filter(array_map('intval', is_array($ids)?$ids:[]), fn($id)=>$id>0)));
    $content = ['sections'=>$sections,'collections'=>$collections];
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute(['homepage_content_v1', json_encode($content, JSON_UNESCAPED_SLASHES), 'Current homepage CMS content and product selections']);
        log_audit($pdo, 'update', 'homepage_content', null, null, $content);
        echo json_encode(['status'=>'success','message'=>'Changes saved successfully.']);
    } catch (PDOException $e) { error_log('Homepage CMS save failed: '.$e->getMessage()); echo json_encode(['status'=>'error','message'=>'Could not save homepage content.']); }
    exit;
}
http_response_code(400);
echo json_encode(['status'=>'error','message'=>'Invalid request.']);
