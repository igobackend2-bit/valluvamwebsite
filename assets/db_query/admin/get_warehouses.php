<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'warehouses.view');

try {
    $stmt = $pdo->query("SELECT id, name, code, location, manager_name, contact, capacity, status, created_at FROM warehouses ORDER BY name ASC");
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'warehouses' => $warehouses]);
} catch (PDOException $e) {
    error_log("Error fetching warehouses: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'warehouses' => []]);
}
