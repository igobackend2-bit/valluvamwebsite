<?php
// Two-step Credit Sale entry (same pattern as save_manual_sale.php).
//
// Step 1 — create (no `id`, no `confirm`): saves the credit sale + its
// items. Stock is NOT touched yet (stock_deducted stays 0). An optional
// "amount received now" can be recorded as an advance against the total.
//
// Step 2 — confirm (`id` + `confirm=1`): deducts stock for each item,
// transactionally (SELECT ... FOR UPDATE), and logs to stock_movements —
// the modern stock ledger — exactly like every other stock-out in this
// system. Guarded by stock_deducted so it can only run once per sale.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/credit_sale_helper.php';

require_admin_session();
require_permission($pdo, 'credit_sales.create');

ensure_credit_sale_tables($pdo);

$id = (int)($_POST['id'] ?? 0);
$confirm = ($_POST['confirm'] ?? '') === '1';
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

// ── Step 2: confirm & deduct stock for an already-saved credit sale ───────
if ($id && $confirm) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM credit_sales WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Credit sale not found']);
            exit;
        }
        if ((int)$sale['stock_deducted'] === 1) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Stock has already been deducted for this sale']);
            exit;
        }

        $itemsStmt = $pdo->prepare("SELECT * FROM credit_sale_items WHERE credit_sale_id = ?");
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($items) === 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'This sale has no line items to deduct']);
            exit;
        }

        foreach ($items as $item) {
            $prodStmt = $pdo->prepare("SELECT id, stock, product_name, CONCAT('PRD-', id) AS sku FROM product_details WHERE id = ? FOR UPDATE");
            $prodStmt->execute([$item['product_id']]);
            $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) continue; // product deleted since — skip rather than fail the whole sale

            $qty = (float)$item['quantity'];
            $previousStock = (int)$product['stock'];
            $newStock = max(0, $previousStock - (int)ceil($qty));

            $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?")->execute([$newStock, $item['product_id']]);

            try {
                $movementInsert = $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id,
                                                  quantity, previous_stock, new_stock, reference_type, reference_number,
                                                  reason, created_by, created_at)
                                                  VALUES ('stock_out', ?, ?, ?, ?, ?, ?, 'credit_sale', ?, ?, ?, NOW())");
                $movementInsert->execute([
                    $item['product_id'], $product['sku'], $sale['warehouse_id'],
                    abs($qty), $previousStock, $newStock,
                    $sale['credit_number'], 'Credit sale ' . $sale['credit_number'], $adminUsername,
                ]);
            } catch (PDOException $e) {
                error_log("save_credit_sale: stock_movements insert failed: " . $e->getMessage());
            }
        }

        $pdo->prepare("UPDATE credit_sales SET stock_deducted = 1 WHERE id = ?")->execute([$id]);

        $pdo->commit();

        log_audit($pdo, 'update', 'credit_sales', $id, ['stock_deducted' => 0], ['stock_deducted' => 1]);

        echo json_encode(['status' => 'success', 'id' => $id, 'stock_deducted' => 1]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error confirming credit sale stock deduction: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to deduct stock: ' . $e->getMessage()]);
    }
    exit;
}

// ── Step 1: create the credit sale record (no stock movement yet) ─────────
$sale_date        = trim($_POST['sale_date'] ?? date('Y-m-d'));
$customer_name     = trim($_POST['customer_name'] ?? '');
$customer_mobile   = trim($_POST['customer_mobile'] ?? '');
$customer_address  = trim($_POST['customer_address'] ?? '');
$warehouse_id      = (int)($_POST['warehouse_id'] ?? 1) ?: 1;
$notes             = trim($_POST['notes'] ?? '');
$amount_received   = (float)($_POST['amount_received'] ?? 0);
$payment_mode      = trim($_POST['payment_mode'] ?? '');
$itemsRaw          = $_POST['items'] ?? '[]';

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

if ($amount_received < 0) $amount_received = 0;
if ($amount_received > $grandTotal) $amount_received = $grandTotal;
$status = $amount_received <= 0 ? 'outstanding' : ($amount_received >= $grandTotal ? 'paid' : 'partially_paid');

try {
    $pdo->beginTransaction();

    $creditNumber = next_document_number($pdo, 'credit_sale', 'CR');

    $ins = $pdo->prepare("INSERT INTO credit_sales (credit_number, sale_date, customer_name, customer_mobile, customer_address,
        warehouse_id, subtotal, total_tax, grand_total, amount_paid, status, notes, stock_deducted, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?)");
    $ins->execute([
        $creditNumber, $sale_date, $customer_name, $customer_mobile ?: null, $customer_address ?: null,
        $warehouse_id, $subtotal, $totalTax, $grandTotal, $amount_received, $status, $notes ?: null, $adminUsername
    ]);
    $saleId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO credit_sale_items (credit_sale_id, product_id, quantity, rate, discount, tax, line_total) VALUES (?,?,?,?,?,?,?)");
    foreach ($cleanItems as $ci) {
        $itemStmt->execute([$saleId, $ci['product_id'], $ci['quantity'], $ci['rate'], $ci['discount'], $ci['tax'], $ci['lineTotal']]);
    }

    if ($amount_received > 0) {
        $payStmt = $pdo->prepare("INSERT INTO credit_sale_payments (credit_sale_id, amount, payment_mode, notes, created_by) VALUES (?,?,?,?,?)");
        $payStmt->execute([$saleId, $amount_received, $payment_mode ?: null, 'Received at time of sale', $adminUsername]);
    }

    $pdo->commit();

    log_audit($pdo, 'create', 'credit_sales', $saleId, null, ['credit_number' => $creditNumber, 'grand_total' => $grandTotal, 'amount_paid' => $amount_received]);

    // Best-effort Accounts integration, same pattern as record_invoice_payment.php.
    if ($amount_received > 0) {
        try {
            $tx = $pdo->prepare("INSERT INTO accounts_transactions (type, reference_type, reference_number, amount, payment_mode, created_by, created_at)
                                  VALUES ('payment_received', 'credit_sale', ?, ?, ?, ?, NOW())");
            $tx->execute([$creditNumber, $amount_received, $payment_mode ?: null, $adminUsername]);
        } catch (PDOException $e) {
            error_log('accounts_transactions integration skipped (table not ready): ' . $e->getMessage());
        }
    }

    echo json_encode(['status' => 'success', 'id' => $saleId, 'credit_number' => $creditNumber, 'grand_total' => $grandTotal]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error saving credit sale: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save credit sale: ' . $e->getMessage()]);
}
