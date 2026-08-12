<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db_query/config.php';
require_once __DIR__ . '/../../db_query/razorpay.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Razorpay\Api\Api;

try {
  // 1) Basic validation
  $required = ['first_name','last_name','email','phone','state','street_address','city','postcode','payment_method','terms'];
  foreach ($required as $f) {
    if (empty($_POST[$f])) throw new Exception("Missing field: $f");
  }

  $paymentMethod = $_POST['payment_method']; // RZP or COD
  if (!in_array($paymentMethod, ['RZP','COD'])) throw new Exception("Invalid payment method");

  // 2) Get cart + total from session (you likely set these earlier)
  $cart = $_SESSION['cart'] ?? [];
  $total = (float) ($_SESSION['total'] ?? 0);
  if ($total <= 0 || empty($cart)) throw new Exception("Cart is empty");

  // Amount in paise for Razorpay
  $amountPaise = (int) round($total * 100);

  // 3) Create local order (pending)
  $receipt = strtoupper(bin2hex(random_bytes(6))); // e.g. A1B2C3D4E5F6
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    INSERT INTO orders
    (receipt, first_name, last_name, email, phone, state, city, street_address, apartment, postcode,
     amount, amount_paise, payment_method, payment_status)
    VALUES
    (:receipt,:first_name,:last_name,:email,:phone,:state,:city,:street_address,:apartment,:postcode,
     :amount,:amount_paise,:payment_method,'pending')
  ");
  $stmt->execute([
    ':receipt' => $receipt,
    ':first_name' => $_POST['first_name'],
    ':last_name'  => $_POST['last_name'],
    ':email'      => $_POST['email'],
    ':phone'      => $_POST['phone'],
    ':state'      => $_POST['state'],
    ':city'       => $_POST['city'],
    ':street_address' => $_POST['street_address'],
    ':apartment'  => $_POST['apartment'] ?? '',
    ':postcode'   => $_POST['postcode'],
    ':amount'     => $total,
    ':amount_paise'=> $amountPaise,
    ':payment_method' => $paymentMethod
  ]);
  $orderId = (int)$pdo->lastInsertId();

  // Order items
  $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
  foreach ($cart as $item) {
    $stmtItem->execute([$orderId, $item['product_id'], $item['quantity'], $item['dis_price']]);
  }

  // 4) If Razorpay, create RZP order
  $razorpayOrderId = null;
  if ($paymentMethod === 'RZP') {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $rzpOrder = $api->order->create([
      'receipt'         => $receipt,
      'amount'          => $amountPaise, // in paise
      'currency'        => RAZORPAY_CURRENCY,
      'payment_capture' => 1              // auto-capture
    ]);
    $razorpayOrderId = $rzpOrder['id'];

    $upd = $pdo->prepare("UPDATE orders SET razorpay_order_id = :oid WHERE id = :id");
    $upd->execute([':oid' => $razorpayOrderId, ':id' => $orderId]);
  }

  $pdo->commit();

  echo json_encode([
    'status' => 'success',
    'order_id' => $orderId,
    'receipt'  => $receipt,
    'payment_method' => $paymentMethod,
    'key_id' => RAZORPAY_KEY_ID,
    'amount' => $amountPaise,
    'currency' => RAZORPAY_CURRENCY,
    'razorpay_order_id' => $razorpayOrderId,
    'prefill_name' => $_POST['first_name'].' '.$_POST['last_name'],
    'prefill_email'=> $_POST['email'],
    'prefill_phone'=> $_POST['phone']
  ]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}