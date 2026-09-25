<?php
// Single asset + assignment history + maintenance history — for asset_detail.php.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'assets.view');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT a.*, s.supplier_name FROM assets a
                            LEFT JOIN suppliers s ON s.id = a.supplier_id WHERE a.id = ?");
    $stmt->execute([$id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        echo json_encode(['status' => 'error', 'message' => 'Asset not found']);
        exit;
    }

    $asgnStmt = $pdo->prepare("SELECT * FROM asset_assignments WHERE asset_id = ? ORDER BY id DESC");
    $asgnStmt->execute([$id]);
    $asset['assignments'] = $asgnStmt->fetchAll(PDO::FETCH_ASSOC);

    $maintStmt = $pdo->prepare("SELECT * FROM asset_maintenance WHERE asset_id = ? ORDER BY id DESC");
    $maintStmt->execute([$id]);
    $asset['maintenance'] = $maintStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'asset' => $asset]);
} catch (PDOException $e) {
    error_log("Error fetching asset: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load asset']);
}
