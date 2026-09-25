<?php
// ✅ Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/var/log/php_errors.log'); // run: sudo tail -f /var/log/php_errors.log

header('Content-Type: application/json');
session_start();

// ✅ PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Razorpay\Api\Api;

require_once __DIR__ . '/../../../vendor/autoload.php';   // Correct path
require_once __DIR__ . '/../config.php';         // DB config


// Load PHPMailer
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/SMTP.php';

// ✅ Validate required fields
$required = ["first_name", "last_name", "email", "phone", "state", "city", "street_address", "postcode", "payment_method"];
foreach ($required as $field) {
  if (empty($_POST[$field])) {
    echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
    exit;
  }
}

// ✅ Validate payment method
$paymentMethod = $_POST['payment_method'] ?? 'COD';
if (!in_array($paymentMethod, ['COD', 'RZP'])) {
  echo json_encode(["status" => "error", "message" => "Invalid payment method"]);
  exit;
}

// ✅ Get user ID
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
  echo json_encode(["status" => "error", "message" => "Login required"]);
  exit;
}

// ✅ Load cart from database (more reliable than session)
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

// ✅ Stock check — block placing the order if any item now has less stock
// available than the customer has in their cart (e.g. sold out since it was
// added, or someone else bought the last of it). Checked here, before the
// order is created, for both COD and Razorpay — not just at COD's deduction
// step below, so an out-of-stock item can never actually be ordered.
$insufficient = [];
$stockCheckStmt = $pdo->prepare("SELECT stock FROM product_details WHERE id = ?");
foreach ($cart as $item) {
  if (empty($item['product_id'])) continue;
  $stockCheckStmt->execute([$item['product_id']]);
  $row = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
  $available = $row ? (int)$row['stock'] : 0;
  if ($available < (int)$item['quantity']) {
    $insufficient[] = $item['product_name'] . ($available > 0 ? " (only {$available} left)" : " (out of stock)");
  }
}
if (!empty($insufficient)) {
  echo json_encode([
    "status" => "error",
    "message" => "Some items in your cart are no longer available in the quantity requested: " . implode(', ', $insufficient) . ". Please update your cart."
  ]);
  exit;
}

// ✅ Calculate total
$subtotal = 0;
foreach ($cart as $item) {
  $subtotal += $item['total'];
}
$delivery = 0.00;
$discount = 3.00;
$amount = max(0, $subtotal + $delivery - $discount);
$amount_paise = (int)round($amount * 100);

// ✅ Generate unique receipt ID
$receipt = "ORD" . time() . rand(1000, 9999);

