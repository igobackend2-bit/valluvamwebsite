<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min($limit, 200));

    $sql = "SELECT h.id, h.product_id, h.change_type, h.quantity_change, h.resulting_stock, h.reason, h.admin_username, h.created_at,
                   p.product_name
            FROM stock_history h
            LEFT JOIN product_details p ON p.id = h.product_id
            ORDER BY h.id DESC
            LIMIT " . $limit;

    $stmt = $pdo->query($sql);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'history' => $history]);
} catch (PDOException $e) {
    // stock_history may not exist yet if the migration hasn't run —
    // report an empty list rather than a hard error so the page still
    // loads cleanly.
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['status' => 'success', 'history' => []]);
        exit;
    }
    error_log("Error fetching stock history: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch stock history: ' . $e->getMessage()]);
}
