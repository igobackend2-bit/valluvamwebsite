<?php
// List suppliers with optional search (q) across supplier_name, company_name,
// mobile, email, gst_number.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'suppliers.view');

$q = trim($_GET['q'] ?? '');

try {
    $sql = "SELECT * FROM suppliers WHERE 1=1";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (supplier_name LIKE ? OR company_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR gst_number LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY supplier_name ASC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'suppliers' => $suppliers]);
} catch (PDOException $e) {
    error_log("Error listing suppliers: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'suppliers' => []]);
}
