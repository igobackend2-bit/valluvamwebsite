<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); 

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../razorpay.php';
// Project root is 4 levels up from payment/ (payment -> order -> db_query -> assets -> root)
$projectRoot = dirname(__DIR__, 4);
$autoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Payment dependencies not found. Please run composer install.']);
    exit;
}
require_once $autoload;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer (same project root as autoload)
require $projectRoot . '/vendor/phpmailer/phpmailer/src/Exception.php';
require $projectRoot . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require $projectRoot . '/vendor/phpmailer/phpmailer/src/SMTP.php';

try {
  if (empty($_POST['razorpay_order_id']) || empty($_POST['razorpay_payment_id']) || empty($_POST['razorpay_signature'])) {
    throw new Exception("Missing payment fields");
  }

  $razorpayOrderId = $_POST['razorpay_order_id'];
  $razorpayPaymentId = $_POST['razorpay_payment_id'];
  $razorpaySignature = $_POST['razorpay_signature'];

  // Check if order already exists (shouldn't for new flow, but handle it)
  $stmt = $pdo->prepare("SELECT id, receipt, first_name, last_name, email, phone, state, city, street_address, apartment, postcode, amount FROM orders WHERE razorpay_order_id = :oid LIMIT 1");
  $stmt->execute([':oid' => $razorpayOrderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  // If order doesn't exist, get from session (new flow)
  if (!$order) {
    if (empty($_SESSION['pending_order']) || $_SESSION['pending_order']['razorpay_order_id'] !== $razorpayOrderId) {
      throw new Exception("Order data not found. Please try again.");
    }
    $orderData = $_SESSION['pending_order'];
  } else {
    // Order already exists (old flow or duplicate payment attempt)
    $orderData = null;
  }

  // Verify signature
  $generated = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
  if (!hash_equals($generated, $razorpaySignature)) {
    throw new Exception("Signature mismatch");
  }

  $pdo->beginTransaction();

  // If order doesn't exist, create it now (after successful payment)
  if (!$order && $orderData) {
    // Insert into orders
    $stmt = $pdo->prepare("INSERT INTO orders 
          (receipt, first_name, last_name, email, phone, state, city, street_address, apartment, postcode, amount, amount_paise, payment_method, payment_status, razorpay_order_id, razorpay_payment_id, razorpay_signature) 
          VALUES (:receipt, :first_name, :last_name, :email, :phone, :state, :city, :street_address, :apartment, :postcode, :amount, :amount_paise, :payment_method, 'paid', :razorpay_order_id, :razorpay_payment_id, :razorpay_signature)");

    $stmt->execute([
      ":receipt" => $orderData['receipt'],
      ":first_name" => $orderData['first_name'],
      ":last_name" => $orderData['last_name'],
      ":email" => $orderData['email'],
      ":phone" => $orderData['phone'],
      ":state" => $orderData['state'],
      ":city" => $orderData['city'],
      ":street_address" => $orderData['street_address'],
      ":apartment" => $orderData['apartment'],
      ":postcode" => $orderData['postcode'],
      ":amount" => $orderData['amount'],
      ":amount_paise" => $orderData['amount_paise'],
      ":payment_method" => $orderData['payment_method'],
      ":razorpay_order_id" => $razorpayOrderId,
      ":razorpay_payment_id" => $razorpayPaymentId,
      ":razorpay_signature" => $razorpaySignature
    ]);

    $order_id = $pdo->lastInsertId();

    // Insert order items
    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                 VALUES (:order_id, :product_id, :quantity, :price)");

    foreach ($orderData['cart_items'] as $item) {
      $itemStmt->execute([
        ":order_id"   => $order_id,
        ":product_id" => $item['product_id'],
        ":quantity"   => $item['quantity'],
        ":price"      => $item['price']
      ]);
    }

    // Update cart status to purchased
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id) {
      $productIds = array_column($orderData['cart_items'], 'product_id');
      if (!empty($productIds)) {
        $in = str_repeat('?,', count($productIds) - 1) . '?';
        $updateCart = $pdo->prepare("UPDATE cart SET status = 'purchased' WHERE product_id IN ($in) AND user_id = ? AND status = 'pending'");
        $params = array_merge($productIds, [$user_id]);
        $updateCart->execute($params);
      }
    }

    // Clear pending order from session
    unset($_SESSION['pending_order']);

    // Get order details for email
    $order = [
      'id' => $order_id,
      'receipt' => $orderData['receipt'],
      'first_name' => $orderData['first_name'],
      'last_name' => $orderData['last_name'],
      'email' => $orderData['email'],
      'phone' => $orderData['phone'],
      'state' => $orderData['state'],
      'city' => $orderData['city'],
      'street_address' => $orderData['street_address'],
      'apartment' => $orderData['apartment'],
      'postcode' => $orderData['postcode'],
      'amount' => $orderData['amount']
    ];

    // Get order items for email (format from cart_items)
    $items = [];
    foreach ($orderData['cart_items'] as $item) {
      $items[] = [
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'product_name' => $item['product_name']
      ];
    }
  } else {
    // Order already exists, just update payment status
    $upd = $pdo->prepare("UPDATE orders SET razorpay_payment_id = :pid, razorpay_signature = :sig, payment_status = 'paid' WHERE razorpay_order_id = :oid");
    $upd->execute([
      ':pid' => $razorpayPaymentId,
      ':sig' => $razorpaySignature,
      ':oid' => $razorpayOrderId
    ]);

    // Update cart status to purchased
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id) {
      $updateCart = $pdo->prepare("UPDATE cart SET status = 'purchased' WHERE user_id = ? AND status = 'pending'");
      $updateCart->execute([$user_id]);
    }

    // Get order items for email
    $itemsStmt = $pdo->prepare("
      SELECT oi.quantity, oi.price, p.product_name 
      FROM order_items oi 
      JOIN product_details p ON oi.product_id = p.id 
      WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$order['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
  }

  // Build items HTML for email (common for both flows)

  // Build items HTML for email
  $itemsHtml = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>
        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>";
  foreach ($items as $item) {
    $itemTotal = $item['quantity'] * $item['price'];
    $itemsHtml .= "<tr>
            <td>{$item['product_name']}</td>
            <td>{$item['quantity']}</td>
            <td>&#8377; {$item['price']}</td>
            <td>&#8377; {$itemTotal}</td>
        </tr>";
  }
  $itemsHtml .= "<tr><td colspan='3'><b>Grand Total</b></td><td><b>&#8377; {$order['amount']}</b></td></tr></table>";

  // SendMail function
  function sendMail(string $toEmail, string $toName, string $subject, string $body): array
  {
    try {
      $mail = new PHPMailer(true);
      $mail->isSMTP();
      $mail->Host       = "smtp.gmail.com";
      $mail->SMTPAuth   = true;
      $mail->Username   = "marketing@igogroups.com";
      $mail->Password   = "ochv yqhv gvml fqxa";
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = 587;
      $mail->CharSet = "UTF-8";
      $mail->setFrom("marketing@igogroups.com", "ValluvamProducts");
      $mail->addAddress($toEmail, $toName);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body    = $body;
      $mail->send();
      return ["ok" => true];
    } catch (Throwable $e) {
      error_log("MAIL FAIL to={$toEmail} subject={$subject} err=" . $e->getMessage());
      return ["ok" => false, "error" => $e->getMessage()];
    }
  }

  // Send customer confirmation email
  $customerBody = "
        <h3>Dear {$order['first_name']},</h3>
        <p>Thank you for shopping with us! Your payment has been received successfully.</p>
        <p><b>Order ID:</b> {$order['receipt']}</p>
        <p><b>Payment Method:</b> Razorpay (Online Payment)</p>
        <p><b>Payment Status:</b> Paid</p>
        <p><b>Payment ID:</b> {$razorpayPaymentId}</p>
        <p><b>Shipping Address:</b><br>
           {$order['street_address']}, {$order['apartment']}<br>
           {$order['city']}, {$order['state']} - {$order['postcode']}<br>
           Phone: {$order['phone']}
        </p>
        {$itemsHtml}
        <p>We will notify you once your order is out for delivery.</p>
        <p>Regards,<br><b>Valluvam Products</b></p>
    ";
  sendMail($order['email'], $order['first_name'] . " " . $order['last_name'], "Payment Confirmed - Order {$order['receipt']}", $customerBody);

  // Send admin notification
  $adminBody = "
        <h3>Payment Received - New Order</h3>
        <p><b>Order ID:</b> {$order['receipt']}</p>
        <p><b>Payment Method:</b> Razorpay</p>
        <p><b>Payment ID:</b> {$razorpayPaymentId}</p>
        <p><b>Customer:</b> {$order['first_name']} {$order['last_name']}</p>
        <p><b>Email:</b> {$order['email']}</p>
        <p><b>Phone:</b> {$order['phone']}</p>
        <p><b>Address:</b> {$order['street_address']}, {$order['apartment']}<br>
           {$order['city']}, {$order['state']} - {$order['postcode']}</p>
        {$itemsHtml}
    ";
  sendMail("admin@valluvam.com", "Valluvam Admin", "Payment Received - Order {$order['receipt']}", $adminBody);

  $pdo->commit();

  echo json_encode([
    'status' => 'success',
    'order_id' => $order['id'],
    'receipt' => $order['receipt']
  ]);
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  // Mark as failed
  if (isset($razorpayOrderId)) {
    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE razorpay_order_id = ?")->execute([$razorpayOrderId]);
  }
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
