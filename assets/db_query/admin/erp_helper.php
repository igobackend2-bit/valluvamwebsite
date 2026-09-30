<?php
// ============================================================================
// Shared helper for the Purchase / Costing / P&L modules (added 30 Sep 2026).
// One place for: auth + JSON responses, money/qty rounding, line calculations,
// item lookup (product packs / raw materials), stock posting through the
// EXISTING stock_ins / stock_in_items / stock_outs / stock_out_items /
// stock_movements tables, raw-material movements, accounts_transactions
// (existing cash book) and document numbers (existing document_sequences).
// Nothing in here changes existing modules.
// ============================================================================
ini_set('display_errors', 0);
error_reporting(E_ALL);
if (!headers_sent()) header('Content-Type: application/json');

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

// ---------------------------------------------------------------- responses
function erp_out(array $data) { echo json_encode($data); exit; }
function erp_fail(string $msg, int $http = 200) { if ($http !== 200) http_response_code($http); erp_out(['status' => 'error', 'message' => $msg]); }
function erp_user(): string { return $_SESSION['admin_username'] ?? 'Admin'; }

/** Admin session + permission (existing role system) + ERP tables present. */
function erp_guard(PDO $pdo, ?string $perm = null) {
    require_admin_session();
    if ($perm) require_permission($pdo, $perm);
    static $checked = false;
    if (!$checked) {
        try { $pdo->query("SELECT 1 FROM purchase_orders LIMIT 1"); }
        catch (PDOException $e) { erp_fail('The purchase module database tables are not installed yet. Run erp_purchase_migration.sql once in phpMyAdmin.'); }
        $checked = true;
    }
}

/** Logs the real error, returns a friendly message (no raw DB errors to users). */
function erp_db_error(Throwable $e, string $context) {
    global $pdo;
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if (!$e instanceof ErpValidation) error_log("[ERP {$context}] " . $e->getMessage());
    $msg = $e instanceof ErpValidation ? $e->getMessage() : "Could not complete the request ({$context}). Please try again.";
    erp_fail($msg);
}
class ErpValidation extends Exception {}
function erp_invalid(string $msg) { throw new ErpValidation($msg); }

function erp_input(string $key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
function erp_json_input(string $key): array {
    $raw = $_POST[$key] ?? '[]';
    $arr = is_array($raw) ? $raw : json_decode((string)$raw, true);
    return is_array($arr) ? $arr : [];
}
function erp_date($v, bool $required = false): ?string {
    $v = trim((string)$v);
    if ($v === '') { if ($required) erp_invalid('A date is required.'); return null; }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) erp_invalid("Invalid date: {$v}");
    return $v;
}

// ---------------------------------------------------------------- numbers
// All money is rounded to paise at every stored step; quantities to 3 decimals;
// unit costs to 4 decimals. Calculations use round() on each line so totals
// always equal the sum of the stored lines.
function erp_m($v): float { return round((float)$v + 0.0, 2); }
function erp_q($v): float { return round((float)$v + 0.0, 3); }
function erp_u($v): float { return round((float)$v + 0.0, 4); }
function erp_num($v, string $label, bool $allowZero = true, bool $allowNeg = false): float {
    $s = trim(str_replace(',', '', (string)$v));
    if ($s === '') $s = '0';
    if (!is_numeric($s)) erp_invalid("{$label} must be a number.");
    $n = (float)$s;
    if (!$allowNeg && $n < 0) erp_invalid("{$label} cannot be negative.");
    if (!$allowZero && $n == 0) erp_invalid("{$label} must be more than zero.");
    return $n;
}

/** Central line calculation: qty x rate - discount, + tax% on the net. */
function erp_line(float $qty, float $rate, float $discount, float $taxPercent): array {
    $gross = erp_m($qty * $rate);
    $discount = erp_m(min($discount, $gross));
    $net = erp_m($gross - $discount);
    $tax = erp_m($net * $taxPercent / 100);
    return ['gross' => $gross, 'discount' => $discount, 'net' => $net, 'tax' => $tax, 'total' => erp_m($net + $tax)];
}

/**
 * Whether GST paid on purchases is added to inventory cost. Default NO: a
 * GST-registered business claims it back as input tax credit. Change with
 * admin_settings key erp_tax_in_cost = 1 if purchase GST is not claimable.
 */
