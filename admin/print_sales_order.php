<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

$id = (int)($_GET['id'] ?? 0);
$order = null;
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
        $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $itemsStmt = $pdo->prepare("SELECT soi.*, pd.product_name FROM sales_order_items soi
                                         LEFT JOIN product_details pd ON pd.id = soi.product_id
                                         WHERE soi.sales_order_id = ? ORDER BY soi.id ASC");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $order = null;
    }
}

function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Order <?php echo $order ? esc($order['so_number']) : ''; ?> — <?php echo esc($storeName); ?></title>
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
<?php if (!$order): ?>
    <p>Sales order not found.</p>
<?php else: ?>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
    <div class="print-head">
        <div><h1><?php echo esc($storeName); ?></h1><div>Sales Order</div></div>
        <div style="text-align:right;">
            <div><strong>SO #:</strong> <?php echo esc($order['so_number']); ?></div>
            <div><strong>Date:</strong> <?php echo esc($order['order_date']); ?></div>
            <div><strong>Status:</strong> <?php echo esc(str_replace('_',' ', $order['status'])); ?></div>
        </div>
    </div>
    <p>
        <strong>Bill to:</strong> <?php echo esc($order['customer_name']); ?><br>
        <?php if ($order['customer_mobile']): ?>Mobile: <?php echo esc($order['customer_mobile']); ?><br><?php endif; ?>
        <?php if ($order['shipping_address']): ?><?php echo nl2br(esc($order['shipping_address'])); ?><?php endif; ?>
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
        <div>Subtotal: ₹<?php echo number_format((float)$order['subtotal'], 2); ?></div>
        <div>Tax: ₹<?php echo number_format((float)$order['total_tax'], 2); ?></div>
        <div><strong>Grand total: ₹<?php echo number_format((float)$order['grand_total'], 2); ?></strong></div>
    </div>
    <?php if ($order['notes']): ?><p><strong>Notes:</strong> <?php echo nl2br(esc($order['notes'])); ?></p><?php endif; ?>
<?php endif; ?>
</body>
</html>
