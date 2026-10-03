<?php
// ============================================================================
// Stock lifecycle API (added 2 Oct 2026) — needs stock_lifecycle_migration.sql
//
//   Purchase order → consignment (material ready) → LOADING check + machine
//   weight → TRANSPORT (internal vehicle / courier) → DELIVERED → UNLOADING +
//   weight → RECEIVED → existing GRN (draft) + existing QC → QC (pass / partial /
//   hold / fail) → existing GRN post = STOCK IN (stock_ins + stock_movements +
//   warehouse buckets + batches).  Plus: controlled stock out, damage approval,
//   sales-return receiving + QC, opening stock, purchase-return dispatch, daily
//   stock, monthly audit on top of stock counts, executive handover.
//
// Every stock change goes through the existing ledger functions
// (erp_post_stock_in / erp_post_stock_out / erp_product_movement /
// erp_raw_movement / wh_apply) — there is no second ledger. Differences are
// generated columns in the database (cannot be typed over). Every action writes
// audit_logs with the old and new values.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/costing_engine.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$PERMS = [
    'meta' => 'stockflow.view', 'po_list' => 'stockflow.loading', 'po_lines' => 'stockflow.loading',
    'cns_list' => 'stockflow.view', 'cns_get' => 'stockflow.view',
    'cns_create' => 'stockflow.loading', 'cns_loading_save' => 'stockflow.loading', 'cns_transport_save' => 'stockflow.loading', 'cns_deliver' => 'stockflow.loading|stockflow.receive',
    'cns_unload_save' => 'stockflow.receive', 'cns_link_qc' => 'stockflow.receive', 'cns_qc_save' => 'stockflow.qc', 'cns_qc_done' => 'stockflow.qc',
    'cns_stocked' => 'stockflow.receive', 'cns_cancel' => 'stockflow.loading|stockflow.approve', 'cns_correct' => 'stockflow.approve',
    'qcp_list' => 'stockflow.view', 'qcp_save' => 'stockflow.config', 'qcp_status' => 'stockflow.config',
    'iss_list' => 'stockflow.view', 'iss_get' => 'stockflow.view', 'iss_create' => 'stockflow.issue', 'iss_approve' => 'stockflow.approve', 'iss_reject' => 'stockflow.approve',
    'iss_issue' => 'stockflow.issue', 'iss_cancel' => 'stockflow.issue',
    'dmg_list' => 'stockflow.view', 'dmg_get' => 'stockflow.view', 'dmg_create' => 'stockflow.issue', 'dmg_verify' => 'stockflow.approve|stockflow.qc',
    'dmg_approve' => 'stockflow.approve', 'dmg_reject' => 'stockflow.approve|purchase.manager_approve', 'dmg_dispose' => 'stockflow.approve',
    'dmg_mgr_ok' => 'purchase.manager_approve',   // FIX (3 Oct 2026): damage → Manager approval, then Admin
    'rr_list' => 'stockflow.view', 'rr_get' => 'stockflow.view', 'rr_create' => 'stockflow.returns', 'rr_qc' => 'stockflow.qc', 'rr_link' => 'stockflow.returns', 'rr_cancel' => 'stockflow.returns',
    'ops_list' => 'stockflow.view', 'ops_create' => 'stockflow.receive', 'ops_verify' => 'stockflow.approve', 'ops_reject' => 'stockflow.approve',
    'rd_list' => 'stockflow.view', 'rd_get' => 'stockflow.view', 'rd_rejected_stock' => 'stockflow.view', 'rd_create' => 'stockflow.issue', 'rd_approve' => 'stockflow.approve',
    'rd_reject' => 'stockflow.approve', 'rd_dispatch' => 'stockflow.issue', 'rd_deliver' => 'stockflow.issue',
    'daily' => 'stockflow.view', 'out_register' => 'stockflow.view', 'location_stock' => 'stockflow.view',
    'aud_list' => 'stockflow.view', 'aud_get' => 'stockflow.view', 'aud_plan' => 'stockflow.audit', 'aud_start' => 'stockflow.audit', 'aud_save' => 'stockflow.audit',
    'aud_close' => 'stockflow.audit_approve', 'aud_cancel' => 'stockflow.audit', 'aud_report' => 'stockflow.view',
    'aud_signoff' => 'stockflow.audit|stockflow.audit_approve|stockflow.audit_sign',   // + External Auditor (2 Oct 2026)   // FIX (2 Oct 2026): auditor (external) QC report + name + signature
    'hnd_list' => 'stockflow.view', 'hnd_get' => 'stockflow.view', 'hnd_preview' => 'stockflow.handover', 'hnd_create' => 'stockflow.handover',
    'hnd_check' => 'stockflow.handover', 'hnd_ack' => 'stockflow.handover_ack', 'hnd_cancel' => 'stockflow.handover',
];
$READS = ['meta', 'po_list', 'po_lines', 'cns_list', 'cns_get', 'qcp_list', 'iss_list', 'iss_get', 'dmg_list', 'dmg_get', 'rr_list', 'rr_get', 'ops_list', 'rd_list', 'rd_get', 'rd_rejected_stock',
          'daily', 'out_register', 'location_stock', 'aud_list', 'aud_get', 'aud_report', 'hnd_list', 'hnd_get', 'hnd_preview'];
if (!isset($PERMS[$action])) erp_fail('Unknown action.');
erpx_guard($pdo, null);
$need = explode('|', $PERMS[$action]);
$ok = false; foreach ($need as $p) if (erp_can($pdo, $p)) $ok = true;
if (!$ok) erp_fail('You do not have permission to do this.', 403);
if (!in_array($action, $READS, true) && !$isWrite) erp_fail('Invalid request method.');
try { $pdo->query("SELECT 1 FROM sf_consignments LIMIT 1"); }
catch (PDOException $e) { erp_fail('Run stock_lifecycle_migration.sql once in HeidiSQL to switch on the stock lifecycle.'); }

