<?php
// ============================================================================
// Purchase flow helpers (added 1 Oct 2026)
//   - unit conversion for purchase requests: the requester may type g / kg /
//     ml / L; the request line keeps packs (stock is counted in packs)
//   - step status of the guided purchase flow
// Needs erp_helper.php. Safe to include more than once.
// ============================================================================
if (!function_exists('pf_pack')) {

/** "500g" → [500,'g'], "1 kg" → [1000,'g'], "1L" / "1 Ltr" → [1000,'ml'], "200ml" → [200,'ml']. Combos ("1L + 1L") → null. */
function pf_pack($pack): ?array {
    $s = strtolower(trim((string)$pack));
    if ($s === '' || strpos($s, '+') !== false) return null;
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*(kg|kgs|kilo|kilogram|kilograms|g|gm|gms|gram|grams|ml|mls|l|lt|ltr|ltrs|litre|litres|liter|liters)\.?$/', $s, $m)) return null;
    $n = (float)$m[1];
    if ($n <= 0) return null;
    $u = $m[2];
    if (in_array($u, ['kg', 'kgs', 'kilo', 'kilogram', 'kilograms'], true)) return [$n * 1000, 'g'];
    if (in_array($u, ['g', 'gm', 'gms', 'gram', 'grams'], true)) return [$n, 'g'];
    if (in_array($u, ['ml', 'mls'], true)) return [$n, 'ml'];
    return [$n * 1000, 'ml'];
}
/** Base family and factor of a unit: g / kg → g, ml / L → ml. */
function pf_unit_base(string $u): ?array {
    $u = strtolower(trim($u));
    $map = ['g' => ['g', 1], 'gm' => ['g', 1], 'kg' => ['g', 1000], 'ml' => ['ml', 1], 'l' => ['ml', 1000], 'ltr' => ['ml', 1000], 'litre' => ['ml', 1000]];
    return $map[$u] ?? null;
}
/** Units a request line may use for this item. */
function pf_units_for(string $type, array $item): array {
    if ($type === 'product') {
        $p = pf_pack($item['pack'] ?? null);
        if (!$p) return ['pcs'];
        return $p[1] === 'g' ? ['pcs', 'g', 'kg'] : ['pcs', 'ml', 'L'];
    }
    $b = pf_unit_base((string)($item['unit'] ?? ''));
    if (!$b) return [(string)($item['unit'] ?? 'unit')];
    return $b[0] === 'g' ? ['kg', 'g'] : ['L', 'ml'];
}
/**
 * Converts a typed quantity to the item's stock quantity.
 * Products → whole packs (rounded up). Raw materials → their own unit.
 * Returns ['qty' => stock qty, 'input_qty', 'input_unit', 'pack_size', 'pack_unit', 'rounded' => bool].
 */
function pf_convert(string $type, array $item, float $qty, string $unit): array {
    $unit = trim($unit);
    if ($qty <= 0) erp_invalid("{$item['name']}: quantity must be more than zero.");
    $allowed = pf_units_for($type, $item);
    $ok = array_filter($allowed, fn($a) => strcasecmp($a, $unit) === 0);
    if (!$ok) erp_invalid("{$item['name']}: choose one of these units — " . implode(', ', $allowed) . '.');
    $unit = array_values($ok)[0];
    if ($type === 'product') {
        if ($unit === 'pcs') {
            if (floor($qty) != $qty) erp_invalid("{$item['name']}: packs must be whole numbers.");
            return ['qty' => $qty, 'input_qty' => $qty, 'input_unit' => 'pcs', 'pack_size' => null, 'pack_unit' => null, 'rounded' => false];
        }
        $p = pf_pack($item['pack'] ?? null); $b = pf_unit_base($unit);
        $exact = $qty * $b[1] / $p[0];
        $packs = ceil(round($exact, 6));
        return ['qty' => $packs, 'input_qty' => $qty, 'input_unit' => $unit, 'pack_size' => $p[0], 'pack_unit' => $p[1], 'rounded' => abs($packs - $exact) > 0.000001];
    }
    $from = pf_unit_base($unit); $to = pf_unit_base((string)$item['unit']);
    $q = ($from && $to) ? $qty * $from[1] / $to[1] : $qty;
    return ['qty' => round($q, 3), 'input_qty' => $qty, 'input_unit' => $unit, 'pack_size' => null, 'pack_unit' => null, 'rounded' => false];
}
function pf_installed(PDO $pdo): bool {
    static $ok = null;
    if ($ok === null) { try { $pdo->query("SELECT 1 FROM purchase_flows LIMIT 1"); $ok = true; } catch (PDOException $e) { $ok = false; } }
    return $ok;
}
function pf_units_installed(PDO $pdo): bool {
    static $ok = null;
    if ($ok === null) { try { $pdo->query("SELECT 1 FROM pr_item_units LIMIT 1"); $ok = true; } catch (PDOException $e) { $ok = false; } }
    return $ok;
}
/** Adds input_qty / input_unit to purchase request item rows (when recorded). */
function pf_attach_units(PDO $pdo, array $items): array {
    if (!$items || !pf_units_installed($pdo)) return $items;
    $ids = array_map(fn($i) => (int)$i['id'], $items);
    $u = [];
    foreach (erp_rows($pdo, "SELECT * FROM pr_item_units WHERE pr_item_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", $ids) as $r) $u[(int)$r['pr_item_id']] = $r;
    foreach ($items as &$i) {
        $r = $u[(int)$i['id']] ?? null;
        $i['input_qty'] = $r ? (float)$r['input_qty'] : null;
        $i['input_unit'] = $r ? $r['input_unit'] : null;
    }
    unset($i);
    return $items;
}
}
