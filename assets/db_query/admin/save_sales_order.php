<?php
// Create or update a Sales Order + its line items, transactionally.
// POST fields: id (optional, update), order_date, customer_id (optional),
// customer_name, customer_mobile, customer_email, customer_address,
// billing_address, shipping_address, salesperson, warehouse_id,
// payment_terms, delivery_terms, expected_delivery_date, notes,
// internal_notes, status, items (JSON array of {product_id, sku, quantity,
// unit, rate, discount, tax}).
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_POST['id'] ?? 0);
require_permission($pdo, $id ? 'sales_orders.edit' : 'sales_orders.create');

$order_date       = trim($_POST['order_date'] ?? date('Y-m-d'));
$customer_id      = trim($_POST['customer_id'] ?? '') !== '' ? (int)$_POST['customer_id'] : null;
$customer_name    = trim($_POST['customer_name'] ?? '');
$customer_mobile  = trim($_POST['customer_mobile'] ?? '');
$customer_email   = trim($_POST['customer_email'] ?? '');
$customer_address = trim($_POST['customer_address'] ?? '');
$billing_address  = trim($_POST['billing_address'] ?? '');
$shipping_address = trim($_POST['shipping_address'] ?? '');
$salesperson      = trim($_POST['salesperson'] ?? ($_SESSION['admin_username'] ?? ''));
$warehouse_id     = (int)($_POST['warehouse_id'] ?? 1) ?: 1;
$payment_terms    = trim($_POST['payment_terms'] ?? '');
$delivery_terms   = trim($_POST['delivery_terms'] ?? '');
$expected_delivery_date = trim($_POST['expected_delivery_date'] ?? '') ?: null;
$notes            = trim($_POST['notes'] ?? '');
$internal_notes   = trim($_POST['internal_notes'] ?? '');
$status           = trim($_POST['status'] ?? 'draft');
$itemsRaw         = $_POST['items'] ?? '[]';

$validStatuses = ['draft','confirmed','processing','ready_for_dispatch','dispatched','delivered','completed','cancelled'];
if (!in_array($status, $validStatuses, true)) {
    $status = 'draft';
}

if (!$customer_id && $customer_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Select an existing customer or enter a walk-in customer name']);
    exit;
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one line item is required']);
    exit;
}

// Normalize + compute totals server-side (never trust client math).
$subtotal = 0;
$totalTax = 0;
$cleanItems = [];
foreach ($items as $it) {
    $product_id = (int)($it['product_id'] ?? 0);
    $quantity   = (float)($it['quantity'] ?? 0);
    if (!$product_id || $quantity <= 0) {
        continue;
    }
    $rate     = (float)($it['rate'] ?? 0);
    $discount = (float)($it['discount'] ?? 0);
    $tax      = (float)($it['tax'] ?? 0);
    $unit     = trim($it['unit'] ?? 'pcs') ?: 'pcs';
    $sku      = trim($it['sku'] ?? '');
    $lineBase = ($rate * $quantity) - $discount;
    $lineTax  = $lineBase * ($tax / 100);
    $lineTotal = $lineBase + $lineTax;

    $subtotal += $lineBase;
    $totalTax += $lineTax;

    $cleanItems[] = [
        'product_id' => $product_id,
        'sku' => $sku,
        'quantity' => $quantity,
        'unit' => $unit,
        'rate' => $rate,
        'discount' => $discount,
        'tax' => $tax,
        'line_total' => $lineTotal,
    ];
}

if (count($cleanItems) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one valid line item is required']);
    exit;
}

$grandTotal = $subtotal + $totalTax;
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    if ($id) {
        $old = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $old->execute([$id]);
        $oldRow = $old->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Sales order not found']);
            exit;
        }
        if (!in_array($oldRow['status'], ['draft','confirmed'], true)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Only draft or confirmed sales orders can be edited']);
            exit;
        }

        $upd = $pdo->prepare("UPDATE sales_orders SET order_date=?, customer_id=?, customer_name=?, customer_mobile=?, customer_email=?,
            customer_address=?, billing_address=?, shipping_address=?, salesperson=?, warehouse_id=?, payment_terms=?, delivery_terms=?,
            expected_delivery_date=?, notes=?, internal_notes=?, status=?, subtotal=?, total_tax=?, grand_total=?, updated_by=? WHERE id=?");
        $upd->execute([
            $order_date, $customer_id, $customer_name ?: null, $customer_mobile ?: null, $customer_email ?: null,
            $customer_address ?: null, $billing_address ?: null, $shipping_address ?: null, $salesperson ?: null, $warehouse_id,
            $payment_terms ?: null, $delivery_terms ?: null, $expected_delivery_date, $notes ?: null, $internal_notes ?: null,
            $status, $subtotal, $totalTax, $grandTotal, $adminUsername, $id
        ]);

        $pdo->prepare("DELETE FROM sales_order_items WHERE sales_order_id = ?")->execute([$id]);
        $soId = $id;
        $soNumber = $oldRow['so_number'];
    } else {
        $soNumber = next_document_number($pdo, 'sales_order', 'SO');

        $ins = $pdo->prepare("INSERT INTO sales_orders (so_number, order_date, customer_id, customer_name, customer_mobile, customer_email,
            customer_address, billing_address, shipping_address, salesperson, warehouse_id, payment_terms, delivery_terms,
            expected_delivery_date, notes, internal_notes, status, subtotal, total_tax, grand_total, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([
            $soNumber, $order_date, $customer_id, $customer_name ?: null, $customer_mobile ?: null, $customer_email ?: null,
            $customer_address ?: null, $billing_address ?: null, $shipping_address ?: null, $salesperson ?: null, $warehouse_id,
            $payment_terms ?: null, $delivery_terms ?: null, $expected_delivery_date, $notes ?: null, $internal_notes ?: null,
            $status, $subtotal, $totalTax, $grandTotal, $adminUsername
        ]);
        $soId = (int)$pdo->lastInsertId();
    }

    $itemStmt = $pdo->prepare("INSERT INTO sales_order_items (sales_order_id, product_id, sku, quantity, unit, rate, discount, tax, line_total)
                                VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($cleanItems as $ci) {
        $itemStmt->execute([$soId, $ci['product_id'], $ci['sku'] ?: null, $ci['quantity'], $ci['unit'], $ci['rate'], $ci['discount'], $ci['tax'], $ci['line_total']]);
    }

    $pdo->commit();

    log_audit($pdo, $id ? 'update' : 'create', 'sales_orders', $soId, $id ? ($oldRow ?? null) : null, [
        'so_number' => $soNumber, 'status' => $status, 'grand_total' => $grandTotal
    ]);

    echo json_encode(['status' => 'success', 'id' => $soId, 'so_number' => $soNumber]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving sales order: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save sales order: ' . $e->getMessage()]);
}
