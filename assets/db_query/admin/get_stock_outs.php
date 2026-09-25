<?php
// Lists ALL stock-outs regardless of origin (manual, and — in future —
// programmatically created by sales/DC/manual-sales modules), per the brief.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$reference_type = $_GET['reference_type'] ?? '';
$warehouse_id = $_GET['warehouse_id'] ?? '';
// Period filter for reporting/export: 'day' (today), 'week' (last 7 days),
// 'month' (last 30 days), or explicit date_from/date_to (YYYY-MM-DD).
$period = $_GET['period'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
if ($period === 'day') {
    $date_from = $date_to = date('Y-m-d');
} elseif ($period === 'week') {
    $date_from = date('Y-m-d', strtotime('-6 days'));
    $date_to = date('Y-m-d');
} elseif ($period === 'month') {
    $date_from = date('Y-m-d', strtotime('-29 days'));
    $date_to = date('Y-m-d');
}

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
    if ($date_from !== '') { $sql .= " AND so.stock_out_date >= ?"; $params[] = $date_from; }
    if ($date_to !== '') { $sql .= " AND so.stock_out_date <= ?"; $params[] = $date_to; }

    $sql .= " ORDER BY so.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stock_outs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'stock_outs' => $stock_outs]);
} catch (PDOException $e) {
    error_log("Error fetching stock outs: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'stock_outs' => []]);
}
