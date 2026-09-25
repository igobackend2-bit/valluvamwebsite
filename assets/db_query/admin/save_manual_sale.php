<?php
// Two-step Manual Sale entry.
//
// Step 1 — create (no `id`, no `confirm`): saves the sale + its items as a
// record only. Stock is NOT touched yet (stock_deducted stays 0).
//
// Step 2 — confirm (`id` + `confirm=1`): deducts stock for each item,
// transactionally, the same way adjust_stock.php does (SELECT ... FOR
// UPDATE, update product_details.stock, insert a stock_history row).
// Refuses to run twice for the same manual_sale_id via the
// `stock_deducted` guard column — prevents accidental duplicate stock
// deduction from a double form-submit or a repeated confirm click.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();
require_permission($pdo, 'manual_sales.create');

$id = (int)($_POST['id'] ?? 0);
$confirm = ($_POST['confirm'] ?? '') === '1';
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

// ── Step 2: confirm & deduct stock for an already-saved manual sale ───────
if ($id && $confirm) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM manual_sales WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Manual sale not found']);
            exit;
        }
        if ((int)$sale['stock_deducted'] === 1) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Stock has already been deducted for this sale']);
            exit;
        }

        $itemsStmt = $pdo->prepare("SELECT * FROM manual_sale_items WHERE manual_sale_id = ?");
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($items) === 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'This sale has no line items to deduct']);
            exit;
        }

        foreach ($items as $item) {
            $prodStmt = $pdo->prepare("SELECT stock, product_name FROM product_details WHERE id = ? FOR UPDATE");
            $prodStmt->execute([$item['product_id']]);
            $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) continue; // product deleted since — skip rather than fail the whole sale

            $qty = (float)$item['quantity'];
            $newStock = max(0, (int)$product['stock'] - (int)ceil($qty));

            $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?")->execute([$newStock, $item['product_id']]);

            $reason = 'Manual sale ' . $sale['sale_number'];
            $insHist = $pdo->prepare("INSERT INTO stock_history (product_id, change_type, quantity_change, resulting_stock, reason, admin_username, created_at)
                                       VALUES (?, 'stock_out', ?, ?, ?, ?, NOW())");
            $insHist->execute([$item['product_id'], -abs($qty), $newStock, $reason, $adminUsername]);
        }

        $pdo->prepare("UPDATE manual_sales SET stock_deducted = 1 WHERE id = ?")->execute([$id]);

        $pdo->commit();

        log_audit($pdo, 'update', 'manual_sales', $id, ['stock_deducted' => 0], ['stock_deducted' => 1]);

        echo json_encode(['status' => 'success', 'id' => $id, 'stock_deducted' => 1]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error confirming manual sale stock deduction: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to deduct stock: ' . $e->getMessage()]);
    }
    exit;
}

// ── Step 1: create the manual sale record (no stock movement yet) ─────────
$sales_date      = trim($_POST['sales_date'] ?? date('Y-m-d'));
$customer_name   = trim($_POST['customer_name'] ?? '');
$customer_mobile = trim($_POST['customer_mobile'] ?? '');
$customer_address = trim($_POST['customer_address'] ?? '');
$payment_mode    = trim($_POST['payment_mode'] ?? 'cash');
$payment_status  = trim($_POST['payment_status'] ?? 'paid');
$salesperson     = trim($_POST['salesperson'] ?? $adminUsername);
$warehouse_id    = (int)($_POST['warehouse_id'] ?? 1) ?: 1;
$notes           = trim($_POST['notes'] ?? '');
$itemsRaw        = $_POST['items'] ?? '[]';

$validModes = ['cash','upi','bank_transfer','card','credit','other'];
$validPayStatus = ['paid','partially_paid','pending','credit'];
if (!in_array($payment_mode, $validModes, true)) $payment_mode = 'cash';
if (!in_array($payment_status, $validPayStatus, true)) $payment_status = 'paid';

if (!$customer_name) {
    echo json_encode(['status' => 'error', 'message' => 'Customer name is required']);
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
    $lineBase = ($rate * $quantity) - $discount;
    $lineTax = $lineBase * ($tax / 100);
    $lineTotal = $lineBase + $lineTax;
    $subtotal += $lineBase;
    $totalTax += $lineTax;
    $cleanItems[] = compact('product_id', 'quantity', 'rate', 'discount', 'tax', 'lineTotal');
}
if (count($cleanItems) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'At least one valid line item is required']);
    exit;
}
$grandTotal = $subtotal + $totalTax;

try {
    $pdo->beginTransaction();

    $saleNumber = next_document_number($pdo, 'manual_sale', 'MS');

    $ins = $pdo->prepare("INSERT INTO manual_sales (sale_number, sales_date, customer_name, customer_mobile, customer_address,
        payment_mode, payment_status, salesperson, warehouse_id, subtotal, total_tax, grand_total, notes, stock_deducted, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)");
    $ins->execute([
        $saleNumber, $sales_date, $customer_name, $customer_mobile ?: null, $customer_address ?: null,
        $payment_mode, $payment_status, $salesperson ?: null, $warehouse_id, $subtotal, $totalTax, $grandTotal, $notes ?: null, $adminUsername
    ]);
    $saleId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO manual_sale_items (manual_sale_id, product_id, quantity, rate, discount, tax, line_total) VALUES (?,?,?,?,?,?,?)");
    foreach ($cleanItems as $ci) {
        $itemStmt->execute([$saleId, $ci['product_id'], $ci['quantity'], $ci['rate'], $ci['discount'], $ci['tax'], $ci['lineTotal']]);
    }

    $pdo->commit();

    log_audit($pdo, 'create', 'manual_sales', $saleId, null, ['sale_number' => $saleNumber, 'grand_total' => $grandTotal]);

    echo json_encode(['status' => 'success', 'id' => $saleId, 'sale_number' => $saleNumber, 'grand_total' => $grandTotal]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving manual sale: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save manual sale: ' . $e->getMessage()]);
}