function erp_tax_in_cost(PDO $pdo): bool {
    static $v = null;
    if ($v === null) {
        try {
            $s = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'erp_tax_in_cost'");
            $s->execute(); $v = ((string)$s->fetchColumn()) === '1';
        } catch (PDOException $e) { $v = false; }
    }
    return $v;
}

// ---------------------------------------------------------------- items
function erp_item_type($t): string {
    $t = (string)$t;
    if (!in_array($t, ['product', 'raw_material'], true)) erp_invalid('Invalid item type.');
    return $t;
}

/** Returns ['name','unit','sku','stock'] for a product pack or a raw material, or null. */
function erp_item(PDO $pdo, string $type, int $id): ?array {
    if ($type === 'product') {
        $s = $pdo->prepare("SELECT id, product_name AS name, quantity AS pack, category, stock FROM product_details WHERE id = ?");
        $s->execute([$id]); $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        return ['id' => (int)$r['id'], 'name' => $r['name'] . ($r['pack'] && stripos($r['name'], (string)$r['pack']) === false ? ' (' . $r['pack'] . ')' : ''),
                'unit' => 'pcs', 'sku' => 'PRD-' . $r['id'], 'stock' => (float)$r['stock'], 'category' => $r['category'], 'pack' => $r['pack']];
    }
    $s = $pdo->prepare("SELECT id, name, unit, code, stock, category FROM raw_materials WHERE id = ?");
    $s->execute([$id]); $r = $s->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    return ['id' => (int)$r['id'], 'name' => $r['name'], 'unit' => $r['unit'], 'sku' => $r['code'], 'stock' => (float)$r['stock'], 'category' => $r['category'], 'pack' => null];
}

/** Adds item_name / sku / unit_label to rows having item_type + item_id. */
function erp_attach_item_names(PDO $pdo, array $rows): array {
    $cache = [];
    foreach ($rows as &$r) {
        $k = $r['item_type'] . ':' . $r['item_id'];
        if (!isset($cache[$k])) $cache[$k] = erp_item($pdo, $r['item_type'], (int)$r['item_id']);
        $r['item_name'] = $cache[$k]['name'] ?? ('#' . $r['item_id'] . ' (deleted)');
        $r['item_sku'] = $cache[$k]['sku'] ?? null;
        $r['item_category'] = $cache[$k]['category'] ?? null;
    }
    return $rows;
}

// ---------------------------------------------------------------- stock posting (products)
/**
 * Posts product packs INTO stock through the existing Stock In tables, exactly
 * like admin/complete_stock_in.php does (stock_ins status 'completed',
 * stock_in_items, product_details.stock, stock_movements). Must be called
 * inside a transaction. $lines: [product_id, qty(int), rate, batch, mfg, expiry].
 * Returns ['id' => stock_in id, 'number' => SIN-...].
 */
