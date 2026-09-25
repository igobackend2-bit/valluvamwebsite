<?php
// Lists ALL stock-outs regardless of origin (manual, and — in future —
// programmatically created by sales/DC/manual-sales modules), per the brief.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$reference_type = $_GET['reference_type'] ?? '';
$warehouse_id = $_GET['warehouse_id'] ?? '';

try {
    $sql = "SELECT so.id, so.stock_out_number, so.stock_out_date, so.reference_type, so.reference_number,
                   so.warehouse_id, w.name AS warehouse_name, so.vehicle_number, so.customer_name, so.reason,
                   so.authorized_by, so.created_by, so.created_at,
                   (SELECT COUNT(*) FROM stock_out_items soi WHERE soi.stock_out_id = so.id) AS item_count,
                   (SELECT COALESCE(SUM(soi.quantity), 0) FROM stock_out_items soi WHERE soi.stock_out_id = so.id) AS total_quantity
            FROM stock_outs so
            LEFT JOIN warehouses w ON w.id = so.warehouse_id
            WHERE 1 = 1";
    $params = [];

    if ($reference_type !== '') { $sql .= " AND so.reference_type = ?"; $params[] = $reference_type; }
    if ($warehouse_id !== '') { $sql .= " AND so.warehouse_id = ?"; $params[] = $warehouse_id; }

    $sql .= " ORDER BY so.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stock_outs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'stock_outs' => $stock_outs]);
} catch (PDOException $e) {
    error_log("Error fetching stock outs: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'stock_outs' => []]);
}
