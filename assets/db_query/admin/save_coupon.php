<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? '';
$code = strtoupper(trim($_POST['code'] ?? ''));
$description = trim($_POST['description'] ?? '');
$discount_type = $_POST['discount_type'] ?? 'percent';
$discount_value = $_POST['discount_value'] ?? null;
$min_order_amount = $_POST['min_order_amount'] ?? 0;
$max_uses = $_POST['max_uses'] ?? '';
$expires_at = $_POST['expires_at'] ?? '';

if (!$code || $discount_value === null || $discount_value === '') {
    echo json_encode(['status' => 'error', 'message' => 'Code and discount value are required']);
    exit;
}

if (!in_array($discount_type, ['percent', 'flat'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid discount type']);
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE coupons SET code = ?, description = ?, discount_type = ?, discount_value = ?, min_order_amount = ?, max_uses = ?, expires_at = ? WHERE id = ?");
        $stmt->execute([
            $code, $description ?: null, $discount_type, $discount_value,
            $min_order_amount ?: 0, $max_uses !== '' ? (int)$max_uses : null,
            $expires_at !== '' ? $expires_at : null, $id
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_uses, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $code, $description ?: null, $discount_type, $discount_value,
            $min_order_amount ?: 0, $max_uses !== '' ? (int)$max_uses : null,
            $expires_at !== '' ? $expires_at : null
        ]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['status' => 'error', 'message' => 'That coupon code already exists']);
        exit;
    }
    error_log("Error saving coupon: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save coupon: ' . $e->getMessage()]);
}
