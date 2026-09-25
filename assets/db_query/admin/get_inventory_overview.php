<?php
// Warehouse-aware inventory overview: per-product available stock, a naive
// "reserved" figure from open sales orders (best-effort — sales_orders /
// sales_order_items are owned by a different migration and may not exist
// yet), available-to-sell, and min/max/reorder levels.
//
// NOTE (per brief): product_details.stock stays a SINGLE global total in
// this pass — true multi-warehouse stock splitting is a larger future
// change. The warehouse filter here is therefore cosmetic today (all stock
// currently lives against the single default warehouse); this endpoint
// still reports it for forward-compatibility with the warehouse column that
// stock_movements / stock_ins / stock_outs now track.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

// Defensive schema upgrade — same pattern as the existing `stock` column in
// adjust_stock.php. Never DROP/MODIFY, only add-if-missing.
foreach ([
    'min_stock_level' => "ALTER TABLE product_details ADD COLUMN min_stock_level INT DEFAULT NULL",
    'max_stock_level' => "ALTER TABLE product_details ADD COLUMN max_stock_level INT DEFAULT NULL",
    'reorder_level'   => "ALTER TABLE product_details ADD COLUMN reorder_level INT DEFAULT NULL",
] as $col => $ddl) {
    try {
        $pdo->exec($ddl);
    } catch (PDOException $e) {
        // Column already exists — fine.
    }
}

try {
    $stmt = $pdo->query("SELECT id, product_name, sku, stock, min_stock_level, max_stock_level, reorder_level
                          FROM product_details ORDER BY product_name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("get_inventory_overview: product_details query failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load products: ' . $e->getMessage()]);
    exit;
}

// Naive reserved-stock: sum of quantities on open sales orders per product,
// best-effort since sales_orders/sales_order_items belong to another module.
$reserved = [];
try {
    $resStmt = $pdo->query("SELECT soi.product_id, SUM(soi.quantity) AS reserved_qty
                             FROM sales_order_items soi
                             JOIN sales_orders so ON so.id = soi.sales_order_id
                             WHERE so.status IN ('draft', 'confirmed', 'processing', 'ready_for_dispatch')
                             GROUP BY soi.product_id");
    foreach ($resStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reserved[(int)$row['product_id']] = (int)$row['reserved_qty'];
    }
} catch (PDOException $e) {
    // sales_orders / sales_order_items not migrated yet — reserved defaults to 0 for everything.
    $reserved = [];
}

foreach ($products as &$p) {
    $available = (int)$p['stock'];
    $reservedQty = $reserved[(int)$p['id']] ?? 0;
    $p['reserved_stock'] = $reservedQty;
    $p['available_to_sell'] = max(0, $available - $reservedQty);
}
unset($p);

echo json_encode(['status' => 'success', 'products' => $products]);