function erp_post_stock_in(PDO $pdo, array $hdr, array $lines): array {
    $number = next_document_number($pdo, 'stock_in', 'SIN');
    $user = erp_user();
    $pdo->prepare("INSERT INTO stock_ins (stock_in_number, stock_in_date, supplier_id, purchase_reference, warehouse_id,
                     received_by, vehicle_number, remarks, attachment_note, status, created_by)
                   VALUES (?,?,?,?,?,?,?,?,?, 'completed', ?)")
        ->execute([$number, $hdr['date'], $hdr['supplier_id'] ?? null, $hdr['reference'] ?? null, $hdr['warehouse_id'] ?? 1,
                   $hdr['received_by'] ?? null, $hdr['vehicle_number'] ?? null, $hdr['remarks'] ?? null, $hdr['attachment_note'] ?? null, $user]);
    $stockInId = (int)$pdo->lastInsertId();

    $itemIns = $pdo->prepare("INSERT INTO stock_in_items (stock_in_id, product_id, sku, quantity, unit, batch_number, manufacturing_date, expiry_date, purchase_rate)
                              VALUES (?,?,?,?, 'pcs', ?,?,?,?)");
    foreach ($lines as $l) {
        $qty = (int)$l['qty'];
        if ($qty <= 0) continue;
        $itemIns->execute([$stockInId, $l['product_id'], 'PRD-' . $l['product_id'], $qty, $l['batch'] ?? null, $l['mfg'] ?? null, $l['expiry'] ?? null,
                           isset($l['rate']) ? erp_u($l['rate']) : null]);
        erp_product_movement($pdo, (int)$l['product_id'], $qty, $hdr['movement_type'] ?? 'stock_in', $hdr['reference_type'] ?? 'stock_in', $number,
                             $hdr['reason'] ?? ('Stock in ' . $number), (int)($hdr['warehouse_id'] ?? 1));
    }
    return ['id' => $stockInId, 'number' => $number];
}

/**
 * Posts product packs OUT of stock through the existing Stock Out tables
 * (stock_outs / stock_out_items / stock_movements). Never lets stock go
 * negative: throws if not enough stock. Must be called inside a transaction.
 */
function erp_post_stock_out(PDO $pdo, array $hdr, array $lines): array {
    $number = next_document_number($pdo, 'stock_out', 'SOUT');
    $pdo->prepare("INSERT INTO stock_outs (stock_out_number, stock_out_date, reference_type, reference_number, warehouse_id,
                     customer_name, reason, authorized_by, created_by)
                   VALUES (?,?, 'other', ?,?,?,?,?,?)")
        ->execute([$number, $hdr['date'], $hdr['reference'] ?? null, $hdr['warehouse_id'] ?? 1, $hdr['party'] ?? null,
                   $hdr['reason'] ?? null, erp_user(), erp_user()]);
    $id = (int)$pdo->lastInsertId();
    $itemIns = $pdo->prepare("INSERT INTO stock_out_items (stock_out_id, product_id, sku, quantity, unit) VALUES (?,?,?,?, 'pcs')");
    foreach ($lines as $l) {
        $qty = (int)$l['qty'];
        if ($qty <= 0) continue;
        $itemIns->execute([$id, $l['product_id'], 'PRD-' . $l['product_id'], $qty]);
        erp_product_movement($pdo, (int)$l['product_id'], -$qty, $hdr['movement_type'] ?? 'stock_out', $hdr['reference_type'], $hdr['reference'] ?? $number,
                             $hdr['reason'] ?? ('Stock out ' . $number), (int)($hdr['warehouse_id'] ?? 1));
    }
    return ['id' => $id, 'number' => $number];
}

/** Locks the product, applies a signed change (never below 0 → error), writes stock_movements. */
function erp_product_movement(PDO $pdo, int $productId, int $delta, string $movementType, string $refType, string $refNo, string $reason, int $warehouseId = 1): array {
    $s = $pdo->prepare("SELECT stock, product_name FROM product_details WHERE id = ? FOR UPDATE");
    $s->execute([$productId]);
    $p = $s->fetch(PDO::FETCH_ASSOC);
    if (!$p) erp_invalid("Product #{$productId} no longer exists.");
    $prev = (int)$p['stock'];
    $new = $prev + $delta;
    if ($new < 0) erp_invalid("Not enough stock for {$p['product_name']}: available {$prev}, required " . abs($delta) . '.');
    $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?")->execute([$new, $productId]);
    $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id, quantity, previous_stock, new_stock,
                     reference_type, reference_number, reason, created_by, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())")
        ->execute([$movementType, $productId, 'PRD-' . $productId, $warehouseId, $delta, $prev, $new, $refType, $refNo, mb_substr($reason, 0, 255), erp_user()]);
    return ['previous' => $prev, 'new' => $new];
}

