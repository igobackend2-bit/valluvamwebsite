<?php
// Single manual sale + its line items.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM manual_sales WHERE id = ?");
    $stmt->execute([$id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo json_encode(['status' => 'error', 'message' => 'Manual sale not found']);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT msi.*, pd.product_name FROM manual_sale_items msi
                                 LEFT JOIN product_details pd ON pd.id = msi.product_id
                                 WHERE msi.manual_sale_id = ? ORDER BY msi.id ASC");
    $itemsStmt->execute([$id]);
    $sale['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'manual_sale' => $sale]);
} catch (PDOException $e) {
    error_log("Error fetching manual sale: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load manual sale']);
}
