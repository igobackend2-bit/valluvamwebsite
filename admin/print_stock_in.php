<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

$id = $_GET['id'] ?? 0;
$stockIn = null;
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
        $stmt = $pdo->prepare("SELECT si.*, s.supplier_name, s.company_name, w.name AS warehouse_name
                                FROM stock_ins si
                                LEFT JOIN suppliers s ON s.id = si.supplier_id
                                LEFT JOIN warehouses w ON w.id = si.warehouse_id
                                WHERE si.id = ?");
        $stmt->execute([$id]);
        $stockIn = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stockIn) {
            $itemsStmt = $pdo->prepare("SELECT sii.*, pd.product_name
                                         FROM stock_in_items sii
                                         LEFT JOIN product_details pd ON pd.id = sii.product_id
                                         WHERE sii.stock_in_id = ?
                                         ORDER BY sii.id ASC");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $stockIn = null;
    }
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock In <?= $stockIn ? h($stockIn['stock_in_number']) : '' ?> — Print</title>
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
    <div class="sub">Stock In Document</div>

    <?php if (!$stockIn): ?>
        <p>Stock in record not found.</p>
    <?php else: ?>
        <h2><?= h($stockIn['stock_in_number']) ?></h2>
        <div class="meta">
            <div><span>Date:</span> <?= h($stockIn['stock_in_date']) ?></div>
            <div><span>Status:</span> <?= h($stockIn['status']) ?></div>
            <div><span>Supplier:</span> <?= h($stockIn['supplier_name'] ?? 'No supplier / cash purchase') ?></div>
            <div><span>Purchase reference:</span> <?= h($stockIn['purchase_reference']) ?></div>
            <div><span>Warehouse:</span> <?= h($stockIn['warehouse_name']) ?></div>
            <div><span>Received by:</span> <?= h($stockIn['received_by']) ?></div>
            <div><span>Vehicle number:</span> <?= h($stockIn['vehicle_number']) ?></div>
            <div><span>Remarks:</span> <?= h($stockIn['remarks']) ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th><th>Product</th><th>SKU</th><th>Qty</th><th>Unit</th>
                    <th>Batch #</th><th>Expiry</th><th>Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= h($item['sku']) ?></td>
                    <td><?= h($item['quantity']) ?></td>
                    <td><?= h($item['unit']) ?></td>
                    <td><?= h($item['batch_number']) ?></td>
                    <td><?= h($item['expiry_date']) ?></td>
                    <td><?= $item['purchase_rate'] !== null ? '₹' . h(number_format((float)$item['purchase_rate'], 2)) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($items) === 0): ?>
                <tr><td colspan="8">No line items.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
