<?php
// Single customer's details for auto-filling the New Invoice form the
// moment an existing customer is picked from the dropdown: name, mobile,
// email, and a best-effort billing address pulled from their most recent
// website order (orders are keyed by email, not user_id — same guest-style
// join get_customers.php already uses for its order_count column).
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, phone_number FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
        exit;
    }

    $address = '';
    try {
        $ordStmt = $pdo->prepare("SELECT street_address, apartment, city, state, postcode
                                   FROM orders WHERE email = ? ORDER BY id DESC LIMIT 1");
        $ordStmt->execute([$customer['email']]);
        if ($ord = $ordStmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = array_filter([
                $ord['street_address'] ?? '',
                $ord['apartment'] ?? '',
                $ord['city'] ?? '',
                $ord['state'] ?? '',
                $ord['postcode'] ?? '',
            ], function ($v) { return trim((string)$v) !== ''; });
            $address = implode(', ', $parts);
        }
    } catch (PDOException $e) {
        // orders table shape mismatch — leave address blank, don't fail the whole lookup.
    }

    echo json_encode(['status' => 'success', 'customer' => [
        'id' => $customer['id'],
        'name' => $customer['username'],
        'mobile' => $customer['phone_number'],
        'email' => $customer['email'],
        'address' => $address,
    ]]);
} catch (PDOException $e) {
    error_log("Error fetching customer details: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch customer details']);
}
