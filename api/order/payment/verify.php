<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_query/config.php';
require_once __DIR__ . '/../../db_query/razorpay.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

try {
  if (empty($_POST['razorpay_order_id']) || empty($_POST['razorpay_payment_id']) || empty($_POST['razorpay_signature'])) {
    throw new Exception("Missing payment fields");
  }

  $razorpayOrderId = $_POST['razorpay_order_id'];
  $razorpayPaymentId = $_POST['razorpay_payment_id'];
  $razorpaySignature = $_POST['razorpay_signature'];

  // Find local order by razorpay_order_id
  $stmt = $pdo->prepare("SELECT id FROM orders WHERE razorpay_order_id = :oid LIMIT 1");
  $stmt->execute([':oid' => $razorpayOrderId]);
  $order = $stmt->fetch();
  if (!$order) throw new Exception("Order not found");

  // Verify signature
  $generated = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
  if (!hash_equals($generated, $razorpaySignature)) {
    throw new Exception("Signature mismatch");
  }

  // Update DB
  $upd = $pdo->prepare("UPDATE orders SET razorpay_payment_id = :pid, razorpay_signature = :sig, payment_status = 'paid' WHERE razorpay_order_id = :oid");
  $upd->execute([
    ':pid' => $razorpayPaymentId,
    ':sig' => $razorpaySignature,
    ':oid' => $razorpayOrderId
  ]);

  echo json_encode(['status'=>'success']);
} catch (Exception $e) {
  // Mark as failed if you want:
  // $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE razorpay_order_id = ?")->execute([$razorpayOrderId ?? '']);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}?>