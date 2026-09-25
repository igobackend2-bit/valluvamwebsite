<?php
// Shared helper: auto-generate a billing Invoice straight from a Sales
// Order's own items/amounts — one invoice per customer order, no manual
// re-typing. Used by (1) save_sales_order.php right after a sales order is
// confirmed, and (2) generate_invoices_for_sales_orders.php, the one-off
// "Generate from Sales Orders" backfill button on the Invoices page for
// sales orders that already existed before this feature.
//
// Never throws for "nothing to do" cases (draft order, already invoiced,
// cancelled) — just returns null so callers can treat this as best-effort
// and not block the sales-order save itself.

/**
 * @return array{id:int, invoice_number:string}|null
 */
function auto_generate_invoice_for_sales_order(PDO $pdo, int $salesOrderId, string $adminUsername): ?array {
    $soStmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
    $soStmt->execute([$salesOrderId]);
    $so = $soStmt->fetch(PDO::FETCH_ASSOC);
    if (!$so) return null;

    // Only orders that are actually a real, confirmed sale get billed.
    if (in_array($so['status'], ['draft', 'cancelled'], true)) return null;

    // One active invoice per sales order — never duplicate.
    $dupStmt = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE sales_order_id = ? AND status != 'cancelled' LIMIT 1");
    $dupStmt->execute([$salesOrderId]);
    if ($dupStmt->fetch(PDO::FETCH_ASSOC)) return null;

    $itemsStmt = $pdo->prepare("SELECT * FROM sales_order_items WHERE sales_order_id = ?");
    $itemsStmt->execute([$salesOrderId]);
    $soItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($soItems) === 0) return null;

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $invNumber = next_document_number($pdo, 'invoice', 'INV');

        // Due date: 7 days after the order date, unless the SO carries its
        // own payment terms text — the date itself still defaults to +7d.
        $dueDate = date('Y-m-d', strtotime(($so['order_date'] ?: date('Y-m-d')) . ' +7 days'));

        $ins = $pdo->prepare("INSERT INTO invoices (invoice_number, invoice_date, customer_id, customer_name, customer_mobile, customer_email,
            billing_address, shipping_address, sales_order_id, dc_number, due_date, payment_mode, status, subtotal, tax_amount, grand_total,
            amount_paid, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,NULL,?,NULL,'issued',?,?,?,0,?,?)");
        $ins->execute([
            $invNumber, $so['order_date'] ?: date('Y-m-d'), $so['customer_id'], $so['customer_name'], $so['customer_mobile'], $so['customer_email'],
            $so['billing_address'] ?: $so['customer_address'], $so['shipping_address'] ?: $so['customer_address'], $salesOrderId, $dueDate,
            $so['subtotal'], $so['total_tax'], $so['grand_total'],
            'Auto-generated from sales order ' . $so['so_number'], $adminUsername
        ]);
        $invId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, product_id, sku, quantity, unit, rate, discount, tax, line_total) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($soItems as $it) {
            $itemStmt->execute([
                $invId, $it['product_id'], $it['sku'] ?: null, $it['quantity'], $it['unit'],
                $it['rate'], $it['discount'], $it['tax'], $it['line_total']
            ]);
        }

        if ($ownTransaction) $pdo->commit();

        if (function_exists('log_audit')) {
            log_audit($pdo, 'create', 'invoices', $invId, null, [
                'invoice_number' => $invNumber, 'auto_generated_from_so' => $so['so_number'], 'grand_total' => $so['grand_total']
            ]);
        }

        return ['id' => $invId, 'invoice_number' => $invNumber];
    } catch (Exception $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        error_log("auto_generate_invoice_for_sales_order failed for SO #{$salesOrderId}: " . $e->getMessage());
        return null;
    }
}