// ---------------------------------------------------------------- stock posting (raw materials)
function erp_raw_movement(PDO $pdo, int $rawId, float $delta, string $movementType, string $refType, string $refNo, string $reason, int $warehouseId = 1): array {
    $s = $pdo->prepare("SELECT stock, name, unit FROM raw_materials WHERE id = ? FOR UPDATE");
    $s->execute([$rawId]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if (!$r) erp_invalid("Raw material #{$rawId} no longer exists.");
    $prev = erp_q($r['stock']);
    $new = erp_q($prev + $delta);
    if ($new < -0.0005) erp_invalid("Not enough stock of {$r['name']}: available {$prev} {$r['unit']}, required " . abs($delta) . " {$r['unit']}.");
    if ($new < 0) $new = 0.0;
    $pdo->prepare("UPDATE raw_materials SET stock = ? WHERE id = ?")->execute([$new, $rawId]);
    $pdo->prepare("INSERT INTO raw_material_movements (raw_material_id, warehouse_id, movement_type, quantity, previous_stock, new_stock,
                     reference_type, reference_number, reason, created_by, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?, NOW())")
        ->execute([$rawId, $warehouseId, $movementType, erp_q($delta), $prev, $new, $refType, $refNo, mb_substr($reason, 0, 255), erp_user()]);
    return ['previous' => $prev, 'new' => $new];
}

/** Records the cost of an inflow (or a value-only adjustment) for the costing engine. */
function erp_cost_entry(PDO $pdo, string $itemType, int $itemId, string $entryType, float $qty, float $value, string $refType, string $refNo, ?int $sourceId = null, string $note = '', ?string $date = null) {
    // Value-only rows (landed cost / price difference) remember the last ledger row of this item at
    // posting time, so the costing engine replays them at exactly the right point (timestamps only
    // have one-second resolution).
    $after = null;
    if (in_array($entryType, ['landed', 'price'], true)) {
        $after = $itemType === 'product'
            ? erp_val($pdo, "SELECT MAX(id) FROM stock_movements WHERE product_id = ?", [$itemId])
            : erp_val($pdo, "SELECT MAX(id) FROM raw_material_movements WHERE raw_material_id = ?", [$itemId]);
        $after = $after ? (int)$after : 0;
    }
    $pdo->prepare("INSERT INTO inventory_cost_entries (item_type, item_id, entry_date, entry_type, quantity, value, reference_type, reference_number, source_id, after_movement_id, note, created_by)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$itemType, $itemId, $date ?: date('Y-m-d H:i:s'), $entryType, erp_q($qty), erp_m($value), $refType, $refNo, $sourceId, $after, mb_substr($note, 0, 255), erp_user()]);
}

// ---------------------------------------------------------------- existing cash book (Transactions)
/** Adds a row to the existing accounts_transactions (Accounts → Transactions). Returns its id. */
function erp_cash_entry(PDO $pdo, string $type, string $category, float $amount, string $date, string $mode, ?string $party, string $refType, string $refNo, string $desc, ?string $account = null): int {
    $validModes = ['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'];
    if (!in_array($mode, $validModes, true)) $mode = 'other';
    $txnId = next_document_number($pdo, 'accounts_txn', 'TXN');
    $pdo->prepare("INSERT INTO accounts_transactions (transaction_id, date, type, category, reference_type, reference_number, party_name,
                     amount, payment_mode, account, description, status, created_by)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?, 'completed', ?)")
        ->execute([$txnId, $date, $type, $category, $refType, $refNo, $party, erp_m($amount), $mode, $account, mb_substr($desc, 0, 255), erp_user()]);
    return (int)$pdo->lastInsertId();
}
function erp_cancel_cash_entry(PDO $pdo, ?int $id) {
    if ($id) $pdo->prepare("UPDATE accounts_transactions SET status = 'cancelled', updated_by = ? WHERE id = ? AND status <> 'cancelled'")->execute([erp_user(), $id]);
}

// ---------------------------------------------------------------- lookups
function erp_supplier_name(PDO $pdo, int $id): ?string {
    $s = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
    $s->execute([$id]);
    $n = $s->fetchColumn();
    return $n === false ? null : $n;
}
function erp_row(PDO $pdo, string $sql, array $params) {
    $s = $pdo->prepare($sql); $s->execute($params); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function erp_rows(PDO $pdo, string $sql, array $params = []): array {
    $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function erp_val(PDO $pdo, string $sql, array $params = []) {
    $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchColumn();
}

/** Parses a pack size ("500g", "1kg", "750ml", "1L") into [amount, base unit kg|L]. */
function erp_pack_size($text): ?array {
    if (!preg_match('/(\d+(?:\.\d+)?)\s*(kg|g|gm|ml|l)\b/i', (string)$text, $m)) return null;
    $n = (float)$m[1]; $u = strtolower($m[2]);
    if ($u === 'g' || $u === 'gm') return [$n / 1000, 'kg'];
    if ($u === 'kg') return [$n, 'kg'];
    if ($u === 'ml') return [$n / 1000, 'L'];
    return [$n, 'L'];
}
function erp_to_base_unit(float $qty, string $unit): array {
    switch ($unit) {
        case 'g': return [$qty / 1000, 'kg'];
        case 'ml': return [$qty / 1000, 'L'];
        default: return [$qty, $unit];
    }
}