// ---------------------------------------------------------------- helpers
/** Weight of ONE unit in kg (products: pack size "500g", raw materials: kg/g/L/ml). null = not weight based. */
function sf_unit_weight(PDO $pdo, string $type, int $id): ?float {
    $it = erp_item($pdo, $type, $id);
    if (!$it) return null;
    if ($type === 'product') { $p = erp_pack_size($it['pack'] ?? '') ?: erp_pack_size($it['name']); return $p ? round($p[0], 4) : null; }
    $u = strtolower((string)$it['unit']);
    return ['kg' => 1.0, 'g' => 0.001, 'l' => 1.0, 'ml' => 0.001, 'ltr' => 1.0][$u] ?? null;
}
function sf_dec($v, string $label, bool $required = false): ?float {
    $v = is_string($v) ? trim($v) : $v;
    if ($v === null || $v === '') { if ($required) erp_invalid("{$label} is required."); return null; }
    return erp_q(erp_num($v, $label));
}
function sf_time($v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v)) erp_invalid('Invalid time (use HH:MM).');
    return strlen($v) === 5 ? $v . ':00' : $v;
}
function sf_signoff_installed(PDO $pdo): bool { static $ok = null; if ($ok === null) { try { $pdo->query("SELECT 1 FROM sf_audit_signoffs LIMIT 1"); $ok = true; } catch (PDOException $e) { $ok = false; } } return $ok; }   // FIX (2 Oct 2026)
function sf_txt($v, int $max = 255): ?string { $v = trim((string)$v); return $v === '' ? null : mb_substr($v, 0, $max); }
function sf_whole(string $type, float $q, string $name) { if ($type === 'product' && floor($q) != $q) erp_invalid("{$name}: product packs must be whole numbers."); }
function sf_doc_count(PDO $pdo, string $type, int $id): int {
    return (int)erp_val($pdo, "SELECT COUNT(*) FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE d.entity_type = ? AND d.entity_id = ? AND COALESCE(m.status,'active') = 'active'", [$type, $id]);
}
function sf_cns(PDO $pdo, int $id, bool $lock = false): array {
    $c = erp_row($pdo, "SELECT * FROM sf_consignments WHERE id = ?" . ($lock ? ' FOR UPDATE' : ''), [$id]);
    if (!$c) erp_invalid('Consignment not found.');
    return $c;
}
function sf_lines(PDO $pdo, int $cid): array {
    return erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM sf_consignment_lines WHERE consignment_id = ? ORDER BY id", [$cid]));
}
function sf_need_status(array $c, array $allowed, string $what) {
    if (!in_array($c['status'], $allowed, true)) erp_invalid("{$c['consignment_number']} is " . strtoupper($c['status']) . " — {$what} is not possible now.");
}
/** Moves a quantity out of sellable stock into a non-sellable bucket through the ledger (same as Stock by Warehouse → move to damaged). */
function sf_to_bucket(PDO $pdo, string $type, int $iid, int $wh, float $qty, string $bucket, string $refType, string $num, string $reason, ?int $batchId) {
    if ($type === 'product') erp_product_movement($pdo, $iid, -(int)round($qty), 'damage', $refType, $num, $reason, $wh);
    else erp_raw_movement($pdo, $iid, -$qty, 'waste', $refType, $num, $reason, $wh);
    $mid = (int)erp_val($pdo, $type === 'product' ? "SELECT MAX(id) FROM stock_movements WHERE product_id = ?" : "SELECT MAX(id) FROM raw_material_movements WHERE raw_material_id = ?", [$iid]);
    if ($batchId) $pdo->prepare("INSERT INTO batch_allocations (batch_id, item_type, item_id, movement_id, quantity, allocation_type) VALUES (?,?,?,?,?, 'manual')")->execute([$batchId, $type, $iid, $mid, $qty]);
    wh_apply($pdo, $type, $iid, $wh, $bucket, $qty, 'bucket', null, 'bucket_in', $num, mb_substr($reason, 0, 255));
}
function sf_check_available(PDO $pdo, string $type, int $iid, int $wh, float $qty, ?int $batchId, string $name) {
    $have = wh_qty($pdo, $type, $iid, $wh);
    if ($qty > $have + 0.0005) erp_invalid("{$name}: only " . erp_q($have) . " available at this location.");
    if ($batchId) {
        $b = batch_remaining_rows($pdo, ['batch_id' => $batchId])[0] ?? null;
        if (!$b || $b['item_type'] !== $type || (int)$b['item_id'] !== $iid) erp_invalid("{$name}: the batch does not belong to this item.");
        if ($qty > $b['remaining'] + 0.0005) erp_invalid("{$name}: batch {$b['batch_number']} has only {$b['remaining']} left.");
    }
}
/** Lines [{item_type,item_id,quantity,batch_id,weight_kg}] validated against the location's available stock. */
function sf_item_lines(PDO $pdo, array $raw, int $wh, bool $checkStock = true): array {
    $out = [];
    foreach ($raw as $it) {
        $type = erp_item_type($it['item_type'] ?? 'product');
        $iid = (int)($it['item_id'] ?? 0);
        if (!$iid) continue;
        $item = erp_item($pdo, $type, $iid);
        if (!$item) erp_invalid('An item no longer exists.');
        $q = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity'));
        if ($q <= 0) continue;
        sf_whole($type, $q, $item['name']);
        $batch = (int)($it['batch_id'] ?? 0) ?: null;
        if ($checkStock) sf_check_available($pdo, $type, $iid, $wh, $q, $batch, $item['name']);
        $uw = sf_unit_weight($pdo, $type, $iid);
        $w = sf_dec($it['weight_kg'] ?? null, 'Weight');
        $out[] = ['item_type' => $type, 'item_id' => $iid, 'quantity' => $q, 'batch_id' => $batch, 'weight_kg' => $w ?? ($uw ? erp_q($q * $uw) : null),
                  'unit' => $type === 'product' ? 'pcs' : $item['unit'], 'name' => $item['name']];
    }
    if (!$out) erp_invalid('Add at least one item with a quantity.');
    return $out;
}
function sf_audit_status_sync(PDO $pdo, array &$a) {
    if (!$a['stock_count_id'] || in_array($a['status'], ['closed', 'cancelled', 'planned'], true)) return;
    $cs = erp_val($pdo, "SELECT status FROM stock_counts WHERE id = ?", [$a['stock_count_id']]);
    $to = null;
    if ($cs === 'submitted' && $a['status'] === 'count_completed') $to = 'verification_pending';
    if ($cs === 'posted' && in_array($a['status'], ['count_completed', 'verification_pending'], true)) $to = 'approved';
    if ($cs === 'rejected' && $a['status'] === 'verification_pending') $to = 'in_progress';
    if ($cs === 'cancelled') $to = 'cancelled';
    if ($to && $to !== $a['status']) {
        $pdo->prepare("UPDATE sf_audits SET status = ? WHERE id = ?")->execute([$to, $a['id']]);
        log_audit($pdo, 'status', 'sf_audits', $a['id'], ['status' => $a['status']], ['status' => $to, 'stock_count' => $cs]);
        $a['status'] = $to;
    }
}
/** Classifies one sellable-stock move into a daily-stock column. */
function sf_bucket_of(array $m): string {
    $src = $m['source']; $mt = (string)$m['reason']; $rt = (string)$m['reference_type']; $q = (float)$m['quantity'];
    if ($src === 'realloc' || $src === 'bucket') return 'adjustment';
    if ($rt === 'stock_transfer' || $mt === 'transfer') return $q >= 0 ? 'transfer_in' : 'transfer_out';
    if ($rt === 'sales_return') return 'sales_return';
    if ($rt === 'purchase_return') return 'purchase_return';
    if ($rt === 'stock_damage' || in_array($mt, ['damage', 'waste'], true)) return 'damage';
    if ($mt === 'purchase' || $rt === 'grn') return 'purchase_in';
    if ($mt === 'sale' || in_array($rt, ['manual_sale', 'manual_sales', 'credit_sale', 'delivery_challan', 'website_order', 'invoice', 'sales_order', 'sale'], true)) return 'sales';
    if (in_array($mt, ['adjustment', 'correction'], true) || in_array($rt, ['stock_count', 'stock_adjustment', 'bucket_restore'], true)) return 'adjustment';
    if ($rt === 'opening_stock') return 'opening_entry';
    if ($q >= 0) return 'stock_in';
    return 'stock_out';
}
function sf_snapshot(PDO $pdo, string $from, string $to, ?int $wh): array {
    $whSql = $wh ? ' AND ws.warehouse_id = ' . (int)$wh : '';
    $loc = erp_rows($pdo, "SELECT w.name AS warehouse, ws.bucket, ws.item_type, ws.item_id, ws.quantity FROM warehouse_stock ws JOIN warehouses w ON w.id = ws.warehouse_id WHERE ABS(ws.quantity) >= 0.0005 {$whSql} ORDER BY w.name, ws.bucket");
    $loc = erp_attach_item_names($pdo, $loc);
    $byLoc = [];
    foreach ($loc as $r) { $k = $r['warehouse']; $byLoc[$k] = $byLoc[$k] ?? ['warehouse' => $k, 'available' => 0, 'damaged' => 0, 'rejected' => 0, 'expired' => 0, 'items' => 0]; $byLoc[$k][$r['bucket']] += (float)$r['quantity']; if ($r['bucket'] === 'available') $byLoc[$k]['items']++; }
    $overall = erp_rows($pdo, "SELECT id, product_name, quantity AS pack, category, stock FROM product_details WHERE stock <> 0 ORDER BY product_name");
    $sales = erp_rows($pdo, "SELECT customer, SUM(n) AS documents, SUM(amount) AS amount FROM (
                SELECT COALESCE(NULLIF(customer_name,''),'Walk-in') AS customer, 1 AS n, grand_total AS amount FROM invoices WHERE status <> 'cancelled' AND status <> 'draft' AND invoice_date BETWEEN ? AND ?
                UNION ALL SELECT COALESCE(NULLIF(customer_name,''),'Walk-in'), 1, grand_total FROM manual_sales WHERE sales_date BETWEEN ? AND ?
                UNION ALL SELECT COALESCE(NULLIF(customer_name,''),'Walk-in'), 1, grand_total FROM credit_sales WHERE sale_date BETWEEN ? AND ?
                UNION ALL SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')), 1, amount FROM orders WHERE LOWER(COALESCE(payment_status,'')) IN ('paid','success','captured','completed') AND DATE(created_at) BETWEEN ? AND ?
             ) s GROUP BY customer ORDER BY amount DESC", [$from, $to, $from, $to, $from, $to, $from, $to]);
    $sret = erp_rows($pdo, "SELECT return_number, return_date, source_number, customer_name, total_value, refund_amount, status FROM sales_returns WHERE return_date BETWEEN ? AND ? ORDER BY return_date", [$from, $to]);
    $pur = erp_rows($pdo, "SELECT g.grn_number, g.received_date, s.supplier_name, po.po_number, (SELECT COALESCE(SUM(accepted_qty),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS accepted,
                                  (SELECT COALESCE(SUM(rejected_qty),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS rejected, (SELECT COALESCE(SUM(accepted_qty * rate),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS value
                           FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id WHERE g.status = 'posted' AND g.received_date BETWEEN ? AND ? ORDER BY g.received_date", [$from, $to]);
    $pret = erp_rows($pdo, "SELECT r.return_number, r.return_date, s.supplier_name, r.total_value, r.reason, r.status FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id WHERE r.return_date BETWEEN ? AND ? ORDER BY r.return_date", [$from, $to]);
    $dmg = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT d.damage_number, d.damage_date, d.item_type, d.item_id, d.quantity, d.weight_kg, d.reason, d.status, d.disposal_status, w.name AS warehouse FROM sf_damage_reports d JOIN warehouses w ON w.id = d.warehouse_id WHERE d.damage_date BETWEEN ? AND ? ORDER BY d.damage_date", [$from, $to]));
    $aud = erp_rows($pdo, "SELECT a.audit_number, a.audit_month, a.audit_date, a.auditor_name, a.status, w.name AS warehouse,
                                  (SELECT COUNT(*) FROM sf_audit_lines l WHERE l.audit_id = a.id) AS lines_total,
                                  (SELECT COUNT(*) FROM sf_audit_lines l WHERE l.audit_id = a.id AND ABS(COALESCE(l.qty_diff,0)) >= 0.0005) AS lines_with_difference
                           FROM sf_audits a JOIN warehouses w ON w.id = a.warehouse_id WHERE a.audit_date BETWEEN ? AND ? AND a.status <> 'cancelled' ORDER BY a.audit_date", [$from, $to]);
    return ['generated_at' => date('Y-m-d H:i:s'), 'generated_by' => erp_user(), 'period' => [$from, $to],
            'overall_stock' => $overall, 'location_summary' => array_values($byLoc), 'location_stock' => $loc,
            'sales_by_customer' => $sales, 'sales_total' => erp_m(array_sum(array_column($sales, 'amount'))),
            'sales_returns' => $sret, 'purchases' => $pur, 'purchase_returns' => $pret, 'damage' => $dmg, 'stock_audits' => $aud];
}

try {
    if (!$isWrite) wh_sync($pdo);
    switch ($action) {
        // ============================================================ META
        case 'meta':
            $perms = [];
            foreach (array_unique(explode('|', implode('|', $PERMS))) as $p) $perms[$p] = erp_can($pdo, $p);
            erp_out(['status' => 'success', 'warehouses' => erp_rows($pdo, "SELECT id, name, code, location FROM warehouses WHERE status = 'active' ORDER BY id"),
                     'locations' => erp_rows($pdo, "SELECT id, warehouse_id, level, code, name FROM warehouse_locations WHERE status = 'active' ORDER BY warehouse_id, code"),
                     'couriers' => erp_rows($pdo, "SELECT id, name, tracking_url FROM courier_services WHERE is_active = 1 ORDER BY sort_order, name"),
                     'perms' => $perms, 'me' => erp_user(), 'name' => $_SESSION['admin_full_name'] ?? erp_user(),
                     'qc_required' => erp_setting($pdo, 'erp_qc_required', '0') === '1']);

        // ============================================================ CONSIGNMENTS (purchase → stock in)
        case 'po_list':
            $rows = erp_rows($pdo, "SELECT po.id, po.po_number, po.po_date, po.status, po.warehouse_id, s.supplier_name,
                                           (SELECT COALESCE(SUM(GREATEST(quantity - received_qty,0)),0) FROM purchase_order_items i WHERE i.po_id = po.id) AS pending_qty,
                                           (SELECT COUNT(*) FROM sf_consignments c WHERE c.po_id = po.id AND c.status <> 'cancelled') AS consignments
                                    FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.status IN ('approved','partially_received') ORDER BY po.id DESC LIMIT 300");
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'po_lines':
            $po = erp_row($pdo, "SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?", [(int)erp_input('po_id')]);
            if (!$po) erp_fail('Purchase order not found.');
            $items = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT id, item_type, item_id, quantity, received_qty, unit FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$po['id']]));
            foreach ($items as &$it) {
                $inFlight = (float)erp_val($pdo, "SELECT COALESCE(SUM(l.loaded_qty),0) FROM sf_consignment_lines l JOIN sf_consignments c ON c.id = l.consignment_id
                                                  WHERE l.po_item_id = ? AND c.status NOT IN ('cancelled','stocked','qc_failed')", [$it['id']]);
                $it['pending_qty'] = erp_q(max(0, $it['quantity'] - $it['received_qty']));
                $it['in_transit_qty'] = erp_q($inFlight);
                $it['unit_weight_kg'] = sf_unit_weight($pdo, $it['item_type'], (int)$it['item_id']);
            }
            unset($it);
            erp_out(['status' => 'success', 'po' => $po, 'items' => $items]);

        case 'cns_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'c.status = ?'; $p[] = $s; }
            if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = 'c.destination_warehouse_id = ?'; $p[] = $wid; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(c.consignment_number LIKE ? OR po.po_number LIKE ? OR s.supplier_name LIKE ? OR c.tracking_number LIKE ? OR c.vehicle_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT c.*, po.po_number, s.supplier_name, w.name AS warehouse_name, g.grn_number, g.status AS grn_status, qc.qc_number, qc.status AS qc_status,
                                           (SELECT COALESCE(SUM(loaded_qty),0) FROM sf_consignment_lines l WHERE l.consignment_id = c.id) AS loaded_qty,
                                           (SELECT COALESCE(SUM(unloaded_qty),0) FROM sf_consignment_lines l WHERE l.consignment_id = c.id) AS unloaded_qty,
                                           (SELECT COALESCE(SUM(weight_diff),0) FROM sf_consignment_lines l WHERE l.consignment_id = c.id) AS weight_diff,
                                           (SELECT COUNT(*) FROM erp_documents d WHERE d.entity_type = 'consignment' AND d.entity_id = c.id) AS proofs
                                    FROM sf_consignments c JOIN purchase_orders po ON po.id = c.po_id LEFT JOIN suppliers s ON s.id = c.supplier_id
                                    LEFT JOIN warehouses w ON w.id = c.destination_warehouse_id LEFT JOIN goods_receipts g ON g.id = c.grn_id LEFT JOIN quality_checks qc ON qc.id = c.qc_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY c.id DESC LIMIT 500", $p);
            $counts = erp_rows($pdo, "SELECT status, COUNT(*) n FROM sf_consignments GROUP BY status");
            erp_out(['status' => 'success', 'rows' => $rows, 'counts' => $counts]);

        case 'cns_get':
            $c = sf_cns($pdo, (int)erp_input('id'));
            $c += erp_row($pdo, "SELECT po.po_number, po.status AS po_status, s.supplier_name, w.name AS warehouse_name, uw.name AS unload_warehouse_name, l.code AS unload_location_code,
                                        g.grn_number, g.status AS grn_status, qc.qc_number, qc.status AS qc_status, si.stock_in_number, sh.shipment_number, sh.status AS shipment_status
                                 FROM sf_consignments c JOIN purchase_orders po ON po.id = c.po_id LEFT JOIN suppliers s ON s.id = c.supplier_id LEFT JOIN warehouses w ON w.id = c.destination_warehouse_id
                                 LEFT JOIN warehouses uw ON uw.id = c.unload_warehouse_id LEFT JOIN warehouse_locations l ON l.id = c.unload_location_id
                                 LEFT JOIN goods_receipts g ON g.id = c.grn_id LEFT JOIN quality_checks qc ON qc.id = c.qc_id LEFT JOIN stock_ins si ON si.id = c.stock_in_id
                                 LEFT JOIN inbound_shipments sh ON sh.id = c.shipment_id WHERE c.id = ?", [$c['id']]);
            $c['lines'] = sf_lines($pdo, (int)$c['id']);
            foreach ($c['lines'] as &$l) {
                $item = erp_item($pdo, $l['item_type'], (int)$l['item_id']);
                $l['qc_params'] = erp_rows($pdo, "SELECT id, check_name, check_type, min_value, max_value FROM sf_qc_params WHERE is_active = 1 AND (scope = 'all'
                                                  OR (scope = 'category' AND category = ?) OR (scope = 'product' AND product_id = ? AND ? = 'product')) ORDER BY sort_order, id",
                                                  [(string)($item['category'] ?? ''), (int)$l['item_id'], $l['item_type']]);
                $l['qc_checklist'] = $l['qc_checklist'] ? json_decode($l['qc_checklist'], true) : null;
            }
            unset($l);
            $c['qc_items'] = $c['qc_id'] ? erp_rows($pdo, "SELECT id, grn_item_id, item_type, item_id, received_qty, accepted_qty, rejected_qty, damaged_qty, result FROM quality_check_items WHERE qc_id = ? ORDER BY id", [$c['qc_id']]) : [];
            $c['grn_items'] = $c['grn_id'] ? erp_rows($pdo, "SELECT id, po_item_id, item_type, item_id, received_qty, accepted_qty, rejected_qty, batch_number, expiry_date FROM goods_receipt_items WHERE grn_id = ? ORDER BY id", [$c['grn_id']]) : [];
            $c['history'] = erp_rows($pdo, "SELECT action, username, created_at, new_value FROM audit_logs WHERE module = 'sf_consignments' AND record_id = ? ORDER BY id", [(string)$c['id']]);
            $c['proofs'] = sf_doc_count($pdo, 'consignment', (int)$c['id']);
            erp_out(['status' => 'success', 'record' => $c]);

        case 'cns_create':
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [(int)erp_input('po_id')]);
            if (!$po) erp_invalid('Purchase order not found.');
            if (!in_array($po['status'], ['approved', 'partially_received'], true)) erp_invalid("Material can only be loaded against an approved PO (this one is {$po['status']}).");
            $wh = erp_warehouse_ok($pdo, (int)erp_input('destination_warehouse_id', $po['warehouse_id'] ?? 0));
            $poItems = []; foreach (erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ?", [$po['id']]) as $pi) $poItems[(int)$pi['id']] = $pi;
            $lines = [];
            foreach (erp_json_input('lines') as $l) {
                $pi = $poItems[(int)($l['po_item_id'] ?? 0)] ?? null;
                if (!$pi) erp_invalid('A line does not belong to this purchase order.');
                $mfg = erp_date($l['manufacturing_date'] ?? ''); $exp = erp_date($l['expiry_date'] ?? '');
                if ($mfg && $exp && $exp < $mfg) erp_invalid('Expiry date is before the manufacturing date.');
                $lines[] = [$pi, sf_txt($l['batch_number'] ?? '', 60), $mfg, $exp];
            }
            if (!$lines) erp_invalid('Choose at least one PO line that is ready for loading.');
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'consignment', 'CNS');
            $pdo->prepare("INSERT INTO sf_consignments (consignment_number, po_id, supplier_id, invoice_ref, loading_location, destination_warehouse_id, status, created_by) VALUES (?,?,?,?,?,?, 'ready_for_loading', ?)")
                ->execute([$num, $po['id'], $po['supplier_id'], sf_txt(erp_input('invoice_ref'), 80), sf_txt(erp_input('loading_location'), 150), $wh, erp_user()]);
            $cid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sf_consignment_lines (consignment_id, po_item_id, item_type, item_id, sku, unit, batch_number, manufacturing_date, expiry_date, ordered_qty, unit_weight_kg) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($lines as [$pi, $batch, $mfg, $exp]) {
                $item = erp_item($pdo, $pi['item_type'], (int)$pi['item_id']);
                $ins->execute([$cid, $pi['id'], $pi['item_type'], $pi['item_id'], $item['sku'] ?? null, $pi['item_type'] === 'product' ? 'pcs' : ($item['unit'] ?? $pi['unit']),
                               $batch, $mfg, $exp, erp_q(max(0, $pi['quantity'] - $pi['received_qty'])), sf_unit_weight($pdo, $pi['item_type'], (int)$pi['item_id'])]);
            }
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_consignments', $cid, null, ['consignment' => $num, 'po' => $po['po_number'], 'status' => 'ready_for_loading', 'lines' => count($lines)]);
            erp_out(['status' => 'success', 'id' => $cid, 'message' => "{$num}: material ready for loading. Enter the loading check and machine weight."]);

        case 'cns_loading_save':
            $id = (int)erp_input('id');
            $confirm = erp_input('confirm') === '1';
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['ready_for_loading'], 'changing the loading check');
            $allowOver = erp_setting($pdo, 'erp_allow_over_receipt', '0') === '1';
            $cur = []; foreach (sf_lines($pdo, $id) as $l) $cur[(int)$l['id']] = $l;
            $old = array_values($cur);
            foreach (erp_json_input('lines') as $in) {
                $l = $cur[(int)($in['id'] ?? 0)] ?? null;
                if (!$l) continue;
                $loaded = sf_dec($in['loaded_qty'] ?? null, 'Loaded quantity') ?? 0.0;
                sf_whole($l['item_type'], $loaded, $l['item_name']);
                if (!$allowOver && $l['po_item_id']) {
                    $pi = erp_row($pdo, "SELECT quantity, received_qty FROM purchase_order_items WHERE id = ?", [$l['po_item_id']]);
                    $other = (float)erp_val($pdo, "SELECT COALESCE(SUM(x.loaded_qty),0) FROM sf_consignment_lines x JOIN sf_consignments c ON c.id = x.consignment_id
                                                  WHERE x.po_item_id = ? AND x.id <> ? AND c.status NOT IN ('cancelled','stocked','qc_failed')", [$l['po_item_id'], $l['id']]);
                    $pending = erp_q($pi['quantity'] - $pi['received_qty'] - $other);
                    if ($loaded > $pending + 0.0005) erp_invalid("{$l['item_name']}: loaded {$loaded} is more than the PO quantity still open ({$pending}).");
                }
                $actual = sf_dec($in['actual_weight'] ?? null, 'Machine weight');
                $count = sf_dec($in['physical_count'] ?? null, 'Physical count');
                $mfg = erp_date($in['manufacturing_date'] ?? $l['manufacturing_date']); $exp = erp_date($in['expiry_date'] ?? $l['expiry_date']);
                if ($mfg && $exp && $exp < $mfg) erp_invalid("{$l['item_name']}: expiry date is before the manufacturing date.");
                $expected = $l['unit_weight_kg'] !== null ? erp_q($loaded * (float)$l['unit_weight_kg']) : sf_dec($in['expected_weight'] ?? null, 'Expected weight');
                $pdo->prepare("UPDATE sf_consignment_lines SET loaded_qty = ?, expected_weight = ?, actual_weight = ?, physical_count = ?, batch_number = ?, manufacturing_date = ?, expiry_date = ? WHERE id = ?")
                    ->execute([$loaded, $expected, $actual, $count, sf_txt($in['batch_number'] ?? $l['batch_number'], 60), $mfg, $exp, $l['id']]);
            }
            // a split line (same PO line, another batch)
            foreach (erp_json_input('add_lines') as $in) {
                $src = $cur[(int)($in['copy_of'] ?? 0)] ?? null;
                if (!$src) continue;
                $pdo->prepare("INSERT INTO sf_consignment_lines (consignment_id, po_item_id, item_type, item_id, sku, unit, batch_number, expiry_date, ordered_qty, unit_weight_kg) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$id, $src['po_item_id'], $src['item_type'], $src['item_id'], $src['sku'], $src['unit'], sf_txt($in['batch_number'] ?? '', 60), erp_date($in['expiry_date'] ?? ''), 0, $src['unit_weight_kg']]);
            }
            $date = erp_date(erp_input('loading_date')) ?: null;
            $pdo->prepare("UPDATE sf_consignments SET invoice_ref = ?, loading_location = ?, loading_date = ?, loading_time = ?, loaded_by = ?, checked_by = ?, loading_remarks = ? WHERE id = ?")
                ->execute([sf_txt(erp_input('invoice_ref', $c['invoice_ref']), 80), sf_txt(erp_input('loading_location', $c['loading_location']), 150), $date, sf_time(erp_input('loading_time')),
                           sf_txt(erp_input('loaded_by'), 150), sf_txt(erp_input('checked_by'), 150), sf_txt(erp_input('loading_remarks'), 500), $id]);
            $msg = "{$c['consignment_number']} loading check saved.";
            if ($confirm) {
                $tot = erp_row($pdo, "SELECT COUNT(*) n, COALESCE(SUM(loaded_qty),0) q, SUM(loaded_qty > 0 AND (actual_weight IS NULL OR physical_count IS NULL)) missing FROM sf_consignment_lines WHERE consignment_id = ?", [$id]);
                if ((float)$tot['q'] <= 0) erp_invalid('Enter the loaded quantity of at least one line.');
                if ((int)$tot['missing'] > 0) erp_invalid('Every loaded line needs the machine weight and the physical count.');
                foreach (['loading_date' => 'Loading date', 'loaded_by' => 'Loaded by', 'checked_by' => 'Checked by'] as $k => $lbl) if (!trim((string)erp_input($k, ''))) erp_invalid("{$lbl} is required.");
                if (strcasecmp(trim((string)erp_input('loaded_by')), trim((string)erp_input('checked_by'))) === 0) erp_invalid('Loaded by and Checked by must be two different people.');
                $pdo->prepare("DELETE FROM sf_consignment_lines WHERE consignment_id = ? AND loaded_qty <= 0")->execute([$id]);   // lines not loaded in this dispatch
                $pdo->prepare("UPDATE sf_consignments SET status = 'loaded', loaded_at = NOW(), loaded_user = ? WHERE id = ?")->execute([erp_user(), $id]);
                $msg = "{$c['consignment_number']} LOADED. Now enter the transport (internal vehicle or courier).";
            }
            $pdo->commit();
            log_audit($pdo, $confirm ? 'loaded' : 'update', 'sf_consignments', $id, ['lines' => $old], ['status' => $confirm ? 'loaded' : $c['status'], 'lines' => sf_lines($pdo, $id)]);
            erp_out(['status' => 'success', 'message' => $msg]);

        case 'cns_transport_save':
            $id = (int)erp_input('id');
            $dispatch = erp_input('dispatch') === '1';
            $type = (string)erp_input('transport_type');
            if (!in_array($type, ['internal', 'courier'], true)) erp_invalid('Choose Internal vehicle or Courier.');
            $f = ['vehicle_name' => null, 'vehicle_number' => null, 'driver_name' => null, 'driver_employee_id' => null, 'driver_phone' => null, 'courier_service_id' => null, 'courier_name' => null,
                  'courier_service_type' => null, 'tracking_number' => null, 'courier_phone' => null];
            $phone = function ($v, $lbl) { $v = preg_replace('/[^0-9+]/', '', (string)$v); if ($v !== '' && strlen(ltrim($v, '+')) < 10) erp_invalid("{$lbl}: enter a valid phone number."); return $v === '' ? null : $v; };
            if ($type === 'internal') {
                $f['vehicle_name'] = sf_txt(erp_input('vehicle_name'), 80); $f['vehicle_number'] = strtoupper((string)sf_txt(erp_input('vehicle_number'), 30)) ?: null;
                $f['driver_name'] = sf_txt(erp_input('driver_name'), 150); $f['driver_employee_id'] = sf_txt(erp_input('driver_employee_id'), 40); $f['driver_phone'] = $phone(erp_input('driver_phone'), 'Driver phone');
                if (!$f['vehicle_number'] || !$f['driver_name'] || !$f['driver_phone']) erp_invalid('Internal transport needs the vehicle number, driver name and driver phone.');
            } else {
                $cs = (int)erp_input('courier_service_id', 0);
                $row = $cs ? erp_row($pdo, "SELECT id, name FROM courier_services WHERE id = ?", [$cs]) : null;
                if (!$row) erp_invalid('Choose the courier company.');
                $f['courier_service_id'] = (int)$row['id'];
                $f['courier_name'] = stripos($row['name'], 'other') === 0 ? sf_txt(erp_input('courier_name'), 150) : $row['name'];
                if (!$f['courier_name']) erp_invalid('Type the courier company name.');
                $f['courier_service_type'] = sf_txt(erp_input('courier_service_type'), 60);
                $f['tracking_number'] = strtoupper((string)sf_txt(erp_input('tracking_number'), 80)) ?: null;
                if (!$f['tracking_number']) erp_invalid('Courier needs the tracking number.');
                $f['courier_phone'] = $phone(erp_input('courier_phone'), 'Courier phone');   // optional
            }
            $dd = erp_date(erp_input('departure_date')); $ea = erp_date(erp_input('expected_arrival_date'));
            if ($dd && $ea && $ea < $dd) erp_invalid(($type === 'courier' ? 'Expected delivery' : 'Expected arrival') . ' cannot be before dispatch.');
            if ($dispatch && !$dd) erp_invalid($type === 'courier' ? 'Dispatch date is required.' : 'Departure date is required.');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['loaded', 'in_transit'], 'changing transport');
            $vals = $f + ['transport_type' => $type, 'pickup_location' => sf_txt(erp_input('pickup_location', $c['loading_location']), 150), 'departure_date' => $dd, 'departure_time' => sf_time(erp_input('departure_time')),
                          'expected_arrival_date' => $ea, 'expected_arrival_time' => sf_time(erp_input('expected_arrival_time')), 'transport_remarks' => sf_txt(erp_input('transport_remarks'), 500)];
            $pdo->prepare("UPDATE sf_consignments SET " . implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($vals))) . " WHERE id = ?")->execute(array_merge(array_values($vals), [$id]));
            // keep the existing Transport / Logistics record in step (costs are entered there)
            $shp = [$c['po_id'], $c['supplier_id'], $type === 'courier' ? $f['courier_name'] : $f['driver_name'], $type === 'courier' ? $f['courier_name'] : 'Own vehicle', $type === 'courier' ? 'courier' : 'own_vehicle',
                    $f['vehicle_number'], $f['driver_name'], $f['driver_phone'] ?? $f['courier_phone'], $f['tracking_number'], $c['consignment_number'], $dd, $ea];
            if ($c['shipment_id']) {
                $pdo->prepare("UPDATE inbound_shipments SET po_id=?, supplier_id=?, transporter_name=?, transport_company=?, transport_mode=?, vehicle_number=?, driver_name=?, driver_phone=?, lr_number=?, consignment_number=?,
                               dispatch_date=?, expected_arrival=?, status = IF(? = 1, 'in_transit', status) WHERE id = ? AND status <> 'cancelled'")
                    ->execute(array_merge($shp, [$dispatch ? 1 : 0, $c['shipment_id']]));
                $sid = (int)$c['shipment_id'];
            } else {
                $snum = next_document_number($pdo, 'shipment', 'SHP');
                $pdo->prepare("INSERT INTO inbound_shipments (po_id, supplier_id, transporter_name, transport_company, transport_mode, vehicle_number, driver_name, driver_phone, lr_number, consignment_number,
                               dispatch_date, expected_arrival, shipment_number, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute(array_merge($shp, [$snum, $dispatch ? 'in_transit' : 'planned', "Created by stock lifecycle {$c['consignment_number']}", erp_user()]));
                $sid = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE sf_consignments SET shipment_id = ? WHERE id = ?")->execute([$sid, $id]);
            }
            $msg = "{$c['consignment_number']} transport saved.";
            if ($dispatch && $c['status'] === 'loaded') { $pdo->prepare("UPDATE sf_consignments SET status = 'in_transit' WHERE id = ?")->execute([$id]); $msg = "{$c['consignment_number']} IN TRANSIT."; }
            $pdo->commit();
            log_audit($pdo, $dispatch ? 'dispatch' : 'update', 'sf_consignments', $id, array_intersect_key($c, $vals), $vals + ['status' => $dispatch ? 'in_transit' : $c['status'], 'shipment_id' => $sid]);
            erp_out(['status' => 'success', 'message' => $msg, 'shipment_id' => $sid]);

        case 'cns_deliver':
            $id = (int)erp_input('id');
            $d = erp_date(erp_input('actual_arrival_date'), true);
            if ($d > date('Y-m-d')) erp_invalid('Arrival date cannot be in the future.');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['in_transit'], 'marking delivered');
            if ($c['departure_date'] && $d < $c['departure_date']) erp_invalid('Arrival cannot be before departure.');
            $pdo->prepare("UPDATE sf_consignments SET status = 'delivered', actual_arrival_date = ?, actual_arrival_time = ? WHERE id = ?")->execute([$d, sf_time(erp_input('actual_arrival_time')), $id]);
            if ($c['shipment_id']) $pdo->prepare("UPDATE inbound_shipments SET status = 'arrived', actual_arrival = ? WHERE id = ? AND status <> 'cancelled'")->execute([$d, $c['shipment_id']]);
            $pdo->commit();
            log_audit($pdo, 'delivered', 'sf_consignments', $id, ['status' => $c['status']], ['status' => 'delivered', 'arrival' => $d]);
            erp_out(['status' => 'success', 'message' => "{$c['consignment_number']} DELIVERED — stock is NOT available yet. Unload and receive it."]);

        case 'cns_unload_save':
            $id = (int)erp_input('id');
            $confirm = erp_input('confirm') === '1';
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['delivered'], 'unloading');
            $uwh = erp_warehouse_ok($pdo, (int)erp_input('unload_warehouse_id', $c['destination_warehouse_id']));
            $loc = (int)erp_input('unload_location_id', 0) ?: null;
            if ($loc && !erp_val($pdo, "SELECT id FROM warehouse_locations WHERE id = ? AND warehouse_id = ?", [$loc, $uwh])) erp_invalid('The rack / bin belongs to another location.');
            $cur = []; foreach (sf_lines($pdo, $id) as $l) $cur[(int)$l['id']] = $l;
            foreach (erp_json_input('lines') as $in) {
                $l = $cur[(int)($in['id'] ?? 0)] ?? null;
                if (!$l) continue;
                $q = sf_dec($in['unloaded_qty'] ?? null, 'Unloaded quantity');
                if ($q !== null) sf_whole($l['item_type'], $q, $l['item_name']);
                $exp = erp_date($in['expiry_date'] ?? $l['expiry_date']);
                $pdo->prepare("UPDATE sf_consignment_lines SET unloaded_qty = ?, unload_machine_weight = ?, unload_physical_count = ?, batch_number = ?, expiry_date = ? WHERE id = ?")
                    ->execute([$q, sf_dec($in['unload_machine_weight'] ?? null, 'Machine weight'), sf_dec($in['unload_physical_count'] ?? null, 'Physical count'),
                               sf_txt($in['batch_number'] ?? $l['batch_number'], 60), $exp, $l['id']]);
            }
            $date = erp_date(erp_input('unload_date'));
            $pdo->prepare("UPDATE sf_consignments SET unload_warehouse_id = ?, unload_location_id = ?, receiver_name = ?, receiver_employee_id = ?, unload_date = ?, unload_time = ?, unload_remarks = ? WHERE id = ?")
                ->execute([$uwh, $loc, sf_txt(erp_input('receiver_name'), 150), sf_txt(erp_input('receiver_employee_id'), 40), $date, sf_time(erp_input('unload_time')), sf_txt(erp_input('unload_remarks'), 500), $id]);
            $msg = "{$c['consignment_number']} unloading saved.";
            if ($confirm) {
                if (!trim((string)erp_input('receiver_name', '')) || !trim((string)erp_input('receiver_employee_id', '')) || !$date) erp_invalid('Receiver name, receiver employee ID and unloading date are required.');
                if ($date > date('Y-m-d')) erp_invalid('Unloading date cannot be in the future.');
                $m = (int)erp_val($pdo, "SELECT COUNT(*) FROM sf_consignment_lines WHERE consignment_id = ? AND (unloaded_qty IS NULL OR unload_machine_weight IS NULL OR unload_physical_count IS NULL)", [$id]);
                if ($m) erp_invalid("{$m} line(s) still need the unloaded quantity, machine weight and physical count (enter 0 if nothing arrived).");
                if ((float)erp_val($pdo, "SELECT COALESCE(SUM(unloaded_qty),0) FROM sf_consignment_lines WHERE consignment_id = ?", [$id]) <= 0) erp_invalid('Nothing was unloaded — cancel the consignment instead.');
                $pdo->prepare("UPDATE sf_consignments SET status = 'received', unloaded_at = NOW(), unloaded_user = ? WHERE id = ?")->execute([erp_user(), $id]);
                $msg = "{$c['consignment_number']} RECEIVED. Creating the goods receipt and quality check (stock stays in quarantine).";
            }
            $pdo->commit();
            log_audit($pdo, $confirm ? 'received' : 'update', 'sf_consignments', $id, ['lines' => array_values($cur)], ['status' => $confirm ? 'received' : $c['status'], 'lines' => sf_lines($pdo, $id)]);
            // the GRN draft is built from the consignment lines (unloaded qty) — the page sends these to the existing purchase_api.php grn_save
            $grnLines = [];
            foreach (sf_lines($pdo, $id) as $l) if ((float)$l['unloaded_qty'] > 0)
                $grnLines[] = ['po_item_id' => $l['po_item_id'], 'item_type' => $l['item_type'], 'item_id' => $l['item_id'], 'received_qty' => $l['unloaded_qty'], 'rejected_qty' => 0,
                               'batch_number' => $l['batch_number'], 'manufacturing_date' => $l['manufacturing_date'], 'expiry_date' => $l['expiry_date'], 'qc_status' => 'pending'];
            erp_out(['status' => 'success', 'message' => $msg, 'grn_payload' => $confirm ? ['po_id' => $c['po_id'], 'warehouse_id' => $uwh, 'received_date' => $date,
                     'received_by' => erp_input('receiver_name'), 'vehicle_number' => $c['vehicle_number'], 'supplier_challan_no' => $c['invoice_ref'],
                     'notes' => "Stock lifecycle {$c['consignment_number']}", 'items' => $grnLines] : null]);

        case 'cns_link_qc':
            // after the page created the draft GRN (purchase_api grn_save) and the QC (procurement_api qc_create)
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['received'], 'linking the goods receipt');
            $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE", [(int)erp_input('grn_id')]);
            if (!$g || (int)$g['po_id'] !== (int)$c['po_id'] || $g['status'] !== 'draft') erp_invalid('The goods receipt must be a DRAFT of the same purchase order.');
            if (erp_val($pdo, "SELECT id FROM sf_consignments WHERE grn_id = ? AND id <> ?", [$g['id'], $id])) erp_invalid('This goods receipt already belongs to another consignment.');
            $q = erp_row($pdo, "SELECT * FROM quality_checks WHERE grn_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$g['id']]);
            if (!$q) erp_invalid('Create the quality check for the goods receipt first.');
            $gi = erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ? ORDER BY id", [$g['id']]);
            $ls = erp_rows($pdo, "SELECT * FROM sf_consignment_lines WHERE consignment_id = ? AND unloaded_qty > 0 ORDER BY id", [$id]);
            if (count($gi) !== count($ls)) erp_invalid('The goods receipt lines do not match the unloaded lines.');
            foreach ($ls as $k => $l) {
                if ((int)$gi[$k]['item_id'] !== (int)$l['item_id'] || $gi[$k]['item_type'] !== $l['item_type'] || abs((float)$gi[$k]['received_qty'] - (float)$l['unloaded_qty']) > 0.0005)
                    erp_invalid('The goods receipt quantities do not match what was unloaded.');
                $pdo->prepare("UPDATE sf_consignment_lines SET grn_item_id = ? WHERE id = ?")->execute([$gi[$k]['id'], $l['id']]);
            }
            $pdo->prepare("UPDATE sf_consignments SET grn_id = ?, qc_id = ?, status = 'qc_pending' WHERE id = ?")->execute([$g['id'], $q['id'], $id]);
            if ($c['shipment_id']) $pdo->prepare("UPDATE inbound_shipments SET grn_id = COALESCE(grn_id, ?) WHERE id = ?")->execute([$g['id'], $c['shipment_id']]);
            $pdo->commit();
            log_audit($pdo, 'qc_pending', 'sf_consignments', $id, ['status' => $c['status']], ['status' => 'qc_pending', 'grn' => $g['grn_number'], 'qc' => $q['qc_number']]);
            erp_out(['status' => 'success', 'message' => "{$c['consignment_number']}: {$g['grn_number']} + {$q['qc_number']} created — QC PENDING (goods in quarantine, not sellable)."]);

        case 'cns_qc_save':
            $id = (int)erp_input('id');
            $result = (string)erp_input('result', '');
            if ($result !== '' && !in_array($result, ['pass', 'partial', 'hold', 'fail'], true)) erp_invalid('Invalid QC result.');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['qc_pending', 'qc_hold'], 'QC');
            $cur = []; foreach (sf_lines($pdo, $id) as $l) $cur[(int)$l['id']] = $l;
            $old = array_values($cur);
            $acc = 0; $rej = 0; $qcItems = [];
            $qci = []; foreach (erp_rows($pdo, "SELECT id, grn_item_id FROM quality_check_items WHERE qc_id = ?", [$c['qc_id']]) as $r) $qci[(int)$r['grn_item_id']] = (int)$r['id'];
            foreach (erp_json_input('lines') as $in) {
                $l = $cur[(int)($in['id'] ?? 0)] ?? null;
                if (!$l || (float)$l['unloaded_qty'] <= 0) continue;
                $a = sf_dec($in['qc_accepted'] ?? null, 'Accepted', $result !== 'hold') ?? 0.0;
                $r = sf_dec($in['qc_rejected'] ?? 0, 'Rejected') ?? 0.0;
                $d = sf_dec($in['qc_damaged'] ?? 0, 'Damaged') ?? 0.0;
                foreach ([$a, $r, $d] as $x) sf_whole($l['item_type'], $x, $l['item_name']);
                if ($result !== 'hold' && abs($a + $r + $d - (float)$l['unloaded_qty']) > 0.0005) erp_invalid("{$l['item_name']}: accepted + rejected + damaged must equal the unloaded {$l['unloaded_qty']}.");
                $note = sf_txt($in['qc_remarks'] ?? '', 255);
                if ($result !== 'hold' && ($r + $d) > 0 && !$note) erp_invalid("{$l['item_name']}: give the rejection / damage reason.");
                // excess goods cannot be accepted beyond the invoice / loaded quantity
                $inv = sf_dec($in['invoice_qty'] ?? null, 'Invoice quantity') ?? (float)$l['loaded_qty'];
                if ($result !== 'hold' && $a > $inv + 0.0005 && erp_setting($pdo, 'erp_allow_over_receipt', '0') !== '1') erp_invalid("{$l['item_name']}: accepted {$a} is more than the invoice quantity {$inv} — reject the excess.");
                $check = [];
                foreach ((array)($in['qc_checklist'] ?? []) as $k => $v) $check[mb_substr((string)$k, 0, 120)] = is_scalar($v) ? mb_substr((string)$v, 0, 120) : null;
                $lr = $result === 'hold' ? 'hold' : (($r + $d) <= 0 ? 'pass' : ($a > 0 ? 'partial' : 'fail'));
                $pdo->prepare("UPDATE sf_consignment_lines SET invoice_qty = ?, qc_machine_weight = ?, qc_physical_count = ?, qc_packaging = ?, qc_condition = ?, qc_grade = ?, qc_accepted = ?, qc_rejected = ?, qc_damaged = ?,
                               qc_checklist = ?, qc_line_result = ?, qc_remarks = ?, batch_number = ?, expiry_date = ? WHERE id = ?")
                    ->execute([$inv, sf_dec($in['qc_machine_weight'] ?? null, 'QC machine weight'), sf_dec($in['qc_physical_count'] ?? null, 'QC physical count'),
                               in_array($in['qc_packaging'] ?? '', ['ok', 'damaged', 'na'], true) ? $in['qc_packaging'] : null, in_array($in['qc_condition'] ?? '', ['good', 'fair', 'poor'], true) ? $in['qc_condition'] : null,
                               sf_txt($in['qc_grade'] ?? '', 20), $a, $r, $d, $check ? json_encode($check) : null, $lr, $note, sf_txt($in['batch_number'] ?? $l['batch_number'], 60),
                               erp_date($in['expiry_date'] ?? $l['expiry_date']), $l['id']]);
                $acc += $a; $rej += $r + $d;
                if ($l['grn_item_id'] && isset($qci[(int)$l['grn_item_id']])) $qcItems[] = ['id' => $qci[(int)$l['grn_item_id']], 'accepted_qty' => $a, 'rejected_qty' => $r, 'damaged_qty' => $d, 'rejection_reason' => $note];
            }
            if ($result === '') $result = $rej <= 0.0005 ? 'pass' : ($acc > 0.0005 ? 'partial' : 'fail');
            elseif ($result !== 'hold') {
                $calc = $rej <= 0.0005 ? 'pass' : ($acc > 0.0005 ? 'partial' : 'fail');
                if ($calc !== $result) erp_invalid('The quantities say ' . strtoupper($calc === 'partial' ? 'partial acceptance' : $calc) . ' — the QC result must match.');
            }
            $by = sf_txt(erp_input('qc_by'), 150) ?: ($_SESSION['admin_full_name'] ?? erp_user());
            $pdo->prepare("UPDATE sf_consignments SET qc_result = ?, qc_by = ?, qc_at = NOW(), qc_remarks = ?, status = ? WHERE id = ?")
                ->execute([$result, $by, sf_txt(erp_input('qc_remarks'), 500), $result === 'hold' ? 'qc_hold' : $c['status'], $id]);
            $pdo->commit();
            log_audit($pdo, 'qc_' . $result, 'sf_consignments', $id, ['qc_result' => $c['qc_result'], 'lines' => $old], ['qc_result' => $result, 'lines' => sf_lines($pdo, $id)]);
            $msg = $result === 'hold' ? "{$c['consignment_number']} QC HOLD — the goods stay in quarantine until re-checked." : "QC {$result} recorded — applying it to {$c['consignment_number']}'s quality check.";
            // the page sends qc_payload to the existing procurement_api.php qc_complete, then calls cns_qc_done
            erp_out(['status' => 'success', 'message' => $msg, 'result' => $result,
                     'qc_payload' => $result === 'hold' ? null : ['id' => $c['qc_id'], 'inspected_by' => $by, 'inspection_date' => date('Y-m-d'), 'notes' => "Stock lifecycle {$c['consignment_number']}: " . strtoupper($result) . (erp_input('qc_remarks') ? ' — ' . erp_input('qc_remarks') : ''), 'items' => $qcItems]]);

        case 'cns_qc_done':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['qc_pending', 'qc_hold'], 'finishing QC');
            if (!$c['qc_result'] || $c['qc_result'] === 'hold') erp_invalid('Record the QC result first.');
            $q = erp_row($pdo, "SELECT * FROM quality_checks WHERE id = ?", [$c['qc_id']]);
            if (!$q || in_array($q['status'], ['pending', 'cancelled'], true)) erp_invalid('The quality check is not completed yet.');
            // the completed QC must say exactly what this consignment's QC says
            foreach (erp_rows($pdo, "SELECT * FROM sf_consignment_lines WHERE consignment_id = ? AND unloaded_qty > 0", [$id]) as $l) {
                $qi = erp_row($pdo, "SELECT accepted_qty, rejected_qty, damaged_qty FROM quality_check_items WHERE qc_id = ? AND grn_item_id = ?", [$c['qc_id'], $l['grn_item_id']]);
                if (!$qi || abs((float)$qi['accepted_qty'] - (float)$l['qc_accepted']) > 0.0005 || abs((float)$qi['rejected_qty'] + (float)$qi['damaged_qty'] - (float)$l['qc_rejected'] - (float)$l['qc_damaged']) > 0.0005)
                    erp_invalid('The quality check result differs from the consignment QC. Re-enter the QC.');
            }
            $to = $c['qc_result'] === 'fail' ? 'qc_failed' : 'qc_approved';
            $pdo->prepare("UPDATE sf_consignments SET status = ? WHERE id = ?")->execute([$to, $id]);
            $pdo->commit();
            log_audit($pdo, $to, 'sf_consignments', $id, ['status' => $c['status']], ['status' => $to, 'qc' => $q['qc_number'], 'qc_status' => $q['status']]);
            erp_out(['status' => 'success', 'message' => $to === 'qc_failed' ? "{$c['consignment_number']} QC FAILED — post the receipt to move the goods to REJECTED stock, then return them to the supplier (Return dispatch)."
                                                                            : "{$c['consignment_number']} QC APPROVED — post the stock in.",
                     'grn_payload' => ['id' => $c['grn_id'], 'po_id' => $c['po_id'], 'warehouse_id' => $c['unload_warehouse_id'] ?: $c['destination_warehouse_id'], 'received_date' => $c['unload_date'],
                                       'received_by' => $c['receiver_name'], 'vehicle_number' => $c['vehicle_number'], 'supplier_challan_no' => $c['invoice_ref'], 'notes' => "Stock lifecycle {$c['consignment_number']}", 'items' => []]]);

        case 'cns_stocked':
            // after the page posted the GRN (existing purchase_api grn_post → stock_ins + ledger + batches)
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            sf_need_status($c, ['qc_approved', 'qc_failed'], 'stock in');
            $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ?", [$c['grn_id']]);
            if (!$g || $g['status'] !== 'posted') erp_invalid('The goods receipt is not posted yet.');
            $to = $c['status'] === 'qc_failed' ? 'qc_failed' : 'stocked';
            $pdo->prepare("UPDATE sf_consignments SET status = ?, stock_in_id = ?, stocked_at = NOW(), stocked_by = ? WHERE id = ?")->execute([$to, $g['stock_in_id'], erp_user(), $id]);
            if ($c['unload_location_id']) foreach (erp_rows($pdo, "SELECT DISTINCT item_type, item_id FROM sf_consignment_lines WHERE consignment_id = ? AND qc_accepted > 0", [$id]) as $l)
                $pdo->prepare("INSERT INTO item_locations (item_type, item_id, warehouse_id, location_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE location_id = VALUES(location_id)")
                    ->execute([$l['item_type'], $l['item_id'], $c['unload_warehouse_id'] ?: $c['destination_warehouse_id'], $c['unload_location_id']]);
            $pdo->commit();
            log_audit($pdo, $to === 'stocked' ? 'stocked' : 'rejected_posted', 'sf_consignments', $id, ['status' => $c['status']], ['status' => $to, 'grn' => $g['grn_number'], 'stock_in_id' => $g['stock_in_id']]);
            erp_out(['status' => 'success', 'message' => $to === 'stocked' ? "{$c['consignment_number']} STOCKED — accepted goods are now AVAILABLE at the location." : "{$c['consignment_number']}: rejected goods are held in REJECTED stock."]);

        case 'cns_cancel':
            $id = (int)erp_input('id');
            $reason = sf_txt(erp_input('reason'), 255);
            if (!$reason) erp_invalid('Give a reason.');
            $c = sf_cns($pdo, $id);
            sf_need_status($c, ['ready_for_loading', 'loaded', 'in_transit', 'delivered'], 'cancelling');
            $pdo->prepare("UPDATE sf_consignments SET status = 'cancelled', loading_remarks = CONCAT(COALESCE(loading_remarks,''), ' [Cancelled: ', ?, ']') WHERE id = ?")->execute([$reason, $id]);
            if ($c['shipment_id']) $pdo->prepare("UPDATE inbound_shipments SET status = 'cancelled' WHERE id = ? AND grn_id IS NULL AND cost_applied = 0 AND amount_paid = 0")->execute([$c['shipment_id']]);
            log_audit($pdo, 'cancel', 'sf_consignments', $id, ['status' => $c['status']], ['status' => 'cancelled', 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$c['consignment_number']} cancelled."]);

        case 'cns_correct':
            // controlled correction of a locked loading / unloading figure: approver + reason, old and new value kept in the audit log
            $id = (int)erp_input('id'); $lineId = (int)erp_input('line_id');
            $field = (string)erp_input('field');
            $reason = sf_txt(erp_input('reason'), 255);
            if (!$reason) erp_invalid('A correction needs a reason.');
            $loadF = ['loaded_qty', 'actual_weight', 'physical_count']; $unloadF = ['unloaded_qty', 'unload_machine_weight', 'unload_physical_count'];
            if (!in_array($field, array_merge($loadF, $unloadF), true)) erp_invalid('This figure cannot be corrected.');
            $pdo->beginTransaction();
            $c = sf_cns($pdo, $id, true);
            if (in_array($field, $loadF, true)) sf_need_status($c, ['loaded', 'in_transit', 'delivered'], 'correcting a loading figure');
            else sf_need_status($c, ['received'], 'correcting an unloading figure');
            $l = erp_row($pdo, "SELECT * FROM sf_consignment_lines WHERE id = ? AND consignment_id = ?", [$lineId, $id]);
            if (!$l) erp_invalid('Line not found.');
            $new = sf_dec(erp_input('value'), 'New value', true);
            if (in_array($field, ['loaded_qty', 'unloaded_qty'], true)) sf_whole($l['item_type'], $new, 'Quantity');
            $pdo->prepare("UPDATE sf_consignment_lines SET {$field} = ?" . ($field === 'loaded_qty' && $l['unit_weight_kg'] !== null ? ', expected_weight = ? * unit_weight_kg' : '') . " WHERE id = ?")
                ->execute($field === 'loaded_qty' && $l['unit_weight_kg'] !== null ? [$new, $new, $lineId] : [$new, $lineId]);
            $pdo->commit();
            log_audit($pdo, 'correction', 'sf_consignments', $id, ['line' => $lineId, $field => $l[$field]], ['line' => $lineId, $field => $new, 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "Corrected {$field} from " . ($l[$field] ?? '—') . " to {$new} (logged)."]);

        // ---------------------------------------------------------------- QC check master
        case 'qcp_list':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT q.*, p.product_name FROM sf_qc_params q LEFT JOIN product_details p ON p.id = q.product_id ORDER BY q.is_active DESC, q.scope, q.sort_order, q.id")]);
        case 'qcp_save':
            $scope = in_array(erp_input('scope'), ['all', 'category', 'product'], true) ? erp_input('scope') : 'all';
            $name = sf_txt(erp_input('check_name'), 120);
            if (!$name) erp_invalid('Enter the check name.');
            $cat = $scope === 'category' ? sf_txt(erp_input('category'), 100) : null; $pid = $scope === 'product' ? ((int)erp_input('product_id') ?: null) : null;
            if ($scope === 'category' && !$cat) erp_invalid('Choose the category.');
            if ($scope === 'product' && !$pid) erp_invalid('Choose the product.');
            $type = in_array(erp_input('check_type'), ['yesno', 'number', 'text'], true) ? erp_input('check_type') : 'yesno';
            $vals = [$scope, $cat, $pid, $name, $type, sf_dec(erp_input('min_value'), 'Min'), sf_dec(erp_input('max_value'), 'Max'), (int)erp_input('sort_order', 100)];
            if ($id = (int)erp_input('id', 0)) $pdo->prepare("UPDATE sf_qc_params SET scope=?, category=?, product_id=?, check_name=?, check_type=?, min_value=?, max_value=?, sort_order=? WHERE id=?")->execute(array_merge($vals, [$id]));
            else { $pdo->prepare("INSERT INTO sf_qc_params (scope, category, product_id, check_name, check_type, min_value, max_value, sort_order, created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute(array_merge($vals, [erp_user()])); $id = (int)$pdo->lastInsertId(); }
            log_audit($pdo, 'save', 'sf_qc_params', $id, null, ['scope' => $scope, 'category' => $cat, 'product' => $pid, 'check' => $name]);
            erp_out(['status' => 'success', 'message' => 'QC check saved.']);
        case 'qcp_status':
            $pdo->prepare("UPDATE sf_qc_params SET is_active = ? WHERE id = ?")->execute([erp_input('active') === '1' ? 1 : 0, (int)erp_input('id')]);
            log_audit($pdo, 'status', 'sf_qc_params', (int)erp_input('id'), null, ['active' => erp_input('active')]);
            erp_out(['status' => 'success', 'message' => 'Updated.']);

        // ============================================================ STOCK OUT (controlled issue)
        case 'iss_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'i.status = ?'; $p[] = $s; }
            if ($s = erp_input('purpose')) { $w[] = 'i.purpose = ?'; $p[] = $s; }
            $rows = erp_rows($pdo, "SELECT i.*, w.name AS warehouse_name, (SELECT COALESCE(SUM(quantity),0) FROM sf_stock_issue_lines l WHERE l.issue_id = i.id) AS qty,
                                           (SELECT COALESCE(SUM(weight_kg),0) FROM sf_stock_issue_lines l WHERE l.issue_id = i.id) AS weight
                                    FROM sf_stock_issues i JOIN warehouses w ON w.id = i.warehouse_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY i.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);
        case 'iss_get':
            $r = erp_row($pdo, "SELECT i.*, w.name AS warehouse_name FROM sf_stock_issues i JOIN warehouses w ON w.id = i.warehouse_id WHERE i.id = ?", [(int)erp_input('id')]);
            if (!$r) erp_fail('Not found.');
            $r['lines'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT l.*, b.batch_number FROM sf_stock_issue_lines l LEFT JOIN inventory_batches b ON b.id = l.batch_id WHERE l.issue_id = ?", [$r['id']]));
            erp_out(['status' => 'success', 'record' => $r]);
        case 'iss_create':
            $purpose = (string)erp_input('purpose');
            $redirect = ['sales' => 'Sales reduce stock automatically from the sales documents (Sales Orders / Invoices / Manual Sales).', 'internal_transfer' => 'Use Stock Transfers to move stock between locations.',
                         'damage' => 'Use Damage (identify → verify → approve).', 'return' => 'Use Purchase Returns (to supplier) or Sales Returns.'];
            if (isset($redirect[$purpose])) erp_invalid($redirect[$purpose]);
            if (!in_array($purpose, ['production', 'sample', 'executive_issue', 'office_consumption', 'other'], true)) erp_invalid('Choose the purpose.');
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $date = erp_date(erp_input('issue_date'), true);
            $req = sf_txt(erp_input('requested_by'), 150); $note = sf_txt(erp_input('purpose_note'), 255);
            if (!$req || !$note) erp_invalid('Requested by and the purpose details are required.');
            $lines = sf_item_lines($pdo, erp_json_input('lines'), $wh);
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'stock_issue', 'SIS');
            $pdo->prepare("INSERT INTO sf_stock_issues (issue_number, purpose, warehouse_id, issue_date, requested_by, received_by, purpose_note, remarks, status, created_by) VALUES (?,?,?,?,?,?,?,?, 'requested', ?)")
                ->execute([$num, $purpose, $wh, $date, $req, sf_txt(erp_input('received_by'), 150), $note, sf_txt(erp_input('remarks'), 500), erp_user()]);
            $iid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sf_stock_issue_lines (issue_id, item_type, item_id, batch_id, quantity, weight_kg, unit) VALUES (?,?,?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute([$iid, $l['item_type'], $l['item_id'], $l['batch_id'], $l['quantity'], $l['weight_kg'], $l['unit']]);
            apr_open($pdo, 'stock_issue', (string)$iid, $iid, $num, "Stock out {$num} — " . str_replace('_', ' ', $purpose) . ": {$note}", null, 'stock_flow_api.php', ['action' => 'iss_approve', 'id' => $iid], ['action' => 'iss_reject', 'id' => $iid]);
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_stock_issues', $iid, null, ['issue' => $num, 'purpose' => $purpose, 'lines' => $lines]);
            erp_out(['status' => 'success', 'id' => $iid, 'message' => "{$num} requested — waiting for approval. Stock is reduced only when it is issued."]);
        case 'iss_approve':
        case 'iss_reject':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM sf_stock_issues WHERE id = ?", [$id]);
            if (!$r || $r['status'] !== 'requested') erp_invalid('Only requested stock outs can be approved or rejected.');
            $to = $action === 'iss_approve' ? 'approved' : 'rejected';
            $rem = sf_txt(erp_input('_approval_remarks') ?: erp_input('reason'), 255);
            if ($to === 'rejected' && !$rem) erp_invalid('Give a reason.');
            $pdo->prepare("UPDATE sf_stock_issues SET status = ?, approved_by = ?, approved_at = NOW(), decision_remarks = ? WHERE id = ?")->execute([$to, erp_user(), $rem, $id]);
            apr_close($pdo, 'stock_issue', (string)$id, $to, $rem);
            log_audit($pdo, $to === 'approved' ? 'approve' : 'reject', 'sf_stock_issues', $id, ['status' => 'requested'], ['status' => $to, 'remarks' => $rem]);
            erp_out(['status' => 'success', 'message' => "{$r['issue_number']} {$to}."]);
        case 'iss_issue':
            $id = (int)erp_input('id');
            $recv = sf_txt(erp_input('received_by'), 150);
            $pdo->beginTransaction();
            $r = erp_row($pdo, "SELECT * FROM sf_stock_issues WHERE id = ? FOR UPDATE", [$id]);
            if (!$r || $r['status'] !== 'approved') erp_invalid('Only approved stock outs can be issued.');
            $recv = $recv ?: $r['received_by'];
            if (!$recv) erp_invalid('Who received the goods?');
            $lines = erp_rows($pdo, "SELECT * FROM sf_stock_issue_lines WHERE issue_id = ?", [$id]);
            $prod = [];
            foreach ($lines as $l) {
                $item = erp_item($pdo, $l['item_type'], (int)$l['item_id']);
                sf_check_available($pdo, $l['item_type'], (int)$l['item_id'], (int)$r['warehouse_id'], (float)$l['quantity'], $l['batch_id'] ? (int)$l['batch_id'] : null, $item['name'] ?? 'Item');
                if ($l['item_type'] === 'product') $prod[] = ['product_id' => (int)$l['item_id'], 'qty' => (int)$l['quantity'], 'batch_id' => $l['batch_id']];
                else {
                    erp_raw_movement($pdo, (int)$l['item_id'], -(float)$l['quantity'], 'stock_out', 'stock_issue', $r['issue_number'], "Stock out {$r['issue_number']} ({$r['purpose']})", (int)$r['warehouse_id']);
                    if ($l['batch_id']) $pdo->prepare("INSERT INTO batch_allocations (batch_id, item_type, item_id, movement_id, quantity, allocation_type) VALUES (?, 'raw_material', ?, (SELECT MAX(id) FROM raw_material_movements WHERE raw_material_id = ?), ?, 'manual')")->execute([$l['batch_id'], $l['item_id'], $l['item_id'], $l['quantity']]);
                }
            }
            $ref = $r['issue_number'];
            if ($prod) {
                $so = erp_post_stock_out($pdo, ['date' => date('Y-m-d'), 'reference' => $r['issue_number'], 'warehouse_id' => $r['warehouse_id'], 'party' => $recv,
                                                'reason' => 'Stock out ' . $r['issue_number'] . ' (' . str_replace('_', ' ', $r['purpose']) . '): ' . $r['purpose_note'], 'movement_type' => 'stock_out', 'reference_type' => 'stock_issue'], $prod);
                $ref = $so['number'];
                foreach ($prod as $pl) if ($pl['batch_id']) $pdo->prepare("INSERT INTO batch_allocations (batch_id, item_type, item_id, movement_id, quantity, allocation_type) VALUES (?, 'product', ?, (SELECT MAX(id) FROM stock_movements WHERE product_id = ?), ?, 'manual')")->execute([$pl['batch_id'], $pl['product_id'], $pl['product_id'], $pl['qty']]);
            }
            $pdo->prepare("UPDATE sf_stock_issues SET status = 'issued', issued_by = ?, issued_at = NOW(), received_by = ?, stock_out_ref = ? WHERE id = ?")->execute([erp_user(), $recv, $ref, $id]);
            $pdo->commit();
            wh_sync($pdo);
            log_audit($pdo, 'issue', 'sf_stock_issues', $id, ['status' => 'approved'], ['status' => 'issued', 'stock_out' => $ref, 'received_by' => $recv]);
            erp_out(['status' => 'success', 'message' => "{$r['issue_number']} issued — stock reduced ({$ref})."]);
        case 'iss_cancel':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM sf_stock_issues WHERE id = ?", [$id]);
            if (!$r || !in_array($r['status'], ['requested', 'approved'], true)) erp_invalid('This stock out can no longer be cancelled.');
            $pdo->prepare("UPDATE sf_stock_issues SET status = 'cancelled', decision_remarks = ? WHERE id = ?")->execute([sf_txt(erp_input('reason'), 255), $id]);
            apr_close($pdo, 'stock_issue', (string)$id, 'cancelled', erp_input('reason'));
            log_audit($pdo, 'cancel', 'sf_stock_issues', $id, ['status' => $r['status']], ['status' => 'cancelled']);
            erp_out(['status' => 'success', 'message' => "{$r['issue_number']} cancelled."]);

        // ============================================================ DAMAGE
        case 'dmg_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'd.status = ?'; $p[] = $s; }
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT d.*, w.name AS warehouse_name, b.batch_number FROM sf_damage_reports d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN inventory_batches b ON b.id = d.batch_id" .
                                                         ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY d.id DESC LIMIT 500", $p));
            erp_out(['status' => 'success', 'rows' => $rows]);
        case 'dmg_get':
            $d = erp_row($pdo, "SELECT d.*, w.name AS warehouse_name, b.batch_number FROM sf_damage_reports d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN inventory_batches b ON b.id = d.batch_id WHERE d.id = ?", [(int)erp_input('id')]);
            if (!$d) erp_fail('Not found.');
            $d = erp_attach_item_names($pdo, [$d])[0];
            $d['history'] = erp_rows($pdo, "SELECT action, username, created_at, new_value FROM audit_logs WHERE module = 'sf_damage_reports' AND record_id = ? ORDER BY id", [(string)$d['id']]);
            // FIX (3 Oct 2026): which approval it waits for (Manager, then Admin) and whether this user can give it; photos attached
            $d['stage'] = $d['status'] !== 'verified' ? null : (erp_val($pdo, "SELECT module FROM approval_requests WHERE module IN ('stock_damage_mgr','stock_damage_admin','stock_damage') AND request_key = ? AND status IN ('submitted','under_review') ORDER BY id DESC LIMIT 1", [(string)$d['id']]) ?: 'stock_damage');
            $d['can_mgr'] = $d['stage'] === 'stock_damage_mgr' && erp_can($pdo, 'purchase.manager_approve');
            $d['can_admin'] = in_array($d['stage'], ['stock_damage_admin', 'stock_damage'], true) && ($d['stage'] === 'stock_damage' ? erp_can($pdo, 'stockflow.approve') : erp_can($pdo, 'purchase.backend_approve'));
            $d['photos'] = (int)erp_val($pdo, "SELECT COUNT(*) FROM erp_documents WHERE entity_type = 'stock_damage' AND entity_id = ?", [(int)$d['id']]);
            erp_out(['status' => 'success', 'record' => $d]);
        case 'dmg_create':
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $l = sf_item_lines($pdo, [['item_type' => erp_input('item_type', 'product'), 'item_id' => erp_input('item_id'), 'quantity' => erp_input('quantity'), 'batch_id' => erp_input('batch_id'), 'weight_kg' => erp_input('weight_kg')]], $wh)[0];
            $reason = sf_txt(erp_input('reason'), 255); $by = sf_txt(erp_input('identified_by'), 150);
            if (!$reason || !$by) erp_invalid('Reason and Identified by are required.');
            $src = in_array(erp_input('source'), ['stock', 'sales_return', 'qc', 'audit'], true) ? erp_input('source') : 'stock';
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'stock_damage', 'DMG');
            $pdo->prepare("INSERT INTO sf_damage_reports (damage_number, source, source_ref, item_type, item_id, batch_id, warehouse_id, quantity, weight_kg, reason, damage_date, identified_by, status, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'identified', ?)")
                ->execute([$num, $src, sf_txt(erp_input('source_ref'), 60), $l['item_type'], $l['item_id'], $l['batch_id'], $wh, $l['quantity'], $l['weight_kg'], $reason, erp_date(erp_input('damage_date')) ?: date('Y-m-d'), $by, erp_user()]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_damage_reports', $id, null, ['damage' => $num, 'item' => $l['name'], 'qty' => $l['quantity'], 'reason' => $reason]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$num} recorded — the stock is still AVAILABLE until it is verified and approved. Attach photos."]);
        case 'dmg_verify':
            $id = (int)erp_input('id');
            $d = erp_row($pdo, "SELECT * FROM sf_damage_reports WHERE id = ?", [$id]);
            if (!$d || $d['status'] !== 'identified') erp_invalid('Only identified damage can be verified.');
            $by = sf_txt(erp_input('verified_by'), 150) ?: ($_SESSION['admin_full_name'] ?? erp_user());
            if ($d['created_by'] === erp_user()) erp_invalid('The person who reported the damage cannot verify it.');
            // FIX (3 Oct 2026): a damage photo is required, then Manager → Admin approval (when dmg_approval_migration.sql is installed)
            if (!(int)erp_val($pdo, "SELECT COUNT(*) FROM erp_documents WHERE entity_type = 'stock_damage' AND entity_id = ?", [$id])) erp_invalid('Attach a damage photo first (Documents on this report), then verify.');
            $dmgChain = (bool)apr_policy($pdo, 'stock_damage_mgr') && (bool)apr_policy($pdo, 'stock_damage_admin');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE sf_damage_reports SET status = 'verified', verified_by = ?, verified_at = NOW(), verify_remarks = ? WHERE id = ?")->execute([$by, sf_txt(erp_input('remarks'), 255), $id]);
            if ($dmgChain) apr_open($pdo, 'stock_damage_mgr', (string)$id, $id, $d['damage_number'], "Damage {$d['damage_number']}: " . erp_q($d['quantity']) . " — {$d['reason']}", null, 'stock_flow_api.php', ['action' => 'dmg_mgr_ok', 'id' => $id], ['action' => 'dmg_reject', 'id' => $id]);
            else apr_open($pdo, 'stock_damage', (string)$id, $id, $d['damage_number'], "Damage {$d['damage_number']}: " . erp_q($d['quantity']) . " — {$d['reason']}", null, 'stock_flow_api.php', ['action' => 'dmg_approve', 'id' => $id], ['action' => 'dmg_reject', 'id' => $id]);
            $pdo->commit();
            log_audit($pdo, 'verify', 'sf_damage_reports', $id, ['status' => 'identified'], ['status' => 'verified', 'verified_by' => $by]);
            erp_out(['status' => 'success', 'message' => "{$d['damage_number']} verified — sent to the " . ($dmgChain ? 'Manager' : 'approver') . " for approval."]);
        case 'dmg_mgr_ok':   // FIX (3 Oct 2026): Manager approved → goes to the Admin (stock moves to DAMAGED only after the Admin)
            $id = (int)erp_input('id');
            $d = erp_row($pdo, "SELECT * FROM sf_damage_reports WHERE id = ?", [$id]);
            if (!$d || $d['status'] !== 'verified') erp_invalid('Only verified damage can be approved.');
            if (!erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'stock_damage_mgr' AND request_key = ? AND status IN ('submitted','under_review')", [(string)$id])) erp_invalid("{$d['damage_number']} is not waiting for the Manager.");
            $rem = sf_txt(erp_input('_approval_remarks') ?: erp_input('reason'), 255);
            $pdo->beginTransaction();
            apr_close($pdo, 'stock_damage_mgr', (string)$id, 'approved', $rem);
            $n = apr_open($pdo, 'stock_damage_admin', (string)$id, $id, $d['damage_number'], "Damage {$d['damage_number']}: " . erp_q($d['quantity']) . " — {$d['reason']} · Manager approved: " . erp_user(), null, 'stock_flow_api.php', ['action' => 'dmg_approve', 'id' => $id], ['action' => 'dmg_reject', 'id' => $id]);
            $pdo->commit();
            log_audit($pdo, 'approve', 'sf_damage_reports', $id, ['stage' => 'manager'], ['manager' => erp_user(), 'sent_to_admin' => $n]);
            erp_out(['status' => 'success', 'message' => "{$d['damage_number']} approved by the Manager — sent to the Admin ({$n})."]);
        case 'dmg_approve':
        case 'dmg_reject':
            $id = (int)erp_input('id');
            $rem = sf_txt(erp_input('_approval_remarks') ?: erp_input('reason'), 255);
            $pdo->beginTransaction();
            $d = erp_row($pdo, "SELECT * FROM sf_damage_reports WHERE id = ? FOR UPDATE", [$id]);
            if (!$d || $d['status'] !== 'verified') erp_invalid('Only verified damage can be approved or rejected.');
            // FIX (3 Oct 2026): Manager first, then the Admin
            $dmgStage = erp_val($pdo, "SELECT module FROM approval_requests WHERE module IN ('stock_damage_mgr','stock_damage_admin') AND request_key = ? AND status IN ('submitted','under_review') ORDER BY id DESC LIMIT 1", [(string)$id]);
            if ($action === 'dmg_approve' && $dmgStage === 'stock_damage_mgr') erp_invalid("{$d['damage_number']} is waiting for the Manager first.");
            if ($action === 'dmg_approve' && $dmgStage === 'stock_damage_admin' && !erp_can($pdo, 'purchase.backend_approve')) erp_invalid('The Admin gives the final approval.');
            if ($action === 'dmg_reject' && !erp_can($pdo, $dmgStage === 'stock_damage_admin' ? 'purchase.backend_approve' : ($dmgStage === 'stock_damage_mgr' ? 'purchase.manager_approve' : 'stockflow.approve'))) erp_invalid('You cannot reject at this step.');
            foreach (['stock_damage_mgr', 'stock_damage_admin'] as $dm) apr_close($pdo, $dm, (string)$id, $action === 'dmg_reject' ? 'rejected' : 'approved', $rem);
            if ($action === 'dmg_reject') {
                if (!$rem) erp_invalid('Give a reason.');
                $pdo->prepare("UPDATE sf_damage_reports SET status = 'rejected', approved_by = ?, approved_at = NOW(), decision_remarks = ? WHERE id = ?")->execute([erp_user(), $rem, $id]);
                apr_close($pdo, 'stock_damage', (string)$id, 'rejected', $rem);
                $pdo->commit();
                log_audit($pdo, 'reject', 'sf_damage_reports', $id, ['status' => 'verified'], ['status' => 'rejected', 'remarks' => $rem]);
                erp_out(['status' => 'success', 'message' => "{$d['damage_number']} rejected — stock unchanged."]);
            }
            $item = erp_item($pdo, $d['item_type'], (int)$d['item_id']);
            sf_check_available($pdo, $d['item_type'], (int)$d['item_id'], (int)$d['warehouse_id'], (float)$d['quantity'], $d['batch_id'] ? (int)$d['batch_id'] : null, $item['name'] ?? 'Item');
            sf_to_bucket($pdo, $d['item_type'], (int)$d['item_id'], (int)$d['warehouse_id'], (float)$d['quantity'], 'damaged', 'stock_damage', $d['damage_number'], "Damage {$d['damage_number']}: {$d['reason']}", $d['batch_id'] ? (int)$d['batch_id'] : null);
            $pdo->prepare("UPDATE sf_damage_reports SET status = 'approved', approved_by = ?, approved_at = NOW(), decision_remarks = ?, bucket_ref = ? WHERE id = ?")->execute([erp_user(), $rem, $d['damage_number'], $id]);
            apr_close($pdo, 'stock_damage', (string)$id, 'approved', $rem);
            $pdo->commit();
            wh_sync($pdo);
            log_audit($pdo, 'approve', 'sf_damage_reports', $id, ['status' => 'verified'], ['status' => 'approved', 'moved_to' => 'damaged', 'qty' => $d['quantity']]);
            erp_out(['status' => 'success', 'message' => "{$d['damage_number']} approved — " . erp_q($d['quantity']) . " moved from AVAILABLE to DAMAGED stock."]);
        case 'dmg_dispose':
            $id = (int)erp_input('id');
            $how = (string)erp_input('disposal');
            if (!in_array($how, ['disposed', 'destroyed', 'returned_to_supplier', 'restored'], true)) erp_invalid('Choose what happened to the goods.');
            $pdo->beginTransaction();
            $d = erp_row($pdo, "SELECT * FROM sf_damage_reports WHERE id = ? FOR UPDATE", [$id]);
            if (!$d || $d['status'] !== 'approved' || $d['disposal_status'] !== 'pending') erp_invalid('Only approved damage that is still pending can be closed.');
            $q = (float)$d['quantity'];
            if ($q > wh_qty($pdo, $d['item_type'], (int)$d['item_id'], (int)$d['warehouse_id'], 'damaged') + 0.0005) erp_invalid('Not that much in damaged stock at this location.');
            $num = next_document_number($pdo, 'wh_bucket', 'WHB');
            wh_apply($pdo, $d['item_type'], (int)$d['item_id'], (int)$d['warehouse_id'], 'damaged', -$q, 'bucket', null, $how === 'restored' ? 'bucket_restore' : 'bucket_out', $num, str_replace('_', ' ', $how) . ": {$d['damage_number']}");
            if ($how === 'restored') {
                if ($d['item_type'] === 'product') erp_product_movement($pdo, (int)$d['item_id'], (int)round($q), 'adjustment', 'bucket_restore', $num, "Restored from damaged: {$d['damage_number']}", (int)$d['warehouse_id']);
                else erp_raw_movement($pdo, (int)$d['item_id'], $q, 'adjustment', 'bucket_restore', $num, "Restored from damaged: {$d['damage_number']}", (int)$d['warehouse_id']);
            }
            $pdo->prepare("UPDATE sf_damage_reports SET disposal_status = ? WHERE id = ?")->execute([$how, $id]);
            $pdo->commit();
            wh_sync($pdo);
            log_audit($pdo, 'dispose', 'sf_damage_reports', $id, ['disposal_status' => 'pending'], ['disposal_status' => $how, 'ref' => $num, 'reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => "{$d['damage_number']}: " . str_replace('_', ' ', $how) . " ({$num})."]);

        // ============================================================ SALES RETURN — receiving + QC
        case 'rr_list':
            $rows = erp_rows($pdo, "SELECT r.*, w.name AS warehouse_name, sr.return_number, (SELECT COALESCE(SUM(quantity),0) FROM sf_return_receipt_lines l WHERE l.receipt_id = r.id) AS qty
                                    FROM sf_return_receipts r LEFT JOIN warehouses w ON w.id = r.warehouse_id LEFT JOIN sales_returns sr ON sr.id = r.sales_return_id ORDER BY r.id DESC LIMIT 500");
            erp_out(['status' => 'success', 'rows' => $rows]);
        case 'rr_get':
            $r = erp_row($pdo, "SELECT r.*, w.name AS warehouse_name, sr.return_number FROM sf_return_receipts r LEFT JOIN warehouses w ON w.id = r.warehouse_id LEFT JOIN sales_returns sr ON sr.id = r.sales_return_id WHERE r.id = ?", [(int)erp_input('id')]);
            if (!$r) erp_fail('Not found.');
            $r['lines'] = erp_rows($pdo, "SELECT l.*, p.product_name FROM sf_return_receipt_lines l LEFT JOIN product_details p ON p.id = l.product_id WHERE l.receipt_id = ? ORDER BY l.id", [$r['id']]);
            erp_out(['status' => 'success', 'record' => $r]);
        case 'rr_create':
            $st = (string)erp_input('source_type');
            if (!in_array($st, ['invoice', 'manual_sale', 'credit_sale', 'website_order'], true)) erp_invalid('Choose the sale type.');
            $ref = sf_txt(erp_input('source_ref'), 60); $reason = sf_txt(erp_input('reason'), 255); $by = sf_txt(erp_input('received_by'), 150);
            if (!$ref || !$reason || !$by) erp_invalid('Invoice / sale number, reason and Received by are required.');
            $wh = (int)erp_input('warehouse_id', 0) ? erp_warehouse_ok($pdo, (int)erp_input('warehouse_id')) : null;
            $lines = [];
            foreach (erp_json_input('lines') as $l) {
                $pid = (int)($l['product_id'] ?? 0); $q = erp_q(erp_num($l['quantity'] ?? 0, 'Quantity'));
                if (!$pid || $q <= 0) continue;
                if (!erp_item($pdo, 'product', $pid)) erp_invalid('A product no longer exists.');
                sf_whole('product', $q, 'Returned quantity');
                $uw = sf_unit_weight($pdo, 'product', $pid);
                $lines[] = [$pid, sf_txt($l['batch_number'] ?? '', 60), $q, sf_dec($l['weight_kg'] ?? null, 'Weight') ?? ($uw ? erp_q($q * $uw) : null)];
            }
            if (!$lines) erp_invalid('Enter the returned products and quantities.');
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'return_receipt', 'RRC');
            $pdo->prepare("INSERT INTO sf_return_receipts (receipt_number, source_type, source_ref, customer_name, warehouse_id, return_date, reason, received_by, received_at, status, remarks, created_by) VALUES (?,?,?,?,?,?,?,?, NOW(), 'received', ?, ?)")
                ->execute([$num, $st, $ref, sf_txt(erp_input('customer_name'), 150), $wh, erp_date(erp_input('return_date'), true), $reason, $by, sf_txt(erp_input('remarks'), 500), erp_user()]);
            $rid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sf_return_receipt_lines (receipt_id, product_id, batch_number, quantity, weight_kg) VALUES (?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute(array_merge([$rid], $l));
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_return_receipts', $rid, null, ['receipt' => $num, 'source' => "{$st} {$ref}", 'lines' => $lines]);
            erp_out(['status' => 'success', 'id' => $rid, 'message' => "{$num}: returned goods received (not in stock yet) — QC next."]);
        case 'rr_qc':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $r = erp_row($pdo, "SELECT * FROM sf_return_receipts WHERE id = ? FOR UPDATE", [$id]);
            if (!$r || $r['status'] !== 'received') erp_invalid('QC is done on received returns that are not posted yet.');
            $cur = []; foreach (erp_rows($pdo, "SELECT * FROM sf_return_receipt_lines WHERE receipt_id = ?", [$id]) as $l) $cur[(int)$l['id']] = $l;
            $restock = []; $dmgSum = 0; $resSum = 0;
            foreach (erp_json_input('lines') as $in) {
                $l = $cur[(int)($in['id'] ?? 0)] ?? null;
                if (!$l) continue;
                $a = erp_q(erp_num($in['resalable_qty'] ?? 0, 'Resalable')); $d = erp_q(erp_num($in['damaged_qty'] ?? 0, 'Damaged')); $x = erp_q(erp_num($in['rejected_qty'] ?? 0, 'Rejected'));
                foreach ([$a, $d, $x] as $v) sf_whole('product', $v, 'QC quantity');
                if (abs($a + $d + $x - (float)$l['quantity']) > 0.0005) erp_invalid('Resalable + damaged + rejected must equal the returned quantity on every line.');
                $res = $a > 0 && $d <= 0 && $x <= 0 ? 'resalable' : ($d > 0 && $a <= 0 && $x <= 0 ? 'damaged' : ($x > 0 && $a <= 0 && $d <= 0 ? 'rejected' : 'mixed'));
                $pdo->prepare("UPDATE sf_return_receipt_lines SET resalable_qty = ?, damaged_qty = ?, rejected_qty = ?, qc_result = ?, qc_note = ? WHERE id = ?")->execute([$a, $d, $x, $res, sf_txt($in['qc_note'] ?? '', 255), $l['id']]);
                unset($cur[(int)$l['id']]);
                if ($a + $d > 0) $restock[] = ['product_id' => $l['product_id'], 'restock_qty' => $a, 'damaged_qty' => $d];
                $resSum += $a; $dmgSum += $d;
            }
            if ($cur) erp_invalid('Enter the QC result for every line.');
            $by = sf_txt(erp_input('qc_by'), 150) ?: ($_SESSION['admin_full_name'] ?? erp_user());
            $pdo->prepare("UPDATE sf_return_receipts SET status = 'qc_done', qc_by = ?, qc_at = NOW(), qc_remarks = ? WHERE id = ?")->execute([$by, sf_txt(erp_input('qc_remarks'), 255), $id]);
            $pdo->commit();
            log_audit($pdo, 'qc', 'sf_return_receipts', $id, ['status' => 'received'], ['status' => 'qc_done', 'resalable' => $resSum, 'damaged' => $dmgSum, 'qc_by' => $by]);
            // the page posts this to the existing inventory_ops_api.php sret_post (refund / credit note / stock in), then calls rr_link
            erp_out(['status' => 'success', 'message' => "QC done: {$resSum} resalable (back to stock), {$dmgSum} damaged (to damaged stock)" . ($restock ? '. Post the sales return next.' : '. Nothing to take back — close it.'),
                     'sret_payload' => $restock ? ['source_type' => $r['source_type'], 'source_ref' => $r['source_ref'], 'return_date' => $r['return_date'], 'reason' => $r['reason'], 'items' => $restock,
                                                   'notes' => "Return receipt {$r['receipt_number']} (QC by {$by})"] : null]);
        case 'rr_link':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $r = erp_row($pdo, "SELECT * FROM sf_return_receipts WHERE id = ? FOR UPDATE", [$id]);
            if (!$r || $r['status'] !== 'qc_done') erp_invalid('Finish the QC first.');
            $want = []; foreach (erp_rows($pdo, "SELECT product_id, SUM(resalable_qty) a, SUM(damaged_qty) d FROM sf_return_receipt_lines WHERE receipt_id = ? GROUP BY product_id", [$id]) as $l) if ($l['a'] + $l['d'] > 0) $want[(int)$l['product_id']] = $l;
            $srId = (int)erp_input('sales_return_id', 0) ?: null;
            if ($want) {
                $sr = $srId ? erp_row($pdo, "SELECT * FROM sales_returns WHERE id = ? AND status = 'posted'", [$srId]) : null;
                if (!$sr || $sr['source_type'] !== $r['source_type']) erp_invalid('Post the sales return first.');
                if (erp_val($pdo, "SELECT id FROM sf_return_receipts WHERE sales_return_id = ? AND id <> ?", [$srId, $id])) erp_invalid('That sales return belongs to another receipt.');
                foreach (erp_rows($pdo, "SELECT product_id, restock_qty, damaged_qty FROM sales_return_items WHERE return_id = ?", [$srId]) as $i) {
                    $w = $want[(int)$i['product_id']] ?? null;
                    if (!$w || abs($w['a'] - $i['restock_qty']) > 0.0005 || abs($w['d'] - $i['damaged_qty']) > 0.0005) erp_invalid('The sales return quantities differ from the QC result.');
                }
            } else $srId = null;
            $pdo->prepare("UPDATE sf_return_receipts SET status = 'posted', sales_return_id = ? WHERE id = ?")->execute([$srId, $id]);
            $pdo->commit();
            log_audit($pdo, 'post', 'sf_return_receipts', $id, ['status' => 'qc_done'], ['status' => 'posted', 'sales_return_id' => $srId]);
            erp_out(['status' => 'success', 'message' => "{$r['receipt_number']} closed" . ($srId ? ' — linked to the posted sales return.' : ' (all rejected / handed back).')]);
        case 'rr_cancel':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM sf_return_receipts WHERE id = ?", [$id]);
            if (!$r || !in_array($r['status'], ['received', 'qc_done'], true)) erp_invalid('This receipt can no longer be cancelled.');
            $pdo->prepare("UPDATE sf_return_receipts SET status = 'cancelled', remarks = CONCAT(COALESCE(remarks,''), ' [Cancelled: ', ?, ']') WHERE id = ?")->execute([(string)erp_input('reason', ''), $id]);
            log_audit($pdo, 'cancel', 'sf_return_receipts', $id, ['status' => $r['status']], ['status' => 'cancelled']);
            erp_out(['status' => 'success', 'message' => "{$r['receipt_number']} cancelled."]);

        // ============================================================ OPENING STOCK
        case 'ops_list':
            erp_out(['status' => 'success', 'rows' => erp_attach_item_names($pdo, erp_rows($pdo, "SELECT o.*, w.name AS warehouse_name FROM sf_opening_stock o JOIN warehouses w ON w.id = o.warehouse_id ORDER BY o.id DESC LIMIT 500"))]);
        case 'ops_create':
            $type = erp_item_type(erp_input('item_type', 'product')); $iid = (int)erp_input('item_id');
            $item = erp_item($pdo, $type, $iid);
            if (!$item) erp_invalid('Choose the item.');
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $q = erp_q(erp_num(erp_input('quantity'), 'Opening quantity', false));
            sf_whole($type, $q, $item['name']);
            $pc = sf_dec(erp_input('physical_count'), 'Physical count', true);
            if (abs($pc - $q) > 0.0005) erp_invalid('The opening quantity must equal the physical count.');
            $by = sf_txt(erp_input('entered_by'), 150) ?: ($_SESSION['admin_full_name'] ?? erp_user());
            $uw = sf_unit_weight($pdo, $type, $iid);
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'opening_stock', 'OPS');
            $pdo->prepare("INSERT INTO sf_opening_stock (opening_number, item_type, item_id, warehouse_id, batch_number, expiry_date, quantity, weight_kg, physical_count, unit, unit_cost, opening_date, entered_by, status, remarks, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'entered', ?, ?)")
                ->execute([$num, $type, $iid, $wh, sf_txt(erp_input('batch_number'), 60), erp_date(erp_input('expiry_date')), $q, sf_dec(erp_input('weight_kg'), 'Weight') ?? ($uw ? erp_q($q * $uw) : null), $pc,
                           $type === 'product' ? 'pcs' : $item['unit'], sf_dec(erp_input('unit_cost'), 'Unit cost'), erp_date(erp_input('opening_date'), true), $by, sf_txt(erp_input('remarks'), 500), erp_user()]);
            $oid = (int)$pdo->lastInsertId();
            apr_open($pdo, 'opening_stock', (string)$oid, $oid, $num, "Opening stock {$num}: {$item['name']} × " . erp_q($q), null, 'stock_flow_api.php', ['action' => 'ops_verify', 'id' => $oid], ['action' => 'ops_reject', 'id' => $oid]);
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_opening_stock', $oid, null, ['opening' => $num, 'item' => $item['name'], 'qty' => $q, 'warehouse' => $wh]);
            erp_out(['status' => 'success', 'id' => $oid, 'message' => "{$num} entered — stock is added when it is verified."]);
        case 'ops_verify':
        case 'ops_reject':
            $id = (int)erp_input('id');
            $rem = sf_txt(erp_input('_approval_remarks') ?: erp_input('reason'), 255);
            $pdo->beginTransaction();
            $o = erp_row($pdo, "SELECT * FROM sf_opening_stock WHERE id = ? FOR UPDATE", [$id]);
            if (!$o || $o['status'] !== 'entered') erp_invalid('Only entered opening stock can be verified or rejected.');
            if ($o['created_by'] === erp_user() && (int)($_SESSION['admin_role_id'] ?? 0) !== 1) erp_invalid('The person who entered the opening stock cannot verify it.');
            if ($action === 'ops_reject') {
                if (!$rem) erp_invalid('Give a reason.');
                $pdo->prepare("UPDATE sf_opening_stock SET status = 'rejected', verified_by = ?, verified_at = NOW(), decision_remarks = ? WHERE id = ?")->execute([erp_user(), $rem, $id]);
                apr_close($pdo, 'opening_stock', (string)$id, 'rejected', $rem);
                $pdo->commit();
                log_audit($pdo, 'reject', 'sf_opening_stock', $id, ['status' => 'entered'], ['status' => 'rejected']);
                erp_out(['status' => 'success', 'message' => "{$o['opening_number']} rejected."]);
            }
            $q = (float)$o['quantity'];
            if ($o['item_type'] === 'product') {
                $si = erp_post_stock_in($pdo, ['date' => $o['opening_date'], 'reference' => $o['opening_number'], 'warehouse_id' => $o['warehouse_id'], 'received_by' => $o['entered_by'],
                                               'remarks' => "Opening stock {$o['opening_number']}", 'reference_type' => 'opening_stock', 'movement_type' => 'stock_in', 'reason' => "Opening stock {$o['opening_number']}"],
                                         [['product_id' => (int)$o['item_id'], 'qty' => (int)$q, 'rate' => $o['unit_cost'], 'batch' => $o['batch_number'], 'expiry' => $o['expiry_date']]]);
                $ref = $si['number'];
            } else {
                erp_raw_movement($pdo, (int)$o['item_id'], $q, 'stock_in', 'opening_stock', $o['opening_number'], "Opening stock {$o['opening_number']}", (int)$o['warehouse_id']);
                if ($o['batch_number'] || $o['expiry_date'])
                    $pdo->prepare("INSERT INTO inventory_batches (item_type, item_id, warehouse_id, batch_number, expiry_date, source_type, source_id, received_date, qty_received, unit_cost) VALUES ('raw_material', ?,?,?,?, 'opening', ?,?,?,?)")
                        ->execute([$o['item_id'], $o['warehouse_id'], $o['batch_number'], $o['expiry_date'], $id, $o['opening_date'], $q, $o['unit_cost'] ?: 0]);
                $ref = $o['opening_number'];
            }
            if ($o['unit_cost'] !== null && (float)$o['unit_cost'] > 0) erp_cost_entry($pdo, $o['item_type'], (int)$o['item_id'], 'receipt', $q, $q * (float)$o['unit_cost'], 'opening_stock', $o['opening_number'], $id, "Opening stock {$o['opening_number']}");
            $pdo->prepare("UPDATE sf_opening_stock SET status = 'verified', verified_by = ?, verified_at = NOW(), posted_ref = ?, decision_remarks = ? WHERE id = ?")->execute([erp_user(), $ref, $rem, $id]);
            apr_close($pdo, 'opening_stock', (string)$id, 'approved', $rem);
            $pdo->commit();
            wh_sync($pdo); fefo_sync($pdo);
            log_audit($pdo, 'verify', 'sf_opening_stock', $id, ['status' => 'entered'], ['status' => 'verified', 'posted' => $ref]);
            erp_out(['status' => 'success', 'message' => "{$o['opening_number']} verified — " . erp_q($q) . " added to stock ({$ref})."]);

        // ============================================================ PURCHASE RETURN DISPATCH
        case 'rd_rejected_stock':
            erp_out(['status' => 'success', 'rows' => erp_attach_item_names($pdo, erp_rows($pdo, "SELECT ws.item_type, ws.item_id, ws.warehouse_id, w.name AS warehouse_name, ws.quantity FROM warehouse_stock ws JOIN warehouses w ON w.id = ws.warehouse_id WHERE ws.bucket = 'rejected' AND ws.quantity > 0.0005")),
                     'returns' => erp_rows($pdo, "SELECT r.id, r.return_number, r.return_date, r.total_value, s.supplier_name FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id
                                                  WHERE r.status = 'posted' AND NOT EXISTS (SELECT 1 FROM sf_return_dispatches d WHERE d.purchase_return_id = r.id AND d.status NOT IN ('rejected','cancelled')) ORDER BY r.id DESC LIMIT 200"),
                     'grns' => erp_rows($pdo, "SELECT g.id, g.grn_number, s.supplier_name, g.supplier_id FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id WHERE g.status = 'posted' AND EXISTS (SELECT 1 FROM goods_receipt_items i WHERE i.grn_id = g.id AND i.rejected_qty > 0) ORDER BY g.id DESC LIMIT 200")]);
        case 'rd_list':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT d.*, w.name AS warehouse_name, s.supplier_name, r.return_number, g.grn_number FROM sf_return_dispatches d JOIN warehouses w ON w.id = d.warehouse_id
                                                                     LEFT JOIN suppliers s ON s.id = d.supplier_id LEFT JOIN purchase_returns r ON r.id = d.purchase_return_id LEFT JOIN goods_receipts g ON g.id = d.grn_id ORDER BY d.id DESC LIMIT 500")]);
        case 'rd_get':
            $d = erp_row($pdo, "SELECT d.*, w.name AS warehouse_name, s.supplier_name, r.return_number, g.grn_number FROM sf_return_dispatches d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN suppliers s ON s.id = d.supplier_id
                                LEFT JOIN purchase_returns r ON r.id = d.purchase_return_id LEFT JOIN goods_receipts g ON g.id = d.grn_id WHERE d.id = ?", [(int)erp_input('id')]);
            if (!$d) erp_fail('Not found.');
            $d['lines'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM sf_return_dispatch_lines WHERE dispatch_id = ?", [$d['id']]));
            erp_out(['status' => 'success', 'record' => $d]);
        case 'rd_create':
            $src = (string)erp_input('source');
            $type = (string)erp_input('transport_type');
            if (!in_array($type, ['internal', 'courier'], true)) erp_invalid('Choose Internal vehicle or Courier.');
            $reason = sf_txt(erp_input('reason'), 255);
            if (!$reason) erp_invalid('Give the return reason.');
            $lines = [];
            if ($src === 'purchase_return') {
                $r = erp_row($pdo, "SELECT * FROM purchase_returns WHERE id = ? AND status = 'posted'", [(int)erp_input('purchase_return_id')]);
                if (!$r) erp_invalid('Choose a posted purchase return (it already reduced the stock).');
                if (erp_val($pdo, "SELECT id FROM sf_return_dispatches WHERE purchase_return_id = ? AND status NOT IN ('rejected','cancelled')", [$r['id']])) erp_invalid('This purchase return already has a dispatch.');
                $hdr = [$r['id'], $r['grn_id'], $r['supplier_id'], (int)$r['warehouse_id']];
                foreach (erp_rows($pdo, "SELECT item_type, item_id, quantity FROM purchase_return_items WHERE return_id = ?", [$r['id']]) as $i) {
                    $uw = sf_unit_weight($pdo, $i['item_type'], (int)$i['item_id']);
                    $lines[] = ['item_type' => $i['item_type'], 'item_id' => (int)$i['item_id'], 'quantity' => erp_q($i['quantity']), 'weight_kg' => $uw ? erp_q($i['quantity'] * $uw) : null];
                }
            } elseif ($src === 'qc_rejected') {
                $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ? AND status = 'posted'", [(int)erp_input('grn_id')]);
                if (!$g) erp_invalid('Choose the posted goods receipt the rejected goods came from.');
                $wh = (int)$g['warehouse_id'];
                $hdr = [null, $g['id'], $g['supplier_id'], $wh];
                foreach (erp_json_input('lines') as $l) {
                    $t = erp_item_type($l['item_type'] ?? 'product'); $iid = (int)($l['item_id'] ?? 0); $q = erp_q(erp_num($l['quantity'] ?? 0, 'Quantity'));
                    if (!$iid || $q <= 0) continue;
                    $rej = (float)erp_val($pdo, "SELECT COALESCE(SUM(rejected_qty),0) FROM goods_receipt_items WHERE grn_id = ? AND item_type = ? AND item_id = ?", [$g['id'], $t, $iid]);
                    if ($q > $rej + 0.0005) erp_invalid('More than was rejected on this goods receipt.');
                    if ($q > wh_qty($pdo, $t, $iid, $wh, 'rejected') + 0.0005) erp_invalid('Not that much in rejected stock at this location.');
                    $uw = sf_unit_weight($pdo, $t, $iid);
                    $lines[] = ['item_type' => $t, 'item_id' => $iid, 'quantity' => $q, 'weight_kg' => sf_dec($l['weight_kg'] ?? null, 'Weight') ?? ($uw ? erp_q($q * $uw) : null)];
                }
            } else erp_invalid('Choose what is being returned.');
            if (!$lines) erp_invalid('Nothing to return.');
            $cs = (int)erp_input('courier_service_id', 0) ?: null;
            $cname = $cs ? erp_val($pdo, "SELECT name FROM courier_services WHERE id = ?", [$cs]) : null;
            if ($type === 'courier' && (!$cname || !trim((string)erp_input('tracking_number', '')))) erp_invalid('Courier needs the courier company and the tracking number.');
            if ($type === 'internal' && (!trim((string)erp_input('vehicle_number', '')) || !trim((string)erp_input('driver_name', '')))) erp_invalid('Internal transport needs the vehicle number and driver name.');
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'return_dispatch', 'PRD');
            $pdo->prepare("INSERT INTO sf_return_dispatches (dispatch_number, source, purchase_return_id, grn_id, supplier_id, warehouse_id, reason, transport_type, vehicle_number, driver_name, driver_phone,
                             courier_service_id, courier_name, tracking_number, courier_phone, dispatch_date, status, remarks, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'requested', ?, ?)")
                ->execute(array_merge([$num, $src], $hdr, [$reason, $type, strtoupper((string)sf_txt(erp_input('vehicle_number'), 30)) ?: null, sf_txt(erp_input('driver_name'), 150), sf_txt(erp_input('driver_phone'), 20),
                           $cs, $cname && stripos($cname, 'other') === 0 ? sf_txt(erp_input('courier_name'), 150) : $cname, strtoupper((string)sf_txt(erp_input('tracking_number'), 80)) ?: null, sf_txt(erp_input('courier_phone'), 20),
                           erp_date(erp_input('dispatch_date')), sf_txt(erp_input('remarks'), 500), erp_user()]));
            $did = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sf_return_dispatch_lines (dispatch_id, item_type, item_id, quantity, weight_kg) VALUES (?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute([$did, $l['item_type'], $l['item_id'], $l['quantity'], $l['weight_kg']]);
            apr_open($pdo, 'return_dispatch', (string)$did, $did, $num, "Return to supplier {$num}: {$reason}", null, 'stock_flow_api.php', ['action' => 'rd_approve', 'id' => $did], ['action' => 'rd_reject', 'id' => $did]);
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_return_dispatches', $did, null, ['dispatch' => $num, 'source' => $src, 'lines' => $lines]);
            erp_out(['status' => 'success', 'id' => $did, 'message' => "{$num} requested — waiting for approval."]);
        case 'rd_approve':
        case 'rd_reject':
            $id = (int)erp_input('id');
            $d = erp_row($pdo, "SELECT * FROM sf_return_dispatches WHERE id = ?", [$id]);
            if (!$d || $d['status'] !== 'requested') erp_invalid('Only requested dispatches can be approved or rejected.');
            $to = $action === 'rd_approve' ? 'approved' : 'rejected';
            $rem = sf_txt(erp_input('_approval_remarks') ?: erp_input('reason'), 255);
            if ($to === 'rejected' && !$rem) erp_invalid('Give a reason.');
            $pdo->prepare("UPDATE sf_return_dispatches SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$to, erp_user(), $id]);
            apr_close($pdo, 'return_dispatch', (string)$id, $to, $rem);
            log_audit($pdo, $to === 'approved' ? 'approve' : 'reject', 'sf_return_dispatches', $id, ['status' => 'requested'], ['status' => $to, 'remarks' => $rem]);
            erp_out(['status' => 'success', 'message' => "{$d['dispatch_number']} {$to}."]);
        case 'rd_dispatch':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $d = erp_row($pdo, "SELECT * FROM sf_return_dispatches WHERE id = ? FOR UPDATE", [$id]);
            if (!$d || $d['status'] !== 'approved') erp_invalid('Only approved dispatches can be sent.');
            $date = erp_date(erp_input('dispatch_date')) ?: ($d['dispatch_date'] ?: date('Y-m-d'));
            $ref = null;
            if ($d['source'] === 'qc_rejected') {   // rejected stock leaves the location now
                $ref = next_document_number($pdo, 'wh_bucket', 'WHB');
                foreach (erp_rows($pdo, "SELECT * FROM sf_return_dispatch_lines WHERE dispatch_id = ?", [$id]) as $l) {
                    if ((float)$l['quantity'] > wh_qty($pdo, $l['item_type'], (int)$l['item_id'], (int)$d['warehouse_id'], 'rejected') + 0.0005) erp_invalid('Rejected stock is no longer there.');
                    wh_apply($pdo, $l['item_type'], (int)$l['item_id'], (int)$d['warehouse_id'], 'rejected', -(float)$l['quantity'], 'bucket', null, 'bucket_out', $ref, "returned to supplier: {$d['dispatch_number']}");
                }
            }
            $pdo->prepare("UPDATE sf_return_dispatches SET status = 'dispatched', dispatch_date = ?, dispatched_by = ?, dispatched_at = NOW(), bucket_ref = ?,
                             tracking_number = COALESCE(?, tracking_number), vehicle_number = COALESCE(?, vehicle_number) WHERE id = ?")
                ->execute([$date, erp_user(), $ref, strtoupper((string)sf_txt(erp_input('tracking_number'), 80)) ?: null, strtoupper((string)sf_txt(erp_input('vehicle_number'), 30)) ?: null, $id]);
            $pdo->commit();
            log_audit($pdo, 'dispatch', 'sf_return_dispatches', $id, ['status' => 'approved'], ['status' => 'dispatched', 'date' => $date, 'bucket_ref' => $ref]);
            erp_out(['status' => 'success', 'message' => "{$d['dispatch_number']} dispatched" . ($ref ? " — rejected stock removed ({$ref})." : '.')]);
        case 'rd_deliver':
            $id = (int)erp_input('id');
            $d = erp_row($pdo, "SELECT * FROM sf_return_dispatches WHERE id = ?", [$id]);
            if (!$d || $d['status'] !== 'dispatched') erp_invalid('Only dispatched returns can be marked delivered.');
            $date = erp_date(erp_input('delivered_date'), true);
            $pdo->prepare("UPDATE sf_return_dispatches SET status = 'delivered', delivered_date = ? WHERE id = ?")->execute([$date, $id]);
            log_audit($pdo, 'delivered', 'sf_return_dispatches', $id, ['status' => 'dispatched'], ['status' => 'delivered', 'date' => $date]);
            erp_out(['status' => 'success', 'message' => "{$d['dispatch_number']} delivered to the supplier."]);

        // ============================================================ DAILY STOCK / STOCK OUT REGISTER / LOCATION STOCK
        case 'daily':
            $date = erp_date(erp_input('date')) ?: date('Y-m-d');
            $to = erp_date(erp_input('date_to')) ?: $date;
            if ($to < $date) erp_invalid('The end date is before the start date.');
            $wh = (int)erp_input('warehouse_id', 0) ?: null;
            $start = $date . ' 00:00:00'; $end = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
            $whSql = $wh ? ' AND warehouse_id = ' . (int)$wh : '';
            $cols = ['opening_entry', 'purchase_in', 'sales_return', 'transfer_in', 'stock_in', 'sales', 'stock_out', 'purchase_return', 'damage', 'transfer_out', 'adjustment'];
            $rows = [];
            $key = fn($t, $i, $w) => "{$t}:{$i}:{$w}";
            foreach (erp_rows($pdo, "SELECT item_type, item_id, warehouse_id, SUM(quantity) q FROM warehouse_stock_moves WHERE bucket = 'available' AND created_at < ? {$whSql} GROUP BY item_type, item_id, warehouse_id", [$start]) as $r)
                $rows[$key($r['item_type'], $r['item_id'], $r['warehouse_id'])] = ['item_type' => $r['item_type'], 'item_id' => (int)$r['item_id'], 'warehouse_id' => (int)$r['warehouse_id'], 'opening' => (float)$r['q']];
            foreach (erp_rows($pdo, "SELECT item_type, item_id, warehouse_id, source, reason, reference_type, quantity FROM warehouse_stock_moves WHERE bucket = 'available' AND created_at >= ? AND created_at < ? {$whSql}", [$start, $end]) as $m) {
                $k = $key($m['item_type'], $m['item_id'], $m['warehouse_id']);
                if (!isset($rows[$k])) $rows[$k] = ['item_type' => $m['item_type'], 'item_id' => (int)$m['item_id'], 'warehouse_id' => (int)$m['warehouse_id'], 'opening' => 0.0];
                $c = $m['source'] === 'opening' ? 'opening_entry' : sf_bucket_of($m);
                $rows[$k][$c] = ($rows[$k][$c] ?? 0) + (float)$m['quantity'];
            }
            foreach (erp_rows($pdo, "SELECT item_type, item_id, warehouse_id, SUM(quantity) q FROM warehouse_stock_moves WHERE bucket = 'damaged' AND created_at < ? {$whSql} GROUP BY item_type, item_id, warehouse_id", [$end]) as $r) {
                $k = $key($r['item_type'], $r['item_id'], $r['warehouse_id']);
                if (isset($rows[$k])) $rows[$k]['damaged_closing'] = (float)$r['q'];
            }
            $whn = []; foreach (erp_rows($pdo, "SELECT id, name FROM warehouses") as $w) $whn[(int)$w['id']] = $w['name'];
            $out = []; $tot = array_fill_keys(array_merge(['opening', 'closing'], $cols), 0.0);
            foreach ($rows as $r) {
                foreach ($cols as $c) $r[$c] = erp_q($r[$c] ?? 0);
                $r['opening'] = erp_q($r['opening']);
                $r['in_total'] = erp_q($r['opening_entry'] + $r['purchase_in'] + $r['sales_return'] + $r['transfer_in'] + $r['stock_in']);
                $r['out_total'] = erp_q($r['sales'] + $r['stock_out'] + $r['purchase_return'] + $r['damage'] + $r['transfer_out']);
                $r['closing'] = erp_q($r['opening'] + $r['in_total'] + $r['out_total'] + $r['adjustment']);
                if (abs($r['opening']) < 0.0005 && abs($r['closing']) < 0.0005 && abs($r['in_total']) < 0.0005 && abs($r['out_total']) < 0.0005 && abs($r['adjustment']) < 0.0005) continue;
                $uw = sf_unit_weight($pdo, $r['item_type'], $r['item_id']);
                $r['unit_weight_kg'] = $uw;
                foreach (['opening', 'in_total', 'out_total', 'adjustment', 'closing', 'damage', 'sales_return'] as $c) $r['w_' . $c] = $uw !== null ? erp_q($r[$c] * $uw) : null;
                $r['damaged_closing'] = erp_q($r['damaged_closing'] ?? 0);
                $r['warehouse_name'] = $whn[$r['warehouse_id']] ?? ('#' . $r['warehouse_id']);
                foreach (array_keys($tot) as $c) $tot[$c] += $r[$c];
                $out[] = $r;
            }
            $out = erp_attach_item_names($pdo, $out);
            usort($out, fn($a, $b) => [$a['warehouse_name'], $a['item_name']] <=> [$b['warehouse_name'], $b['item_name']]);
            foreach ($tot as &$t) $t = erp_q($t); unset($t);
            erp_out(['status' => 'success', 'date' => $date, 'date_to' => $to, 'rows' => $out, 'totals' => $tot]);

        case 'out_register':
            $from = erp_date(erp_input('date_from')) ?: date('Y-m-01'); $to = erp_date(erp_input('date_to')) ?: date('Y-m-d');
            $wh = (int)erp_input('warehouse_id', 0);
            $rows = erp_rows($pdo, "SELECT m.created_at, m.warehouse_id, w.name AS warehouse_name, 'product' AS item_type, m.product_id AS item_id, -m.quantity AS quantity, m.movement_type, m.reference_type, m.reference_number, m.reason, m.created_by
                                    FROM stock_movements m LEFT JOIN warehouses w ON w.id = m.warehouse_id WHERE m.new_stock < m.previous_stock AND DATE(m.created_at) BETWEEN ? AND ?" . ($wh ? ' AND m.warehouse_id = ' . $wh : '') . "
                                    UNION ALL SELECT m.created_at, m.warehouse_id, w.name, 'raw_material', m.raw_material_id, -m.quantity, m.movement_type, m.reference_type, m.reference_number, m.reason, m.created_by
                                    FROM raw_material_movements m LEFT JOIN warehouses w ON w.id = m.warehouse_id WHERE m.new_stock < m.previous_stock AND DATE(m.created_at) BETWEEN ? AND ?" . ($wh ? ' AND m.warehouse_id = ' . $wh : '') . "
                                    ORDER BY created_at DESC LIMIT 2000", [$from, $to, $from, $to]);
            $iss = []; foreach (erp_rows($pdo, "SELECT issue_number, purpose, requested_by, approved_by, issued_by, received_by, stock_out_ref FROM sf_stock_issues WHERE status = 'issued'") as $i) { $iss[$i['issue_number']] = $i; if ($i['stock_out_ref']) $iss[$i['stock_out_ref']] = $i; }
            foreach ($rows as &$r) {
                $i = $iss[$r['reference_number']] ?? null;
                $r['category'] = $i ? $i['purpose'] : (sf_bucket_of(['source' => 'ledger', 'reason' => $r['movement_type'], 'reference_type' => $r['reference_type'], 'quantity' => -$r['quantity']]));
                $r['requested_by'] = $i['requested_by'] ?? null; $r['approved_by'] = $i['approved_by'] ?? null; $r['issued_by'] = $i['issued_by'] ?? $r['created_by']; $r['received_by'] = $i['received_by'] ?? null;
                $uw = sf_unit_weight($pdo, $r['item_type'], (int)$r['item_id']);
                $r['weight_kg'] = $uw !== null ? erp_q($r['quantity'] * $uw) : null;
            }
            unset($r);
            erp_out(['status' => 'success', 'rows' => erp_attach_item_names($pdo, $rows)]);

        case 'location_stock':
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT ws.item_type, ws.item_id, ws.warehouse_id, w.name AS warehouse_name, ws.bucket, ws.quantity FROM warehouse_stock ws JOIN warehouses w ON w.id = ws.warehouse_id WHERE ABS(ws.quantity) >= 0.0005 ORDER BY w.id"));
            $g = [];
            foreach ($rows as $r) {
                $k = $r['item_type'] . ':' . $r['item_id'];
                if (!isset($g[$k])) { $uw = sf_unit_weight($pdo, $r['item_type'], (int)$r['item_id']); $g[$k] = ['item_type' => $r['item_type'], 'item_id' => (int)$r['item_id'], 'item_name' => $r['item_name'], 'unit_weight_kg' => $uw, 'total_available' => 0, 'by_location' => []]; }
                $g[$k]['by_location'][$r['warehouse_name']][$r['bucket']] = erp_q($r['quantity']);
                if ($r['bucket'] === 'available') $g[$k]['total_available'] = erp_q($g[$k]['total_available'] + $r['quantity']);
            }
            erp_out(['status' => 'success', 'rows' => array_values($g), 'warehouses' => erp_rows($pdo, "SELECT id, name FROM warehouses ORDER BY id")]);

        // ============================================================ MONTHLY AUDIT
        case 'aud_list':
            $rows = erp_rows($pdo, "SELECT a.*, w.name AS warehouse_name, c.count_number, c.status AS count_status,
                                           (SELECT COUNT(*) FROM sf_audit_lines l WHERE l.audit_id = a.id) AS lines_total,
                                           (SELECT COUNT(*) FROM sf_audit_lines l WHERE l.audit_id = a.id AND ABS(COALESCE(l.qty_diff,0)) >= 0.0005) AS lines_diff,
                                           (SELECT COUNT(*) FROM erp_documents d WHERE d.entity_type = 'stock_audit' AND d.entity_id = a.id) AS proofs
                                    FROM sf_audits a JOIN warehouses w ON w.id = a.warehouse_id LEFT JOIN stock_counts c ON c.id = a.stock_count_id ORDER BY a.id DESC LIMIT 300");
            foreach ($rows as &$a) sf_audit_status_sync($pdo, $a);
            unset($a);
            erp_out(['status' => 'success', 'rows' => $rows]);
        case 'aud_get':
            $a = erp_row($pdo, "SELECT a.*, w.name AS warehouse_name, c.count_number, c.status AS count_status FROM sf_audits a JOIN warehouses w ON w.id = a.warehouse_id LEFT JOIN stock_counts c ON c.id = a.stock_count_id WHERE a.id = ?", [(int)erp_input('id')]);
            if (!$a) erp_fail('Audit not found.');
            sf_audit_status_sync($pdo, $a);
            $a['lines'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT l.*, ci.counted_qty, ci.reason AS count_reason, ci.variance_value FROM sf_audit_lines l LEFT JOIN stock_count_items ci ON ci.id = l.count_item_id WHERE l.audit_id = ? ORDER BY l.id", [$a['id']]));
            $a['proofs'] = erp_rows($pdo, "SELECT d.id, d.original_name, d.uploaded_by, d.created_at, COALESCE(m.category,'') AS category FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE d.entity_type = 'stock_audit' AND d.entity_id = ? AND COALESCE(m.status,'active') = 'active' ORDER BY d.id", [$a['id']]);
            $a['history'] = erp_rows($pdo, "SELECT action, username, created_at FROM audit_logs WHERE module = 'sf_audits' AND record_id = ? ORDER BY id", [(string)$a['id']]);
            $a['signoff_installed'] = sf_signoff_installed($pdo);   // FIX (2 Oct 2026)
            $a['signoff'] = $a['signoff_installed'] ? erp_row($pdo, "SELECT s.*, sd.original_name AS signature_name, rd.original_name AS report_name FROM sf_audit_signoffs s
                                                                     LEFT JOIN erp_documents sd ON sd.id = s.signature_doc_id LEFT JOIN erp_documents rd ON rd.id = s.report_doc_id WHERE s.audit_id = ?", [$a['id']]) : null;
            erp_out(['status' => 'success', 'record' => $a]);
        case 'aud_plan':
            // the page first creates the stock count (existing warehouse_api.php cnt_create — system quantities frozen) and passes its id
            $cnt = erp_row($pdo, "SELECT * FROM stock_counts WHERE id = ?", [(int)erp_input('stock_count_id')]);
            if (!$cnt || $cnt['status'] !== 'draft') erp_invalid('Create the stock count first (draft).');
            if (erp_val($pdo, "SELECT id FROM sf_audits WHERE stock_count_id = ?", [$cnt['id']])) erp_invalid('This stock count already has an audit.');
            $month = (string)erp_input('audit_month', substr($cnt['count_date'], 0, 7));
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) erp_invalid('Choose the audit month.');
            $name = sf_txt(erp_input('auditor_name'), 150); $emp = sf_txt(erp_input('auditor_employee_id'), 40);
            if (!$name || !$emp) erp_invalid('Auditor name and employee ID are required.');
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'stock_audit', 'AUD');
            $pdo->prepare("INSERT INTO sf_audits (audit_number, stock_count_id, audit_month, warehouse_id, auditor_name, auditor_employee_id, audit_date, status, remarks, created_by) VALUES (?,?,?,?,?,?,?, 'planned', ?, ?)")
                ->execute([$num, $cnt['id'], $month, $cnt['warehouse_id'], $name, $emp, erp_date(erp_input('audit_date')) ?: $cnt['count_date'], sf_txt(erp_input('remarks'), 500), erp_user()]);
            $aid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sf_audit_lines (audit_id, count_item_id, item_type, item_id, system_qty, unit_weight_kg) VALUES (?,?,?,?,?,?)");
            foreach (erp_rows($pdo, "SELECT * FROM stock_count_items WHERE count_id = ?", [$cnt['id']]) as $ci) $ins->execute([$aid, $ci['id'], $ci['item_type'], $ci['item_id'], $ci['system_qty'], sf_unit_weight($pdo, $ci['item_type'], (int)$ci['item_id'])]);
            $pdo->commit();
            log_audit($pdo, 'plan', 'sf_audits', $aid, null, ['audit' => $num, 'count' => $cnt['count_number'], 'month' => $month, 'auditor' => $name]);
            erp_out(['status' => 'success', 'id' => $aid, 'message' => "{$num} PLANNED for {$month} ({$cnt['count_number']})."]);
        case 'aud_start':
            $id = (int)erp_input('id');
            $a = erp_row($pdo, "SELECT * FROM sf_audits WHERE id = ?", [$id]);
            if (!$a || $a['status'] !== 'planned') erp_invalid('Only planned audits can be started.');
            $t = sf_time(erp_input('start_time')) ?: date('H:i:s');
            $pdo->prepare("UPDATE sf_audits SET status = 'in_progress', start_time = ? WHERE id = ?")->execute([$t, $id]);
            log_audit($pdo, 'start', 'sf_audits', $id, ['status' => 'planned'], ['status' => 'in_progress', 'start_time' => $t]);
            erp_out(['status' => 'success', 'message' => "{$a['audit_number']} IN PROGRESS (started {$t})."]);
        case 'aud_save':
            // counted quantities are saved first through the existing warehouse_api.php cnt_save (same rules); this saves weights, batch, damage, checks
            $id = (int)erp_input('id');
            $complete = erp_input('complete') === '1';
            $pdo->beginTransaction();
            $a = erp_row($pdo, "SELECT * FROM sf_audits WHERE id = ? FOR UPDATE", [$id]);
            if (!$a || $a['status'] !== 'in_progress') erp_invalid('Start the audit first (only audits in progress can be edited).');
            $cnt = erp_row($pdo, "SELECT status FROM stock_counts WHERE id = ?", [$a['stock_count_id']]);
            if (!$cnt || !in_array($cnt['status'], ['draft', 'rejected'], true)) erp_invalid('The stock count is no longer editable.');
            $cur = []; foreach (erp_rows($pdo, "SELECT * FROM sf_audit_lines WHERE audit_id = ?", [$id]) as $l) $cur[(int)$l['id']] = $l;
            foreach (erp_json_input('lines') as $in) {
                $l = $cur[(int)($in['id'] ?? 0)] ?? null;
                if (!$l) continue;
                $dq = sf_dec($in['damage_qty'] ?? 0, 'Damage quantity') ?? 0.0;
                $pdo->prepare("UPDATE sf_audit_lines SET physical_weight = ?, machine_weight = ?, damage_qty = ?, batch_number = ?, expiry_date = ?, remarks = ? WHERE id = ?")
                    ->execute([sf_dec($in['physical_weight'] ?? null, 'Physical weight'), sf_dec($in['machine_weight'] ?? null, 'Machine weight'), $dq, sf_txt($in['batch_number'] ?? '', 60),
                               erp_date($in['expiry_date'] ?? ''), sf_txt($in['remarks'] ?? '', 255), $l['id']]);
            }
            // physical qty comes from the stock count (single source of the count)
            $pdo->prepare("UPDATE sf_audit_lines l JOIN stock_count_items ci ON ci.id = l.count_item_id SET l.physical_qty = ci.counted_qty WHERE l.audit_id = ?")->execute([$id]);
            $chk = [];
            foreach (['chk_physical_count', 'chk_weight', 'chk_batch', 'chk_expiry', 'chk_damage', 'chk_location'] as $k) $chk[$k] = erp_input($k) === '1' ? 1 : 0;
            $pdo->prepare("UPDATE sf_audits SET chk_physical_count=?, chk_weight=?, chk_batch=?, chk_expiry=?, chk_damage=?, chk_location=?, end_time = ?, remarks = COALESCE(?, remarks) WHERE id = ?")
                ->execute(array_merge(array_values($chk), [sf_time(erp_input('end_time')), sf_txt(erp_input('remarks'), 500), $id]));
            $msg = "{$a['audit_number']} saved.";
            if ($complete) {
                $missing = (int)erp_val($pdo, "SELECT COUNT(*) FROM sf_audit_lines WHERE audit_id = ? AND physical_qty IS NULL", [$id]);
                if ($missing) erp_invalid("{$missing} item(s) have no physical count yet.");
                $noW = (int)erp_val($pdo, "SELECT COUNT(*) FROM sf_audit_lines WHERE audit_id = ? AND unit_weight_kg IS NOT NULL AND (physical_weight IS NULL OR machine_weight IS NULL)", [$id]);
                if ($noW) erp_invalid("{$noW} weight-based item(s) need the physical weight and the machine weight.");
                if (array_sum($chk) < 6) erp_invalid('Tick all six digital checks (count, weight, batch, expiry, damage, location) to confirm.');
                if (erp_input('confirm') !== '1') erp_invalid('Confirm digitally that the audit is correct.');
                if (!sf_time(erp_input('end_time'))) erp_invalid('Enter the end time.');
                $pdo->prepare("UPDATE sf_audits SET status = 'count_completed', confirmed_by = ?, confirmed_at = NOW(), confirm_remarks = ? WHERE id = ?")->execute([erp_user(), sf_txt(erp_input('confirm_remarks'), 500), $id]);
                $msg = "{$a['audit_number']} COUNT COMPLETED and digitally confirmed by " . erp_user() . '. Submit the stock count for verification.';
            }
            $pdo->commit();
            log_audit($pdo, $complete ? 'confirm' : 'update', 'sf_audits', $id, ['lines' => array_values($cur)], ['status' => $complete ? 'count_completed' : $a['status'], 'checks' => $chk, 'lines' => erp_rows($pdo, "SELECT * FROM sf_audit_lines WHERE audit_id = ?", [$id])]);
            erp_out(['status' => 'success', 'message' => $msg]);
        case 'aud_close':
            $id = (int)erp_input('id');
            $a = erp_row($pdo, "SELECT * FROM sf_audits WHERE id = ?", [$id]);
            if ($a) sf_audit_status_sync($pdo, $a);
            if (!$a || $a['status'] !== 'approved') erp_invalid('Only approved audits (stock count approved) can be closed.');
            if (sf_signoff_installed($pdo) && !erp_val($pdo, "SELECT id FROM sf_audit_signoffs WHERE audit_id = ?", [$id])) erp_invalid('The auditor must add the QC report, name and signature before the audit is closed.');   // FIX (2 Oct 2026)
            $pdo->prepare("UPDATE sf_audits SET status = 'closed', closed_by = ?, closed_at = NOW(), remarks = COALESCE(?, remarks) WHERE id = ?")->execute([erp_user(), sf_txt(erp_input('remarks'), 500), $id]);
            log_audit($pdo, 'close', 'sf_audits', $id, ['status' => 'approved'], ['status' => 'closed']);
            erp_out(['status' => 'success', 'message' => "{$a['audit_number']} CLOSED."]);
        // FIX (2 Oct 2026): monthly audit — the (external) auditor's quality check, report, name and digital signature
        case 'aud_signoff':
            if (!sf_signoff_installed($pdo)) erp_invalid('Run sf_audit_signoff_migration.sql once in HeidiSQL to switch on the auditor sign-off.');
            $id = (int)erp_input('id');
            $a = erp_row($pdo, "SELECT * FROM sf_audits WHERE id = ?", [$id]);
            if ($a) sf_audit_status_sync($pdo, $a);
            if (!$a || !in_array($a['status'], ['count_completed', 'verification_pending', 'approved'], true)) erp_invalid('The auditor signs after the count is completed (and before the audit is closed).');
            $type = erp_input('auditor_type') === 'internal' ? 'internal' : 'external';
            $name = sf_txt(erp_input('auditor_name'), 150); $org = sf_txt(erp_input('auditor_org'), 150);
            $res = (string)erp_input('qc_result');
            $qcf = sf_txt(erp_input('qc_findings'), 4000);
            if (!$name) erp_invalid('Enter the auditor name.');
            if ($type === 'external' && !$org) erp_invalid('Enter the auditor firm / organisation.');
            if (!in_array($res, ['satisfactory', 'needs_improvement', 'unsatisfactory'], true)) erp_invalid('Choose the quality result.');
            if (!$qcf) erp_invalid('Enter the quality check findings.');
            $phone = preg_replace('/[^0-9+]/', '', (string)erp_input('auditor_phone', ''));
            if ($phone !== '' && !preg_match('/^\+?\d{10,13}$/', $phone)) erp_invalid('Enter a valid phone number (10 digits) or leave it blank.');
            $docOk = function ($d, bool $req, string $what) use ($pdo, $id) {
                $d = (int)$d;
                if (!$d) { if ($req) erp_invalid($what . ' is required.'); return null; }
                if (!erp_val($pdo, "SELECT id FROM erp_documents WHERE id = ? AND entity_type = 'stock_audit' AND entity_id = ?", [$d, $id])) erp_invalid($what . ' is not attached to this audit.');
                return $d;
            };
            $sig = $docOk(erp_input('signature_doc_id'), true, 'The auditor signature');
            $repDoc = $docOk(erp_input('report_doc_id'), false, 'The report file');
            $cols = ['auditor_type' => $type, 'auditor_name' => $name, 'auditor_org' => $org, 'auditor_designation' => sf_txt(erp_input('auditor_designation'), 100), 'auditor_phone' => $phone ?: null,
                     'qc_result' => $res, 'stock_findings' => sf_txt(erp_input('stock_findings'), 4000), 'qc_findings' => $qcf, 'recommendations' => sf_txt(erp_input('recommendations'), 4000),
                     'signature_doc_id' => $sig, 'report_doc_id' => $repDoc, 'signed_at' => date('Y-m-d H:i:s'), 'entered_by' => erp_user()];
            $old = erp_row($pdo, "SELECT * FROM sf_audit_signoffs WHERE audit_id = ?", [$id]);
            $pdo->prepare("INSERT INTO sf_audit_signoffs (audit_id, " . implode(', ', array_keys($cols)) . ") VALUES (?" . str_repeat(',?', count($cols)) . ")
                           ON DUPLICATE KEY UPDATE " . implode(', ', array_map(fn($k) => "{$k} = VALUES({$k})", array_keys($cols))))->execute(array_merge([$id], array_values($cols)));
            log_audit($pdo, $old ? 'update' : 'sign', 'sf_audits', $id, $old, ['auditor' => $name, 'type' => $type, 'org' => $org, 'qc_result' => $res]);
            erp_out(['status' => 'success', 'message' => "{$a['audit_number']}: signed by {$name}" . ($org ? " ({$org})" : '') . ' — quality ' . str_replace('_', ' ', $res) . '.']);

        case 'aud_cancel':
            $id = (int)erp_input('id');
            $a = erp_row($pdo, "SELECT * FROM sf_audits WHERE id = ?", [$id]);
            if (!$a || !in_array($a['status'], ['planned', 'in_progress', 'count_completed'], true)) erp_invalid('This audit can no longer be cancelled.');
            $pdo->prepare("UPDATE sf_audits SET status = 'cancelled', remarks = CONCAT(COALESCE(remarks,''), ' [Cancelled: ', ?, ']') WHERE id = ?")->execute([(string)erp_input('reason', ''), $id]);
            log_audit($pdo, 'cancel', 'sf_audits', $id, ['status' => $a['status']], ['status' => 'cancelled']);
            erp_out(['status' => 'success', 'message' => "{$a['audit_number']} cancelled (cancel its stock count too if it is still a draft)."]);
        case 'aud_report':
            $w = ["a.status <> 'cancelled'"]; $p = [];
            if ($m = erp_input('month')) { $w[] = 'a.audit_month = ?'; $p[] = $m; }
            if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = 'a.warehouse_id = ?'; $p[] = $wid; }
            if ($aid = (int)erp_input('audit_id', 0)) { $w[] = 'a.id = ?'; $p[] = $aid; }
            $hdr = erp_rows($pdo, "SELECT a.* FROM sf_audits a WHERE " . implode(' AND ', $w), $p);
            foreach ($hdr as &$h) sf_audit_status_sync($pdo, $h);
            unset($h);
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT l.*, a.audit_number, a.audit_month, a.audit_date, a.auditor_name, a.status AS audit_status, w.name AS location,
                                                                       (SELECT COUNT(*) FROM erp_documents d WHERE d.entity_type = 'stock_audit' AND d.entity_id = a.id) AS proofs
                                                                FROM sf_audit_lines l JOIN sf_audits a ON a.id = l.audit_id JOIN warehouses w ON w.id = a.warehouse_id WHERE " . implode(' AND ', $w) . " ORDER BY a.id, l.id", $p));
            $sum = ['matched' => 0, 'shortage' => 0, 'excess' => 0, 'damage_found' => 0, 'pending_verification' => 0];
            foreach ($rows as &$r) {
                if ($r['physical_qty'] === null) $s = 'pending_verification';
                elseif ((float)$r['damage_qty'] > 0) $s = 'damage_found';
                elseif ((float)$r['qty_diff'] < -0.0005) $s = 'shortage';
                elseif ((float)$r['qty_diff'] > 0.0005) $s = 'excess';
                else $s = 'matched';
                $sum[$s]++;
                $r['line_status'] = $s;
                $r['status_label'] = in_array($r['audit_status'], ['approved', 'closed'], true) ? 'approved' : ($s === 'matched' && $r['audit_status'] !== 'verification_pending' ? 'matched' : ($r['audit_status'] === 'verification_pending' ? 'pending_verification' : $s));
            }
            unset($r);
            erp_out(['status' => 'success', 'rows' => $rows, 'summary' => $sum]);

        // ============================================================ EXECUTIVE HANDOVER
        case 'hnd_list':
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT h.id, h.handover_number, h.period_from, h.period_to, h.prepared_by, h.checked_by, h.executive_name, h.handover_date, h.handover_time, h.status, h.ack_by, h.ack_at, w.name AS warehouse_name,
                                                                     (SELECT COUNT(*) FROM erp_documents d WHERE d.entity_type = 'stock_handover' AND d.entity_id = h.id) AS proofs
                                                                     FROM sf_handovers h LEFT JOIN warehouses w ON w.id = h.warehouse_id ORDER BY h.id DESC LIMIT 200")]);
        case 'hnd_get':
            $h = erp_row($pdo, "SELECT h.*, w.name AS warehouse_name FROM sf_handovers h LEFT JOIN warehouses w ON w.id = h.warehouse_id WHERE h.id = ?", [(int)erp_input('id')]);
            if (!$h) erp_fail('Handover not found.');
            $h['snapshot'] = json_decode($h['snapshot'], true);
            erp_out(['status' => 'success', 'record' => $h]);
        case 'hnd_preview':
            $from = erp_date(erp_input('period_from'), true); $to = erp_date(erp_input('period_to'), true);
            if ($to < $from) erp_invalid('The period end is before the start.');
            erp_out(['status' => 'success', 'snapshot' => sf_snapshot($pdo, $from, $to, (int)erp_input('warehouse_id', 0) ?: null)]);
        case 'hnd_create':
            $from = erp_date(erp_input('period_from'), true); $to = erp_date(erp_input('period_to'), true);
            if ($to < $from) erp_invalid('The period end is before the start.');
            $exec = sf_txt(erp_input('executive_name'), 150);
            if (!$exec) erp_invalid('Enter the executive receiving the handover.');
            $wh = (int)erp_input('warehouse_id', 0) ?: null;
            $snap = sf_snapshot($pdo, $from, $to, $wh);
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'stock_handover', 'HND');
            $pdo->prepare("INSERT INTO sf_handovers (handover_number, period_from, period_to, warehouse_id, prepared_by, prepared_at, executive_name, handover_date, handover_time, snapshot, status, remarks) VALUES (?,?,?,?,?, NOW(), ?,?,?,?, 'prepared', ?)")
                ->execute([$num, $from, $to, $wh, erp_user(), $exec, erp_date(erp_input('handover_date')) ?: date('Y-m-d'), sf_time(erp_input('handover_time')) ?: date('H:i:s'), json_encode($snap), sf_txt(erp_input('remarks'), 500)]);
            $hid = (int)$pdo->lastInsertId();
            $pdo->commit();
            log_audit($pdo, 'create', 'sf_handovers', $hid, null, ['handover' => $num, 'period' => [$from, $to], 'executive' => $exec]);
            erp_out(['status' => 'success', 'id' => $hid, 'message' => "{$num} prepared — another person must check it before the executive acknowledges."]);
        case 'hnd_check':
            $id = (int)erp_input('id');
            $h = erp_row($pdo, "SELECT * FROM sf_handovers WHERE id = ?", [$id]);
            if (!$h || $h['status'] !== 'prepared') erp_invalid('Only prepared handovers can be checked.');
            if ($h['prepared_by'] === erp_user()) erp_invalid('The person who prepared the handover cannot also check it.');
            $pdo->prepare("UPDATE sf_handovers SET status = 'checked', checked_by = ?, checked_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
            log_audit($pdo, 'check', 'sf_handovers', $id, ['status' => 'prepared'], ['status' => 'checked']);
            erp_out(['status' => 'success', 'message' => "{$h['handover_number']} checked — the executive can acknowledge it."]);
        case 'hnd_ack':
            $id = (int)erp_input('id');
            $h = erp_row($pdo, "SELECT * FROM sf_handovers WHERE id = ?", [$id]);
            if (!$h || $h['status'] !== 'checked') erp_invalid('Only checked handovers can be acknowledged.');
            if (in_array(erp_user(), [$h['prepared_by'], $h['checked_by']], true)) erp_invalid('The executive acknowledging must be different from the preparer and the checker.');
            $pdo->prepare("UPDATE sf_handovers SET status = 'acknowledged', ack_by = ?, ack_at = NOW(), ack_remarks = ? WHERE id = ?")->execute([erp_user(), sf_txt(erp_input('remarks'), 500), $id]);
            log_audit($pdo, 'acknowledge', 'sf_handovers', $id, ['status' => 'checked'], ['status' => 'acknowledged', 'by' => erp_user()]);
            erp_out(['status' => 'success', 'message' => "{$h['handover_number']} acknowledged by " . erp_user() . '.']);
        case 'hnd_cancel':
            $id = (int)erp_input('id');
            $h = erp_row($pdo, "SELECT * FROM sf_handovers WHERE id = ?", [$id]);
            if (!$h || !in_array($h['status'], ['prepared', 'checked'], true)) erp_invalid('This handover can no longer be cancelled.');
            $pdo->prepare("UPDATE sf_handovers SET status = 'cancelled', remarks = CONCAT(COALESCE(remarks,''), ' [Cancelled: ', ?, ']') WHERE id = ?")->execute([(string)erp_input('reason', ''), $id]);
            log_audit($pdo, 'cancel', 'sf_handovers', $id, ['status' => $h['status']], ['status' => 'cancelled']);
            erp_out(['status' => 'success', 'message' => "{$h['handover_number']} cancelled."]);
    }
    erp_fail('Unknown action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    erp_db_error($e, $action ?: 'stock lifecycle');
}
