<?php
// ============================================================================
// Inventory operations API (added 30 Sep 2026)
//   Raw materials (bulk kg/L items) · Repacking (bulk → product packs)
//   Stock adjustments (request → approve → ledger) · Sales returns
//   Opening cost (one-time cost for stock that existed before costing)
// Every stock change goes through erp_product_movement / erp_raw_movement,
// i.e. the existing stock_movements ledger (products) or raw_material_movements.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/costing_engine.php';

$action = (string) erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$perms = [
    'rm_list' => 'purchase.view', 'rm_get' => 'purchase.view', 'rm_save' => 'raw_materials.manage',
    'repack_list' => 'purchase.view', 'repack_get' => 'purchase.view', 'repack_post' => 'raw_materials.manage',
    'adj_list' => 'inventory.view', 'adj_save' => 'stock_adjust.create', 'adj_approve' => 'stock_adjust.approve', 'adj_reject' => 'stock_adjust.approve',
    'sret_list' => 'invoices.view', 'sret_get' => 'invoices.view', 'sret_source' => 'invoices.view', 'sret_post' => 'sales_return.create',
    'opening_list' => 'pnl.view', 'opening_save' => 'pnl.view', 'items' => null,
];
if (!array_key_exists($action, $perms)) erp_fail('Unknown action.');
erp_guard($pdo, $perms[$action]);
$writeActions = ['rm_save', 'repack_post', 'adj_save', 'adj_approve', 'adj_reject', 'sret_post', 'opening_save'];
if (in_array($action, $writeActions, true) && !$isWrite) erp_fail('Invalid request method.');

