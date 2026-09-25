<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$status = $_GET['status'] ?? '';
$warehouse_id = $_GET['warehouse_id'] ?? '';

try {
    $sql = "SELECT si.id, si.stock_in_number, si.stock_in_date, si.supplier_id, s.supplier_name,
                   si.purchase_reference, si.warehouse_id, w.name AS warehouse_name, si.received_by,
                   si.vehicle_number, si.remarks, si.attachment_note, si.status, si.created_by, si.created_at,
                   (SELECT COUNT(*) FROM stock_in_items sii WHERE sii.stock_in_id = si.id) AS item_count,
                   (SELECT COALESCE(SUM(sii.quantity), 0) FROM stock_in_items sii WHERE sii.stock_in_id = si.id) AS total_quantity
            FROM stock_ins si
            LEFT JOIN suppliers s ON s.id = si.supplier_id
            LEFT JOIN warehouses w ON w.id = si.warehouse_id
            WHERE 1 = 1";
    $params = [];

    if ($status !== '') { $sql .= " AND si.status = ?"; $params[] = $status; }
    if ($warehouse_id !== '') { $sql .= " AND si.warehouse_id = ?"; $params[] = $warehouse_id; }

    $sql .= " ORDER BY si.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stock_ins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'stock_ins' => $stock_ins]);
} catch (PDOException $e) {
    error_log("Error fetching stock ins: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'stock_ins' => []]);
}
