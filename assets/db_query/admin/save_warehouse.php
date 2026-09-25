<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'warehouses.create');

$id = $_POST['id'] ?? '';
$name = trim($_POST['name'] ?? '');
$code = strtoupper(trim($_POST['code'] ?? ''));
$location = trim($_POST['location'] ?? '');
$manager_name = trim($_POST['manager_name'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$capacity = trim($_POST['capacity'] ?? '');
$status = $_POST['status'] ?? 'active';

if (!$name || !$code) {
    echo json_encode(['status' => 'error', 'message' => 'Name and code are required']);
    exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

try {
    if ($id) {
        $old = $pdo->prepare("SELECT * FROM warehouses WHERE id = ?");
        $old->execute([$id]);
        $oldRow = $old->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE warehouses SET name = ?, code = ?, location = ?, manager_name = ?, contact = ?, capacity = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $code, $location ?: null, $manager_name ?: null, $contact ?: null, $capacity ?: null, $status, $id]);

        log_audit($pdo, 'update', 'warehouses', $id, $oldRow, [
            'name' => $name, 'code' => $code, 'location' => $location, 'manager_name' => $manager_name,
            'contact' => $contact, 'capacity' => $capacity, 'status' => $status
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO warehouses (name, code, location, manager_name, contact, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $code, $location ?: null, $manager_name ?: null, $contact ?: null, $capacity ?: null, $status]);
        $newId = $pdo->lastInsertId();

        log_audit($pdo, 'create', 'warehouses', $newId, null, [
            'name' => $name, 'code' => $code, 'location' => $location, 'manager_name' => $manager_name,
            'contact' => $contact, 'capacity' => $capacity, 'status' => $status
        ]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['status' => 'error', 'message' => 'That warehouse code already exists']);
        exit;
    }
    error_log("Error saving warehouse: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save warehouse: ' . $e->getMessage()]);
}
