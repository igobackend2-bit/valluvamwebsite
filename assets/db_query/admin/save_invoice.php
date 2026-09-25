<?php
// Create or update an Invoice (+ items), transactionally.
// POST fields: id (optional, update), invoice_date, customer_id, customer_name,
// customer_mobile, customer_email, billing_address, shipping_address,
// sales_order_id (optional), dc_number (optional), due_date, payment_mode,
// status, notes, items (JSON array of {product_id, sku, quantity, unit, rate, discount, tax}).
//
// Duplicate-invoice prevention: when creating from a sales_order_id, refuses
// if a non-cancelled invoice already exists for that sales order.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$id = (int)($_POST['id'] ?? 0);
// No separate invoices.edit permission is seeded — creating and editing a
// draft/issued invoice both require invoices.create.
require_permission($pdo, 'invoices.create');

$invoice_date     = trim($_POST['invoice_date'] ?? date('Y-m-d'));
$customer_id      = trim($_POST['customer_id'] ?? '') !== '' ? (int)$_POST['customer_id'] : null;
$customer_name    = trim($_POST['customer_name'] ?? '');
$customer_mobile  = trim($_POST['customer_mobile'] ?? '');
$customer_email   = trim($_POST['customer_email'] ?? '');
$billing_address  = trim($_POST['billing_address'] ?? '');
$shipping_address = trim($_POST['shipping_address'] ?? '');
$sales_order_id   = trim($_POST['sales_order_id'] ?? '') !== '' ? (int)$_POST['sales_order_id'] : null;
$dc_number        = trim($_POST['dc_number'] ?? '');
$due_date         = trim($_POST['due_date'] ?? '') ?: null;
$payment_mode     = trim($_POST['payment_mode'] ?? '');
$status           = trim($_POST['status'] ?? 'draft');
$notes            = trim($_POST['notes'] ?? '');
$itemsRaw         = $_POST['items'] ?? '[]';

$validStatuses = ['draft','issued','partially_paid','paid','overdue','cancelled'];
if (!in_array($status, $validStatuses, true)) $status = 'draft';
$validModes = ['cash','upi','bank_transfer','card','credit','other'];
if ($payment_mode !== '' && !in_array($payment_mode, $validModes, true)) $payment_mode = '';

if (!$customer_id && $customer_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Select an existing customer or enter a walk-in customer name']);
    exit;
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one line item is required']);
    exit;
}

$subtotal = 0;
$totalTax = 0;
$cleanItems = [];
foreach ($items as $it) {
    $product_id = (int)($it['product_id'] ?? 0);
    $quantity   = (float)($it['quantity'] ?? 0);
    if (!$product_id || $quantity <= 0) continue;
    $rate = (float)($it['rate'] ?? 0);
    $discount = (float)($it['discount'] ?? 0);
    $tax = (float)($it['tax'] ?? 0);
    $unit = trim($it['unit'] ?? 'pcs') ?: 'pcs';
    $sku = trim($it['sku'] ?? '');
    $lineBase = ($rate * $quantity) - $discount;
    $lineTax = $lineBase * ($tax / 100);
    $lineTotal = $lineBase + $lineTax;
    $subtotal += $lineBase;
    $totalTax += $lineTax;
    $cleanItems[] = ['product_id'=>$product_id,'sku'=>$sku,'quantity'=>$quantity,'unit'=>$unit,'rate'=>$rate,'discount'=>$discount,'tax'=>$tax,'line_total'=>$lineTotal];
}
if (count($cleanItems) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one valid line item is required']);
    exit;
}
$grandTotal = $subtotal + $totalTax;
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $pdo->beginTransaction();

    // Duplicate-invoice prevention: one active invoice per sales order.
    if ($sales_order_id) {
        $dupStmt = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE sales_order_id = ? AND status != 'cancelled' " . ($id ? "AND id != ?" : ""));
        $dupParams = $id ? [$sales_order_id, $id] : [$sales_order_id];
        $dupStmt->execute($dupParams);
        if ($dup = $dupStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => "An invoice ({$dup['invoice_number']}) already exists for this sales order"]);
            exit;
        }
    }

    $oldRow = null;
    if ($id) {
        $old = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $old->execute([$id]);
        $oldRow = $old->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
            exit;
        }
        if (in_array($oldRow['status'], ['paid','cancelled'], true)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'A paid or cancelled invoice cannot be edited']);
            exit;
        }

        $upd = $pdo->prepare("UPDATE invoices SET invoice_date=?, customer_id=?, customer_name=?, customer_mobile=?, customer_email=?,
            billing_address=?, shipping_address=?, sales_order_id=?, dc_number=?, due_date=?, payment_mode=?, status=?,
            subtotal=?, tax_amount=?, grand_total=?, notes=? WHERE id=?");
        $upd->execute([
            $invoice_date, $customer_id, $customer_name ?: null, $customer_mobile ?: null, $customer_email ?: null,
            $billing_address ?: null, $shipping_address ?: null, $sales_order_id, $dc_number ?: null, $due_date,
            $payment_mode ?: null, $status, $subtotal, $totalTax, $grandTotal, $notes ?: null, $id
        ]);

        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
        $invId = $id;
        $invNumber = $oldRow['invoice_number'];
    } else {
        $invNumber = next_document_number($pdo, 'invoice', 'INV');

        $ins = $pdo->prepare("INSERT INTO invoices (invoice_number, invoice_date, customer_id, customer_name, customer_mobile, customer_email,
            billing_address, shipping_address, sales_order_id, dc_number, due_date, payment_mode, status, subtotal, tax_amount, grand_total,
            amount_paid, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)");
        $ins->execute([
            $invNumber, $invoice_date, $customer_id, $customer_name ?: null, $customer_mobile ?: null, $customer_email ?: null,
            $billing_address ?: null, $shipping_address ?: null, $sales_order_id, $dc_number ?: null, $due_date,
            $payment_mode ?: null, $status, $subtotal, $totalTax, $grandTotal, $notes ?: null, $adminUsername
        ]);
        $invId = (int)$pdo->lastInsertId();
    }

    $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, product_id, sku, quantity, unit, rate, discount, tax, line_total) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($cleanItems as $ci) {
        $itemStmt->execute([$invId, $ci['product_id'], $ci['sku'] ?: null, $ci['quantity'], $ci['unit'], $ci['rate'], $ci['discount'], $ci['tax'], $ci['line_total']]);
    }

    $pdo->commit();

    log_audit($pdo, $id ? 'update' : 'create', 'invoices', $invId, $oldRow, ['invoice_number' => $invNumber, 'status' => $status, 'grand_total' => $grandTotal]);

    echo json_encode(['status' => 'success', 'id' => $invId, 'invoice_number' => $invNumber]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving invoice: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save invoice: ' . $e->getMessage()]);
}
