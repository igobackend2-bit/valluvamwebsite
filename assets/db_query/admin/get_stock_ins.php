<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$status = $_GET['status'] ?? '';
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
    if ($date_from !== '') { $sql .= " AND si.stock_in_date >= ?"; $params[] = $date_from; }
    if ($date_to !== '') { $sql .= " AND si.stock_in_date <= ?"; $params[] = $date_to; }

    $sql .= " ORDER BY si.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stock_ins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Not every stock receipt has a stock_ins document. Imports and older
    // inventory actions are recorded directly in the stock_movements ledger.
    // Include those rows so the history page shows all stock received, not
    // only receipts created from the newer Stock In form.
    $documentNumbers = array_flip(array_filter(array_column($stock_ins, 'stock_in_number')));
    try {
        $movementSql = "SELECT sm.id, sm.reference_number, sm.warehouse_id, w.name AS warehouse_name,
                               sm.quantity, sm.created_by, sm.created_at
                        FROM stock_movements sm
                        LEFT JOIN warehouses w ON w.id = sm.warehouse_id
                        WHERE sm.movement_type = 'stock_in'";
        $movementParams = [];
        if ($warehouse_id !== '') { $movementSql .= " AND sm.warehouse_id = ?"; $movementParams[] = $warehouse_id; }
        if ($date_from !== '') { $movementSql .= " AND DATE(sm.created_at) >= ?"; $movementParams[] = $date_from; }
        if ($date_to !== '') { $movementSql .= " AND DATE(sm.created_at) <= ?"; $movementParams[] = $date_to; }
        $movementSql .= " ORDER BY sm.created_at DESC";

        $movementStmt = $pdo->prepare($movementSql);
        $movementStmt->execute($movementParams);
        foreach ($movementStmt->fetchAll(PDO::FETCH_ASSOC) as $movement) {
            // A completed Stock In already appears above as its document.
            if ($movement['reference_number'] && isset($documentNumbers[$movement['reference_number']])) {
                continue;
            }
            $stock_ins[] = [
                'id' => 'movement-' . $movement['id'],
                'stock_in_number' => $movement['reference_number'] ?: 'Stock movement #' . $movement['id'],
                'stock_in_date' => substr($movement['created_at'], 0, 10),
                'supplier_id' => null,
                'supplier_name' => null,
                'purchase_reference' => null,
                'warehouse_id' => $movement['warehouse_id'],
                'warehouse_name' => $movement['warehouse_name'],
                'received_by' => null,
                'vehicle_number' => null,
                'remarks' => 'Recorded from inventory movement',
                'attachment_note' => null,
                'status' => 'completed',
                'created_by' => $movement['created_by'],
                'created_at' => $movement['created_at'],
                'item_count' => 1,
                'total_quantity' => $movement['quantity'],
            ];
        }
    } catch (PDOException $e) {
        // The document list remains usable on databases that predate the
        // movement ledger.
        error_log('Error fetching stock-in movements: ' . $e->getMessage());
    }

    // The long-standing Inventory adjustment screen stores its records in
    // stock_history. Include its positive adjustments too, so pre-ledger
    // stock-ins remain visible here.
    try {
        $legacySql = "SELECT sh.id, sh.quantity_change, sh.reason, sh.admin_username, sh.created_at
                      FROM stock_history sh
                      WHERE sh.change_type = 'stock_in'";
        $legacyParams = [];
        if ($date_from !== '') { $legacySql .= " AND DATE(sh.created_at) >= ?"; $legacyParams[] = $date_from; }
        if ($date_to !== '') { $legacySql .= " AND DATE(sh.created_at) <= ?"; $legacyParams[] = $date_to; }
        $legacySql .= " ORDER BY sh.created_at DESC";

        $legacyStmt = $pdo->prepare($legacySql);
        $legacyStmt->execute($legacyParams);
        foreach ($legacyStmt->fetchAll(PDO::FETCH_ASSOC) as $legacy) {
            $stock_ins[] = [
                'id' => 'legacy-' . $legacy['id'],
                'stock_in_number' => 'Stock adjustment #' . $legacy['id'],
                'stock_in_date' => substr($legacy['created_at'], 0, 10),
                'supplier_id' => null,
                'supplier_name' => null,
                'purchase_reference' => null,
                'warehouse_id' => 1,
                'warehouse_name' => 'Main Warehouse',
                'received_by' => null,
                'vehicle_number' => null,
                'remarks' => $legacy['reason'] ?: 'Recorded from inventory adjustment',
                'attachment_note' => null,
                'status' => 'completed',
                'created_by' => $legacy['admin_username'],
                'created_at' => $legacy['created_at'],
                'item_count' => 1,
                'total_quantity' => $legacy['quantity_change'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Error fetching legacy stock-ins: ' . $e->getMessage());
    }

    usort($stock_ins, static function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    echo json_encode(['status' => 'success', 'stock_ins' => $stock_ins]);
} catch (PDOException $e) {
    error_log("Error fetching stock ins: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Unable to load stock ins. Please check the inventory database migration.']);
}
