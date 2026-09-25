<?php
// Assigns an asset to an employee: inserts an asset_assignments row AND
// updates the parent asset's status/assigned_employee.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'assets.edit');

$asset_id = (int)($_POST['asset_id'] ?? 0);
$assigned_to = trim($_POST['assigned_to'] ?? '');
$assigned_date = trim($_POST['assigned_date'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

if (!$asset_id || !$assigned_to || !$assigned_date) {
    echo json_encode(['status' => 'error', 'message' => 'asset_id, assigned_to and assigned_date are required']);
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

    $insert = $pdo->prepare("INSERT INTO asset_assignments (asset_id, assigned_to, assigned_date, notes, created_by)
                              VALUES (?, ?, ?, ?, ?)");
    $insert->execute([$asset_id, $assigned_to, $assigned_date, $notes ?: null, $adminUsername]);
    $assignmentId = $pdo->lastInsertId();

    $update = $pdo->prepare("UPDATE assets SET status = 'assigned', assigned_employee = ?, updated_by = ? WHERE id = ?");
    $update->execute([$assigned_to, $adminUsername, $asset_id]);

    $pdo->commit();

    log_audit($pdo, 'assign', 'assets', $asset_id, ['status' => $asset['status'], 'assigned_employee' => $asset['assigned_employee']], ['status' => 'assigned', 'assigned_employee' => $assigned_to]);

    echo json_encode(['status' => 'success', 'assignment_id' => (int)$assignmentId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error assigning asset: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to assign asset: ' . $e->getMessage()]);
}
