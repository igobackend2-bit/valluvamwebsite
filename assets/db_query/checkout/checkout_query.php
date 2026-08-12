<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';


// Validate required fields
$required_fields = ['first_name', 'last_name', 'state', 'street_address', 'city', 'postcode', 'phone', 'email', 'payment_method', 'terms'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["status" => "error", "message" => "$field is required"]);
        exit;
    }
}

// Insert into orders table
try {
    $stmt = $pdo->prepare("INSERT INTO orders 
        (first_name, last_name, state, street_address, apartment, city, postcode, phone, email, payment_method) 
        VALUES 
        (:first_name, :last_name, :state, :street_address, :apartment, :city, :postcode, :phone, :email, :payment_method)");

    $stmt->execute([
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':state' => $_POST['state'],
        ':street_address' => $_POST['street_address'],
        ':apartment' => $_POST['apartment'] ?? '',
        ':city' => $_POST['city'],
        ':postcode' => $_POST['postcode'],
        ':phone' => $_POST['phone'],
        ':email' => $_POST['email'],
        ':payment_method' => $_POST['payment_method']
    ]);

    $order_id = $pdo->lastInsertId();

    echo json_encode(["status" => "success", "order_id" => $order_id]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
