<?php
// Create or update an asset. Create requires assets.create, update requires assets.edit.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = $_POST['id'] ?? '';

if ($id) {
    require_permission($pdo, 'assets.edit');
} else {
    require_permission($pdo, 'assets.create');
}

$asset_name    = trim($_POST['asset_name'] ?? '');
$asset_category = trim($_POST['asset_category'] ?? '');
$asset_type    = trim($_POST['asset_type'] ?? '');
$purchase_date = trim($_POST['purchase_date'] ?? '');
$purchase_cost = $_POST['purchase_cost'] ?? 0;
$current_value = $_POST['current_value'] ?? '';
$supplier_id   = $_POST['supplier_id'] ?? '';
$serial_number = trim($_POST['serial_number'] ?? '');
$model_number  = trim($_POST['model_number'] ?? '');
$location      = trim($_POST['location'] ?? '');
$department    = trim($_POST['department'] ?? '');
$assigned_employee = trim($_POST['assigned_employee'] ?? '');
$warranty_start_date = trim($_POST['warranty_start_date'] ?? '');
$warranty_end_date   = trim($_POST['warranty_end_date'] ?? '');
$document_note = trim($_POST['document_note'] ?? '');
$asset_condition = $_POST['asset_condition'] ?? 'new';
$notes = trim($_POST['notes'] ?? '');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

if (!$asset_name || !$asset_category) {
    echo json_encode(['status' => 'error', 'message' => 'Asset name and category are required']);
    exit;
}

$validConditions = ['new','good','fair','damaged','critical'];
if (!in_array($asset_condition, $validConditions)) {
    $asset_condition = 'new';
}

try {
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
            echo json_encode(['status' => 'error', 'message' => 'Asset not found']);
            exit;
        }

        $update = $pdo->prepare("UPDATE assets SET asset_name=?, asset_category=?, asset_type=?, purchase_date=?,
                                  purchase_cost=?, current_value=?, supplier_id=?, serial_number=?, model_number=?,
                                  location=?, department=?, assigned_employee=?, warranty_start_date=?, warranty_end_date=?,
                                  document_note=?, asset_condition=?, notes=?, updated_by=? WHERE id=?");
        $update->execute([
            $asset_name, $asset_category, $asset_type ?: null, $purchase_date ?: null,
            $purchase_cost ?: 0, $current_value !== '' ? $current_value : null, $supplier_id !== '' ? (int)$supplier_id : null,
            $serial_number ?: null, $model_number ?: null, $location ?: null, $department ?: null,
            $assigned_employee ?: null, $warranty_start_date ?: null, $warranty_end_date ?: null,
            $document_note ?: null, $asset_condition, $notes ?: null, $adminUsername, $id
        ]);

        log_audit($pdo, 'update', 'assets', $id, $old, $_POST);
        echo json_encode(['status' => 'success', 'id' => (int)$id]);
    } else {
        $assetId = next_document_number($pdo, 'asset', 'AST');

        $insert = $pdo->prepare("INSERT INTO assets (asset_id, asset_name, asset_category, asset_type, purchase_date,
                                  purchase_cost, current_value, supplier_id, serial_number, model_number, location,
                                  department, assigned_employee, warranty_start_date, warranty_end_date, document_note,
                                  status, asset_condition, notes, created_by)
                                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insert->execute([
            $assetId, $asset_name, $asset_category, $asset_type ?: null, $purchase_date ?: null,
            $purchase_cost ?: 0, $current_value !== '' ? $current_value : null, $supplier_id !== '' ? (int)$supplier_id : null,
            $serial_number ?: null, $model_number ?: null, $location ?: null, $department ?: null,
            $assigned_employee ?: null, $warranty_start_date ?: null, $warranty_end_date ?: null,
            $document_note ?: null, 'active', $asset_condition, $notes ?: null, $adminUsername
        ]);
        $newId = $pdo->lastInsertId();

        log_audit($pdo, 'create', 'assets', $newId, null, array_merge($_POST, ['asset_id' => $assetId]));
        echo json_encode(['status' => 'success', 'id' => (int)$newId, 'asset_id' => $assetId]);
    }
} catch (PDOException $e) {
    error_log("Error saving asset: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save asset: ' . $e->getMessage()]);
}
