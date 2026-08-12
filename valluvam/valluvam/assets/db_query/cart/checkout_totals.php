<?php
session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if ($_POST['action'] === 'add_to_cart') {
    $item = [
        'product_id' => $_POST['product_id'],
        'name'       => $_POST['name'],
        'price'      => $_POST['price'],
        'qty'        => $_POST['qty']
    ];

    // prevent duplicates → update qty if same product
    $found = false;
    foreach ($_SESSION['cart'] as &$cartItem) {
        if ($cartItem['product_id'] == $item['product_id']) {
            $cartItem['qty'] += $item['qty'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $_SESSION['cart'][] = $item;
    }

    echo json_encode(['status' => 'success', 'message' => 'Product added to cart']);
}
