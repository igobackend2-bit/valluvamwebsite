<?php
// Logs a maintenance record for an asset AND updates the parent asset's
// status to under_maintenance (and condition, if supplied).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'assets.edit');

$asset_id = (int)($_POST['asset_id'] ?? 0);
$maintenance_date = trim($_POST['maintenance_date'] ?? '');
$description = trim($_POST['description'] ?? '');
$cost = $_POST['cost'] ?? '';
$performed_by = trim($_POST['performed_by'] ?? '');
$next_due_date = trim($_POST['next_due_date'] ?? '');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

if (!$asset_id || !$maintenance_date || !$description) {
    echo json_encode(['status' => 'error', 'message' => 'asset_id, maintenance_date and description are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ? FOR UPDATE");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Asset not found']);
        exit;
    }

    $insert = $pdo->prepare("INSERT INTO asset_maintenance (asset_id, maintenance_date, description, cost, performed_by, next_due_date, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([$asset_id, $maintenance_date, $description, $cost !== '' ? $cost : null, $performed_by ?: null, $next_due_date ?: null, $adminUsername]);
    $maintId = $pdo->lastInsertId();

    $update = $pdo->prepare("UPDATE assets SET status = 'under_maintenance', updated_by = ? WHERE id = ?");
    $update->execute([$adminUsername, $asset_id]);

    $pdo->commit();

    log_audit($pdo, 'maintenance', 'assets', $asset_id, ['status' => $asset['status']], ['status' => 'under_maintenance', 'maintenance_id' => $maintId]);

    echo json_encode(['status' => 'success', 'maintenance_id' => (int)$maintId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error logging asset maintenance: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to log maintenance: ' . $e->getMessage()]);
}
