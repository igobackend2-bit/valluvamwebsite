<?php
// ============================================================================
// Central costing engine — WEIGHTED AVERAGE COST (added 30 Sep 2026)
//
// The single place where inventory value, COGS and cost of write-offs are
// calculated. It REPLAYS the existing stock ledger (stock_movements for
// product packs, raw_material_movements for bulk items) in date order:
//
//   * an inflow is valued at its recorded cost:
//       1. inventory_cost_entries 'receipt' row with the same reference number
//          (GRN posting, repack output), else
//       2. the purchase_rate on the existing Stock In line (stock_in_items), else
//       3. the current average cost (sales-return restock, found stock, etc.)
//   * value-only rows (landed cost from a purchase invoice, price differences)
//     are added to the stock value on their date; if the stock is already sold
//     they go straight to COGS
//   * every outflow is valued at the running average → COGS / waste / etc.
//   * if a movement's previous_stock doesn't match the running quantity (a
//     change that was never written to the ledger), the gap is booked as an
//     "unrecorded" movement at the average cost and reported as variance.
//
// Because it only reads the existing ledger, no existing sales/stock code
// had to change for COGS to work. Historical costs are never overwritten.
// ============================================================================

const CE_SALE_REFS = ['website_order', 'credit_sale', 'manual_sale', 'manual_sales', 'sales_order', 'delivery_challan', 'invoice'];

/** Classifies a ledger row into a costing bucket. */
function ce_bucket(string $itemType, float $delta, string $movementType, string $refType): string {
    $refType = strtolower($refType);
    if ($refType === 'stock_transfer') return $delta > 0 ? 'transfer_in' : 'transfer_out';   // between warehouses (1 Oct 2026)
    if ($itemType === 'raw_material') {
        if ($delta > 0) return in_array($movementType, ['purchase'], true) ? 'purchase' : 'adjust_in';
        if ($movementType === 'repack_consume') return 'repack_consume';
        if ($movementType === 'purchase_return') return 'purchase_return';
        if ($movementType === 'waste') return 'waste';
        return 'adjust_out';
    }
    if ($delta > 0) {
        if ($refType === 'grn' || $movementType === 'purchase') return 'purchase';
        if ($refType === 'repack') return 'repack_in';
        if ($refType === 'sales_return' || ($movementType === 'return' && $refType !== 'purchase_return')) return 'sales_return';
        if ($refType === 'stock_in' || $movementType === 'stock_in') return 'purchase';   // existing manual Stock In
        return 'adjust_in';
    }
    if ($refType === 'purchase_return') return 'purchase_return';
    if (in_array($refType, CE_SALE_REFS, true) || $movementType === 'sale') return 'sale';
    if (in_array($refType, ['waste', 'damage'], true) || in_array($movementType, ['waste', 'damage'], true)) return 'waste';
    return 'adjust_out';
}

/**
 * Runs the engine for one item type. Returns per-item results:
 *  [item_id => [
 *     'opening_qty','opening_value',                      (at start of $from)
 *     'in' => [bucket => [qty, value]], 'out' => [bucket => [qty, value]],
 *     'landed_to_cogs', 'landed_to_stock',                (value-only rows in period)
 *     'closing_qty','closing_value','avg_cost',          (at end of $to)
 *     'ledger_qty','unrecorded_qty','cost_missing' ]]
 * $from/$to: 'Y-m-d' or null (null $from = beginning of time, null $to = now).
 * $itemIds: optional list to limit the run.
 */
