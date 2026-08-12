<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; // your PDO connection file


// if config uses $conn instead of $pdo, normalize:
if (!isset($pdo) && isset($conn)) $pdo = $conn;

$action = $_GET['action'] ?? '';

/* ---------- Add product to wishlist ---------- */
if ($action === 'add') {
    // Check login
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['status' => 'not_logged_in']);
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    $product_id = (int) ($_POST['product_id'] ?? 0);

    // Validate product ID
    if (!$product_id) {
        echo json_encode(['status' => 'error', 'message' => 'Product ID missing']);
        exit;
    }

    // Check if already in wishlist
    $chk = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $chk->execute([$user_id, $product_id]);
    if ($chk->rowCount() > 0) {
        echo json_encode(['status' => 'exists']);
        exit;
    }

    // Fetch product details
    $p = $pdo->prepare("SELECT product_name, category, quantity, dis_price FROM product_details WHERE id = ?");
    $p->execute([$product_id]);
    $prod = $p->fetch(PDO::FETCH_ASSOC);
    if (!$prod) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }

    // Get username from session, fallback to empty string if not set
    $username = $_SESSION['username'] ?? '';

    // Insert into wishlist
    try {
        $ins = $pdo->prepare("
            INSERT INTO wishlist (user_id, username, product_id, product_name, category, quantity, dis_price, status, created_at, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $ins->execute([
            $user_id,
            $username,
            $product_id,
            $prod['product_name'],
            $prod['category'],
            $prod['quantity'],
            $prod['dis_price']
        ]);

        echo json_encode(['status' => 'added', 'wishlist_id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add to wishlist: ' . $e->getMessage()]);
    }
    exit;
}

/* ---------- Get wishlist for current user ---------- */
// inside wishlist_query.php — action = 'get'
if ($action === 'get') {
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['status' => 'not_logged_in']);
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    $filter_product = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;

    try {
        // join product_details so we can return image and product price fields
        $sql = "
            SELECT w.id, w.product_id, w.product_name, w.category, w.quantity AS wishlist_quantity,
                   w.dis_price AS wishlist_dis_price, w.created_at,
                   pd.price AS product_price, pd.dis_price AS product_dis_price, pd.image AS product_image
            FROM wishlist w
            LEFT JOIN product_details pd ON w.product_id = pd.id
            WHERE w.user_id = ?
        ";
        $params = [$user_id];

        if ($filter_product) {
            $sql .= " AND w.product_id = ?";
            $params[] = $filter_product;
        }
        $sql .= " ORDER BY w.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // normalize/prepare values for JSON
        $basePath = 'assets/uploads/'; // adjust to where your product images live
        $defaultImage = '/images/default.jpg';

        $out = [];
        foreach ($rows as $r) {
            // decide display price: prefer wishlist stored discount, then product discount, then product price
            $price = null;
            if (!empty($r['wishlist_dis_price'])) {
                $price = (float)$r['wishlist_dis_price'];
            } elseif (!empty($r['product_dis_price'])) {
                $price = (float)$r['product_dis_price'];
            } elseif (!empty($r['product_price'])) {
                $price = (float)$r['product_price'];
            } else {
                $price = 0.0;
            }

            // image url
            $image = !empty($r['product_image']) ? $basePath . $r['product_image'] : $defaultImage;

            // numeric quantity: try to parse numeric portion if stored like "70g", otherwise default to 1
            $qty_numeric = 1;
            if (!empty($r['wishlist_quantity'])) {
                // extract number from strings like "70g" or "100"
                if (preg_match('/\d+/', $r['wishlist_quantity'], $m)) {
                    $qty_numeric = (int)$m[0] ?: 1;
                } else {
                    $qty_numeric = (int)$r['wishlist_quantity'] ?: 1;
                }
            }

            $out[] = [
                'id' => (int)$r['id'],
                'product_id' => (int)$r['product_id'],
                'product_name' => $r['product_name'],
                'category' => $r['category'],
                'quantity' => $r['wishlist_quantity'],
                'quantity_number' => $qty_numeric,
                'price' => $price,                 // numeric
                'created_at' => $r['created_at'],
                'image_url' => $image
            ];
        }

        echo json_encode(['status' => 'success', 'wishlist' => $out]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}



/* ---------- Delete wishlist item (only by owner) ---------- */
if ($action === 'delete') {
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['status' => 'not_logged_in']);
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    $wishlist_id = (int) ($_POST['wishlist_id'] ?? 0);

    $del = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
    $del->execute([$wishlist_id, $user_id]);

    if ($del->rowCount()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not found or not allowed']);
    }
    exit;
}