try {
    switch ($action) {
        // ---------------------------------------------------------------- item picker (products + raw materials)
        case 'items':
            $q = trim((string)erp_input('q', ''));
            $type = (string)erp_input('type', '');
            $out = [];
            if ($type !== 'raw_material') {
                foreach (erp_rows($pdo, "SELECT id, product_name, quantity, category, stock FROM product_details" . ($q !== '' ? " WHERE product_name LIKE ? OR category LIKE ?" : '') .
                                        " ORDER BY product_name LIMIT 400", $q !== '' ? ["%$q%", "%$q%"] : []) as $r) {
                    $out[] = ['item_type' => 'product', 'item_id' => (int)$r['id'], 'label' => $r['product_name'] . ($r['quantity'] && stripos($r['product_name'], (string)$r['quantity']) === false ? ' (' . $r['quantity'] . ')' : ''),
                              'unit' => 'pcs', 'category' => $r['category'], 'stock' => (float)$r['stock'], 'pack' => $r['quantity']];
                }
            }
            if ($type !== 'product') {
                foreach (erp_rows($pdo, "SELECT id, name, code, unit, category, stock FROM raw_materials WHERE status = 'active'" . ($q !== '' ? " AND (name LIKE ? OR code LIKE ?)" : '') .
                                        " ORDER BY name LIMIT 400", $q !== '' ? ["%$q%", "%$q%"] : []) as $r) {
                    $out[] = ['item_type' => 'raw_material', 'item_id' => (int)$r['id'], 'label' => $r['name'] . ' [bulk ' . $r['unit'] . ']', 'unit' => $r['unit'],
                              'category' => $r['category'], 'stock' => (float)$r['stock'], 'pack' => null];
                }
            }
            erp_out(['status' => 'success', 'items' => $out]);

        // ---------------------------------------------------------------- raw materials
        case 'rm_list':
            $rows = erp_rows($pdo, "SELECT * FROM raw_materials ORDER BY status, name");
            $run = ce_run($pdo, 'raw_material');
            foreach ($rows as &$r) {
                $c = $run[(int)$r['id']] ?? null;
                $r['avg_cost'] = $c ? $c['avg_cost'] : 0;
                $r['stock_value'] = $c ? $c['closing_value'] : 0;
                $r['low'] = $r['reorder_level'] !== null && (float)$r['stock'] <= (float)$r['reorder_level'];
            }
            unset($r);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'rm_get':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM raw_materials WHERE id = ?", [$id]);
            if (!$r) erp_fail('Raw material not found.');
            $r['movements'] = erp_rows($pdo, "SELECT * FROM raw_material_movements WHERE raw_material_id = ? ORDER BY id DESC LIMIT 300", [$id]);
            $r['batches'] = erp_rows($pdo, "SELECT b.*, s.supplier_name FROM inventory_batches b LEFT JOIN suppliers s ON s.id = b.supplier_id WHERE b.item_type = 'raw_material' AND b.item_id = ? ORDER BY b.id DESC", [$id]);
            $c = ce_run($pdo, 'raw_material', null, null, [$id]);
            $r['avg_cost'] = $c[$id]['avg_cost'] ?? 0;
            erp_out(['status' => 'success', 'record' => $r]);

        case 'rm_save':
            $id = (int)erp_input('id', 0);
            $name = trim((string)erp_input('name', ''));
            if ($name === '') erp_invalid('Enter the raw material name.');
            $unit = (string)erp_input('unit', 'kg');
            if (!in_array($unit, ['kg', 'g', 'L', 'ml', 'pcs'], true)) erp_invalid('Choose a unit.');
            $reorder = trim((string)erp_input('reorder_level', '')) === '' ? null : erp_q(erp_num(erp_input('reorder_level'), 'Reorder level'));
            $status = erp_input('status') === 'inactive' ? 'inactive' : 'active';
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM raw_materials WHERE id = ?", [$id]);
                if (!$old) erp_invalid('Raw material not found.');
                if ($old['unit'] !== $unit && (float)$old['stock'] != 0) erp_invalid('The unit cannot be changed while there is stock.');
                $pdo->prepare("UPDATE raw_materials SET name=?, category=?, unit=?, reorder_level=?, status=?, notes=? WHERE id=?")
                    ->execute([$name, erp_input('category') ?: null, $unit, $reorder, $status, erp_input('notes') ?: null, $id]);
                log_audit($pdo, 'update', 'raw_materials', $id, $old, $_POST);
            } else {
                $pdo->beginTransaction();
                $code = next_document_number($pdo, 'raw_material', 'RM');
                $pdo->prepare("INSERT INTO raw_materials (code, name, category, unit, reorder_level, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$code, $name, erp_input('category') ?: null, $unit, $reorder, $status, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
                $pdo->commit();
                log_audit($pdo, 'create', 'raw_materials', $id, null, $_POST + ['code' => $code]);
            }
            erp_out(['status' => 'success', 'id' => $id, 'message' => 'Raw material saved. Stock is added only through a goods receipt or an approved adjustment.']);

        // ---------------------------------------------------------------- repacking
        case 'repack_list':
            $rows = erp_rows($pdo, "SELECT j.*, r.name AS raw_name, r.unit AS raw_unit, si.stock_in_number,
                                    (SELECT COALESCE(SUM(packs),0) FROM repack_job_outputs o WHERE o.repack_id = j.id) AS packs
                                    FROM repack_jobs j JOIN raw_materials r ON r.id = j.raw_material_id LEFT JOIN stock_ins si ON si.id = j.stock_in_id ORDER BY j.id DESC LIMIT 300");
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'repack_get':
            $id = (int)erp_input('id');
            $j = erp_row($pdo, "SELECT j.*, r.name AS raw_name, r.unit AS raw_unit, si.stock_in_number FROM repack_jobs j JOIN raw_materials r ON r.id = j.raw_material_id LEFT JOIN stock_ins si ON si.id = j.stock_in_id WHERE j.id = ?", [$id]);
            if (!$j) erp_fail('Repack job not found.');
            $j['outputs'] = erp_rows($pdo, "SELECT o.*, p.product_name, p.quantity AS pack FROM repack_job_outputs o LEFT JOIN product_details p ON p.id = o.product_id WHERE o.repack_id = ?", [$id]);
            erp_out(['status' => 'success', 'record' => $j]);

        case 'repack_post':
            $rawId = (int)erp_input('raw_material_id');
            $raw = erp_row($pdo, "SELECT * FROM raw_materials WHERE id = ?", [$rawId]);
            if (!$raw) erp_invalid('Choose the bulk raw material.');
            $date = erp_date(erp_input('repack_date'), true);
            $consumed = erp_q(erp_num(erp_input('consumed_qty'), 'Consumed quantity', false));
            if ($consumed > (float)$raw['stock'] + 0.0005) erp_invalid("Only {$raw['stock']} {$raw['unit']} of {$raw['name']} is in stock.");
            $packing = erp_m(erp_num(erp_input('packing_cost', 0), 'Packing cost'));
            [$rawBaseFactor, $rawBase] = erp_to_base_unit(1, $raw['unit']);   // e.g. g → 0.001 kg
            $outputs = []; $outBase = 0;
            foreach (erp_json_input('outputs') as $o) {
                $pid = (int)($o['product_id'] ?? 0);
                $packs = (int)erp_num($o['packs'] ?? 0, 'Packs');
                if (!$pid || $packs <= 0) continue;
                $p = erp_row($pdo, "SELECT id, product_name, quantity FROM product_details WHERE id = ?", [$pid]);
                if (!$p) erp_invalid('A product in the output list no longer exists.');
                $size = erp_pack_size($p['quantity']);
                if ($raw['unit'] === 'pcs') { $perPackBase = 1; }
                else {
                    if (!$size) erp_invalid("{$p['product_name']}: its Quantity (\"{$p['quantity']}\") has no weight/volume, so it can't be repacked from {$raw['unit']}.");
                    if ($size[1] !== $rawBase) erp_invalid("{$p['product_name']} is measured in {$size[1]} but {$raw['name']} is in {$raw['unit']}.");
                    $perPackBase = $size[0];
                }
                $outBase += $perPackBase * $packs;
                $outputs[] = ['product_id' => $pid, 'packs' => $packs, 'per_pack_base' => $perPackBase, 'name' => $p['product_name'],
                              'batch' => trim((string)($o['batch_number'] ?? '')) ?: null, 'expiry' => erp_date($o['expiry_date'] ?? '')];
            }
            if (!$outputs) erp_invalid('Add at least one output product with the number of packs.');
            $consumedBase = $consumed * $rawBaseFactor;
            if ($raw['unit'] !== 'pcs' && $outBase > $consumedBase + 0.0005) erp_invalid('The packs contain ' . erp_q($outBase / $rawBaseFactor) . " {$raw['unit']}, more than the {$consumed} {$raw['unit']} consumed.");

            $avg = ce_current_avg($pdo, 'raw_material', $rawId);
            $materialCost = erp_m($consumed * $avg);
            $totalCost = $materialCost + $packing;
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'repack', 'RPK');
            erp_raw_movement($pdo, $rawId, -$consumed, 'repack_consume', 'repack', $num, "Repacked into packs ({$num})", (int)erp_input('warehouse_id', 1) ?: 1);
            $pdo->prepare("INSERT INTO repack_jobs (repack_number, repack_date, raw_material_id, warehouse_id, consumed_qty, material_cost, packing_cost, output_qty_total, process_loss_qty, notes, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num, $date, $rawId, (int)erp_input('warehouse_id', 1) ?: 1, $consumed, $materialCost, $packing, erp_q($outBase / $rawBaseFactor),
                           erp_q(max(0, $consumed - $outBase / $rawBaseFactor)), erp_input('notes') ?: null, erp_user()]);
            $jobId = (int)$pdo->lastInsertId();
            $lines = []; $allocated = 0.0;
            foreach ($outputs as $i => $o) {
                $share = $i === count($outputs) - 1 ? erp_m($totalCost - $allocated) : erp_m($outBase > 0 ? $totalCost * ($o['per_pack_base'] * $o['packs']) / $outBase : $totalCost / count($outputs));
                $allocated += $share;
                $unitCost = erp_u($share / $o['packs']);
                $pdo->prepare("INSERT INTO repack_job_outputs (repack_id, product_id, packs, pack_size_qty, unit_cost, batch_number, expiry_date) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$jobId, $o['product_id'], $o['packs'], erp_q($o['per_pack_base'] / $rawBaseFactor), $unitCost, $o['batch'], $o['expiry']]);
                $lines[] = ['product_id' => $o['product_id'], 'qty' => $o['packs'], 'rate' => $unitCost, 'batch' => $o['batch'], 'expiry' => $o['expiry'], 'value' => $share];
            }
            $si = erp_post_stock_in($pdo, ['date' => $date, 'reference' => $num, 'warehouse_id' => (int)erp_input('warehouse_id', 1) ?: 1, 'remarks' => "Repacked from {$raw['name']} ({$num})",
                                           'reference_type' => 'repack', 'movement_type' => 'stock_in', 'reason' => "Repack {$num}"], $lines);
            foreach ($lines as $l) {
                erp_cost_entry($pdo, 'product', $l['product_id'], 'receipt', $l['qty'], $l['value'], 'repack', $si['number'], $jobId, "Repack {$num}");
                $pdo->prepare("INSERT INTO inventory_batches (item_type, item_id, warehouse_id, batch_number, expiry_date, source_type, source_id, received_date, qty_received, unit_cost)
                               VALUES ('product', ?,?,?,?, 'repack', ?,?,?,?)")
                    ->execute([$l['product_id'], (int)erp_input('warehouse_id', 1) ?: 1, $l['batch'], $l['expiry'], $jobId, $date, $l['qty'], $l['rate']]);
            }
            $pdo->prepare("UPDATE repack_jobs SET stock_in_id = ? WHERE id = ?")->execute([$si['id'], $jobId]);
            $pdo->commit();
            log_audit($pdo, 'create', 'repack_jobs', $jobId, null, ['repack_number' => $num, 'raw' => $raw['name'], 'consumed' => $consumed, 'material_cost' => $materialCost, 'packing_cost' => $packing, 'outputs' => $lines]);
            erp_out(['status' => 'success', 'id' => $jobId, 'message' => "{$num} posted: {$consumed} {$raw['unit']} consumed, " . array_sum(array_column($lines, 'qty')) . " packs added ({$si['number']})." .
                                                                      ($avg <= 0 ? ' Warning: this raw material has no purchase cost yet, so the packs were costed at packing cost only.' : '')]);

        // ---------------------------------------------------------------- stock adjustments
        case 'adj_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'a.status = ?'; $p[] = $s; }
            $rows = erp_rows($pdo, "SELECT a.*, w.name AS warehouse_name FROM stock_adjustments a LEFT JOIN warehouses w ON w.id = a.warehouse_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY a.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => erp_attach_item_names($pdo, $rows)]);

        case 'adj_save':
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            $item = erp_item($pdo, $type, $iid);
            if (!$item) erp_invalid('Choose the item to adjust.');
            $adjType = (string)erp_input('adjustment_type');
            if (!in_array($adjType, ['increase', 'decrease', 'physical_count', 'damage', 'missing', 'found'], true)) erp_invalid('Choose the adjustment type.');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('A reason is required for every stock adjustment.');
            $system = $item['stock'];
            $counted = null;
            if ($adjType === 'physical_count') {
                $counted = erp_q(erp_num(erp_input('counted_qty'), 'Counted quantity'));
                $delta = erp_q($counted - $system);
                if (abs($delta) < 0.0005) erp_invalid('The counted quantity equals the system stock — nothing to adjust.');
            } else {
                $qty = erp_q(erp_num(erp_input('quantity'), 'Quantity', false));
                $delta = in_array($adjType, ['increase', 'found'], true) ? $qty : -$qty;
            }
            if ($type === 'product' && floor(abs($delta)) != abs($delta)) erp_invalid('Product packs must be whole numbers.');
            if ($system + $delta < -0.0005) erp_invalid("This would make stock negative (current {$system}).");
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'stock_adjustment', 'ADJ');
            $pdo->prepare("INSERT INTO stock_adjustments (adj_number, adj_date, adjustment_type, item_type, item_id, warehouse_id, batch_id, quantity, system_qty, counted_qty, reason, status, requested_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending', ?)")
                ->execute([$num, erp_date(erp_input('adj_date'), true), $adjType, $type, $iid, (int)erp_input('warehouse_id', 1) ?: 1, (int)erp_input('batch_id', 0) ?: null,
                           $delta, $system, $counted, $reason, erp_user()]);
            $aid = (int)$pdo->lastInsertId();
            $pdo->commit();
            log_audit($pdo, 'create', 'stock_adjustments', $aid, null, ['adj_number' => $num, 'item' => $item['name'], 'change' => $delta, 'reason' => $reason]);
            erp_out(['status' => 'success', 'id' => $aid, 'message' => "{$num} requested ({$delta} {$item['unit']}). Stock changes after approval."]);

        case 'adj_approve':
        case 'adj_reject':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $a = erp_row($pdo, "SELECT * FROM stock_adjustments WHERE id = ? FOR UPDATE", [$id]);
            if (!$a) erp_invalid('Adjustment not found.');
            if ($a['status'] !== 'pending') erp_invalid("This adjustment is already {$a['status']}.");
            if ($action === 'adj_reject') {
                $pdo->prepare("UPDATE stock_adjustments SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
                $pdo->commit();
                log_audit($pdo, 'reject', 'stock_adjustments', $id, ['status' => 'pending'], ['status' => 'rejected']);
                erp_out(['status' => 'success', 'message' => "{$a['adj_number']} rejected."]);
            }
            $delta = (float)$a['quantity'];
            if ($a['adjustment_type'] === 'physical_count') {   // re-base on the stock at approval time
                $item = erp_item($pdo, $a['item_type'], (int)$a['item_id']);
                $delta = erp_q((float)$a['counted_qty'] - $item['stock']);
            }
            $mtype = in_array($a['adjustment_type'], ['damage'], true) ? 'damage' : 'adjustment';
            if (abs($delta) >= 0.0005) {
                if ($a['item_type'] === 'product') erp_product_movement($pdo, (int)$a['item_id'], (int)round($delta), $mtype, 'stock_adjustment', $a['adj_number'], "Adjustment ({$a['adjustment_type']}): {$a['reason']}", (int)$a['warehouse_id']);
                else erp_raw_movement($pdo, (int)$a['item_id'], $delta, $a['adjustment_type'] === 'damage' ? 'waste' : 'adjustment', 'stock_adjustment', $a['adj_number'], "Adjustment ({$a['adjustment_type']}): {$a['reason']}", (int)$a['warehouse_id']);
            }
            $pdo->prepare("UPDATE stock_adjustments SET status = 'approved', quantity = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$delta, erp_user(), $id]);
            $pdo->commit();
            log_audit($pdo, 'approve', 'stock_adjustments', $id, ['status' => 'pending'], ['status' => 'approved', 'applied_change' => $delta]);
            erp_out(['status' => 'success', 'message' => "{$a['adj_number']} approved and applied ({$delta})."]);

        // ---------------------------------------------------------------- sales returns
        case 'sret_list':
            $w = []; $p = [];
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'return_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'return_date <= ?'; $p[] = $d; }
            $rows = erp_rows($pdo, "SELECT r.*, si.stock_in_number FROM sales_returns r LEFT JOIN stock_ins si ON si.id = r.stock_in_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY r.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'sret_get':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM sales_returns WHERE id = ?", [$id]);
            if (!$r) erp_fail('Sales return not found.');
            $r['items'] = erp_rows($pdo, "SELECT i.*, p.product_name FROM sales_return_items i LEFT JOIN product_details p ON p.id = i.product_id WHERE i.return_id = ?", [$id]);
            erp_out(['status' => 'success', 'record' => $r]);

        case 'sret_source':
            $src = sret_load_source($pdo, (string)erp_input('source_type'), trim((string)erp_input('source_ref', '')));
            erp_out(['status' => 'success'] + $src);

        case 'sret_post':
            $src = sret_load_source($pdo, (string)erp_input('source_type'), trim((string)erp_input('source_ref', '')));
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a return reason.');
            $date = erp_date(erp_input('return_date'), true);
            $settlement = in_array(erp_input('settlement'), ['refund', 'replacement', 'credit_note'], true) ? erp_input('settlement') : 'refund';
            $byProduct = [];
            foreach ($src['lines'] as $l) $byProduct[(int)$l['product_id']] = $l;
            $lines = []; $total = 0;
            foreach (erp_json_input('items') as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                if (!isset($byProduct[$pid])) continue;
                $sl = $byProduct[$pid];
                $restock = erp_q(erp_num($it['restock_qty'] ?? 0, 'Restock quantity'));
                $damaged = erp_q(erp_num($it['damaged_qty'] ?? 0, 'Damaged quantity'));
                $qty = erp_q($restock + $damaged);
                if ($qty <= 0) continue;
                if (floor($restock) != $restock || floor($damaged) != $damaged) erp_invalid("{$sl['product_name']}: product packs must be whole numbers.");
                if ($qty > $sl['returnable_qty'] + 0.0005) erp_invalid("{$sl['product_name']}: at most {$sl['returnable_qty']} can still be returned.");
                $val = erp_m($qty * $sl['net_rate']);
                $total += $val;
                $cond = $damaged > 0 ? ($restock > 0 ? 'mixed' : (erp_input('damage_kind') === 'expired' ? 'expired' : 'damaged')) : 'good';
                $lines[] = ['product_id' => $pid, 'qty' => $qty, 'restock' => $restock, 'damaged' => $damaged, 'cond' => $cond, 'rate' => $sl['net_rate'], 'value' => $val];
            }
            if (!$lines) erp_invalid('Enter the returned quantity for at least one product.');
            // refund_amount = money paid back (refund) or credit given against the customer's balance (credit_note)
            $refund = $settlement === 'replacement' ? 0.0 : erp_m(erp_num(erp_input('refund_amount', $total), $settlement === 'refund' ? 'Refund amount' : 'Credit note amount'));
            if ($refund > $src['max_refund'] + 0.005) erp_invalid('Refund / credit cannot be more than the amount billed (₹' . number_format($src['max_refund'], 2) . ').');
            $mode = (string)erp_input('refund_mode', 'cash');

            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'sales_return', 'SRET');
            $pdo->prepare("INSERT INTO sales_returns (return_number, return_date, source_type, source_id, source_number, customer_name, customer_mobile, warehouse_id, reason, settlement,
                             refund_mode, total_value, refund_amount, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num, $date, $src['source_type'], $src['source_id'], $src['source_number'], $src['customer_name'], $src['customer_mobile'], $src['warehouse_id'],
                           $reason, $settlement, $refund > 0 ? $mode : null, erp_m($total), $refund, erp_input('notes') ?: null, erp_user()]);
            $rid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sales_return_items (return_id, product_id, quantity, restock_qty, damaged_qty, stock_condition, rate, line_value) VALUES (?,?,?,?,?,?,?,?)");
            $restockLines = [];
            foreach ($lines as $l) {
                $ins->execute([$rid, $l['product_id'], $l['qty'], $l['restock'], $l['damaged'], $l['cond'], $l['rate'], $l['value']]);
                if ($l['restock'] > 0) $restockLines[] = ['product_id' => $l['product_id'], 'qty' => (int)$l['restock'], 'rate' => null];
            }
            $stockMsg = 'No stock added (all damaged).';
            if ($restockLines) {   // good stock back to sellable inventory at the current average cost
                $si = erp_post_stock_in($pdo, ['date' => $date, 'reference' => $num, 'warehouse_id' => $src['warehouse_id'], 'remarks' => "Sales return {$num} ({$src['source_number']})",
                                               'reference_type' => 'sales_return', 'movement_type' => 'return', 'reason' => "Sales return {$num}"], $restockLines);
                $pdo->prepare("UPDATE sales_returns SET stock_in_id = ? WHERE id = ?")->execute([$si['id'], $rid]);
                $stockMsg = array_sum(array_column($restockLines, 'qty')) . " packs back in stock ({$si['number']}).";
            }
            if ($refund > 0 && $settlement === 'refund') erp_cash_entry($pdo, 'refund', 'Sales Return Refund', $refund, $date, $mode, $src['customer_name'], 'sales_return', $num, "Refund for {$src['source_number']}");
            $pdo->commit();
            log_audit($pdo, 'create', 'sales_returns', $rid, null, ['return_number' => $num, 'source' => $src['source_number'], 'value' => $total, 'refund' => $refund, 'lines' => $lines]);
            erp_out(['status' => 'success', 'id' => $rid, 'message' => "{$num} posted. {$stockMsg}" . ($refund > 0 ? ($settlement === 'refund' ? ' Refund ₹' . number_format($refund, 2) . ' recorded in Transactions.' : ' Credit note ₹' . number_format($refund, 2) . ' reduces the customer balance.') : '')]);

        // ---------------------------------------------------------------- opening cost
        case 'opening_list':
            $type = erp_item_type(erp_input('item_type', 'product'));
            $run = ce_run($pdo, $type);
            $rows = [];
            $src = $type === 'product' ? erp_rows($pdo, "SELECT id, product_name AS name, quantity AS pack, category, stock FROM product_details ORDER BY product_name")
                                       : erp_rows($pdo, "SELECT id, name, unit AS pack, category, stock FROM raw_materials ORDER BY name");
            $openings = [];
            foreach (erp_rows($pdo, "SELECT item_id, value, quantity, entry_date FROM inventory_cost_entries WHERE item_type = ? AND entry_type = 'opening' AND status = 'active' ORDER BY entry_date", [$type]) as $o)
                $openings[(int)$o['item_id']] = ['unit' => (float)$o['quantity'] > 0 ? round($o['value'] / $o['quantity'], 4) : 0, 'date' => $o['entry_date']];
            foreach ($src as $r) {
                $c = $run[(int)$r['id']] ?? null;
                $rows[] = $r + ['avg_cost' => $c['avg_cost'] ?? 0, 'cost_missing' => $c['cost_missing'] ?? ((float)$r['stock'] > 0), 'opening_unit_cost' => $openings[(int)$r['id']]['unit'] ?? null];
            }
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'opening_save':
            $type = erp_item_type(erp_input('item_type', 'product'));
            $iid = (int)erp_input('item_id');
            $item = erp_item($pdo, $type, $iid);
            if (!$item) erp_invalid('Item not found.');
            $unit = erp_u(erp_num(erp_input('unit_cost'), 'Unit cost', false));
            erp_cost_entry($pdo, $type, $iid, 'opening', 1, $unit, 'opening', 'OPENING', null, 'Opening cost set by ' . erp_user(), '1970-01-01 00:00:00');
            log_audit($pdo, 'update', 'opening_cost', $iid, null, ['item_type' => $type, 'item' => $item['name'], 'unit_cost' => $unit]);
            erp_out(['status' => 'success', 'message' => "Opening cost for {$item['name']} set to ₹{$unit}."]);
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'inventory');
}

/**
 * Loads a sale document for a sales return: header + product lines with
 * sold qty, already-returned qty and the net (ex-tax) unit rate.
 */
function sret_load_source(PDO $pdo, string $type, string $ref): array {
    if ($ref === '') erp_invalid('Enter the invoice / sale / order number.');
    switch ($type) {
        case 'invoice':
            $h = erp_row($pdo, "SELECT id, invoice_number AS num, customer_name, customer_mobile, grand_total, status, 1 AS warehouse_id FROM invoices WHERE invoice_number = ? OR id = ?", [$ref, ctype_digit($ref) ? (int)$ref : 0]);
            if ($h && in_array($h['status'], ['draft', 'cancelled'], true)) erp_invalid("Invoice {$h['num']} is {$h['status']}.");
            $lines = $h ? erp_rows($pdo, "SELECT product_id, SUM(quantity) q, SUM(rate*quantity - discount) net FROM invoice_items WHERE invoice_id = ? GROUP BY product_id", [$h['id']]) : [];
            break;
        case 'manual_sale':
            $h = erp_row($pdo, "SELECT id, sale_number AS num, customer_name, customer_mobile, grand_total, warehouse_id FROM manual_sales WHERE sale_number = ? OR id = ?", [$ref, ctype_digit($ref) ? (int)$ref : 0]);
            $lines = $h ? erp_rows($pdo, "SELECT product_id, SUM(quantity) q, SUM(rate*quantity - discount) net FROM manual_sale_items WHERE manual_sale_id = ? GROUP BY product_id", [$h['id']]) : [];
            break;
        case 'credit_sale':
            $h = erp_row($pdo, "SELECT id, credit_number AS num, customer_name, customer_mobile, grand_total, warehouse_id FROM credit_sales WHERE credit_number = ? OR id = ?", [$ref, ctype_digit($ref) ? (int)$ref : 0]);
            $lines = $h ? erp_rows($pdo, "SELECT product_id, SUM(quantity) q, SUM(rate*quantity - discount) net FROM credit_sale_items WHERE credit_sale_id = ? GROUP BY product_id", [$h['id']]) : [];
            break;
        case 'website_order':
            $h = erp_row($pdo, "SELECT id, receipt AS num, CONCAT(first_name,' ',last_name) AS customer_name, phone AS customer_mobile, amount AS grand_total, 1 AS warehouse_id FROM orders WHERE receipt = ? OR id = ?", [$ref, ctype_digit($ref) ? (int)$ref : 0]);
            $lines = $h ? erp_rows($pdo, "SELECT product_id, SUM(quantity) q, SUM(price*quantity) net FROM order_items WHERE order_id = ? GROUP BY product_id", [$h['id']]) : [];
            break;
        default:
            erp_invalid('Choose what is being returned (invoice, manual sale, credit sale or website order).');
    }
    if (!$h) erp_invalid('No matching document found for "' . $ref . '".');
    $out = [];
    foreach ($lines as $l) {
        $returned = (float)erp_val($pdo, "SELECT COALESCE(SUM(i.quantity),0) FROM sales_return_items i JOIN sales_returns r ON r.id = i.return_id
                                          WHERE r.source_type = ? AND r.source_id = ? AND r.status = 'posted' AND i.product_id = ?", [$type, $h['id'], $l['product_id']]);
        $name = erp_val($pdo, "SELECT product_name FROM product_details WHERE id = ?", [$l['product_id']]) ?: ('Product #' . $l['product_id']);
        $out[] = ['product_id' => (int)$l['product_id'], 'product_name' => $name, 'sold_qty' => erp_q($l['q']), 'returned_qty' => erp_q($returned),
                  'returnable_qty' => erp_q(max(0, $l['q'] - $returned)), 'net_rate' => $l['q'] > 0 ? erp_u($l['net'] / $l['q']) : 0];
    }
    $prevRefunds = (float)erp_val($pdo, "SELECT COALESCE(SUM(refund_amount),0) FROM sales_returns WHERE source_type = ? AND source_id = ? AND status = 'posted'", [$type, $h['id']]);
    return ['source_type' => $type, 'source_id' => (int)$h['id'], 'source_number' => $h['num'], 'customer_name' => $h['customer_name'], 'customer_mobile' => $h['customer_mobile'],
            'warehouse_id' => (int)($h['warehouse_id'] ?? 1), 'grand_total' => (float)$h['grand_total'], 'max_refund' => erp_m(max(0, $h['grand_total'] - $prevRefunds)), 'lines' => $out];
}
