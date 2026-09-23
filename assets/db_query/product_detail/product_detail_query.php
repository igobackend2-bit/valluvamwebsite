<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file


function slugify_product_name($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}

// Removes a trailing size like " 1kg", " 500g", " 750ml" from a product name so that
// "Raw Rice 10kg" and "Raw Rice 25kg" are recognised as size variants of the same product.
// A name with no size suffix (e.g. "Raw rice") is returned unchanged.
function strip_size_suffix($name) {
    return trim(preg_replace('/\s+\d+(\.\d+)?\s*(kg|g|ml|l)$/i', '', $name));
}

$raw = trim($_GET['id'] ?? '');
if ($raw === '') {
    echo json_encode(['status' => 'error', 'message' => 'No product ID']);
    exit;
}

if (ctype_digit($raw)) {
    // Legacy link: plain numeric id
    $id = (int) $raw;
} else {
    // URL is a product name slug (e.g. "honey") - resolve it to an id
    $id = null;
    $stmt = $pdo->query("SELECT id, product_name FROM product_details");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (slugify_product_name($row['product_name']) === $raw) {
            $id = (int) $row['id'];
            break;
        }
    }
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }
}

// Fetch main product
$stmt = $pdo->prepare("SELECT id, product_name, price, dis_price, description, category, quantity, image, benefits, rating
                       FROM product_details
                       WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit;
}

// Fetch similar products (same category, exclude current product)
$stmt = $pdo->prepare("SELECT id, product_name, price, dis_price, image
                       FROM product_details
                       WHERE category = ? AND id != ?
                       ORDER BY timestamp DESC LIMIT 5");
$stmt->execute([$product['category'], $id]);
$similar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch size variants: other products in the same category whose name is the same once
// a trailing size (1kg / 5kg / 10kg / 25kg / 500g / ...) is stripped off. Only returned
// when there's more than one, so single-size products render exactly as before.
$variants = [];
$baseName = strip_size_suffix($product['product_name']);
if ($baseName !== '') {
    $stmt = $pdo->prepare("SELECT id, product_name, price, dis_price, quantity
                           FROM product_details
                           WHERE category = ?");
    $stmt->execute([$product['category']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (strcasecmp(strip_size_suffix($row['product_name']), $baseName) === 0) {
            $variants[] = [
                'id' => (int) $row['id'],
                'slug' => slugify_product_name($row['product_name']),
                'quantity' => $row['quantity'],
                'price' => $row['price'],
                'dis_price' => $row['dis_price'],
                'is_current' => ((int) $row['id'] === (int) $product['id']),
            ];
        }
    }
    usort($variants, function ($a, $b) {
        $qa = (int) preg_replace('/\D/', '', (string) $a['quantity']);
        $qb = (int) preg_replace('/\D/', '', (string) $b['quantity']);
        return $qa <=> $qb;
    });
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'product' => $product,
        'similar' => $similar,
        'variants' => count($variants) > 1 ? $variants : [],
    ]
]);
