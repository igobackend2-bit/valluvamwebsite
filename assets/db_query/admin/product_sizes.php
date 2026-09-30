<?php
// ============================================================
// Admin: sizes of one product (added 30 Sep 2026)
//
// The website already shows a "Size" row on the product page for packs of the
// same product (same category + same name once the size at the end is removed,
// e.g. "Dry Fig", "Dry Fig 500g", "Dry Fig 1kg"), and every pack has its OWN
// price / discount price, so the cart charges the right amount.
// This endpoint lets admin manage those packs from the product form:
//   GET  ?action=list&id=<product id>
//   POST action=save   id=<product id being edited>, size_id (empty = new),
//                      quantity ("250g"/"1kg"/"500ml"/"1L"), price, dis_price
//   POST action=delete id=<product id being edited>, size_id
// No database change: it only reads/writes product_details rows.
// ============================================================
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_admin_session();

// Same rules as product_detail_query.php so admin sees exactly what the website shows
function ps_strip_size_suffix($name) {
    return trim(preg_replace('/\s+\d+(\.\d+)?\s*(kg|g|ml|l)$/i', '', (string) $name));
}
function ps_qty_amount($q) {
    if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(kg|g|ml|l)\s*$/i', (string) $q, $m)) return null;
    $n = (float) $m[1];
    $u = strtolower($m[2]);
    if ($u === 'g') return $n / 1000;
    if ($u === 'ml') return $n / 1000;
    return $n; // kg or l
}
function ps_norm_qty($q) {
    if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(kg|g|ml|l)\s*$/i', (string) $q, $m)) return null;
    $u = strtolower($m[2]);
    return ((float) $m[1] + 0) . ($u === 'l' ? 'L' : $u);
}
function ps_money($v, $required) {
    $v = trim(str_replace(',', '', (string) $v));
    if ($v === '') return $required ? false : null;
    if (!is_numeric($v) || (float) $v < 0) return false;
    return round((float) $v, 2);
}
function ps_out($arr) { echo json_encode($arr); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($productId <= 0) ps_out(['status' => 'error', 'message' => 'Save the product first, then add its sizes.']);

try {
    $stmt = $pdo->prepare("SELECT * FROM product_details WHERE id = ?");
    $stmt->execute([$productId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$base) ps_out(['status' => 'error', 'message' => 'Product not found']);

    $baseName = ps_strip_size_suffix($base['product_name']);

    // All packs of this product (including the one being edited)
    $loadSizes = function () use ($pdo, $base, $baseName) {
        $stmt = $pdo->prepare("SELECT id, product_name, quantity, price, dis_price, stock FROM product_details WHERE category = ?");
        $stmt->execute([$base['category']]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (strcasecmp(ps_strip_size_suffix($r['product_name']), $baseName) === 0) {
                $r['id'] = (int) $r['id'];
                $r['is_current'] = ($r['id'] === (int) $base['id']);
                $rows[] = $r;
            }
        }
        usort($rows, function ($a, $b) {
            return (ps_qty_amount($a['quantity']) ?? 0) <=> (ps_qty_amount($b['quantity']) ?? 0);
        });
        return $rows;
    };

    if ($action === 'list') {
        ps_out(['status' => 'success', 'base_name' => $baseName, 'sizes' => $loadSizes()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ps_out(['status' => 'error', 'message' => 'Invalid request']);

    $sizes = $loadSizes();
    $sizeId = (int) ($_POST['size_id'] ?? 0);
    $sizeIds = array_column($sizes, 'id');

    if ($action === 'save') {
        $qty = ps_norm_qty($_POST['quantity'] ?? '');
        if ($qty === null) ps_out(['status' => 'error', 'message' => 'Enter a size with its unit, e.g. 250g, 1kg, 500ml or 1L.']);
        $price = ps_money($_POST['price'] ?? '', true);
        if ($price === false || $price <= 0) ps_out(['status' => 'error', 'message' => 'Enter a valid price.']);
        $dis = ps_money($_POST['dis_price'] ?? '', false);
        if ($dis === false) ps_out(['status' => 'error', 'message' => 'Enter a valid discount price (or leave it empty).']);
        if ($dis !== null && $dis > $price) ps_out(['status' => 'error', 'message' => 'Discount price cannot be more than the price.']);

        // No two packs with the same size
        foreach ($sizes as $s) {
            if ($s['id'] !== $sizeId && ps_norm_qty($s['quantity']) === $qty) {
                ps_out(['status' => 'error', 'message' => "This product already has a $qty size."]);
            }
        }

        $name = $baseName . ' ' . $qty;

        if ($sizeId) {
            if (!in_array($sizeId, $sizeIds, true)) ps_out(['status' => 'error', 'message' => 'Size not found for this product.']);
            if ($sizeId === (int) $base['id']) ps_out(['status' => 'error', 'message' => 'This is the product open in the form - change its price / quantity in the form above.']);
            $old = null;
            foreach ($sizes as $s) if ($s['id'] === $sizeId) $old = $s;
            $upd = $pdo->prepare("UPDATE product_details SET product_name = ?, quantity = ?, price = ?, dis_price = ? WHERE id = ?");
            $upd->execute([$name, $qty, $price, $dis, $sizeId]);
            log_audit($pdo, 'update', 'product_sizes', $sizeId, $old, ['product_name' => $name, 'quantity' => $qty, 'price' => $price, 'dis_price' => $dis]);
            ps_out(['status' => 'success', 'message' => "$qty updated", 'sizes' => $loadSizes()]);
        }

        // New pack: copy the product's details (category, image, description...)
        $ins = $pdo->prepare("INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$name, $price, $dis, $base['category'], $qty, $base['rating'], $base['description'], $base['benefits'], $base['image']]);
        $newId = (int) $pdo->lastInsertId();
        log_audit($pdo, 'create', 'product_sizes', $newId, null, ['product_name' => $name, 'quantity' => $qty, 'price' => $price, 'dis_price' => $dis, 'copied_from' => (int) $base['id']]);
        ps_out(['status' => 'success', 'message' => "$qty added", 'sizes' => $loadSizes()]);
    }

    if ($action === 'delete') {
        if (!$sizeId || !in_array($sizeId, $sizeIds, true)) ps_out(['status' => 'error', 'message' => 'Size not found for this product.']);
        if ($sizeId === (int) $base['id']) ps_out(['status' => 'error', 'message' => 'This is the product open in the form - delete it from the product list instead.']);
        $old = null;
        foreach ($sizes as $s) if ($s['id'] === $sizeId) $old = $s;
        try {
            // The image file is shared with the other sizes, so it is NOT deleted here.
            $del = $pdo->prepare("DELETE FROM product_details WHERE id = ?");
            $del->execute([$sizeId]);
        } catch (PDOException $e) {
            ps_out(['status' => 'error', 'message' => 'This size is linked to past orders, so it cannot be deleted.']);
        }
        log_audit($pdo, 'delete', 'product_sizes', $sizeId, $old, null);
        ps_out(['status' => 'success', 'message' => 'Size deleted', 'sizes' => $loadSizes()]);
    }

    ps_out(['status' => 'error', 'message' => 'Unknown action']);
} catch (PDOException $e) {
    error_log('product_sizes: ' . $e->getMessage());
    ps_out(['status' => 'error', 'message' => 'Database error - please try again.']);
}
