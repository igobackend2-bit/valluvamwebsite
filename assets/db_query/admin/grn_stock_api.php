<?php
// ============================================================================
// Received purchases → stock (added 1 Oct 2026). Used by the panel at the top
// of Stock In:
//   list    goods receipts waiting to be added to stock (draft) + the last ones added
//   csv     download one goods receipt as CSV (warehouse + location columns to fill)
//   locate  after the Admin added it to stock: save the rack / bin of each item
// Adding to stock itself is the existing purchase_api.php grn_post (posts once,
// creates the Stock In + accounts entries) — nothing is posted twice.
// Read-only except item_locations. Needs no new database change.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';

erp_guard($pdo, null);
if (!erp_can($pdo, 'purchase.view') && !erp_can($pdo, 'stock_in.create') && !erp_can($pdo, 'grn.create')) erp_fail('You do not have permission to do this.', 403);
$action = (string)erp_input('action', 'list');

/** Only Admin / Super Admin (or a user given the Admin dashboard) add received goods to stock. */
function gs_can_post(PDO $pdo): bool {
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) return true;
    $keys = array_merge([role_access_key((string)($_SESSION['admin_role_name'] ?? ''))], user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)));
    return in_array('admin', $keys, true) && erp_can($pdo, 'grn.create');
}
function gs_locations(PDO $pdo): array {
    try { return erp_rows($pdo, "SELECT id, warehouse_id, code, name, level FROM warehouse_locations WHERE status = 'active' ORDER BY warehouse_id, code"); }
    catch (PDOException $e) { return []; }
}

try {
    switch ($action) {
        case 'list':
            $wait = erp_rows($pdo, "SELECT g.id, g.grn_number, g.received_date, g.warehouse_id, w.name AS warehouse_name, s.supplier_name, po.po_number,
                                           (SELECT COUNT(*) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS lines_n,
                                           (SELECT COALESCE(SUM(accepted_qty),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS accepted,
                                           (SELECT q.status FROM quality_checks q WHERE q.grn_id = g.id AND q.status <> 'cancelled' ORDER BY q.id DESC LIMIT 1) AS qc_status
                                    FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id LEFT JOIN warehouses w ON w.id = g.warehouse_id
                                    WHERE g.status = 'draft' ORDER BY g.received_date DESC, g.id DESC LIMIT 50");
            $done = erp_rows($pdo, "SELECT g.id, g.grn_number, g.received_date, g.posted_at, g.posted_by, w.name AS warehouse_name, s.supplier_name, po.po_number, si.stock_in_number,
                                           (SELECT COALESCE(SUM(accepted_qty),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS accepted
                                    FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id LEFT JOIN warehouses w ON w.id = g.warehouse_id
                                    LEFT JOIN stock_ins si ON si.id = g.stock_in_id WHERE g.status = 'posted' ORDER BY g.posted_at DESC, g.id DESC LIMIT 5");
            erp_out(['status' => 'success', 'waiting' => $wait, 'last' => $done, 'can_post' => gs_can_post($pdo),
                     'warehouses' => erp_rows($pdo, "SELECT id, name FROM warehouses ORDER BY id"), 'locations' => gs_locations($pdo)]);

        case 'csv':
            $id = (int)erp_input('id');
            $g = erp_row($pdo, "SELECT g.*, w.name AS warehouse_name, s.supplier_name, po.po_number FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id
                                LEFT JOIN purchase_orders po ON po.id = g.po_id LEFT JOIN warehouses w ON w.id = g.warehouse_id WHERE g.id = ?", [$id]);
            if (!$g) erp_fail('Goods receipt not found.');
            $items = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ? ORDER BY id", [$id]));
            $loc = [];
            try { foreach (erp_rows($pdo, "SELECT il.item_type, il.item_id, l.code FROM item_locations il JOIN warehouse_locations l ON l.id = il.location_id WHERE il.warehouse_id = ?", [(int)$g['warehouse_id']]) as $r) $loc[$r['item_type'] . ':' . $r['item_id']] = $r['code']; }
            catch (PDOException $e) {}
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '_', $g['grn_number']) . '_to_stock.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // Excel opens ₹ / Tamil names correctly
            fputcsv($out, ['grn_number', 'line_id', 'po_item_id', 'item_type', 'item_id', 'item_name', 'unit', 'ordered_qty', 'received_qty', 'rejected_qty', 'accepted_qty',
                           'rejection_reason', 'batch_number', 'manufacturing_date', 'expiry_date', 'warehouse_id', 'warehouse_name', 'location_code', 'supplier', 'po_number', 'received_date']);
            $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;   // no spreadsheet formulas
            foreach ($items as $i)
                fputcsv($out, array_map($safe, [$g['grn_number'], $i['id'], $i['po_item_id'], $i['item_type'], $i['item_id'], $i['item_name'] ?? '', $i['unit'], $i['ordered_qty'], $i['received_qty'], $i['rejected_qty'], $i['accepted_qty'],
                               $i['rejection_reason'], $i['batch_number'], $i['manufacturing_date'], $i['expiry_date'], $g['warehouse_id'], $g['warehouse_name'], $loc[$i['item_type'] . ':' . $i['item_id']] ?? '',
                               $g['supplier_name'], $g['po_number'], $g['received_date']]));
            fclose($out);
            exit;

        case 'locate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            if (!gs_can_post($pdo)) erp_fail('Only the Admin adds received goods to stock.', 403);
            $id = (int)erp_input('id');
            $g = erp_row($pdo, "SELECT id, grn_number, warehouse_id, status FROM goods_receipts WHERE id = ?", [$id]);
            if (!$g || $g['status'] !== 'posted') erp_fail('Add the goods receipt to stock first.');
            $wh = (int)$g['warehouse_id']; $n = 0;
            $codes = [];
            foreach (gs_locations($pdo) as $l) if ((int)$l['warehouse_id'] === $wh) $codes[strtoupper($l['code'])] = (int)$l['id'];
            $mine = [];
            foreach (erp_rows($pdo, "SELECT item_type, item_id FROM goods_receipt_items WHERE grn_id = ?", [$id]) as $r) $mine[$r['item_type'] . ':' . $r['item_id']] = 1;
            $up = $pdo->prepare("INSERT INTO item_locations (item_type, item_id, warehouse_id, location_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE location_id = VALUES(location_id)");
            foreach (erp_json_input('lines') as $l) {
                $code = strtoupper(trim((string)($l['location_code'] ?? ''))); $key = ($l['item_type'] ?? '') . ':' . (int)($l['item_id'] ?? 0);
                if ($code === '' || !isset($mine[$key])) continue;
                if (!isset($codes[$code])) erp_fail("Location {$code} does not exist in this warehouse — add it in Warehouse Locations first.");
                $up->execute([$l['item_type'], (int)$l['item_id'], $wh, $codes[$code]]); $n++;
            }
            log_audit($pdo, 'update', 'item_locations', $id, null, ['grn' => $g['grn_number'], 'lines' => $n]);
            erp_out(['status' => 'success', 'saved' => $n]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'grn to stock');
}
