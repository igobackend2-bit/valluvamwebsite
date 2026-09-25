<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$product_id = $_POST['product_id'] ?? 0;
$quantity_change = isset($_POST['quantity_change']) ? (int)$_POST['quantity_change'] : 0;
$reason = trim($_POST['reason'] ?? '');

if (!$product_id || !$quantity_change) {
    echo json_encode(['status' => 'error', 'message' => 'product_id and a non-zero quantity_change are required']);
    exit;
}

try {
    try {
        $pdo->exec("ALTER TABLE product_details ADD COLUMN stock INT NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists — fine.
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT stock, product_name FROM product_details WHERE id = ? FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }

    $newStock = max(0, (int)$product['stock'] + $quantity_change);

    $update = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");
    $update->execute([$newStock, $product_id]);

    $changeType = $quantity_change > 0 ? 'stock_in' : 'stock_out';
    $adminUsername = $_SESSION['admin_username'] ?? 'Admin';

    $insert = $pdo->prepare("INSERT INTO stock_history (product_id, change_type, quantity_change, resulting_stock, reason, admin_username, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insert->execute([$product_id, $changeType, $quantity_change, $newStock, $reason ?: null, $adminUsername]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'stock' => $newStock]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error adjusting stock: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to adjust stock: ' . $e->getMessage()]);
}
