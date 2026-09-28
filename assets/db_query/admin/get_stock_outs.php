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

    // Sales, website orders, credit sales and adjustments record their stock
    // deduction in stock_movements instead of creating a manual stock_outs
    // document. Include those ledger rows in this history as well.
    $documentNumbers = array_flip(array_filter(array_column($stock_outs, 'stock_out_number')));
    try {
        $movementSql = "SELECT sm.id, sm.reference_type, sm.reference_number, sm.warehouse_id,
                               w.name AS warehouse_name, sm.quantity, sm.reason, sm.created_by, sm.created_at
                        FROM stock_movements sm
                        LEFT JOIN warehouses w ON w.id = sm.warehouse_id
                        WHERE sm.movement_type = 'stock_out'";
        $movementParams = [];
        if ($reference_type !== '') { $movementSql .= " AND sm.reference_type = ?"; $movementParams[] = $reference_type; }
        if ($warehouse_id !== '') { $movementSql .= " AND sm.warehouse_id = ?"; $movementParams[] = $warehouse_id; }
        if ($date_from !== '') { $movementSql .= " AND DATE(sm.created_at) >= ?"; $movementParams[] = $date_from; }
        if ($date_to !== '') { $movementSql .= " AND DATE(sm.created_at) <= ?"; $movementParams[] = $date_to; }
        $movementSql .= " ORDER BY sm.created_at DESC";

        $movementStmt = $pdo->prepare($movementSql);
        $movementStmt->execute($movementParams);
        foreach ($movementStmt->fetchAll(PDO::FETCH_ASSOC) as $movement) {
            // Manual Stock Out records create both a document and a ledger row.
            if ($movement['reference_number'] && isset($documentNumbers[$movement['reference_number']])) {
                continue;
            }
            $stock_outs[] = [
                'id' => 'movement-' . $movement['id'],
                'stock_out_number' => $movement['reference_number'] ?: 'Stock movement #' . $movement['id'],
                'stock_out_date' => substr($movement['created_at'], 0, 10),
                'reference_type' => $movement['reference_type'] ?: 'other',
                'reference_number' => $movement['reference_number'],
                'warehouse_id' => $movement['warehouse_id'],
                'warehouse_name' => $movement['warehouse_name'],
                'vehicle_number' => null,
                'customer_name' => null,
                'reason' => $movement['reason'],
                'authorized_by' => null,
                'created_by' => $movement['created_by'],
                'created_at' => $movement['created_at'],
                'item_count' => 1,
                'total_quantity' => abs((int)$movement['quantity']),
            ];
        }
    } catch (PDOException $e) {
        // Keep manual documents visible if the ledger is not available yet.
        error_log('Error fetching stock-out movements: ' . $e->getMessage());
    }

    // Older Inventory adjustments and Manual Sales entries use stock_history
    // instead of stock_movements. Include its stock-out rows in this page.
    if ($reference_type === '' || $reference_type === 'other') {
        try {
            $legacySql = "SELECT sh.id, sh.quantity_change, sh.reason, sh.admin_username, sh.created_at
                          FROM stock_history sh
                          WHERE sh.change_type = 'stock_out'";
            $legacyParams = [];
            if ($date_from !== '') { $legacySql .= " AND DATE(sh.created_at) >= ?"; $legacyParams[] = $date_from; }
            if ($date_to !== '') { $legacySql .= " AND DATE(sh.created_at) <= ?"; $legacyParams[] = $date_to; }
            $legacySql .= " ORDER BY sh.created_at DESC";

            $legacyStmt = $pdo->prepare($legacySql);
            $legacyStmt->execute($legacyParams);
            foreach ($legacyStmt->fetchAll(PDO::FETCH_ASSOC) as $legacy) {
                $stock_outs[] = [
                    'id' => 'legacy-' . $legacy['id'],
                    'stock_out_number' => 'Stock adjustment #' . $legacy['id'],
                    'stock_out_date' => substr($legacy['created_at'], 0, 10),
                    'reference_type' => 'other',
                    'reference_number' => null,
                    'warehouse_id' => 1,
                    'warehouse_name' => 'Main Warehouse',
                    'vehicle_number' => null,
                    'customer_name' => null,
                    'reason' => $legacy['reason'] ?: 'Recorded from inventory adjustment',
                    'authorized_by' => null,
                    'created_by' => $legacy['admin_username'],
                    'created_at' => $legacy['created_at'],
                    'item_count' => 1,
                    'total_quantity' => abs((int)$legacy['quantity_change']),
                ];
            }
        } catch (PDOException $e) {
            error_log('Error fetching legacy stock-outs: ' . $e->getMessage());
        }
    }

    usort($stock_outs, static function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    echo json_encode(['status' => 'success', 'stock_outs' => $stock_outs]);
} catch (PDOException $e) {
    error_log("Error fetching stock outs: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Unable to load stock outs. Please check the inventory database migration.']);
}
