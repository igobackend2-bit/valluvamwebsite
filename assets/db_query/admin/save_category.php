<?php
// Add or edit a product category. Categories created here immediately show
// up in the New Product form's Category dropdown and, if given a thumbnail
// image and a link, in the homepage category slider — both already read
// live from this same product_category table, so nothing else needed to
// change for that to work.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = $_POST['id'] ?? '';
$category_name = trim($_POST['category_name'] ?? '');
$link = trim($_POST['link'] ?? '');

if (!$category_name) {
    echo json_encode(['status' => 'error', 'message' => 'Category name is required']);
    exit;
}

try {
    // Prevent two categories with the same name (case-insensitive) — the
    // Product form dropdown and the slider would otherwise show duplicates.
    $dupStmt = $pdo->prepare("SELECT id FROM product_category WHERE LOWER(category_name) = LOWER(?) AND id != ?");
    $dupStmt->execute([$category_name, $id ?: 0]);
    if ($dupStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'A category with that name already exists']);
        exit;
    }

    // Optional thumbnail upload — same validation as product image uploads
    // (new_product_query.php): only real image files, saved with a unique
    // name so a re-upload never collides with or overwrites another one.
    $thumbFileName = '';
    if (!empty($_FILES['thumbnail']['name'])) {
        $uploadDir = __DIR__ . '/../../thumbnail/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true) || @getimagesize($_FILES['thumbnail']['tmp_name']) === false) {
            echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, WEBP or GIF images are allowed for the thumbnail']);
            exit;
        }
        $thumbFileName = time() . '_' . basename($_FILES['thumbnail']['name']);
        if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbFileName)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload the thumbnail image']);
            exit;
        }
    }

    if ($id) {
        $old = $pdo->prepare("SELECT * FROM product_category WHERE id = ?");
        $old->execute([$id]);
        $oldRow = $old->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            echo json_encode(['status' => 'error', 'message' => 'Category not found']);
            exit;
        }

        if ($thumbFileName !== '') {
            $stmt = $pdo->prepare("UPDATE product_category SET category_name = ?, link = ?, thumbnali = ? WHERE id = ?");
            $stmt->execute([$category_name, $link, $thumbFileName, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE product_category SET category_name = ?, link = ? WHERE id = ?");
            $stmt->execute([$category_name, $link, $id]);
        }

        log_audit($pdo, 'update', 'product_category', $id, $oldRow, ['category_name' => $category_name, 'link' => $link]);
        echo json_encode(['status' => 'success', 'message' => 'Category updated']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO product_category (category_name, thumbnali, link) VALUES (?, ?, ?)");
        $stmt->execute([$category_name, $thumbFileName, $link]);
        $newId = $pdo->lastInsertId();

        log_audit($pdo, 'create', 'product_category', $newId, null, ['category_name' => $category_name, 'link' => $link]);
        echo json_encode(['status' => 'success', 'message' => 'Category added', 'id' => $newId]);
    }
} catch (PDOException $e) {
    error_log("Error saving category: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save category: ' . $e->getMessage()]);
}
