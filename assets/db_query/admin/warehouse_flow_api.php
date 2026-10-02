<?php
// ============================================================================
// Stock by Warehouse — "on the way & movements" (added 2 Oct 2026). Read only.
//   coming   purchases on the way to each warehouse (loaded / in transit)
//   unloaded goods unloaded at the warehouse, not yet in stock (draft goods
//            receipt: waiting for QC / waiting to be added to stock)
//   transfers stock transfers on the way between warehouses
//   moves    latest stock movements per warehouse (in / out / transfers)
// Stock itself is added only when the goods receipt is posted (Stock In),
// which already writes the warehouse stock — nothing is posted here.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
erp_guard($pdo, null);
if (!erp_can($pdo, 'warehouse.view') && !erp_can($pdo, 'inventory.view') && !erp_can($pdo, 'warehouses.view')) erp_fail('You do not have permission to do this.', 403);
$wh = (int)erp_input('warehouse_id', 0);
$has = function ($t) use ($pdo) { try { $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1"); return true; } catch (PDOException $e) { return false; } };
try {
    $out = ['status' => 'success', 'coming' => [], 'unloaded' => [], 'transfers' => [], 'moves' => []];
    if ($has('purchase_flows')) {
        $p = []; $wsql = '';
        if ($wh) { $wsql = ' AND COALESCE(po.warehouse_id, pr.warehouse_id) = ?'; $p[] = $wh; }
        $out['coming'] = erp_rows($pdo, "SELECT f.pr_id, pr.pr_number, po.po_number, s.supplier_name, w.name AS warehouse_name, f.delivery_mode, f.courier_name, f.tracking_number, f.vehicle_number,
                                                f.driver_name, f.driver_phone, f.dispatch_date, f.quote_submitted_by,
                                                (SELECT COALESCE(SUM(i.quantity),0) FROM purchase_order_items i WHERE i.po_id = po.id) AS qty
                                         FROM purchase_flows f JOIN purchase_requests pr ON pr.id = f.pr_id JOIN purchase_orders po ON po.id = f.po_id
                                         LEFT JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN warehouses w ON w.id = COALESCE(po.warehouse_id, pr.warehouse_id)
                                         WHERE f.delivery_mode IS NOT NULL AND f.grn_id IS NULL AND po.status IN ('approved','partially_received'){$wsql}
                                         ORDER BY f.dispatch_date, f.id LIMIT 50", $p);
    }
    $p = []; $wsql = '';
    if ($wh) { $wsql = ' AND g.warehouse_id = ?'; $p[] = $wh; }
    $out['unloaded'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT g.id AS grn_id, g.grn_number, g.received_date, w.name AS warehouse_name, s.supplier_name, i.item_type, i.item_id, i.received_qty, i.accepted_qty, i.unit,
                                                    (SELECT q.status FROM quality_checks q WHERE q.grn_id = g.id AND q.status <> 'cancelled' ORDER BY q.id DESC LIMIT 1) AS qc_status
                                             FROM goods_receipts g JOIN goods_receipt_items i ON i.grn_id = g.id LEFT JOIN warehouses w ON w.id = g.warehouse_id LEFT JOIN suppliers s ON s.id = g.supplier_id
                                             WHERE g.status = 'draft'{$wsql} ORDER BY g.received_date, g.id LIMIT 100", $p));
    if ($has('stock_transfers')) {
        $p = []; $wsql = '';
        if ($wh) { $wsql = ' AND (t.from_warehouse_id = ? OR t.to_warehouse_id = ?)'; array_push($p, $wh, $wh); }
        $out['transfers'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT t.id, t.transfer_number, t.transfer_date, t.status, t.vehicle_number, t.sent_by, fw.name AS from_name, tw.name AS to_name, i.item_type, i.item_id, i.quantity, i.unit
                                                  FROM stock_transfers t JOIN stock_transfer_items i ON i.transfer_id = t.id LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                                                  WHERE t.status IN ('draft','in_transit'){$wsql} ORDER BY t.transfer_date DESC, t.id DESC LIMIT 50", $p));
    }
    if ($has('warehouse_stock_moves')) {
        $p = []; $wsql = '1=1';
        if ($wh) { $wsql = 'm.warehouse_id = ?'; $p[] = $wh; }
        $out['moves'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT m.item_type, m.item_id, m.bucket, m.quantity, m.reference_type, m.reference_number, m.reason, m.created_by, m.created_at, w.name AS warehouse_name
                                              FROM warehouse_stock_moves m LEFT JOIN warehouses w ON w.id = m.warehouse_id WHERE {$wsql} ORDER BY m.id DESC LIMIT 30", $p));
    }
    erp_out($out);
} catch (Throwable $e) {
    erp_db_error($e, 'warehouse flow');
}
