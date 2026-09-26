<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // your PDO connection file
require_once __DIR__ . '/../config.php'; // your PDO connection file
$action = $_GET['action'] ?? '';


if ($action == 'category_slider') {
    try {
        // Only categories with a homepage image appear in the slider (new admin categories without an image stay hidden here).
        $stmt = $pdo->prepare("SELECT category_name,thumbnali, link FROM product_category WHERE thumbnali IS NOT NULL AND thumbnali <> ''");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $categories]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action == 'product_catelog') {
    try {
        $stmt = $pdo->query("
            SELECT id, product_name, price, dis_price, category, image, quantity
            FROM product_details
            WHERE image IS NOT NULL
              AND TRIM(image) <> ''
            ORDER BY id DESC
            LIMIT 8
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $products
        ]);
        exit;
    } catch (PDOException $d) {
        echo json_encode([
            'status' => 'error',
            'message' => $d->getMessage()
        ]);
    }
} elseif ($action === 'top_rated') {
    // New, additive action for the homepage "Top Rated" carousel. Only ever
    // reads product_details.rating, an existing real column - no schema
    // change, no new table. Products with no rating set (NULL or 0) are
    // excluded rather than shown with a fabricated score.
    try {
        $stmt = $pdo->query("
            SELECT id, product_name, price, dis_price, category, image, quantity, rating
            FROM product_details
            WHERE image IS NOT NULL
              AND TRIM(image) <> ''
              AND rating IS NOT NULL
              AND rating > 0
            ORDER BY rating DESC, id DESC
            LIMIT 10
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $products
        ]);
        exit;
    } catch (PDOException $d) {
        echo json_encode([
            'status' => 'error',
            'message' => $d->getMessage()
        ]);
    }
} elseif ($action === 'product_search') {
    $query = trim($_GET['query'] ?? '');

    try {
        if ($query !== "") {
            // category added to the SELECT (additive) so search-result cards can
            // build the correct "/{category}/{slug}" product URL.
            $sql = "SELECT id, product_name, price, dis_price, quantity, image, category
                    FROM product_details
                    WHERE product_name LIKE :query
                       OR description LIKE :query
                       OR category LIKE :query";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['query' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($results) {
                echo json_encode(['status' => 'success','data' => $results]);
            } else {
                echo json_encode(['status' => 'not_found','message' => 'No products found']);
            }
        } else {
            echo json_encode(['status' => 'empty','message' => 'Type something to search...']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error','message' => $e->getMessage()]);
    }
    exit;
} elseif ($action === 'collection') {
    $type = $_GET['type'] ?? '';
    $cats = [];
    if ($type === 'healthy') $cats = ['Dry Fruits','Nuts','Honey','Millets'];
    elseif ($type === 'traditional') $cats = ['Millets','Rice','Palm Jaggery'];
    elseif ($type === 'gifting') $cats = ['Combo','Combos','Gifting'];
    elseif ($type === 'everyday') $cats = ['Oils','Spices','Dal','Pulses','Ghee'];
    else { echo json_encode(['status'=>'error','message'=>'Invalid collection type']); exit; }
    
    try {
        $placeholders = str_repeat('?,', count($cats) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, product_name, price, dis_price, category, image, quantity, rating FROM product_details WHERE image IS NOT NULL AND TRIM(image) <> '' AND category IN ($placeholders) ORDER BY id DESC LIMIT 8");
        $stmt->execute($cats);
        echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action'
    ]);
    exit;
}