try {
  $pdo->beginTransaction();

  // ✅ Insert into orders
  $stmt = $pdo->prepare("INSERT INTO orders 
        (receipt, first_name, last_name, email, phone, state, city, street_address, apartment, postcode, amount, amount_paise, payment_method, payment_status) 
        VALUES (:receipt, :first_name, :last_name, :email, :phone, :state, :city, :street_address, :apartment, :postcode, :amount, :amount_paise, :payment_method, :payment_status)");

  $paymentStatus = ($paymentMethod === 'COD') ? 'pending' : 'pending';
  
  $stmt->execute([
    ":receipt" => $receipt,
    ":first_name" => $_POST['first_name'],
    ":last_name" => $_POST['last_name'],
    ":email" => $_POST['email'],
    ":phone" => $_POST['phone'],
    ":state" => $_POST['state'],
    ":city" => $_POST['city'],
    ":street_address" => $_POST['street_address'],
    ":apartment" => $_POST['apartment'] ?? "",
    ":postcode" => $_POST['postcode'],
    ":amount" => $amount,
    ":amount_paise" => $amount_paise,
    ":payment_method" => $paymentMethod,
    ":payment_status" => $paymentStatus
  ]);

  $order_id = $pdo->lastInsertId();

  // ✅ Insert order items
  $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                               VALUES (:order_id, :product_id, :quantity, :price)");

  $productIds = []; // collect product IDs for cart update

  foreach ($cart as $item) {
    $itemStmt->execute([
      ":order_id"   => $order_id,
      ":product_id" => $item['product_id'],
      ":quantity"   => $item['quantity'],
      ":price"      => $item['price']
    ]);

    if (!empty($item['product_id'])) {
      $productIds[] = $item['product_id'];
    }
  }

  // ✅ Deduct stock immediately for Cash on Delivery orders (payment is
  // confirmed at order time for COD). For Razorpay orders, stock is deducted
  // only after payment is verified successfully — see order/payment/verify.php
  // — so an abandoned/failed Razorpay checkout never reserves stock.
  if ($paymentMethod === 'COD') {
    $stockLock = $pdo->prepare("SELECT stock FROM product_details WHERE id = ? FOR UPDATE");
    $stockUpdate = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");
    foreach ($cart as $item) {
      if (empty($item['product_id'])) continue;
      $stockLock->execute([$item['product_id']]);
      $prod = $stockLock->fetch(PDO::FETCH_ASSOC);
      if (!$prod) continue;
      $previousStock = (int)$prod['stock'];
      $newStock = max(0, $previousStock - (int)$item['quantity']);
      $stockUpdate->execute([$newStock, $item['product_id']]);
      // Best-effort movement log — table added by the ERP module; order
      // placement still succeeds even if it isn't present.
      try {
        $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, warehouse_id, quantity,
                        previous_stock, new_stock, reference_type, reference_number, reason, created_by, created_at)
                        VALUES ('stock_out', ?, 1, ?, ?, ?, 'website_order', ?, 'Website order (COD)', 'Website', NOW())")
            ->execute([$item['product_id'], -1 * (int)$item['quantity'], $previousStock, $newStock, $receipt]);
      } catch (PDOException $e) { /* stock_movements not present — ignore */ }
    }
  }

  // ✅ Handle Razorpay order creation
  $razorpayOrderId = null;
  if ($paymentMethod === 'RZP') {
    require_once __DIR__ . '/../razorpay.php';
    
    try {
      $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
      $rzpOrder = $api->order->create([
        'receipt'         => $receipt,
        'amount'          => $amount_paise,
        'currency'        => RAZORPAY_CURRENCY,
        'payment_capture' => 1
      ]);
      $razorpayOrderId = $rzpOrder['id'];
      
      // Update order with Razorpay order ID
      $upd = $pdo->prepare("UPDATE orders SET razorpay_order_id = :oid WHERE id = :id");
      $upd->execute([':oid' => $razorpayOrderId, ':id' => $order_id]);
    } catch (Exception $e) {
      $pdo->rollBack();
      echo json_encode(["status" => "error", "message" => "Razorpay order creation failed: " . $e->getMessage()]);
      exit;
    }
  }

  // ✅ Update cart status → 'purchased'
  if (!empty($productIds)) {
    $in = str_repeat('?,', count($productIds) - 1) . '?';
    $sql = "UPDATE cart 
                SET status = 'purchased' 
                WHERE product_id IN ($in) 
                  AND user_id = ? 
                  AND status = 'pending'";

    $updateCart = $pdo->prepare($sql);
    $params = array_merge($productIds, [$user_id]);
    $updateCart->execute($params);
  }

  $pdo->commit();

  // ✅ Build order items table (for email)
  $itemsHtml = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>
        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>";
  foreach ($cart as $item) {
    $itemTotal = $item['quantity'] * $item['price'];
    $itemsHtml .= "<tr>
            <td>{$item['product_name']}</td>
            <td>{$item['quantity']}</td>
            <td>&#8377; {$item['price']}</td>
            <td>&#8377; {$itemTotal}</td>
        </tr>";
  }
  $itemsHtml .= "<tr><td colspan='3'><b>Grand Total</b></td><td><b>&#8377; {$amount}</b></td></tr></table>";

  // ✅ SendMail function with try/catch
  function sendMail(string $toEmail, string $toName, string $subject, string $body): array
  {
    try {
      $mail = new PHPMailer(true);
      $mail->isSMTP();
      $mail->Host       = "smtp.gmail.com";
      $mail->SMTPAuth   = true;
      $mail->Username   = "marketing@igogroups.com";
      $mail->Password   = "ochv yqhv gvml fqxa"; // create new one
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = 587;

      // IMPORTANT
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

  // ✅ Customer Email (only send for COD, Razorpay will send after payment verification)
  if ($paymentMethod === 'COD') {
    $customerBody = "
          <h3>Dear {$_POST['first_name']},</h3>
          <p>Thank you for shopping with us! Your order has been placed successfully.</p>
          <p><b>Order ID:</b> {$receipt}</p>
          <p><b>Payment Method:</b> Cash on Delivery</p>
          <p><b>Payment Status:</b> Pending (Pay on Delivery)</p>
          <p><b>Shipping Address:</b><br>
             {$_POST['street_address']}, {$_POST['apartment']}<br>
             {$_POST['city']}, {$_POST['state']} - {$_POST['postcode']}<br>
             Phone: {$_POST['phone']}
          </p>
          {$itemsHtml}
          <p>We will notify you once your order is out for delivery.</p>
          <p>Regards,<br><b>Valluvam Products</b></p>
      ";
    sendMail($_POST['email'], $_POST['first_name'] . " " . $_POST['last_name'], "Your Order Confirmation - {$receipt}", $customerBody);
  }

  // ✅ Admin Email (send for both COD and Razorpay)
  $paymentStatusText = ($paymentMethod === 'COD') ? 'Cash on Delivery (Pending)' : 'Razorpay (Pending Payment)';
  $adminBody = "
        <h3>New Order Received</h3>
        <p><b>Order ID:</b> {$receipt}</p>
        <p><b>Customer:</b> {$_POST['first_name']} {$_POST['last_name']}</p>
        <p><b>Email:</b> {$_POST['email']}</p>
        <p><b>Phone:</b> {$_POST['phone']}</p>
        <p><b>Payment Method:</b> {$paymentStatusText}</p>
        <p><b>Address:</b> {$_POST['street_address']}, {$_POST['apartment']}<br>
           {$_POST['city']}, {$_POST['state']} - {$_POST['postcode']}</p>
        {$itemsHtml}
    ";
  sendMail("admin@valluvam.com", "Valluvam Admin", "New Order Received - {$receipt}", $adminBody);

  // ✅ Clear cart (update status to purchased)
  if (!empty($productIds)) {
    $in = str_repeat('?,', count($productIds) - 1) . '?';
    $sql = "UPDATE cart 
            SET status = 'purchased' 
            WHERE product_id IN ($in) 
              AND user_id = ? 
              AND status = 'pending'";
    $updateCart = $pdo->prepare($sql);
    $params = array_merge($productIds, [$user_id]);
    $updateCart->execute($params);
  }

  // ✅ Clear session data
  unset($_SESSION['checkout_cart']);
  unset($_SESSION['checkout_subtotal']);
  unset($_SESSION['checkout_delivery']);
  unset($_SESSION['checkout_discount']);
  unset($_SESSION['checkout_total']);

  $response = [
    "status" => "success",
    "order_id" => $order_id,
    "receipt" => $receipt,
    "payment_method" => $paymentMethod
  ];

  // Add Razorpay data if applicable
  if ($paymentMethod === 'RZP' && $razorpayOrderId) {
    $response['razorpay_order_id'] = $razorpayOrderId;
    $response['key_id'] = RAZORPAY_KEY_ID;
    $response['amount_paise'] = $amount_paise;
    $response['currency'] = RAZORPAY_CURRENCY;
  }

  echo json_encode($response);
} catch (Exception $e) {
  $pdo->rollBack();
  error_log("Order error: " . $e->getMessage());
  echo json_encode(["status" => "error", "message" => "Order failed: " . $e->getMessage()]);
}
