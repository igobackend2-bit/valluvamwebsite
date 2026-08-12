<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_logged_in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Get user email first
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['email'])) {
        echo json_encode(['status' => 'not_found']);
        exit;
    }
    
    // Get the most recent order address for this user
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email, phone, state, city, street_address, apartment, postcode
        FROM orders 
        WHERE email = ?
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$user['email']]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($address) {
        echo json_encode([
            'status' => 'success',
            'address' => $address
        ]);
    } else {
        // If no previous order, try to get from users table
        $userStmt = $pdo->prepare("SELECT email, phone_number as phone FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'status' => 'success',
                'address' => [
                    'email' => $user['email'] ?? '',
                    'phone' => $user['phone'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

