<?php
// Create or update a Delivery Challan (+ items), transactionally.
// POST fields: id (optional, update), sales_order_id (optional),
// customer_name, customer_mobile, customer_email, delivery_address,
// warehouse_id, vehicle_number, driver_name, driver_mobile, dispatch_date,
// expected_delivery_date, delivery_status, remarks,
// items (JSON array of {product_id, sku, quantity, unit}).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_POST['id'] ?? 0);
require_permission($pdo, $id ? 'dc.edit' : 'dc.create');

$sales_order_id   = trim($_POST['sales_order_id'] ?? '') !== '' ? (int)$_POST['sales_order_id'] : null;
$customer_name    = trim($_POST['customer_name'] ?? '');
$customer_mobile  = trim($_POST['customer_mobile'] ?? '');
$customer_email   = trim($_POST['customer_email'] ?? '');
$delivery_address = trim($_POST['delivery_address'] ?? '');
$warehouse_id     = (int)($_POST['warehouse_id'] ?? 1) ?: 1;
$vehicle_number   = trim($_POST['vehicle_number'] ?? '');
$driver_name      = trim($_POST['driver_name'] ?? '');
$driver_mobile    = trim($_POST['driver_mobile'] ?? '');
$dispatch_date    = trim($_POST['dispatch_date'] ?? '') ?: null;
$expected_delivery_date = trim($_POST['expected_delivery_date'] ?? '') ?: null;
$delivery_status  = trim($_POST['delivery_status'] ?? 'draft');
$remarks          = trim($_POST['remarks'] ?? '');
$itemsRaw         = $_POST['items'] ?? '[]';

$validStatuses = ['draft','ready','loaded','dispatched','in_transit','delivered','cancelled'];
if (!in_array($delivery_status, $validStatuses, true)) {
    $delivery_status = 'draft';
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one line item is required']);
    exit;
}

$cleanItems = [];
foreach ($items as $it) {
    $product_id = (int)($it['product_id'] ?? 0);
    $quantity   = (float)($it['quantity'] ?? 0);
    if (!$product_id || $quantity <= 0) continue;
    $cleanItems[] = [
        'product_id' => $product_id,
        'sku' => trim($it['sku'] ?? ''),
        'quantity' => $quantity,
        'unit' => trim($it['unit'] ?? 'pcs') ?: 'pcs',
    ];
}
if (count($cleanItems) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one valid line item is required']);
    exit;
}

if (!$customer_name && $sales_order_id) {
    // Pull customer info from the linked sales order when not supplied.
    $soStmt = $pdo->prepare("SELECT customer_name, customer_mobile, customer_email, shipping_address FROM sales_orders WHERE id = ?");
    $soStmt->execute([$sales_order_id]);
    if ($so = $soStmt->fetch(PDO::FETCH_ASSOC)) {
        $customer_name = $customer_name ?: $so['customer_name'];
        $customer_mobile = $customer_mobile ?: $so['customer_mobile'];
        $customer_email = $customer_email ?: $so['customer_email'];
        $delivery_address = $delivery_address ?: $so['shipping_address'];
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    $oldRow = null;
    $wasDispatchedBefore = false;

    if ($id) {
        $old = $pdo->prepare("SELECT * FROM delivery_challans WHERE id = ?");
        $old->execute([$id]);
        $oldRow = $old->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Delivery challan not found']);
            exit;
        }
        if (in_array($oldRow['delivery_status'], ['dispatched','in_transit','delivered'], true)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'This delivery challan has already been dispatched and can no longer be edited']);
            exit;
        }
        $wasDispatchedBefore = in_array($oldRow['delivery_status'], ['dispatched','in_transit','delivered'], true);

        $upd = $pdo->prepare("UPDATE delivery_challans SET sales_order_id=?, customer_name=?, customer_mobile=?, customer_email=?,
            delivery_address=?, warehouse_id=?, vehicle_number=?, driver_name=?, driver_mobile=?, dispatch_date=?,
            expected_delivery_date=?, delivery_status=?, remarks=? WHERE id=?");
        $upd->execute([
            $sales_order_id, $customer_name ?: null, $customer_mobile ?: null, $customer_email ?: null,
            $delivery_address ?: null, $warehouse_id, $vehicle_number ?: null, $driver_name ?: null, $driver_mobile ?: null,
            $dispatch_date, $expected_delivery_date, $delivery_status, $remarks ?: null, $id
        ]);

        $pdo->prepare("DELETE FROM delivery_challan_items WHERE delivery_challan_id = ?")->execute([$id]);
        $dcId = $id;
        $dcNumber = $oldRow['dc_number'];
    } else {
        $dcNumber = next_document_number($pdo, 'dc', 'DC');

        $ins = $pdo->prepare("INSERT INTO delivery_challans (dc_number, sales_order_id, customer_name, customer_mobile, customer_email,
            delivery_address, warehouse_id, vehicle_number, driver_name, driver_mobile, dispatch_date, expected_delivery_date,
            delivery_status, remarks, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([
            $dcNumber, $sales_order_id, $customer_name ?: null, $customer_mobile ?: null, $customer_email ?: null,
            $delivery_address ?: null, $warehouse_id, $vehicle_number ?: null, $driver_name ?: null, $driver_mobile ?: null,
            $dispatch_date, $expected_delivery_date, $delivery_status, $remarks ?: null, $adminUsername
        ]);
        $dcId = (int)$pdo->lastInsertId();
    }

    $itemStmt = $pdo->prepare("INSERT INTO delivery_challan_items (delivery_challan_id, product_id, sku, quantity, unit) VALUES (?,?,?,?,?)");
    foreach ($cleanItems as $ci) {
        $itemStmt->execute([$dcId, $ci['product_id'], $ci['sku'] ?: null, $ci['quantity'], $ci['unit']]);
    }

    // ── Integration point: Stock Out ────────────────────────────────────
    // When a DC moves to 'dispatched' for the FIRST time, this is the
    // trigger for a Stock Out record. The `stock_outs` table is owned by
    // the Inventory-ops agent and may not exist yet at the time this file
    // is deployed. We attempt the insert inside this same transaction and
    // silently ignore a "table doesn't exist" error so this endpoint never
    // hard-fails before that agent's migration has run. Once `stock_outs`
    // exists, expected columns: reference_type='delivery_challan',
    // reference_number=dc_number (+ product_id/quantity per item, warehouse_id,
    // created_by). Do NOT create/alter `stock_outs` from this file.
    $justDispatched = ($delivery_status === 'dispatched') && !$wasDispatchedBefore;
    if ($justDispatched) {
        try {
            foreach ($cleanItems as $ci) {
                $so = $pdo->prepare("INSERT INTO stock_outs (reference_type, reference_number, product_id, quantity, warehouse_id, created_by, created_at)
                                      VALUES ('delivery_challan', ?, ?, ?, ?, ?, NOW())");
                $so->execute([$dcNumber, $ci['product_id'], $ci['quantity'], $warehouse_id, $adminUsername]);
            }
        } catch (PDOException $e) {
            // stock_outs doesn't exist yet (or its schema differs) — ignore.
            // This DC's own status update still commits below.
            error_log('stock_outs integration skipped (table not ready): ' . $e->getMessage());
        }
    }

    $pdo->commit();

    log_audit($pdo, $id ? 'update' : 'create', 'delivery_challans', $dcId, $oldRow, [
        'dc_number' => $dcNumber, 'delivery_status' => $delivery_status
    ]);

    echo json_encode(['status' => 'success', 'id' => $dcId, 'dc_number' => $dcNumber]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving delivery challan: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save delivery challan: ' . $e->getMessage()]);
}
