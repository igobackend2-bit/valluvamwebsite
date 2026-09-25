<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

$id = $_GET['id'] ?? 0;
$stockOut = null;
$items = [];
$storeName = 'Valluvam Products';

try {
    $stmt = $pdo->prepare("SELECT store_name FROM admin_settings LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['store_name'])) {
        $storeName = $row['store_name'];
    }
} catch (PDOException $e) {
    // admin_settings not present — use the default store name.
}

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT so.*, w.name AS warehouse_name
                                FROM stock_outs so
                                LEFT JOIN warehouses w ON w.id = so.warehouse_id
                                WHERE so.id = ?");
        $stmt->execute([$id]);
        $stockOut = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stockOut) {
            $itemsStmt = $pdo->prepare("SELECT soi.*, pd.product_name
                                         FROM stock_out_items soi
                                         LEFT JOIN product_details pd ON pd.id = soi.product_id
                                         WHERE soi.stock_out_id = ?
                                         ORDER BY soi.id ASC");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $stockOut = null;
    }
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Out <?= $stockOut ? h($stockOut['stock_out_number']) : '' ?> — Print</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 30px; }
        h1 { font-size: 20px; margin-bottom: 2px; }
        .sub { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; font-size: 13px; }
        th { background: #f2f2f2; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-top: 12px; font-size: 14px; }
        .meta div span { color: #666; }
        .print-btn { margin-bottom: 16px; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <div class="print-btn"><button onclick="window.print()">Print / Save as PDF</button></div>

    <h1><?= h($storeName) ?></h1>
    <div class="sub">Stock Out Document</div>

    <?php if (!$stockOut): ?>
        <p>Stock out record not found.</p>
    <?php else: ?>
        <h2><?= h($stockOut['stock_out_number']) ?></h2>
        <div class="meta">
            <div><span>Date:</span> <?= h($stockOut['stock_out_date']) ?></div>
            <div><span>Reference type:</span> <?= h($stockOut['reference_type']) ?></div>
            <div><span>Reference number:</span> <?= h($stockOut['reference_number']) ?></div>
            <div><span>Warehouse:</span> <?= h($stockOut['warehouse_name']) ?></div>
            <div><span>Customer:</span> <?= h($stockOut['customer_name']) ?></div>
            <div><span>Vehicle number:</span> <?= h($stockOut['vehicle_number']) ?></div>
            <div><span>Authorized by:</span> <?= h($stockOut['authorized_by']) ?></div>
            <div><span>Reason:</span> <?= h($stockOut['reason']) ?></div>
        </div>

        <table>
            <thead>
                <tr><th>#</th><th>Product</th><th>SKU</th><th>Qty</th><th>Unit</th></tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= h($item['sku']) ?></td>
                    <td><?= h($item['quantity']) ?></td>
                    <td><?= h($item['unit']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($items) === 0): ?>
                <tr><td colspan="5">No line items.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
