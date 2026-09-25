<?php
// Centralized, read-only Stock Movement History.
//
// IMMUTABLE VIEW: this endpoint only ever SELECTs. There is no edit/delete
// action anywhere in this module for a movement row — rows are permanent
// audit history. The only future exception is a "Super Admin correction"
// feature (NOT built in this pass); even then, a correction must INSERT a
// new row (movement_type = 'correction') and must never UPDATE/DELETE an
// existing row.
//
// Combines the new `stock_movements` ledger (written to by Stock In / Stock
// Out going forward) with the legacy `stock_history` table (still written to
// by the existing manual "Adjust stock" feature on admin/inventory.php) via
// UNION ALL, each wrapped in its own try/catch so either table being
// temporarily absent doesn't take down the whole listing.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$product_id = $_GET['product_id'] ?? '';
$warehouse_id = $_GET['warehouse_id'] ?? '';
$movement_type = $_GET['movement_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$rows = [];

// ─── stock_movements (new source of truth) ─────────────────────────────
try {
    $sql = "SELECT sm.id, 'stock_movements' AS source, sm.movement_type, sm.product_id,
                   pd.product_name, sm.sku, sm.warehouse_id, w.name AS warehouse_name,
                   sm.quantity, sm.previous_stock, sm.new_stock, sm.reference_type,
                   sm.reference_number, sm.reason, sm.created_by, sm.created_at
            FROM stock_movements sm
            LEFT JOIN product_details pd ON pd.id = sm.product_id
            LEFT JOIN warehouses w ON w.id = sm.warehouse_id
            WHERE 1 = 1";
    $params = [];

    if ($product_id !== '') { $sql .= " AND sm.product_id = ?"; $params[] = $product_id; }
    if ($warehouse_id !== '') { $sql .= " AND sm.warehouse_id = ?"; $params[] = $warehouse_id; }
    if ($movement_type !== '') { $sql .= " AND sm.movement_type = ?"; $params[] = $movement_type; }
    if ($date_from !== '') { $sql .= " AND sm.created_at >= ?"; $params[] = $date_from . ' 00:00:00'; }
    if ($date_to !== '') { $sql .= " AND sm.created_at <= ?"; $params[] = $date_to . ' 23:59:59'; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log("get_stock_movements: stock_movements query failed: " . $e->getMessage());
}

// ─── stock_history (legacy, still live from admin/inventory.php) ───────
// Only include when no warehouse filter is applied (legacy rows have no
// warehouse concept) or when the filter is the default warehouse (id 1),
// since every legacy row implicitly belongs to the single warehouse that
// existed before this module.
if ($warehouse_id === '' || (string)$warehouse_id === '1') {
    try {
        $sql = "SELECT sh.id, 'stock_history' AS source, sh.change_type AS movement_type, sh.product_id,
                       pd.product_name, pd.sku AS sku, 1 AS warehouse_id, 'Main Warehouse' AS warehouse_name,
                       sh.quantity_change AS quantity, (sh.resulting_stock - sh.quantity_change) AS previous_stock,
                       sh.resulting_stock AS new_stock, 'legacy_adjust' AS reference_type,
                       NULL AS reference_number, sh.reason, sh.admin_username AS created_by, sh.created_at
                FROM stock_history sh
                LEFT JOIN product_details pd ON pd.id = sh.product_id
                WHERE 1 = 1";
        $params = [];

        if ($product_id !== '') { $sql .= " AND sh.product_id = ?"; $params[] = $product_id; }
        if ($movement_type !== '') { $sql .= " AND sh.change_type = ?"; $params[] = $movement_type; }
        if ($date_from !== '') { $sql .= " AND sh.created_at >= ?"; $params[] = $date_from . ' 00:00:00'; }
        if ($date_to !== '') { $sql .= " AND sh.created_at <= ?"; $params[] = $date_to . ' 23:59:59'; }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log("get_stock_movements: stock_history query failed: " . $e->getMessage());
    }
}

usort($rows, function ($a, $b) {
    return strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now');
});

echo json_encode(['status' => 'success', 'movements' => array_values($rows)]);
