<?php
// Reports a new waste record. product is optional (packaging waste etc may
// not be tied to a product). Does not affect stock — that happens only on
// approval, see approve_waste.php.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'waste.create');

$date        = trim($_POST['date'] ?? '');
$waste_type  = trim($_POST['waste_type'] ?? '');
$product_id  = $_POST['product_id'] ?? '';
$sku         = trim($_POST['sku'] ?? '');
$quantity    = $_POST['quantity'] ?? '';
$unit        = trim($_POST['unit'] ?? 'pcs');
$warehouse_id = $_POST['warehouse_id'] ?? 1;
$reason      = trim($_POST['reason'] ?? '');
$estimated_value = $_POST['estimated_value'] ?? '';
$disposal_method = trim($_POST['disposal_method'] ?? '');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

$validTypes = ['product_waste','damaged_stock','expired_stock','production_waste','packaging_waste','other'];

if (!$date || !in_array($waste_type, $validTypes, true) || !$reason) {
    echo json_encode(['status' => 'error', 'message' => 'date, waste_type and reason are required']);
    exit;
}

try {
    $wasteId = next_document_number($pdo, 'waste', 'WST');

    $insert = $pdo->prepare("INSERT INTO waste_records (waste_id, date, waste_type, product_id, sku, quantity, unit,
                              warehouse_id, reason, estimated_value, disposal_method, status, created_by)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insert->execute([
        $wasteId, $date, $waste_type, $product_id !== '' ? (int)$product_id : null, $sku ?: null,
        $quantity !== '' ? (int)$quantity : null, $unit ?: 'pcs', $warehouse_id ?: 1, $reason,
        $estimated_value !== '' ? $estimated_value : null, $disposal_method ?: null, 'reported', $adminUsername
    ]);
    $newId = $pdo->lastInsertId();

    log_audit($pdo, 'create', 'waste_records', $newId, null, array_merge($_POST, ['waste_id' => $wasteId]));

    echo json_encode(['status' => 'success', 'id' => (int)$newId, 'waste_id' => $wasteId]);
} catch (PDOException $e) {
    error_log("Error saving waste record: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save waste record: ' . $e->getMessage()]);
}
