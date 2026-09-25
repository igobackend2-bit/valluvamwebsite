<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'suppliers.create');

$id             = $_POST['id'] ?? '';
$supplier_name  = trim($_POST['supplier_name'] ?? '');
$company_name   = trim($_POST['company_name'] ?? '');
$mobile         = trim($_POST['mobile'] ?? '');
$email          = trim($_POST['email'] ?? '');
$address        = trim($_POST['address'] ?? '');
$gst_number     = trim($_POST['gst_number'] ?? '');
$payment_terms  = trim($_POST['payment_terms'] ?? '');
$bank_details   = trim($_POST['bank_details'] ?? '');
$status         = $_POST['status'] ?? 'active';
$notes          = trim($_POST['notes'] ?? '');

if (!$supplier_name) {
    echo json_encode(['status' => 'error', 'message' => 'Supplier name is required']);
    exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

try {
    if ($id) {
        $old = null;
        $sel = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $sel->execute([$id]);
        $old = $sel->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name = ?, company_name = ?, mobile = ?, email = ?, address = ?, gst_number = ?, payment_terms = ?, bank_details = ?, status = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $supplier_name, $company_name ?: null, $mobile ?: null, $email ?: null,
            $address ?: null, $gst_number ?: null, $payment_terms ?: null, $bank_details ?: null,
            $status, $notes ?: null, $id
        ]);

        log_audit($pdo, 'update', 'suppliers', $id, $old, [
            'supplier_name' => $supplier_name, 'company_name' => $company_name, 'mobile' => $mobile,
            'email' => $email, 'address' => $address, 'gst_number' => $gst_number,
            'payment_terms' => $payment_terms, 'bank_details' => $bank_details, 'status' => $status, 'notes' => $notes
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, company_name, mobile, email, address, gst_number, payment_terms, bank_details, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $supplier_name, $company_name ?: null, $mobile ?: null, $email ?: null,
            $address ?: null, $gst_number ?: null, $payment_terms ?: null, $bank_details ?: null,
            $status, $notes ?: null
        ]);
        $newId = $pdo->lastInsertId();

        log_audit($pdo, 'create', 'suppliers', $newId, null, [
            'supplier_name' => $supplier_name, 'company_name' => $company_name, 'mobile' => $mobile,
            'email' => $email, 'address' => $address, 'gst_number' => $gst_number,
            'payment_terms' => $payment_terms, 'bank_details' => $bank_details, 'status' => $status, 'notes' => $notes
        ]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Error saving supplier: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save supplier: ' . $e->getMessage()]);
}
