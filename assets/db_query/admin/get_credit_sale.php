<?php
// Fetch one credit sale with its line items and payment history.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/credit_sale_helper.php';

require_admin_session();
ensure_credit_sale_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM credit_sales WHERE id = ?");
    $stmt->execute([$id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo json_encode(['status' => 'error', 'message' => 'Credit sale not found']);
        exit;
    }

    $itemStmt = $pdo->prepare("SELECT ci.*, pd.product_name FROM credit_sale_items ci
                                LEFT JOIN product_details pd ON pd.id = ci.product_id
                                WHERE ci.credit_sale_id = ?");
    $itemStmt->execute([$id]);
    $sale['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $payStmt = $pdo->prepare("SELECT * FROM credit_sale_payments WHERE credit_sale_id = ? ORDER BY created_at ASC, id ASC");
    $payStmt->execute([$id]);
    $sale['payments'] = $payStmt->fetchAll(PDO::FETCH_ASSOC);

    $sale['balance_due'] = round((float)$sale['grand_total'] - (float)$sale['amount_paid'], 2);

    echo json_encode(['status' => 'success', 'credit_sale' => $sale]);
} catch (PDOException $e) {
    error_log("Error fetching credit sale: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load credit sale: ' . $e->getMessage()]);
}
