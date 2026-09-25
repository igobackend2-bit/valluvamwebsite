<?php
// Creates or updates a Stock In header + its line items. Does NOT touch
// product_details.stock or stock_movements — that only happens when the
// record is moved to 'completed' via complete_stock_in.php, matching the
// draft -> received -> verified -> completed workflow described in the brief.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'stock_in.create');

$id = $_POST['id'] ?? '';
$stock_in_date = $_POST['stock_in_date'] ?? date('Y-m-d');
$supplier_id = $_POST['supplier_id'] ?? '';
$purchase_reference = trim($_POST['purchase_reference'] ?? '');
$warehouse_id = $_POST['warehouse_id'] ?? 1;
$received_by = trim($_POST['received_by'] ?? '');
$vehicle_number = trim($_POST['vehicle_number'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$attachment_note = trim($_POST['attachment_note'] ?? '');
$status = $_POST['status'] ?? 'draft';
$itemsRaw = $_POST['items'] ?? '[]';

$allowedStatuses = ['draft', 'received', 'verified', 'completed', 'cancelled'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'draft';
}
if ($status === 'completed') {
    // completing must go through complete_stock_in.php so the stock-affecting
    // transaction actually runs — saving here never silently "completes".
    echo json_encode(['status' => 'error', 'message' => 'Use the Complete action to finalize a stock in.']);
    exit;
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one line item is required']);
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    if ($id) {
        $existing = $pdo->prepare("SELECT * FROM stock_ins WHERE id = ? FOR UPDATE");
        $existing->execute([$id]);
        $oldRow = $existing->fetch(PDO::FETCH_ASSOC);

        if (!$oldRow) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Stock in record not found']);
            exit;
        }
        if (in_array($oldRow['status'], ['completed', 'cancelled'], true)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'A completed or cancelled stock in cannot be edited']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE stock_ins SET stock_in_date = ?, supplier_id = ?, purchase_reference = ?,
                                warehouse_id = ?, received_by = ?, vehicle_number = ?, remarks = ?,
                                attachment_note = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $stock_in_date, $supplier_id ?: null, $purchase_reference ?: null, $warehouse_id ?: 1,
            $received_by ?: null, $vehicle_number ?: null, $remarks ?: null, $attachment_note ?: null,
            $status, $id
        ]);

        $del = $pdo->prepare("DELETE FROM stock_in_items WHERE stock_in_id = ?");
        $del->execute([$id]);

        $stockInId = $id;
    } else {
        $stock_in_number = next_document_number($pdo, 'stock_in', 'SIN');

        $stmt = $pdo->prepare("INSERT INTO stock_ins (stock_in_number, stock_in_date, supplier_id, purchase_reference,
                                warehouse_id, received_by, vehicle_number, remarks, attachment_note, status, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $stock_in_number, $stock_in_date, $supplier_id ?: null, $purchase_reference ?: null, $warehouse_id ?: 1,
            $received_by ?: null, $vehicle_number ?: null, $remarks ?: null, $attachment_note ?: null,
            $status, $adminUsername
        ]);
        $stockInId = $pdo->lastInsertId();
        $oldRow = null;
    }

    $itemStmt = $pdo->prepare("INSERT INTO stock_in_items (stock_in_id, product_id, sku, quantity, unit,
                                batch_number, manufacturing_date, expiry_date, purchase_rate)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $productId = $item['product_id'] ?? 0;
        $qty = (int)($item['quantity'] ?? 0);
        if (!$productId || $qty <= 0) {
            continue;
        }
        $itemStmt->execute([
            $stockInId,
            $productId,
            trim($item['sku'] ?? '') ?: null,
            $qty,
            trim($item['unit'] ?? '') ?: 'pcs',
            trim($item['batch_number'] ?? '') ?: null,
            $item['manufacturing_date'] ?? null ?: null,
            $item['expiry_date'] ?? null ?: null,
            isset($item['purchase_rate']) && $item['purchase_rate'] !== '' ? (float)$item['purchase_rate'] : null,
        ]);
    }

    $pdo->commit();

    log_audit($pdo, $id ? 'update' : 'create', 'stock_ins', $stockInId, $oldRow, [
        'stock_in_date' => $stock_in_date, 'supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id,
        'status' => $status, 'items' => $items
    ]);

    echo json_encode(['status' => 'success', 'id' => $stockInId]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving stock in: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save stock in: ' . $e->getMessage()]);
}
