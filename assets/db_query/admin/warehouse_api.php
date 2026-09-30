<?php
// ============================================================================
// Warehouse / inventory completion API (added 1 Oct 2026)
//   Stock by warehouse & bucket (available / damaged / rejected / expired,
//   + quarantine = goods waiting for QC, + committed = open sales orders)
//   Stock transfers (send → in transit → receive) · Stock counts (approval)
//   Zone / rack / shelf / bin locations · Batches (FEFO) and expiry
// The existing product_details.stock stays the master total; every change
// here goes through the existing stock ledger (erp_product_movement /
// erp_raw_movement) so storefront, costing and reports stay consistent.
// ============================================================================
require_once __DIR__ . '/erp_ext.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$perms = [
    'sync' => 'warehouse.view',
    'bucket_move' => 'warehouse.transfer', 'bucket_out' => 'warehouse.transfer', 'bucket_restore' => 'stock_adjust.approve', 'realloc' => 'warehouse.transfer',
    'tr_save' => 'warehouse.transfer', 'tr_send' => 'warehouse.transfer', 'tr_receive' => 'warehouse.transfer', 'tr_cancel' => 'warehouse.transfer',
    'cnt_create' => 'stock_count.create', 'cnt_save' => 'stock_count.create', 'cnt_submit' => 'stock_count.create', 'cnt_cancel' => 'stock_count.create',
    'cnt_post' => 'stock_count.approve', 'cnt_reject' => 'stock_count.approve',
    'loc_save' => 'warehouse.locations', 'loc_status' => 'warehouse.locations', 'item_loc_assign' => 'warehouse.locations',
    'settings_save' => 'warehouse.locations',
];
erpx_guard($pdo, $perms[$action] ?? 'warehouse.view');
if (isset($perms[$action]) && $action !== 'sync' && !$isWrite) erp_fail('Invalid request method.');

/** One movement through the existing ledger, product or raw material. */
function wh_ledger_move(PDO $pdo, string $type, int $id, float $qty, string $mtype, string $refType, string $refNo, string $reason, int $wh) {
    if ($type === 'product') {
        if (floor(abs($qty)) != abs($qty)) erp_invalid('Product packs must be whole numbers.');
        return erp_product_movement($pdo, $id, (int)round($qty), $mtype, $refType, $refNo, $reason, $wh);
    }
    return erp_raw_movement($pdo, $id, $qty, $mtype === 'damage' ? 'waste' : $mtype, $refType, $refNo, $reason, $wh);
}
function wh_avg_costs(PDO $pdo): array {
    $out = [];
    foreach (['product', 'raw_material'] as $t) foreach (ce_run($pdo, $t) as $id => $r) $out[$t . ':' . $id] = (float)$r['avg_cost'];
    return $out;
}

// after a write, bring the warehouse sub-ledger up to date straight away (the response is already sent)
if ($isWrite) register_shutdown_function(function () use ($pdo) { try { if (!$pdo->inTransaction()) wh_sync($pdo); } catch (Throwable $e) { error_log('[ERP wh_sync] ' . $e->getMessage()); } });

