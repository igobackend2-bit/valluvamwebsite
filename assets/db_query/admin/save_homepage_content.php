<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_admin_session();
header('Content-Type: application/json');

$raw = $_POST['content'] ?? '';
$content = json_decode($raw, true);
if (!is_array($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid content payload.']);
    exit;
}

// This is intentionally a settings value, not a new CMS schema. Product
// collections may contain only IDs, so names, prices, stock and images still
// come from product_details everywhere on the public site.
$allowedSections = ['hero','categories','best_sellers','why','healthy','did_you_know','traditional','story','gifting','curated_combos','everyday','trending','reviews','marketplaces','wholesale','private_label','kitchen','newsletter','final_cta','footer'];
$sections = [];
foreach (($content['sections'] ?? []) as $key => $section) {
    if (!in_array($key, $allowedSections, true) || !is_array($section)) continue;
    $sections[$key] = [
        'enabled' => !empty($section['enabled']),
        'order' => max(0, (int)($section['order'] ?? 0)),
        'heading' => trim((string)($section['heading'] ?? '')),
        'description' => trim((string)($section['description'] ?? '')),
        'image' => trim((string)($section['image'] ?? '')),
        'cta_text' => trim((string)($section['cta_text'] ?? '')),
        'cta_url' => trim((string)($section['cta_url'] ?? '')),
    ];
}
$collections = [];
foreach (($content['collections'] ?? []) as $key => $ids) {
    if (!in_array($key, ['best_sellers','healthy','traditional','gifting','everyday','trending'], true)) continue;
    $collections[$key] = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), fn($id) => $id > 0)));
}
$safe = ['sections' => $sections, 'collections' => $collections];

try {
    $stmt = $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute(['homepage_content_v1', json_encode($safe, JSON_UNESCAPED_SLASHES), 'Current homepage CMS content and product selections']);
    log_audit($pdo, 'update', 'homepage_content', null, null, $safe);
    echo json_encode(['status' => 'success', 'message' => 'Changes saved successfully.']);
} catch (PDOException $e) {
    error_log('Homepage content save failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not save homepage content.']);
}
