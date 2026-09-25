<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

$id = (int)($_GET['id'] ?? 0);
$invoice = null;
$items = [];
$storeName = 'Valluvam Products';

try {
    $stmt = $pdo->prepare("SELECT store_name FROM admin_settings LIMIT 1");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['store_name'])) $storeName = $row['store_name'];
    }
} catch (PDOException $e) {
    // admin_settings not present yet — fall back to default store name.
}

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            $itemsStmt = $pdo->prepare("SELECT ii.*, pd.product_name FROM invoice_items ii
                                         LEFT JOIN product_details pd ON pd.id = ii.product_id
                                         WHERE ii.invoice_id = ? ORDER BY ii.id ASC");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $invoice = null;
    }
}

function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?php echo $invoice ? esc($invoice['invoice_number']) : ''; ?> — <?php echo esc($storeName); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; color:#222; margin:30px; }
    .print-head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1c5034; padding-bottom:10px; margin-bottom:16px; }
    .print-head h1 { margin:0; color:#1c5034; font-size:20px; }
    table { width:100%; border-collapse:collapse; margin-top:12px; }
    th, td { border:1px solid #ccc; padding:6px 8px; font-size:13px; text-align:left; }
    th { background:#f2f2f2; }
    .totals { margin-top:12px; text-align:right; font-size:14px; }
    .btn-print { margin-bottom:16px; padding:8px 16px; background:#1c5034; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
    @media print { .btn-print { display:none; } }
</style>
</head>
<body>
<?php if (!$invoice): ?>
    <p>Invoice not found.</p>
<?php else: ?>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
    <div class="print-head">
        <div><h1><?php echo esc($storeName); ?></h1><div>Tax Invoice</div></div>
        <div style="text-align:right;">
            <div><strong>Invoice #:</strong> <?php echo esc($invoice['invoice_number']); ?></div>
            <div><strong>Date:</strong> <?php echo esc($invoice['invoice_date']); ?></div>
            <?php if ($invoice['due_date']): ?><div><strong>Due:</strong> <?php echo esc($invoice['due_date']); ?></div><?php endif; ?>
            <div><strong>Status:</strong> <?php echo esc(str_replace('_',' ', $invoice['status'])); ?></div>
        </div>
    </div>
    <p>
        <strong>Bill to:</strong> <?php echo esc($invoice['customer_name']); ?><br>
        <?php if ($invoice['customer_mobile']): ?>Mobile: <?php echo esc($invoice['customer_mobile']); ?><br><?php endif; ?>
        <?php if ($invoice['billing_address']): ?><?php echo nl2br(esc($invoice['billing_address'])); ?><?php endif; ?>
    </p>
    <table>
        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Discount</th><th>Tax %</th><th>Line total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i => $it): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo esc($it['product_name'] ?? ('#' . $it['product_id'])); ?></td>
                <td><?php echo esc($it['quantity']); ?></td>
                <td><?php echo esc($it['unit']); ?></td>
                <td>₹<?php echo number_format((float)$it['rate'], 2); ?></td>
                <td>₹<?php echo number_format((float)$it['discount'], 2); ?></td>
                <td><?php echo number_format((float)$it['tax'], 2); ?>%</td>
                <td>₹<?php echo number_format((float)$it['line_total'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="totals">
        <div>Subtotal: ₹<?php echo number_format((float)$invoice['subtotal'], 2); ?></div>
        <div>Tax: ₹<?php echo number_format((float)$invoice['tax_amount'], 2); ?></div>
        <div><strong>Grand total: ₹<?php echo number_format((float)$invoice['grand_total'], 2); ?></strong></div>
        <div>Amount paid: ₹<?php echo number_format((float)$invoice['amount_paid'], 2); ?></div>
        <div>Balance due: ₹<?php echo number_format((float)$invoice['grand_total'] - (float)$invoice['amount_paid'], 2); ?></div>
    </div>
    <?php if ($invoice['notes']): ?><p><strong>Notes:</strong> <?php echo nl2br(esc($invoice['notes'])); ?></p><?php endif; ?>
<?php endif; ?>
</body>
</html>
