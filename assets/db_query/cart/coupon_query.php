<?php
// FIX (3 Oct 2026): "Have a coupon code? Apply" on the cart page.
//   POST action=apply  code=…   → checks the code against the customer's cart, keeps it for checkout
//   POST action=remove          → removes it
//   GET  action=status          → the coupon applied now (re-checked against the cart)
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../coupon_helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) { echo json_encode(['status' => 'error', 'message' => 'Please log in to use a coupon.']); exit; }

try {
    $st = $pdo->prepare("SELECT COALESCE(SUM(c.quantity * p.dis_price), 0) FROM cart c JOIN product_details p ON p.id = c.product_id WHERE c.user_id = ? AND c.status = 'pending'");
    $st->execute([$userId]);
    $subtotal = round((float)$st->fetchColumn(), 2);
    $action = $_POST['action'] ?? $_GET['action'] ?? 'status';

    if ($action === 'apply') {
        if ($subtotal <= 0) { echo json_encode(['status' => 'error', 'message' => 'Your cart is empty.']); exit; }
        [$ok, $msg, $disc, $c] = vp_coupon_check($pdo, (string)($_POST['code'] ?? ''), $subtotal);
        if (!$ok) { unset($_SESSION['coupon_code']); echo json_encode(['status' => 'error', 'message' => $msg]); exit; }
        $_SESSION['coupon_code'] = $c['code'];
        echo json_encode(['status' => 'success', 'message' => $msg, 'code' => $c['code'], 'discount' => $disc, 'subtotal' => $subtotal]);
        exit;
    }
    if ($action === 'remove') {
        unset($_SESSION['coupon_code']);
        echo json_encode(['status' => 'success', 'message' => 'Coupon removed.', 'code' => null, 'discount' => 0, 'subtotal' => $subtotal]);
        exit;
    }
    [$code, $disc, $msg] = vp_coupon_session($pdo, $subtotal);
    echo json_encode(['status' => 'success', 'code' => $code, 'discount' => $disc, 'subtotal' => $subtotal, 'message' => $code ? $msg : ($msg ?: '')]);
} catch (PDOException $e) {
    error_log('[coupon_query] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not check the coupon. Please try again.']);
}
