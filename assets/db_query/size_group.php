<?php
// FIX (3 Oct 2026): show one card per product on the website lists.
// Each pack size is its own product row (own price, own stock), e.g. "BAY LEAVES 100g" and "BAY LEAVES 200g".
// Lists (shop, category pages, home, search) now show the product once — the smallest pack in stock is the card
// (its price, Add to Cart) with "100g · 2 sizes"; the customer picks the size on the product page (Size row).
// Same grouping rule as product_detail_query.php: same category + same name once the size at the end is removed.
// Read-only: it only reshapes the list that was already fetched.

if (!function_exists('vp_size_strip')) {
    function vp_size_strip($name) {
        return trim(preg_replace('/\s+\d+(\.\d+)?\s*(kg|g|ml|l)$/i', '', (string) $name));
    }
    function vp_size_amount($q) {
        if (!preg_match('/([\d.]+)\s*(kg|g|gm|gram|grams|ml|l)?/i', (string) $q, $m) || $m[1] === '') return PHP_INT_MAX;
        $n = (float) $m[1];
        $u = strtolower($m[2] ?? 'g');
        return in_array($u, ['kg', 'l'], true) ? $n : $n / 1000;
    }
    function vp_group_sizes(array $rows): array {
        $groups = []; $order = [];
        foreach ($rows as $r) {
            $base = vp_size_strip($r['product_name'] ?? '');
            $key = strtolower(($r['category'] ?? '') . '|' . ($base !== '' ? $base : ($r['product_name'] ?? '')));
            if (!isset($groups[$key])) { $groups[$key] = []; $order[] = $key; }
            $groups[$key][] = $r;
        }
        $out = [];
        foreach ($order as $key) {
            $g = $groups[$key];
            if (count($g) === 1) { $out[] = $g[0]; continue; }
            usort($g, fn($a, $b) => vp_size_amount($a['quantity'] ?? '') <=> vp_size_amount($b['quantity'] ?? ''));
            if (!array_key_exists('stock', $g[0]) && ($GLOBALS['pdo'] ?? null) instanceof PDO) {   // search lists don't select stock — look it up
                try {
                    $ids = array_map(fn($r) => (int) $r['id'], $g);
                    $st = $GLOBALS['pdo']->prepare("SELECT id, stock FROM product_details WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                    $st->execute($ids);
                    $stk = $st->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($g as &$r) $r['_stock'] = (int) ($stk[(int) $r['id']] ?? 0);
                    unset($r);
                } catch (PDOException $e) { /* keep the smallest pack */ }
            }
            $rep = $g[0];
            foreach ($g as $r) { $sv = $r['stock'] ?? ($r['_stock'] ?? null); if ($sv === null || (int) $sv > 0) { $rep = $r; break; } }   // smallest pack that is in stock
            unset($rep['_stock']);
            $sizes = array_values(array_filter(array_map(fn($r) => trim((string) ($r['quantity'] ?? '')), $g)));
            $base = vp_size_strip($rep['product_name']);
            if ($base !== '') $rep['product_name'] = $base;
            $rep['size_count'] = count($g);
            $rep['sizes'] = $sizes;
            $rep['quantity'] = trim(($rep['quantity'] ?? '') . ' · ' . count($g) . ' sizes', ' ·');
            $out[] = $rep;
        }
        return $out;
    }
}