function ce_run(PDO $pdo, string $itemType, ?string $from = null, ?string $to = null, ?array $itemIds = null, ?array &$trail = null): array {
    // $trail (optional, added 1 Oct 2026): when passed, receives one row per valued event in the period
    // (movement id, date, bucket, direction, qty, value) for journals, channel COGS and the transaction trace.
    $wantTrail = func_num_args() >= 6;
    if ($wantTrail) $trail = [];
    $fromTs = $from ? $from . ' 00:00:00' : null;
    $toTs = $to ? $to . ' 23:59:59' : null;
    $idFilter = '';
    $params = [];
    if ($itemIds !== null) {
        $itemIds = array_values(array_filter(array_map('intval', $itemIds)));
        if (!$itemIds) return [];
        $idFilter = ' AND %s IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')';
        $params = $itemIds;
    }

    // ---- ledger rows
    if ($itemType === 'product') {
        $sql = "SELECT id, product_id AS item_id, movement_type, quantity, previous_stock, new_stock,
                       COALESCE(reference_type,'') AS reference_type, COALESCE(reference_number,'') AS reference_number, created_at
                FROM stock_movements WHERE 1=1" . ($idFilter ? sprintf($idFilter, 'product_id') : '') .
               ($toTs ? " AND created_at <= ?" : '') . " ORDER BY id";
        $current = "SELECT id, stock FROM product_details" . ($idFilter ? ' WHERE 1=1' . sprintf($idFilter, 'id') : '');
    } else {
        $sql = "SELECT id, raw_material_id AS item_id, movement_type, quantity, previous_stock, new_stock,
                       COALESCE(reference_type,'') AS reference_type, COALESCE(reference_number,'') AS reference_number, created_at
                FROM raw_material_movements WHERE 1=1" . ($idFilter ? sprintf($idFilter, 'raw_material_id') : '') .
               ($toTs ? " AND created_at <= ?" : '') . " ORDER BY id";
        $current = "SELECT id, stock FROM raw_materials" . ($idFilter ? ' WHERE 1=1' . sprintf($idFilter, 'id') : '');
    }
    $moves = erp_rows_ce($pdo, $sql, $toTs ? array_merge($params, [$toTs]) : $params);

    // ---- cost entries (receipts + value-only + opening)
    $ceSql = "SELECT item_id, entry_date, entry_type, quantity, value, COALESCE(reference_number,'') AS reference_number, COALESCE(reference_type,'') AS reference_type, after_movement_id
              FROM inventory_cost_entries WHERE status = 'active' AND item_type = ?" .
             ($idFilter ? sprintf($idFilter, 'item_id') : '') . ($toTs ? " AND entry_date <= ?" : '') . " ORDER BY entry_date, id";
    $ceParams = array_merge([$itemType], $params, $toTs ? [$toTs] : []);
    $receipts = []; $valueOnly = []; $opening = [];
    foreach (erp_rows_ce($pdo, $ceSql, $ceParams) as $c) {
        $iid = (int)$c['item_id'];
        if ($c['entry_type'] === 'receipt') {
            $k = $iid . '|' . $c['reference_number'];
            if (!isset($receipts[$k])) $receipts[$k] = [0.0, 0.0];
            $receipts[$k][0] += (float)$c['quantity']; $receipts[$k][1] += (float)$c['value'];
        } elseif ($c['entry_type'] === 'opening') {
            if ((float)$c['quantity'] > 0) $opening[$iid] = (float)$c['value'] / (float)$c['quantity']; // latest wins
        } else {
            $valueOnly[] = ['item_id' => $iid, 'date' => $c['entry_date'], 'value' => (float)$c['value'], 'after' => $c['after_movement_id'] === null ? null : (int)$c['after_movement_id'],
                             'ref_type' => $c['reference_type'], 'ref_no' => $c['reference_number']];
        }
    }

    // ---- existing Stock In purchase rates (manual Stock In with a rate typed in)
    $stockInRates = [];
    if ($itemType === 'product') {
        foreach (erp_rows_ce($pdo, "SELECT si.stock_in_number, sii.product_id, SUM(sii.quantity) q, SUM(sii.quantity * sii.purchase_rate) v
                                    FROM stock_in_items sii JOIN stock_ins si ON si.id = sii.stock_in_id
                                    WHERE sii.purchase_rate IS NOT NULL AND sii.purchase_rate > 0
                                    GROUP BY si.stock_in_number, sii.product_id") as $r) {
            if ((float)$r['q'] > 0) $stockInRates[(int)$r['product_id'] . '|' . $r['stock_in_number']] = (float)$r['v'] / (float)$r['q'];
        }
    }

    // ---- merge ledger rows (insertion order) + value-only rows into one timeline.
    // A value-only row is replayed right after the ledger row it recorded (after_movement_id);
    // rows without that (none expected) fall back to their timestamp.
    $byAfter = []; $before = [];
    foreach ($valueOnly as $v) {
        if ($v['after'] === null) {
            $v['after'] = 0;
            foreach ($moves as $m) { if ((int)$m['item_id'] === $v['item_id'] && $m['created_at'] <= $v['date']) $v['after'] = (int)$m['id']; }
        }
        if ($v['after'] === 0) $before[] = $v; else $byAfter[$v['after']][] = $v;
    }
    $events = [];
    foreach ($before as $v) $events[] = ['t' => $v['date'], 'k' => 1, 'v' => $v];
    foreach ($moves as $m) {
        $events[] = ['t' => $m['created_at'], 'k' => 0, 'm' => $m];
        foreach ($byAfter[(int)$m['id']] ?? [] as $v) $events[] = ['t' => max($v['date'], $m['created_at']), 'k' => 1, 'v' => $v];
    }

    $blank = function () {
        return ['opening_qty' => 0.0, 'opening_value' => 0.0, 'in' => [], 'out' => [], 'landed_to_cogs' => 0.0, 'landed_to_stock' => 0.0,
                'closing_qty' => 0.0, 'closing_value' => 0.0, 'avg_cost' => 0.0, 'unrecorded_qty' => 0.0, 'cost_missing' => false,
                'opening_estimated' => false, '_qty' => 0.0, '_val' => 0.0, '_avg' => 0.0, '_started' => false, '_snap' => false, '_pending_open' => 0.0];
    };
    $S = [];
    $add = function (array &$st, string $dir, string $bucket, float $qty, float $value, bool $inPeriod) {
        if (!$inPeriod) return;
        if (!isset($st[$dir][$bucket])) $st[$dir][$bucket] = [0.0, 0.0];
        $st[$dir][$bucket][0] += $qty; $st[$dir][$bucket][1] += $value;
    };
    $snapshot = function (array &$st) {
        if ($st['_snap']) return;
        $st['opening_qty'] = $st['_qty']; $st['opening_value'] = $st['_val']; $st['_snap'] = true;
    };

    foreach ($events as $e) {
        $iid = $e['k'] === 0 ? (int)$e['m']['item_id'] : (int)$e['v']['item_id'];
        if (!isset($S[$iid])) $S[$iid] = $blank();
        $st = &$S[$iid];
        $inPeriod = !$fromTs || $e['t'] >= $fromTs;
        if ($inPeriod) $snapshot($st);

        if ($e['k'] === 1) {                        // value-only (landed / price difference)
            $v = $e['v']['value'];
            if ($st['_qty'] > 0.0005) { $st['_val'] += $v; $st['_avg'] = $st['_val'] / $st['_qty']; if ($inPeriod) $st['landed_to_stock'] += $v; }
            else { if ($inPeriod) $st['landed_to_cogs'] += $v; }
            if ($wantTrail && $inPeriod) $trail[] = ['kind' => 'value', 'id' => null, 'item_id' => $iid, 'date' => $e['t'], 'bucket' => $st['_qty'] > 0.0005 ? 'landed_stock' : 'landed_cogs', 'qty' => 0.0, 'value' => round($v, 2), 'ref_type' => $e['v']['ref_type'] ?? '', 'ref_no' => $e['v']['ref_no'] ?? ''];
            unset($st); continue;
        }

        $m = $e['m'];
        $prev = (float)$m['previous_stock']; $new = (float)$m['new_stock'];
        $delta = $new - $prev;
        if (abs($delta) < 0.0005 && abs((float)$m['quantity']) > 0) $delta = 0.0;   // clamped at 0 → nothing moved

        // Opening stock that existed before the ledger started
        if (!$st['_started']) {
            $st['_started'] = true;
            if ($prev > 0) {
                $st['_qty'] = $prev;
                if (isset($opening[$iid])) { $st['_avg'] = $opening[$iid]; }
                else { $st['_pending_open'] = $prev; $st['opening_estimated'] = true; }
                $st['_val'] = $st['_qty'] * $st['_avg'];
                if (!$st['_snap'] && $inPeriod) { $st['opening_qty'] = $st['_qty']; $st['opening_value'] = $st['_val']; $st['_snap'] = true; }
                // stock that existed before the ledger started, first seen inside the period (journals need it)
                if ($wantTrail && $inPeriod && $st['_val'] > 0.005) $trail[] = ['kind' => 'open_est', 'id' => (int)$m['id'], 'item_id' => $iid, 'date' => $m['created_at'], 'bucket' => 'opening_estimate', 'qty' => round($prev, 3), 'value' => round($st['_val'], 2), 'ref_type' => '', 'ref_no' => '', 'in_period' => true, 'retro' => false];
            }
        } elseif (abs($prev - $st['_qty']) > 0.0005) {
            // A stock change happened that was never written to the ledger
            $gap = $prev - $st['_qty'];
            $gapVal = $gap * $st['_avg'];
            $st['_qty'] = $prev; $st['_val'] += $gapVal;
            if ($st['_qty'] <= 0.0005) { $st['_qty'] = max(0.0, $st['_qty']); $st['_val'] = 0.0; }
            if ($inPeriod) { $st['unrecorded_qty'] += $gap; $add($st, $gap > 0 ? 'in' : 'out', 'unrecorded', abs($gap), abs($gapVal), true); }
            if ($wantTrail && $inPeriod) $trail[] = ['kind' => 'gap', 'id' => (int)$m['id'], 'item_id' => $iid, 'date' => $m['created_at'], 'bucket' => 'unrecorded', 'qty' => round($gap, 3), 'value' => round($gapVal, 2), 'ref_type' => '', 'ref_no' => ''];
        }
        if (abs($delta) < 0.0005) { unset($st); continue; }

        $bucket = ce_bucket($itemType, $delta, $m['movement_type'], $m['reference_type']);
        if ($delta > 0) {
            $unit = null;
            $rk = $iid . '|' . $m['reference_number'];
            if (isset($receipts[$rk]) && $receipts[$rk][0] > 0) $unit = $receipts[$rk][1] / $receipts[$rk][0];
            elseif (isset($stockInRates[$rk])) $unit = $stockInRates[$rk];
            if ($unit === null) {
                $unit = $st['_avg'];
                if ($unit <= 0 && in_array($bucket, ['purchase', 'repack_in'], true)) $st['cost_missing'] = true;
            }
            // An opening balance with unknown cost takes the first known purchase cost
            if ($st['_pending_open'] > 0 && $unit > 0) {
                $st['_val'] += $st['_pending_open'] * $unit;
                $retro = false;
                if ($st['_snap'] && !$inPeriod) {} // snapshot not yet taken — fine
                elseif ($st['_snap'] && abs($st['opening_value']) < 0.005 && $st['opening_qty'] > 0) { $st['opening_value'] = $st['opening_qty'] * $unit; $retro = true; }
                if ($wantTrail) $trail[] = ['kind' => 'open_est', 'id' => (int)$m['id'], 'item_id' => $iid, 'date' => $m['created_at'], 'bucket' => 'opening_estimate', 'qty' => round($st['_pending_open'], 3), 'value' => round($st['_pending_open'] * $unit, 2), 'ref_type' => '', 'ref_no' => '', 'in_period' => $inPeriod, 'retro' => $retro];
                $st['_pending_open'] = 0.0;
            }
            $val = $delta * $unit;
            $st['_qty'] += $delta; $st['_val'] += $val;
            if ($st['_qty'] > 0.0005) $st['_avg'] = $st['_val'] / $st['_qty'];
            $add($st, 'in', $bucket, $delta, $val, $inPeriod);
            if ($wantTrail && $inPeriod) $trail[] = ['kind' => 'move', 'id' => (int)$m['id'], 'item_id' => $iid, 'date' => $m['created_at'], 'bucket' => $bucket, 'qty' => round($delta, 3), 'value' => round($val, 2), 'ref_type' => $m['reference_type'], 'ref_no' => $m['reference_number'], 'unit_cost' => round($unit, 4)];
        } else {
            $out = -$delta;
            $cost = $out * $st['_avg'];
            if ($out >= $st['_qty'] - 0.0005) { $cost = $st['_val']; }        // last units take any rounding remainder
            $st['_qty'] -= $out; $st['_val'] -= $cost;
            if ($st['_qty'] <= 0.0005) { $st['_qty'] = 0.0; $st['_val'] = 0.0; }
            $add($st, 'out', $bucket, $out, $cost, $inPeriod);
            if ($wantTrail && $inPeriod) $trail[] = ['kind' => 'move', 'id' => (int)$m['id'], 'item_id' => $iid, 'date' => $m['created_at'], 'bucket' => $bucket, 'qty' => round(-$out, 3), 'value' => round(-$cost, 2), 'ref_type' => $m['reference_type'], 'ref_no' => $m['reference_number'], 'unit_cost' => $out > 0 ? round($cost / $out, 4) : 0.0];
        }
        unset($st);
    }

    // ---- close off: items with no ledger rows still have a current stock
    $currentStock = [];
    foreach (erp_rows_ce($pdo, $current, $params) as $r) $currentStock[(int)$r['id']] = (float)$r['stock'];
    $result = [];
    foreach ($currentStock as $iid => $stockNow) {
        if (!isset($S[$iid])) {
            $S[$iid] = $blank();
            if ($stockNow > 0) {
                $S[$iid]['_qty'] = $stockNow;
                $S[$iid]['_avg'] = $opening[$iid] ?? 0.0;
                $S[$iid]['_val'] = $stockNow * $S[$iid]['_avg'];
                $S[$iid]['cost_missing'] = !isset($opening[$iid]);
                $S[$iid]['opening_estimated'] = !isset($opening[$iid]);
            }
        }
    }
    foreach ($S as $iid => $st) {
        if (!$st['_snap']) { $st['opening_qty'] = $st['_qty']; $st['opening_value'] = $st['_val']; }
        if ($st['_pending_open'] > 0) $st['cost_missing'] = true;
        $st['closing_qty'] = round($st['_qty'], 3);
        $st['closing_value'] = round($st['_val'], 2);
        $st['avg_cost'] = $st['_qty'] > 0.0005 ? round($st['_val'] / $st['_qty'], 4) : round($st['_avg'], 4);
        $st['opening_qty'] = round($st['opening_qty'], 3);
        $st['opening_value'] = round($st['opening_value'], 2);
        $st['ledger_qty'] = $st['closing_qty'];
        $st['current_stock'] = $currentStock[$iid] ?? null;
        foreach (['in', 'out'] as $d) foreach ($st[$d] as $b => $pair) $st[$d][$b] = [round($pair[0], 3), round($pair[1], 2)];
        $st['landed_to_cogs'] = round($st['landed_to_cogs'], 2);
        $st['landed_to_stock'] = round($st['landed_to_stock'], 2);
        foreach (['_qty', '_val', '_avg', '_started', '_snap', '_pending_open'] as $k) unset($st[$k]);
        $result[$iid] = $st;
    }
    return $result;
}

function erp_rows_ce(PDO $pdo, string $sql, array $params = []): array {
    $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
}

/** Sum a bucket across items: returns [qty, value]. */
function ce_sum(array $run, string $dir, string $bucket): array {
    $q = 0.0; $v = 0.0;
    foreach ($run as $r) if (isset($r[$dir][$bucket])) { $q += $r[$dir][$bucket][0]; $v += $r[$dir][$bucket][1]; }
    return [round($q, 3), round($v, 2)];
}

/** Current weighted-average unit cost of one item (used for returns, repacking, adjustments). */
function ce_current_avg(PDO $pdo, string $itemType, int $itemId): float {
    $r = ce_run($pdo, $itemType, null, null, [$itemId]);
    return isset($r[$itemId]) ? (float)$r[$itemId]['avg_cost'] : 0.0;
}
