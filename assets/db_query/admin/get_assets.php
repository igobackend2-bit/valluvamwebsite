<?php
// List assets with optional filters: status, asset_category, department, q
// (search asset_id/asset_name/serial_number/assigned_employee).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'assets.view');

$status   = trim($_GET['status'] ?? '');
$category = trim($_GET['asset_category'] ?? '');
$department = trim($_GET['department'] ?? '');
$q        = trim($_GET['q'] ?? '');

try {
    $sql = "SELECT a.*, s.supplier_name FROM assets a
            LEFT JOIN suppliers s ON s.id = a.supplier_id WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    if ($category !== '') {
        $sql .= " AND a.asset_category = ?";
        $params[] = $category;
    }
    if ($department !== '') {
        $sql .= " AND a.department = ?";
        $params[] = $department;
    }
    if ($q !== '') {
        $sql .= " AND (a.asset_id LIKE ? OR a.asset_name LIKE ? OR a.serial_number LIKE ? OR a.assigned_employee LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY a.id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'assets' => $assets]);
} catch (PDOException $e) {
    error_log("Error listing assets: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'assets' => []]);
}
