<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'suppliers.create');

$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'id is required']);
    exit;
}

try {
    $sel = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $sel->execute([$id]);
    $old = $sel->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);

    log_audit($pdo, 'delete', 'suppliers', $id, $old, null);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['status' => 'error', 'message' => "This supplier is used elsewhere and can't be deleted — set it to Inactive instead."]);
        exit;
    }
    error_log("Error deleting supplier: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete supplier: ' . $e->getMessage()]);
}
