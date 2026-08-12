<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");


require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_logged_in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'fetch') {
    $sql = "SELECT c.id AS cart_id, p.id AS product_id, p.product_name, p.category, p.image, 
                   p.dis_price, c.quantity
            FROM cart c
            JOIN product_details p ON c.product_id = p.id
            WHERE c.user_id = ? and c.status='pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'cart' => $items]);
    exit;
} elseif ($action === 'add') {
    $product_id = isset($_POST['product_id']) ? trim((string) $_POST['product_id']) : '';

    if ($product_id === '' || !ctype_digit($product_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product.']);
        exit;
    }
    $product_id = (int) $product_id;

    try {
        // Ensure product exists
        $prodCheck = $pdo->prepare("SELECT id FROM product_details WHERE id = ?");
        $prodCheck->execute([$product_id]);
        if (!$prodCheck->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
            exit;
        }

        // Check if already in cart (only pending rows)
        $check = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ? AND (status = 'pending' OR status IS NULL)");
        $check->execute([$user_id, $product_id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?");
            $update->execute([$existing['id']]);
            if ($update->rowCount() === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Could not update cart.']);
                exit;
            }
        } else {
            // Insert new row; use status='pending' if column exists so item shows in cart
            $hasStatus = $pdo->query("SHOW COLUMNS FROM cart LIKE 'status'")->rowCount() > 0;
            if ($hasStatus) {
                $insert = $pdo->prepare("INSERT INTO cart (user_id, username, product_id, quantity, status) VALUES (?, ?, ?, 1, 'pending')");
            } else {
                $insert = $pdo->prepare("INSERT INTO cart (user_id, username, product_id, quantity) VALUES (?, ?, ?, 1)");
            }
            $insert->execute([$user_id, $username, $product_id]);
            if ($insert->rowCount() === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Could not add to cart.']);
                exit;
            }
        }

        echo json_encode(['status' => 'success']);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not add to cart. Please try again.']);
        exit;
    }
} elseif ($_POST['action'] === 'proceedtocheckout') {
    // Fetch current cart items for the user
    $stmt = $pdo->prepare("SELECT c.id AS cart_id, p.id AS product_id, p.product_name, p.category, p.image, p.dis_price, c.quantity
                           FROM cart c
                           JOIN product_details p ON c.product_id = p.id
                           WHERE c.user_id = ? AND c.status='pending'");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        echo json_encode(['status' => 'error', 'message' => 'Your cart is empty.']);
        exit;
    }

    // Calculate subtotal from cart items
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['dis_price'] * $item['quantity'];
    }
    
    $delivery = 0.00;
    $discount = 3.00;
    $total = max(0, $subtotal + $delivery - $discount);
  
    $_SESSION['checkout_subtotal'] = $subtotal;
    $_SESSION['checkout_delivery'] = $delivery;
    $_SESSION['checkout_discount'] = $discount;
    $_SESSION['checkout_total'] = $total;
    $_SESSION['checkout_cart_items'] = $cart_items; // Store full cart details
  
    echo json_encode(['status' => 'success']);
    exit;
}
  



// echo json_encode(['status' => 'error', 'message' => 'invalid action']);
// exit;


if ($action === 'remove') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);

    if ($cart_id > 0) {
        try {
            // First, get current quantity
            $check = $pdo->prepare("SELECT quantity FROM cart WHERE id = ? AND user_id = ?");
            $check->execute([$cart_id, $user_id]);
            $item = $check->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                echo json_encode(['status' => 'error', 'message' => 'Item not found in cart']);
                exit;
            }

            $current_quantity = (int)$item['quantity'];

            // If quantity is 1, delete the item completely
            if ($current_quantity <= 1) {
                $delete = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $delete->execute([$cart_id, $user_id]);

                if ($delete->rowCount() > 0) {
                    echo json_encode([
                        'status' => 'success', 
                        'message' => 'Item removed from cart',
                        'removed' => true,
                        'new_quantity' => 0
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
                }
            } else {
                // Decrease quantity by 1
                $update = $pdo->prepare("UPDATE cart SET quantity = quantity - 1 WHERE id = ? AND user_id = ?");
                $update->execute([$cart_id, $user_id]);

                if ($update->rowCount() > 0) {
                    $new_quantity = $current_quantity - 1;
                    echo json_encode([
                        'status' => 'success', 
                        'message' => 'Quantity decreased',
                        'removed' => false,
                        'new_quantity' => $new_quantity
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update quantity']);
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to remove item: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid cart item ID']);
    exit;
}
