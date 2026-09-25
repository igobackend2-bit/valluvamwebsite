<?php
// Changes an asset's status (Active/Assigned/Under Maintenance/Damaged/Lost/Disposed/Retired).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'assets.edit');

$asset_id = (int)($_POST['asset_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

$validStatuses = ['active','assigned','under_maintenance','damaged','lost','disposed','retired'];

if (!$asset_id || !in_array($status, $validStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'asset_id and a valid status are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        echo json_encode(['status' => 'error', 'message' => 'Asset not found']);
        exit;
    }

    $update = $pdo->prepare("UPDATE assets SET status = ?, updated_by = ? WHERE id = ?");
    $update->execute([$status, $adminUsername, $asset_id]);

    log_audit($pdo, 'status_change', 'assets', $asset_id, ['status' => $asset['status']], ['status' => $status]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error updating asset status: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status: ' . $e->getMessage()]);
}
