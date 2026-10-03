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
    // FIX (30 Sep 2026): the old insert used columns stock_outs doesn't have (product_id,
    // quantity), so it always failed silently and a dispatched DC never reduced stock. Now it
    // creates a proper Stock Out (stock_outs + stock_out_items + stock_movements, same as the
    // Stock Out page). Guards: skipped if a Stock Out already references this DC number (no
    // double deduction), and skipped — without blocking the DC — if any item lacks stock.
    if ($justDispatched) {
        try {
            $already = $pdo->prepare("SELECT COUNT(*) FROM stock_outs WHERE reference_type = 'delivery_challan' AND reference_number = ?");
            $already->execute([$dcNumber]);
            if ((int)$already->fetchColumn() === 0 && $cleanItems) {
                $pdo->exec("SAVEPOINT dc_stock_out");
                $lock = $pdo->prepare("SELECT stock FROM product_details WHERE id = ? FOR UPDATE");
                $stocks = [];
                foreach ($cleanItems as $ci) {
                    $lock->execute([$ci['product_id']]);
                    $cur = $lock->fetchColumn();
                    $need = (int)ceil((float)$ci['quantity']) + ($stocks[$ci['product_id']]['need'] ?? 0);
                    if ($cur === false || (int)$cur < $need) throw new RuntimeException("insufficient stock for product {$ci['product_id']}");
                    $stocks[$ci['product_id']] = ['cur' => (int)$cur, 'need' => $need];
                }
                $soNumber = next_document_number($pdo, 'stock_out', 'SOUT');
                $pdo->prepare("INSERT INTO stock_outs (stock_out_number, stock_out_date, reference_type, reference_number, warehouse_id, vehicle_number, customer_name, reason, authorized_by, created_by)
                               VALUES (?, ?, 'delivery_challan', ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$soNumber, $dispatch_date ?: date('Y-m-d'), $dcNumber, $warehouse_id, $vehicle_number ?: null, $customer_name ?: null, 'Dispatched on ' . $dcNumber, $adminUsername, $adminUsername]);
                $soId = (int)$pdo->lastInsertId();
                $soItem = $pdo->prepare("INSERT INTO stock_out_items (stock_out_id, product_id, sku, quantity, unit) VALUES (?,?,?,?,?)");
                $upd = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");
                $mv = $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id, quantity, previous_stock, new_stock, reference_type, reference_number, reason, created_by, created_at)
                                     VALUES ('stock_out', ?, ?, ?, ?, ?, ?, 'delivery_challan', ?, ?, ?, NOW())");
                $running = [];
                foreach ($cleanItems as $ci) {
                    $q = (int)ceil((float)$ci['quantity']);
                    $prev = $running[$ci['product_id']] ?? $stocks[$ci['product_id']]['cur'];
                    $newS = $prev - $q;
                    $running[$ci['product_id']] = $newS;
                    $soItem->execute([$soId, $ci['product_id'], $ci['sku'] ?: null, $q, $ci['unit'] ?: 'pcs']);
                    $upd->execute([$newS, $ci['product_id']]);
                    $mv->execute([$ci['product_id'], $ci['sku'] ?: ('PRD-' . $ci['product_id']), $warehouse_id, -$q, $prev, $newS, $dcNumber, 'Delivery challan ' . $dcNumber, $adminUsername]);
                }
                $pdo->exec("RELEASE SAVEPOINT dc_stock_out");
            }
        } catch (Throwable $e) {
            try { $pdo->exec("ROLLBACK TO SAVEPOINT dc_stock_out"); } catch (PDOException $e2) { /* no savepoint yet */ }
            // The DC itself still saves; stock was not changed. Do a manual Stock Out if needed.
            error_log('DC auto stock-out skipped for ' . $dcNumber . ': ' . $e->getMessage());
        }
    }

    // FIX (3 Oct 2026): the sales order follows its DC (ready / loaded → ready for dispatch, dispatched → dispatched, delivered → delivered) — forward only
    $soNext = ['ready' => 'ready_for_dispatch', 'loaded' => 'ready_for_dispatch', 'dispatched' => 'dispatched', 'in_transit' => 'dispatched', 'delivered' => 'delivered'][$delivery_status] ?? null;
    if ($sales_order_id && $soNext) {
        $pdo->prepare("UPDATE sales_orders SET status = ?, updated_by = ? WHERE id = ?
                         AND FIELD(status, 'confirmed','processing','ready_for_dispatch','dispatched','delivered') BETWEEN 1 AND FIELD(?, 'confirmed','processing','ready_for_dispatch','dispatched','delivered') - 1")
            ->execute([$soNext, $adminUsername, $sales_order_id, $soNext]);
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