try {
    wh_sync($pdo);   // keep the warehouse sub-ledger current before every read / write
    switch ($action) {
        case 'sync':
            fefo_sync($pdo);
            erp_out(['status' => 'success', 'message' => 'Warehouse stock and batch allocation are up to date.']);

        // ============================================================ STOCK BY WAREHOUSE
        case 'stock':
            $w = ['1=1']; $p = [];
            if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = 'ws.warehouse_id = ?'; $p[] = $wid; }
            if ($t = (string)erp_input('item_type', '')) { $w[] = 'ws.item_type = ?'; $p[] = erp_item_type($t); }
            $rows = erp_rows($pdo, "SELECT ws.item_type, ws.item_id, ws.warehouse_id, w.name AS warehouse_name,
                                           SUM(CASE WHEN ws.bucket = 'available' THEN ws.quantity ELSE 0 END) available,
                                           SUM(CASE WHEN ws.bucket = 'damaged' THEN ws.quantity ELSE 0 END) damaged,
                                           SUM(CASE WHEN ws.bucket = 'rejected' THEN ws.quantity ELSE 0 END) rejected,
                                           SUM(CASE WHEN ws.bucket = 'expired' THEN ws.quantity ELSE 0 END) expired
                                    FROM warehouse_stock ws LEFT JOIN warehouses w ON w.id = ws.warehouse_id WHERE " . implode(' AND ', $w) . "
                                    GROUP BY ws.item_type, ws.item_id, ws.warehouse_id, w.name", $p);
            $idx = [];
            foreach ($rows as $i => $r) $idx[$r['item_type'] . ':' . $r['item_id'] . ':' . $r['warehouse_id']] = $i;
            // quarantine: received on a draft goods receipt (not yet stock — waiting for QC / posting)
            foreach (erp_rows($pdo, "SELECT gi.item_type, gi.item_id, g.warehouse_id, w.name AS warehouse_name, SUM(gi.received_qty) q FROM goods_receipt_items gi JOIN goods_receipts g ON g.id = gi.grn_id
                                     LEFT JOIN warehouses w ON w.id = g.warehouse_id WHERE g.status = 'draft'" . ($wid ? ' AND g.warehouse_id = ' . (int)$wid : '') . " GROUP BY gi.item_type, gi.item_id, g.warehouse_id, w.name") as $q) {
                $k = $q['item_type'] . ':' . $q['item_id'] . ':' . $q['warehouse_id'];
                if (!isset($idx[$k])) { $rows[] = ['item_type' => $q['item_type'], 'item_id' => $q['item_id'], 'warehouse_id' => $q['warehouse_id'], 'warehouse_name' => $q['warehouse_name'], 'available' => 0, 'damaged' => 0, 'rejected' => 0, 'expired' => 0]; $idx[$k] = count($rows) - 1; }
                $rows[$idx[$k]]['quarantine'] = (float)$q['q'];
            }
            // committed: open sales orders not dispatched yet (reporting only — the storefront is not changed)
            foreach (erp_rows($pdo, "SELECT si.product_id, so.warehouse_id, SUM(si.quantity) q FROM sales_order_items si JOIN sales_orders so ON so.id = si.sales_order_id
                                     WHERE so.status IN ('confirmed','processing','ready_for_dispatch') GROUP BY si.product_id, so.warehouse_id") as $c) {
                $k = 'product:' . $c['product_id'] . ':' . $c['warehouse_id'];
                if (isset($idx[$k])) $rows[$idx[$k]]['committed'] = (float)$c['q'];
            }
            $avg = wh_avg_costs($pdo);
            $rows = erp_attach_item_names($pdo, $rows);
            $q = mb_strtolower(trim((string)erp_input('q', '')));
            $bucket = (string)erp_input('bucket', '');
            $out = [];
            foreach ($rows as $r) {
                foreach (['available', 'damaged', 'rejected', 'expired'] as $b) $r[$b] = erp_q($r[$b]);
                $r['quarantine'] = erp_q($r['quarantine'] ?? 0); $r['committed'] = erp_q($r['committed'] ?? 0);
                $r['free_to_sell'] = erp_q($r['available'] - $r['committed']);
                $r['avg_cost'] = $avg[$r['item_type'] . ':' . $r['item_id']] ?? 0;
                $r['value'] = erp_m($r['available'] * $r['avg_cost']);
                if ($q !== '' && strpos(mb_strtolower($r['item_name'] . ' ' . $r['item_sku']), $q) === false) continue;
                if ($bucket !== '' && abs((float)($r[$bucket] ?? 0)) < 0.0005) continue;
                if (erp_input('nonzero', '1') === '1' && abs($r['available']) + $r['damaged'] + $r['rejected'] + $r['expired'] + $r['quarantine'] < 0.0005) continue;
                $out[] = $r;
            }
            usort($out, function ($a, $b) { return [$a['warehouse_name'], $a['item_name']] <=> [$b['warehouse_name'], $b['item_name']]; });
            // total by item must equal the master stock; show any difference (a change made outside the ledger)
            $diffs = [];
            foreach (['product' => 'product_details', 'raw_material' => 'raw_materials'] as $t => $master) {
                foreach (erp_rows($pdo, "SELECT m.id, m.stock, COALESCE((SELECT SUM(quantity) FROM warehouse_stock ws WHERE ws.item_type = ? AND ws.item_id = m.id AND ws.bucket = 'available'),0) wsum
                                         FROM {$master} m HAVING ABS(m.stock - wsum) > 0.0005", [$t]) as $d)
                    $diffs[] = ['item_type' => $t, 'item_id' => (int)$d['id'], 'master_stock' => erp_q($d['stock']), 'warehouse_total' => erp_q($d['wsum']), 'difference' => erp_q($d['stock'] - $d['wsum'])];
            }
            $summary = [];
            foreach ($out as $r) {
                $k = $r['warehouse_id'];
                if (!isset($summary[$k])) $summary[$k] = ['warehouse_id' => $k, 'warehouse_name' => $r['warehouse_name'], 'items' => 0, 'value' => 0, 'damaged' => 0, 'rejected' => 0, 'expired' => 0, 'quarantine' => 0];
                $summary[$k]['items']++; $summary[$k]['value'] += $r['value'];
                foreach (['damaged', 'rejected', 'expired', 'quarantine'] as $b) $summary[$k][$b] += $r[$b];
            }
            erp_out(['status' => 'success', 'rows' => $out, 'summary' => array_values($summary), 'differences' => erp_attach_item_names($pdo, $diffs),
                     'default_warehouse' => wh_default($pdo)]);

        case 'moves':
            $w = ['1=1']; $p = [];
            if ($t = (string)erp_input('item_type', '')) { $w[] = 'm.item_type = ?'; $p[] = erp_item_type($t); }
            if ($iid = (int)erp_input('item_id', 0)) { $w[] = 'm.item_id = ?'; $p[] = $iid; }
            if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = 'm.warehouse_id = ?'; $p[] = $wid; }
            if ($b = (string)erp_input('bucket', '')) { $w[] = 'm.bucket = ?'; $p[] = $b; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'm.created_at >= ?'; $p[] = $d . ' 00:00:00'; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'm.created_at <= ?'; $p[] = $d . ' 23:59:59'; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(m.reference_number LIKE ? OR m.reason LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
            [$lim, $off] = erp_page_args(100);
            $where = implode(' AND ', $w);
            $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM warehouse_stock_moves m WHERE {$where}", $p);
            $rows = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT m.*, w.name AS warehouse_name FROM warehouse_stock_moves m LEFT JOIN warehouses w ON w.id = m.warehouse_id WHERE {$where} ORDER BY m.id DESC LIMIT {$lim} OFFSET {$off}", $p));
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => $total]);

        case 'bucket_move':
            // available → damaged / expired: leaves sellable stock through the ledger (written off at average cost)
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $to = (string)erp_input('bucket');
            if (!in_array($to, ['damaged', 'expired'], true)) erp_invalid('Choose damaged or expired.');
            $qty = erp_q(erp_num(erp_input('quantity'), 'Quantity', false));
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason.');
            $item = erp_item($pdo, $type, $iid);
            if (!$item) erp_invalid('Item not found.');
            $have = wh_qty($pdo, $type, $iid, $wh);
            if ($qty > $have + 0.0005) erp_invalid("Only " . erp_q($have) . " {$item['unit']} of {$item['name']} are available at this warehouse.");
            $batchId = (int)erp_input('batch_id', 0) ?: null;
            if ($batchId) {
                $b = batch_remaining_rows($pdo, ['batch_id' => $batchId])[0] ?? null;
                if (!$b || $b['item_type'] !== $type || (int)$b['item_id'] !== $iid) erp_invalid('The batch does not belong to this item.');
                if ($qty > $b['remaining'] + 0.0005) erp_invalid('The batch has only ' . $b['remaining'] . ' left.');
            }
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'wh_bucket', 'WHB');
            $mv = wh_ledger_move($pdo, $type, $iid, -$qty, 'damage', 'damage', $num, ucfirst($to) . ": {$reason}", $wh);
            $mid = (int)erp_val($pdo, $type === 'product' ? "SELECT MAX(id) FROM stock_movements WHERE product_id = ?" : "SELECT MAX(id) FROM raw_material_movements WHERE raw_material_id = ?", [$iid]);
            if ($batchId) $pdo->prepare("INSERT INTO batch_allocations (batch_id, item_type, item_id, movement_id, quantity, allocation_type) VALUES (?,?,?,?,?, 'manual')")->execute([$batchId, $type, $iid, $mid, $qty]);
            wh_apply($pdo, $type, $iid, $wh, $to, $qty, 'bucket', null, 'bucket_in', $num, $reason);
            $pdo->commit();
            wh_sync($pdo);
            log_audit($pdo, 'stock_movement', 'warehouse_stock', $iid, null, ['number' => $num, 'item' => $item['name'], 'to' => $to, 'qty' => $qty, 'warehouse' => $wh, 'batch' => $batchId, 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$num}: " . erp_q($qty) . " {$item['unit']} of {$item['name']} moved to {$to} stock (not sellable)."]);

        case 'bucket_out':
            // dispose / return to supplier / destroy from damaged, expired or rejected stock (already out of sellable stock)
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $from = (string)erp_input('bucket');
            if (!in_array($from, ['damaged', 'expired', 'rejected'], true)) erp_invalid('Invalid stock bucket.');
            $qty = erp_q(erp_num(erp_input('quantity'), 'Quantity', false));
            $how = (string)erp_input('disposal', 'disposed');
            if (!in_array($how, ['disposed', 'destroyed', 'returned_to_supplier', 'donated', 'sold_as_scrap', 'other'], true)) erp_invalid('Choose what happened to the goods.');
            $have = wh_qty($pdo, $type, $iid, $wh, $from);
            if ($qty > $have + 0.0005) erp_invalid("Only " . erp_q($have) . " are in {$from} stock at this warehouse.");
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'wh_bucket', 'WHB');
            wh_apply($pdo, $type, $iid, $wh, $from, -$qty, 'bucket', null, 'bucket_out', $num, str_replace('_', ' ', $how) . ': ' . trim((string)erp_input('reason', '')));
            $pdo->commit();
            log_audit($pdo, 'stock_movement', 'warehouse_stock', $iid, null, ['number' => $num, 'from' => $from, 'qty' => $qty, 'disposal' => $how, 'reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => "{$num}: " . erp_q($qty) . " removed from {$from} stock (" . str_replace('_', ' ', $how) . ').']);

        case 'bucket_restore':
            // damaged / expired / rejected → sellable again (re-inspected). Enters the ledger at the current average cost.
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $from = (string)erp_input('bucket');
            if (!in_array($from, ['damaged', 'expired', 'rejected'], true)) erp_invalid('Invalid stock bucket.');
            $qty = erp_q(erp_num(erp_input('quantity'), 'Quantity', false));
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give the inspection result / reason.');
            if ($qty > wh_qty($pdo, $type, $iid, $wh, $from) + 0.0005) erp_invalid("Not that much in {$from} stock.");
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'wh_bucket', 'WHB');
            wh_apply($pdo, $type, $iid, $wh, $from, -$qty, 'bucket', null, 'bucket_restore', $num, "Back to sellable: {$reason}");
            wh_ledger_move($pdo, $type, $iid, $qty, 'adjustment', 'bucket_restore', $num, "Restored from {$from}: {$reason}", $wh);
            $pdo->commit();
            wh_sync($pdo);
            log_audit($pdo, 'stock_movement', 'warehouse_stock', $iid, null, ['number' => $num, 'restore_from' => $from, 'qty' => $qty, 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$num}: " . erp_q($qty) . " back in sellable stock."]);

        case 'realloc':
            // correct WHERE stock sits (no change to the total): align a difference, or move an opening allocation
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            $to = erp_warehouse_ok($pdo, (int)erp_input('to_warehouse_id'));
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason for the correction.');
            $master = (float)(erp_item($pdo, $type, $iid)['stock'] ?? 0);
            $sum = (float)erp_val($pdo, "SELECT COALESCE(SUM(quantity),0) FROM warehouse_stock WHERE item_type = ? AND item_id = ? AND bucket = 'available'", [$type, $iid]);
            $pdo->beginTransaction();
            if (erp_input('mode') === 'align') {
                $diff = erp_q($master - $sum);
                if (abs($diff) < 0.0005) erp_invalid('Warehouse totals already match the stock.');
                wh_apply($pdo, $type, $iid, $to, 'available', $diff, 'realloc', null, 'align', null, "Align with master stock: {$reason}");
                $msg = 'Aligned: ' . erp_q($diff) . ' placed in the chosen warehouse.';
            } else {
                $from = erp_warehouse_ok($pdo, (int)erp_input('from_warehouse_id'));
                if ($from === $to) erp_invalid('Choose two different warehouses.');
                $qty = erp_q(erp_num(erp_input('quantity'), 'Quantity', false));
                if ($qty > wh_qty($pdo, $type, $iid, $from) + 0.0005) erp_invalid('Not that much available at the source warehouse.');
                wh_apply($pdo, $type, $iid, $from, 'available', -$qty, 'realloc', null, 'realloc', null, "Allocation correction: {$reason}");
                wh_apply($pdo, $type, $iid, $to, 'available', $qty, 'realloc', null, 'realloc', null, "Allocation correction: {$reason}");
                $msg = erp_q($qty) . ' re-allocated (correction, not a physical transfer — use Stock Transfers for real moves).';
            }
            $pdo->commit();
            log_audit($pdo, 'realloc', 'warehouse_stock', $iid, null, ['item_type' => $type, 'mode' => erp_input('mode'), 'to' => $to, 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => $msg]);

        // ============================================================ TRANSFERS
        case 'tr_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 't.status = ?'; $p[] = $s; }
            if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = '(t.from_warehouse_id = ? OR t.to_warehouse_id = ?)'; array_push($p, $wid, $wid); }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 't.transfer_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 't.transfer_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(t.transfer_number LIKE ? OR t.vehicle_number LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT t.*, wf.name AS from_name, wt.name AS to_name, (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS item_count,
                                           (SELECT COALESCE(SUM(quantity * unit_cost),0) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS value
                                    FROM stock_transfers t LEFT JOIN warehouses wf ON wf.id = t.from_warehouse_id LEFT JOIN warehouses wt ON wt.id = t.to_warehouse_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY t.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'tr_get':
            $id = (int)erp_input('id');
            $t = erp_row($pdo, "SELECT t.*, wf.name AS from_name, wt.name AS to_name FROM stock_transfers t LEFT JOIN warehouses wf ON wf.id = t.from_warehouse_id LEFT JOIN warehouses wt ON wt.id = t.to_warehouse_id WHERE t.id = ?", [$id]);
            if (!$t) erp_fail('Transfer not found.');
            $t['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM stock_transfer_items WHERE transfer_id = ? ORDER BY id", [$id]));
            foreach ($t['items'] as &$i) $i['available_at_source'] = erp_q(wh_qty($pdo, $i['item_type'], (int)$i['item_id'], (int)$t['from_warehouse_id']));
            unset($i);
            erp_out(['status' => 'success', 'record' => $t]);

        case 'tr_save':
            $id = (int)erp_input('id', 0);
            $from = erp_warehouse_ok($pdo, (int)erp_input('from_warehouse_id'));
            $to = erp_warehouse_ok($pdo, (int)erp_input('to_warehouse_id'));
            if ($from === $to) erp_invalid('Choose two different warehouses.');
            $date = erp_date(erp_input('transfer_date'), true);
            $lines = [];
            foreach (erp_json_input('items') as $it) {
                $type = erp_item_type($it['item_type'] ?? 'product');
                $iid = (int)($it['item_id'] ?? 0);
                if (!$iid) continue;
                $item = erp_item($pdo, $type, $iid);
                if (!$item) erp_invalid('An item no longer exists.');
                $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity', false));
                if ($type === 'product' && floor($qty) != $qty) erp_invalid("{$item['name']}: product packs must be whole numbers.");
                $lines[] = [$type, $iid, $qty, $type === 'product' ? 'pcs' : $item['unit']];
            }
            if (!$lines) erp_invalid('Add at least one item.');
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM stock_transfers WHERE id = ? FOR UPDATE", [$id]);
                if (!$old || $old['status'] !== 'draft') erp_invalid('Only draft transfers can be edited.');
                $pdo->prepare("UPDATE stock_transfers SET transfer_date=?, from_warehouse_id=?, to_warehouse_id=?, vehicle_number=?, notes=? WHERE id=?")
                    ->execute([$date, $from, $to, erp_input('vehicle_number') ?: null, erp_input('notes') ?: null, $id]);
                $pdo->prepare("DELETE FROM stock_transfer_items WHERE transfer_id = ?")->execute([$id]);
                $num = $old['transfer_number'];
            } else {
                $num = next_document_number($pdo, 'stock_transfer', 'STR');
                $pdo->prepare("INSERT INTO stock_transfers (transfer_number, transfer_date, from_warehouse_id, to_warehouse_id, vehicle_number, notes, status, created_by) VALUES (?,?,?,?,?,?, 'draft', ?)")
                    ->execute([$num, $date, $from, $to, erp_input('vehicle_number') ?: null, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, item_type, item_id, quantity, unit) VALUES (?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute(array_merge([$id], $l));
            $pdo->commit();
            log_audit($pdo, 'save', 'stock_transfers', $id, null, ['transfer_number' => $num, 'from' => $from, 'to' => $to, 'lines' => $lines]);
            if (erp_input('send_now') === '1') { $_POST['id'] = $id; $action = 'tr_send'; }
            else erp_out(['status' => 'success', 'id' => $id, 'message' => "{$num} saved as draft (no stock moved yet)."]);
            // fall through to send
        case 'tr_send':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $t = erp_row($pdo, "SELECT * FROM stock_transfers WHERE id = ? FOR UPDATE", [$id]);
            if (!$t || $t['status'] !== 'draft') erp_invalid('Only draft transfers can be sent.');
            $items = erp_rows($pdo, "SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$id]);
            foreach ($items as $i) {
                $item = erp_item($pdo, $i['item_type'], (int)$i['item_id']);
                $have = wh_qty($pdo, $i['item_type'], (int)$i['item_id'], (int)$t['from_warehouse_id']);
                if ((float)$i['quantity'] > $have + 0.0005) erp_invalid("{$item['name']}: only " . erp_q($have) . " available at the source warehouse.");
                $unit = ce_current_avg($pdo, $i['item_type'], (int)$i['item_id']);
                wh_ledger_move($pdo, $i['item_type'], (int)$i['item_id'], -(float)$i['quantity'], 'transfer', 'stock_transfer', $t['transfer_number'], "Transfer {$t['transfer_number']} out (in transit)", (int)$t['from_warehouse_id']);
                $pdo->prepare("UPDATE stock_transfer_items SET unit_cost = ? WHERE id = ?")->execute([erp_u($unit), $i['id']]);
            }
            $pdo->prepare("UPDATE stock_transfers SET status = 'in_transit', sent_by = ?, sent_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
            $pdo->commit();
            log_audit($pdo, 'stock_movement', 'stock_transfers', $id, ['status' => 'draft'], ['status' => 'in_transit']);
            if (erp_input('receive_now') === '1') { $action = 'tr_receive'; }
            else erp_out(['status' => 'success', 'id' => $id, 'message' => "{$t['transfer_number']} sent — the goods are in transit (not sellable until received)."]);
            // fall through to receive
        case 'tr_receive':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $t = erp_row($pdo, "SELECT * FROM stock_transfers WHERE id = ? FOR UPDATE", [$id]);
            if (!$t || $t['status'] !== 'in_transit') erp_invalid('Only transfers in transit can be received.');
            foreach (erp_rows($pdo, "SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$id]) as $i) {
                wh_ledger_move($pdo, $i['item_type'], (int)$i['item_id'], (float)$i['quantity'], 'transfer', 'stock_transfer', $t['transfer_number'], "Transfer {$t['transfer_number']} received", (int)$t['to_warehouse_id']);
                erp_cost_entry($pdo, $i['item_type'], (int)$i['item_id'], 'receipt', (float)$i['quantity'], (float)$i['quantity'] * (float)$i['unit_cost'], 'stock_transfer', $t['transfer_number'], $id, "Transfer {$t['transfer_number']} at sending cost");
                $pdo->prepare("UPDATE stock_transfer_items SET received_qty = quantity WHERE id = ?")->execute([$i['id']]);
            }
            $pdo->prepare("UPDATE stock_transfers SET status = 'received', received_by = ?, received_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
            $pdo->commit();
            log_audit($pdo, 'stock_movement', 'stock_transfers', $id, ['status' => 'in_transit'], ['status' => 'received']);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$t['transfer_number']} received at the destination warehouse."]);

        case 'tr_cancel':
            $id = (int)erp_input('id');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason.');
            $pdo->beginTransaction();
            $t = erp_row($pdo, "SELECT * FROM stock_transfers WHERE id = ? FOR UPDATE", [$id]);
            if (!$t || !in_array($t['status'], ['draft', 'in_transit'], true)) erp_invalid('Only draft or in-transit transfers can be cancelled.');
            if ($t['status'] === 'in_transit') {   // goods go back to the source warehouse at the same cost
                foreach (erp_rows($pdo, "SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$id]) as $i) {
                    wh_ledger_move($pdo, $i['item_type'], (int)$i['item_id'], (float)$i['quantity'], 'transfer', 'stock_transfer', $t['transfer_number'], "Transfer {$t['transfer_number']} cancelled — back to source", (int)$t['from_warehouse_id']);
                    erp_cost_entry($pdo, $i['item_type'], (int)$i['item_id'], 'receipt', (float)$i['quantity'], (float)$i['quantity'] * (float)$i['unit_cost'], 'stock_transfer', $t['transfer_number'], $id, "Transfer {$t['transfer_number']} returned");
                }
            }
            $pdo->prepare("UPDATE stock_transfers SET status = 'cancelled', notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?")->execute(["\n[Cancelled by " . erp_user() . ": {$reason}]", $id]);
            $pdo->commit();
            log_audit($pdo, 'cancel', 'stock_transfers', $id, ['status' => $t['status']], ['status' => 'cancelled', 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$t['transfer_number']} cancelled" . ($t['status'] === 'in_transit' ? ' — the goods are back at the source warehouse.' : '.')]);

        // ============================================================ STOCK COUNTS
        case 'cnt_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'c.status = ?'; $p[] = $s; }
            if ($wid = (int)erp_input('warehouse_id', 0)) { $w[] = 'c.warehouse_id = ?'; $p[] = $wid; }
            $rows = erp_rows($pdo, "SELECT c.*, w.name AS warehouse_name, (SELECT COUNT(*) FROM stock_count_items i WHERE i.count_id = c.id) AS item_count,
                                           (SELECT COUNT(*) FROM stock_count_items i WHERE i.count_id = c.id AND i.counted_qty IS NOT NULL) AS counted_count,
                                           (SELECT COALESCE(SUM(variance_value),0) FROM stock_count_items i WHERE i.count_id = c.id) AS variance_value
                                    FROM stock_counts c LEFT JOIN warehouses w ON w.id = c.warehouse_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY c.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'cnt_get':
            $id = (int)erp_input('id');
            $c = erp_row($pdo, "SELECT c.*, w.name AS warehouse_name FROM stock_counts c LEFT JOIN warehouses w ON w.id = c.warehouse_id WHERE c.id = ?", [$id]);
            if (!$c) erp_fail('Stock count not found.');
            $c['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM stock_count_items WHERE count_id = ? ORDER BY id", [$id]));
            $c['moved_since'] = [];
            if (in_array($c['status'], ['draft', 'submitted'], true)) foreach ($c['items'] as $i) {
                $n = (float)erp_val($pdo, "SELECT COALESCE(SUM(quantity),0) FROM warehouse_stock_moves WHERE item_type = ? AND item_id = ? AND warehouse_id = ? AND bucket = 'available' AND created_at > ?",
                                    [$i['item_type'], $i['item_id'], $c['warehouse_id'], $c['created_at']]);
                if (abs($n) >= 0.0005) $c['moved_since'][] = ['item_name' => $i['item_name'], 'change' => erp_q($n)];
            }
            erp_out(['status' => 'success', 'record' => $c]);

        case 'cnt_create':
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $date = erp_date(erp_input('count_date'), true);
            $scope = (string)erp_input('scope', 'all');
            $cat = trim((string)erp_input('category', ''));
            $picked = erp_json_input('items');
            $cand = [];
            if ($scope === 'selected') {
                foreach ($picked as $it) { $t = erp_item_type($it['item_type'] ?? 'product'); if ((int)($it['item_id'] ?? 0)) $cand[$t . ':' . (int)$it['item_id']] = [$t, (int)$it['item_id']]; }
            } else {
                foreach (erp_rows($pdo, "SELECT item_type, item_id FROM warehouse_stock WHERE warehouse_id = ? AND bucket = 'available' AND quantity <> 0", [$wh]) as $r) $cand[$r['item_type'] . ':' . $r['item_id']] = [$r['item_type'], (int)$r['item_id']];
                if (erp_input('include_zero') === '1') foreach (erp_rows($pdo, "SELECT id FROM product_details") as $r) $cand['product:' . $r['id']] = ['product', (int)$r['id']];
            }
            $lines = [];
            foreach ($cand as [$t, $iid]) {
                $item = erp_item($pdo, $t, $iid);
                if (!$item) continue;
                if ($cat !== '' && strcasecmp((string)$item['category'], $cat) !== 0) continue;
                $lines[] = [$t, $iid, erp_q(wh_qty($pdo, $t, $iid, $wh)), erp_u(ce_current_avg($pdo, $t, $iid))];
            }
            if (!$lines) erp_invalid('No items to count for this selection.');
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'stock_count', 'SC');
            $pdo->prepare("INSERT INTO stock_counts (count_number, count_date, warehouse_id, scope, status, notes, created_by) VALUES (?,?,?,?, 'draft', ?, ?)")
                ->execute([$num, $date, $wh, $scope === 'selected' ? 'Selected items' : ($cat !== '' ? "Category: {$cat}" : 'All items in stock'), erp_input('notes') ?: null, erp_user()]);
            $cid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO stock_count_items (count_id, item_type, item_id, system_qty, unit_cost) VALUES (?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute(array_merge([$cid], $l));
            $pdo->commit();
            log_audit($pdo, 'create', 'stock_counts', $cid, null, ['count_number' => $num, 'items' => count($lines)]);
            erp_out(['status' => 'success', 'id' => $cid, 'message' => "{$num} created with " . count($lines) . ' item(s). The system quantities are frozen now — enter what you physically count.']);

        case 'cnt_save':
        case 'cnt_submit':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $c = erp_row($pdo, "SELECT * FROM stock_counts WHERE id = ? FOR UPDATE", [$id]);
            if (!$c || !in_array($c['status'], ['draft', 'rejected'], true)) erp_invalid('Only draft counts can be edited.');
            $cur = []; foreach (erp_rows($pdo, "SELECT * FROM stock_count_items WHERE count_id = ?", [$id]) as $i) $cur[(int)$i['id']] = $i;
            foreach (erp_json_input('items') as $it) {
                $ci = $cur[(int)($it['id'] ?? 0)] ?? null;
                if (!$ci) continue;
                $raw = trim((string)($it['counted_qty'] ?? ''));
                if ($raw === '') { $pdo->prepare("UPDATE stock_count_items SET counted_qty = NULL, variance = 0, variance_value = 0, reason = ? WHERE id = ?")->execute([$it['reason'] ?? null, $ci['id']]); continue; }
                $cq = erp_q(erp_num($raw, 'Counted quantity'));
                if ($ci['item_type'] === 'product' && floor($cq) != $cq) erp_invalid('Product packs must be whole numbers.');
                $var = erp_q($cq - (float)$ci['system_qty']);
                $pdo->prepare("UPDATE stock_count_items SET counted_qty = ?, variance = ?, variance_value = ?, reason = ? WHERE id = ?")
                    ->execute([$cq, $var, erp_m($var * (float)$ci['unit_cost']), mb_substr(trim((string)($it['reason'] ?? '')), 0, 255) ?: null, $ci['id']]);
            }
            $msg = "{$c['count_number']} saved.";
            if ($action === 'cnt_submit') {
                $missing = (int)erp_val($pdo, "SELECT COUNT(*) FROM stock_count_items WHERE count_id = ? AND counted_qty IS NULL", [$id]);
                if ($missing) erp_invalid("{$missing} item(s) have no counted quantity yet.");
                $noReason = (int)erp_val($pdo, "SELECT COUNT(*) FROM stock_count_items WHERE count_id = ? AND ABS(variance) >= 0.0005 AND (reason IS NULL OR reason = '')", [$id]);
                if ($noReason) erp_invalid("Give a reason for every difference ({$noReason} line(s)).");
                $pdo->prepare("UPDATE stock_counts SET status = 'submitted', submitted_by = ? WHERE id = ?")->execute([erp_user(), $id]);
                $val = (float)erp_val($pdo, "SELECT COALESCE(SUM(ABS(variance_value)),0) FROM stock_count_items WHERE count_id = ?", [$id]);
                apr_open($pdo, 'stock_count', (string)$id, $id, $c['count_number'], "Stock count {$c['count_number']} — differences worth ₹" . number_format($val, 2), $val, 'warehouse_api.php',
                         ['action' => 'cnt_post', 'id' => $id], ['action' => 'cnt_reject', 'id' => $id]);
                $msg = "{$c['count_number']} submitted for approval. Stock changes only when it is approved.";
            }
            $pdo->commit();
            log_audit($pdo, $action === 'cnt_submit' ? 'submit' : 'update', 'stock_counts', $id, null, ['status' => $action === 'cnt_submit' ? 'submitted' : 'draft']);
            erp_out(['status' => 'success', 'message' => $msg]);

        case 'cnt_post':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $c = erp_row($pdo, "SELECT * FROM stock_counts WHERE id = ? FOR UPDATE", [$id]);
            if (!$c || $c['status'] !== 'submitted') erp_invalid('Only submitted counts can be approved.');
            $n = 0;
            foreach (erp_rows($pdo, "SELECT * FROM stock_count_items WHERE count_id = ? AND ABS(variance) >= 0.0005", [$id]) as $i) {
                wh_ledger_move($pdo, $i['item_type'], (int)$i['item_id'], (float)$i['variance'], 'adjustment', 'stock_count', $c['count_number'], "Stock count {$c['count_number']}: " . ($i['reason'] ?: 'count difference'), (int)$c['warehouse_id']);
                $n++;
            }
            $pdo->prepare("UPDATE stock_counts SET status = 'posted', posted_by = ?, posted_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
            apr_close($pdo, 'stock_count', (string)$id, 'approved', erp_input('_approval_remarks') ?: null);
            $pdo->commit();
            log_audit($pdo, 'approve', 'stock_counts', $id, ['status' => 'submitted'], ['status' => 'posted', 'adjusted_lines' => $n]);
            erp_out(['status' => 'success', 'message' => "{$c['count_number']} approved: {$n} stock difference(s) posted to the ledger."]);

        case 'cnt_reject':
        case 'cnt_cancel':
            $id = (int)erp_input('id');
            $c = erp_row($pdo, "SELECT * FROM stock_counts WHERE id = ?", [$id]);
            if (!$c) erp_invalid('Stock count not found.');
            if ($action === 'cnt_reject' && $c['status'] !== 'submitted') erp_invalid('Only submitted counts can be rejected.');
            if ($action === 'cnt_cancel' && !in_array($c['status'], ['draft', 'rejected', 'submitted'], true)) erp_invalid('This count can no longer be cancelled.');
            $to = $action === 'cnt_reject' ? 'rejected' : 'cancelled';
            $pdo->prepare("UPDATE stock_counts SET status = ? WHERE id = ?")->execute([$to, $id]);
            apr_close($pdo, 'stock_count', (string)$id, $to === 'rejected' ? 'rejected' : 'cancelled', erp_input('_approval_remarks') ?: erp_input('reason') ?: null);
            log_audit($pdo, $to, 'stock_counts', $id, ['status' => $c['status']], ['status' => $to]);
            erp_out(['status' => 'success', 'message' => "{$c['count_number']} {$to}" . ($to === 'rejected' ? ' — it can be corrected and submitted again.' : '.')]);

        // ============================================================ LOCATIONS
        case 'loc_list':
            $wid = (int)erp_input('warehouse_id', 0);
            $rows = erp_rows($pdo, "SELECT l.*, w.name AS warehouse_name, (SELECT COUNT(*) FROM item_locations i WHERE i.location_id = l.id) AS items
                                    FROM warehouse_locations l JOIN warehouses w ON w.id = l.warehouse_id" . ($wid ? ' WHERE l.warehouse_id = ' . $wid : '') . " ORDER BY w.name, FIELD(l.level,'zone','rack','shelf','bin'), l.code");
            $assign = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT i.*, l.code AS location_code, w.name AS warehouse_name FROM item_locations i JOIN warehouse_locations l ON l.id = i.location_id
                                                                  JOIN warehouses w ON w.id = i.warehouse_id" . ($wid ? ' WHERE i.warehouse_id = ' . $wid : '') . " ORDER BY w.name, l.code"));
            erp_out(['status' => 'success', 'rows' => $rows, 'assignments' => $assign]);

        case 'loc_save':
            $id = (int)erp_input('id', 0);
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id'));
            $level = (string)erp_input('level');
            $order = ['zone' => 1, 'rack' => 2, 'shelf' => 3, 'bin' => 4];
            if (!isset($order[$level])) erp_invalid('Choose zone, rack, shelf or bin.');
            $code = strtoupper(trim((string)erp_input('code', '')));
            if (!preg_match('/^[A-Z0-9\-\/\.]{1,40}$/', $code)) erp_invalid('Code: letters, numbers, - / . only (e.g. Z1-R2-S3-B4).');
            $parent = (int)erp_input('parent_id', 0) ?: null;
            if ($parent) {
                $pr = erp_row($pdo, "SELECT * FROM warehouse_locations WHERE id = ?", [$parent]);
                if (!$pr || (int)$pr['warehouse_id'] !== $wh || $order[$pr['level']] >= $order[$level]) erp_invalid('The parent must be a higher level in the same warehouse (zone → rack → shelf → bin).');
            }
            if (erp_val($pdo, "SELECT id FROM warehouse_locations WHERE warehouse_id = ? AND code = ? AND id <> ?", [$wh, $code, $id])) erp_invalid("Code {$code} already exists in this warehouse.");
            if ($id) $pdo->prepare("UPDATE warehouse_locations SET warehouse_id=?, parent_id=?, level=?, code=?, name=? WHERE id=?")->execute([$wh, $parent, $level, $code, erp_input('name') ?: null, $id]);
            else { $pdo->prepare("INSERT INTO warehouse_locations (warehouse_id, parent_id, level, code, name) VALUES (?,?,?,?,?)")->execute([$wh, $parent, $level, $code, erp_input('name') ?: null]); $id = (int)$pdo->lastInsertId(); }
            log_audit($pdo, 'save', 'warehouse_locations', $id, null, ['code' => $code, 'level' => $level]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "Location {$code} saved."]);

        case 'loc_status':
            $id = (int)erp_input('id');
            $to = erp_input('status') === 'active' ? 'active' : 'inactive';
            $pdo->prepare("UPDATE warehouse_locations SET status = ? WHERE id = ?")->execute([$to, $id]);
            log_audit($pdo, 'update', 'warehouse_locations', $id, null, ['status' => $to]);
            erp_out(['status' => 'success', 'message' => "Location {$to}."]);

        case 'item_loc_assign':
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            if (!erp_item($pdo, $type, $iid)) erp_invalid('Choose an item.');
            $loc = erp_row($pdo, "SELECT * FROM warehouse_locations WHERE id = ? AND status = 'active'", [(int)erp_input('location_id')]);
            if (!$loc) erp_invalid('Choose an active location.');
            $pdo->prepare("INSERT INTO item_locations (item_type, item_id, warehouse_id, location_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE location_id = VALUES(location_id)")
                ->execute([$type, $iid, $loc['warehouse_id'], $loc['id']]);
            log_audit($pdo, 'update', 'item_locations', $iid, null, ['item_type' => $type, 'location' => $loc['code']]);
            erp_out(['status' => 'success', 'message' => "Stored at {$loc['code']}."]);

        // ============================================================ BATCHES / EXPIRY
        case 'batches':
            fefo_sync($pdo);
            $rows = batch_remaining_rows($pdo, ['item_type' => (string)erp_input('item_type', ''), 'item_id' => (int)erp_input('item_id', 0)]);
            $days = (int)erp_setting($pdo, 'erp_expiry_alert_days', 30);
            $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime("+{$days} days"));
            $st = (string)erp_input('status', '');
            $q = mb_strtolower(trim((string)erp_input('q', '')));
            $rows = erp_attach_item_names($pdo, $rows);
            $out = [];
            foreach ($rows as $r) {
                $r['expiry_status'] = $r['remaining'] <= 0.0005 ? 'empty' : (!$r['expiry_date'] ? 'none' : ($r['expiry_date'] < $today ? 'expired' : ($r['expiry_date'] <= $soon ? 'expiring' : 'ok')));
                $r['days_left'] = $r['expiry_date'] ? (int)floor((strtotime($r['expiry_date']) - strtotime($today)) / 86400) : null;
                $r['remaining_value'] = erp_m($r['remaining'] * (float)$r['unit_cost']);
                if ($st !== '' && $r['expiry_status'] !== $st && !($st === 'open' && $r['remaining'] > 0.0005)) continue;
                if ($q !== '' && strpos(mb_strtolower($r['item_name'] . ' ' . $r['batch_number'] . ' ' . $r['lot_number'] . ' ' . $r['supplier_name']), $q) === false) continue;
                if (($sid = (int)erp_input('supplier_id', 0)) && (int)$r['supplier_id'] !== $sid) continue;
                if (($wid = (int)erp_input('warehouse_id', 0)) && (int)$r['warehouse_id'] !== $wid) continue;
                $r['source_number'] = $r['source_type'] === 'grn' ? erp_val($pdo, "SELECT grn_number FROM goods_receipts WHERE id = ?", [$r['source_id']])
                                    : ($r['source_type'] === 'repack' ? erp_val($pdo, "SELECT repack_number FROM repack_jobs WHERE id = ?", [$r['source_id']])
                                    : erp_val($pdo, "SELECT si.stock_in_number FROM stock_in_items sii JOIN stock_ins si ON si.id = sii.stock_in_id WHERE sii.id = ?", [$r['source_item_id']]));
                $out[] = $r;
            }
            erp_out(['status' => 'success', 'rows' => $out, 'alert_days' => $days,
                     'counts' => ['expired' => count(array_filter($rows, function ($r) use ($today) { return $r['remaining'] > 0.0005 && $r['expiry_date'] && $r['expiry_date'] < $today; })),
                                  'expiring' => count(array_filter($rows, function ($r) use ($today, $soon) { return $r['remaining'] > 0.0005 && $r['expiry_date'] && $r['expiry_date'] >= $today && $r['expiry_date'] <= $soon; }))]]);

        case 'batch_get':
            fefo_sync($pdo);
            $b = batch_remaining_rows($pdo, ['batch_id' => (int)erp_input('id')])[0] ?? null;
            if (!$b) erp_fail('Batch not found.');
            $b = erp_attach_item_names($pdo, [$b])[0];
            $tbl = $b['item_type'] === 'product' ? 'stock_movements' : 'raw_material_movements';
            $b['allocations'] = erp_rows($pdo, "SELECT a.quantity, a.allocation_type, m.id AS movement_id, m.created_at, m.movement_type, m.reference_type, m.reference_number, m.reason
                                                FROM batch_allocations a JOIN {$tbl} m ON m.id = a.movement_id WHERE a.batch_id = ? AND a.item_type = ? ORDER BY m.id", [$b['id'], $b['item_type']]);
            $b['origin'] = null;
            if ($b['source_type'] === 'grn') $b['origin'] = erp_row($pdo, "SELECT g.id AS grn_id, g.grn_number, g.received_date, po.id AS po_id, po.po_number, s.id AS supplier_id, s.supplier_name,
                                                                          (SELECT pi.id FROM purchase_invoice_items pii JOIN purchase_invoices pi ON pi.id = pii.pinv_id WHERE pii.grn_item_id = ? AND pi.status = 'posted' LIMIT 1) AS pinv_id,
                                                                          (SELECT pii.landed_unit_cost FROM purchase_invoice_items pii JOIN purchase_invoices pi ON pi.id = pii.pinv_id WHERE pii.grn_item_id = ? AND pi.status = 'posted' LIMIT 1) AS landed_unit
                                                                   FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id WHERE g.id = ?", [$b['source_item_id'], $b['source_item_id'], $b['source_id']]);
            erp_out(['status' => 'success', 'record' => $b]);

        case 'settings':
            erp_out(['status' => 'success', 'default_warehouse' => wh_default($pdo), 'expiry_alert_days' => (int)erp_setting($pdo, 'erp_expiry_alert_days', 30),
                     'qc_required' => erp_setting($pdo, 'erp_qc_required', '0') === '1']);

        case 'settings_save':
            $wh = erp_warehouse_ok($pdo, (int)erp_input('default_warehouse'));
            $days = max(1, min(365, (int)erp_input('expiry_alert_days', 30)));
            erp_setting_set($pdo, 'erp_default_warehouse', (string)$wh, 'Default warehouse for stock with no warehouse');
            erp_setting_set($pdo, 'erp_expiry_alert_days', (string)$days, 'Alert when a batch expires within this many days');
            erp_setting_set($pdo, 'erp_qc_required', erp_input('qc_required') === '1' ? '1' : '0', 'Goods receipts need a completed quality check before posting');
            log_audit($pdo, 'update', 'settings', 'warehouse', null, ['default_warehouse' => $wh, 'expiry_alert_days' => $days, 'qc_required' => erp_input('qc_required')]);
            erp_out(['status' => 'success', 'message' => 'Inventory settings saved.']);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'warehouse');
}
