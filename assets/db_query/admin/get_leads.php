<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // Defensive, same pattern as order_status/stock elsewhere in this
    // codebase: add lead_status if the migration hasn't run yet, rather
    // than hard-failing.
    try {
        $pdo->exec("ALTER TABLE contacts ADD COLUMN lead_status VARCHAR(20) NOT NULL DEFAULT 'new'");
    } catch (PDOException $e) {
        // Column already exists — fine, ignore.
    }

    $status = $_GET['status'] ?? '';

    $sql = "SELECT id, name, email, phone, subject, message,
                   COALESCE(lead_status, 'new') AS lead_status,
                   COALESCE(created_at, NOW()) AS created_at
            FROM contacts WHERE 1=1";
    $params = [];

    if (!empty($status)) {
        $sql .= " AND COALESCE(lead_status, 'new') = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'leads' => $leads]);
} catch (PDOException $e) {
    error_log("Error fetching leads: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch leads: ' . $e->getMessage()]);
}
