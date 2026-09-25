<?php
// Deletes a product category. Refuses if any product still uses it, so
// deleting a category can never silently strand products with a category
// that no longer exists — the admin has to move/recategorize those
// products first (e.g. via the product editor or Import Products).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Category ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT category_name, thumbnali FROM product_category WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        echo json_encode(['status' => 'error', 'message' => 'Category not found']);
        exit;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM product_details WHERE LOWER(category) = LOWER(?)");
    $countStmt->execute([$category['category_name']]);
    $productCount = (int)$countStmt->fetchColumn();

    if ($productCount > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => $productCount . ' product(s) are still in "' . $category['category_name'] . '". Move them to a different category first (edit each product, or re-import), then delete this category.'
        ]);
        exit;
    }

    $deleteStmt = $pdo->prepare("DELETE FROM product_category WHERE id = ?");
    $deleteStmt->execute([$id]);

    if ($deleteStmt->rowCount() > 0) {
        if (!empty($category['thumbnali'])) {
            $thumbPath = __DIR__ . '/../../thumbnail/' . $category['thumbnali'];
            if (file_exists($thumbPath)) {
                @unlink($thumbPath); // Suppress errors if file doesn't exist
            }
        }
        log_audit($pdo, 'delete', 'product_category', $id, ['category_name' => $category['category_name']], null);
        echo json_encode(['status' => 'success', 'message' => 'Category deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete category']);
    }
} catch (PDOException $e) {
    error_log("Error deleting category: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete category: ' . $e->getMessage()]);
}
