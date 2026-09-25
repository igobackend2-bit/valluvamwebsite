<?php
// Lightweight supplier picker used by the Stock In form. suppliers may have
// zero rows — the caller must offer a "no supplier / cash purchase" option.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

try {
    $stmt = $pdo->query("SELECT id, supplier_name, company_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'suppliers' => $suppliers]);
} catch (PDOException $e) {
    error_log("Error fetching suppliers for picker: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'suppliers' => []]);
}
