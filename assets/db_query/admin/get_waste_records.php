<?php
// List waste records with filters: status, waste_type, date_from, date_to,
// q (search waste_id/reason/sku).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'waste.view');

$status     = trim($_GET['status'] ?? '');
$waste_type = trim($_GET['waste_type'] ?? '');
$date_from  = trim($_GET['date_from'] ?? '');
$date_to    = trim($_GET['date_to'] ?? '');
$q          = trim($_GET['q'] ?? '');

try {
    // FIX (3 Oct 2026): + latest proof photo and whether it is waiting for the Admin (after the Manager approved)
    $sql = "SELECT w.*, pd.product_name,
                   (SELECT MAX(d.id) FROM erp_documents d WHERE d.entity_type = 'waste_record' AND d.entity_id = w.id) AS proof_doc_id,
                   (SELECT COUNT(*) FROM erp_documents d WHERE d.entity_type = 'waste_record' AND d.entity_id = w.id) AS proof_count,
                   (SELECT ar.request_number FROM approval_requests ar WHERE ar.module = 'waste_admin' AND ar.request_key = CONCAT('w', w.id) AND ar.status IN ('submitted','under_review') LIMIT 1) AS awaiting_admin
            FROM waste_records w
            LEFT JOIN product_details pd ON pd.id = w.product_id WHERE 1=1";
    $params = [];

    if ($status !== '') {
        $sql .= " AND w.status = ?";
        $params[] = $status;
    }
    if ($waste_type !== '') {
        $sql .= " AND w.waste_type = ?";
        $params[] = $waste_type;
    }
    if ($date_from !== '') {
        $sql .= " AND w.date >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND w.date <= ?";
        $params[] = $date_to;
    }
    if ($q !== '') {
        $sql .= " AND (w.waste_id LIKE ? OR w.reason LIKE ? OR w.sku LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY w.id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'waste_records' => $records]);
} catch (PDOException $e) {
    error_log("Error listing waste records: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'waste_records' => []]);
}
