<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../razorpay.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Razorpay\Api\Api;

// Validate required fields
$required = ["first_name", "last_name", "email", "phone", "state", "city", "street_address", "postcode", "payment_method"];
foreach ($required as $field) {
  if (empty($_POST[$field])) {
    echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
    exit;
  }
}

// Validate payment method
$paymentMethod = $_POST['payment_method'] ?? 'RZP';
if ($paymentMethod !== 'RZP') {
  echo json_encode(["status" => "error", "message" => "Invalid payment method"]);
  exit;
}

// Get user ID
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
  echo json_encode(["status" => "error", "message" => "Login required"]);
  exit;
}

// Load cart from database
$stmt = $pdo->prepare("
    SELECT c.id AS cart_id, c.product_id, c.quantity, 
           p.product_name, p.dis_price AS price,
           (c.quantity * p.dis_price) AS total
    FROM cart c
    JOIN product_details p ON c.product_id = p.id
    WHERE c.user_id = ? AND c.status = 'pending'
");
$stmt->execute([$user_id]);
$cart = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart)) {
  echo json_encode(["status" => "error", "message" => "Cart is empty"]);
  exit;
}

// Calculate total
$subtotal = 0;
foreach ($cart as $item) {
  $subtotal += $item['total'];
}
$delivery = 0.00;
$discount = 3.00;
$amount = max(0, $subtotal + $delivery - $discount);
$amount_paise = (int)round($amount * 100);

// Generate unique receipt ID
$receipt = "ORD" . time() . rand(1000, 9999);

try {
  // Create Razorpay order (NOT database order yet)
  $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
  $rzpOrder = $api->order->create([
    'receipt'         => $receipt,
    'amount'          => $amount_paise,
    'currency'        => RAZORPAY_CURRENCY,
    'payment_capture' => 1
  ]);
  
  $razorpayOrderId = $rzpOrder['id'];
  
  // Store order data in session temporarily (will be used after payment success)
  $_SESSION['pending_order'] = [
    'receipt' => $receipt,
    'first_name' => $_POST['first_name'],
    'last_name' => $_POST['last_name'],
    'email' => $_POST['email'],
    'phone' => $_POST['phone'],
    'state' => $_POST['state'],
    'city' => $_POST['city'],
    'street_address' => $_POST['street_address'],
    'apartment' => $_POST['apartment'] ?? '',
    'postcode' => $_POST['postcode'],
    'amount' => $amount,
    'amount_paise' => $amount_paise,
    'payment_method' => $paymentMethod,
    'razorpay_order_id' => $razorpayOrderId,
    'cart_items' => $cart
  ];
  
  echo json_encode([
    "status" => "success",
    "razorpay_order_id" => $razorpayOrderId,
    "receipt" => $receipt,
    "key_id" => RAZORPAY_KEY_ID,
    "amount_paise" => $amount_paise,
    "currency" => RAZORPAY_CURRENCY
  ]);
} catch (Exception $e) {
  error_log("Razorpay order creation error: " . $e->getMessage());
  echo json_encode(["status" => "error", "message" => "Failed to create Razorpay order: " . $e->getMessage()]);
}

