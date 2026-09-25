<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

$id = (int)($_GET['id'] ?? 0);
$dc = null;
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
        $stmt = $pdo->prepare("SELECT dc.*, so.so_number FROM delivery_challans dc
                                LEFT JOIN sales_orders so ON so.id = dc.sales_order_id WHERE dc.id = ?");
        $stmt->execute([$id]);
        $dc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dc) {
            $itemsStmt = $pdo->prepare("SELECT dci.*, pd.product_name FROM delivery_challan_items dci
                                         LEFT JOIN product_details pd ON pd.id = dci.product_id
                                         WHERE dci.delivery_challan_id = ? ORDER BY dci.id ASC");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $dc = null;
    }
}

function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delivery Challan <?php echo $dc ? esc($dc['dc_number']) : ''; ?> — <?php echo esc($storeName); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; color:#222; margin:30px; }
    .print-head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1c5034; padding-bottom:10px; margin-bottom:16px; }
    .print-head h1 { margin:0; color:#1c5034; font-size:20px; }
    table { width:100%; border-collapse:collapse; margin-top:12px; }
    th, td { border:1px solid #ccc; padding:6px 8px; font-size:13px; text-align:left; }
    th { background:#f2f2f2; }
    .btn-print { margin-bottom:16px; padding:8px 16px; background:#1c5034; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
    @media print { .btn-print { display:none; } }
</style>
</head>
<body>
<?php if (!$dc): ?>
    <p>Delivery challan not found.</p>
<?php else: ?>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
    <div class="print-head">
        <div><h1><?php echo esc($storeName); ?></h1><div>Delivery Challan</div></div>
        <div style="text-align:right;">
            <div><strong>DC #:</strong> <?php echo esc($dc['dc_number']); ?></div>
            <?php if ($dc['so_number']): ?><div><strong>SO #:</strong> <?php echo esc($dc['so_number']); ?></div><?php endif; ?>
            <div><strong>Dispatch date:</strong> <?php echo esc($dc['dispatch_date'] ?: '—'); ?></div>
            <div><strong>Status:</strong> <?php echo esc(str_replace('_',' ', $dc['delivery_status'])); ?></div>
        </div>
    </div>
    <p>
        <strong>Deliver to:</strong> <?php echo esc($dc['customer_name']); ?><br>
        <?php if ($dc['customer_mobile']): ?>Mobile: <?php echo esc($dc['customer_mobile']); ?><br><?php endif; ?>
        <?php if ($dc['delivery_address']): ?><?php echo nl2br(esc($dc['delivery_address'])); ?><?php endif; ?>
    </p>
    <p>
        <strong>Vehicle:</strong> <?php echo esc($dc['vehicle_number'] ?: '—'); ?> &nbsp;
        <strong>Driver:</strong> <?php echo esc($dc['driver_name'] ?: '—'); ?>
        <?php if ($dc['driver_mobile']): ?> (<?php echo esc($dc['driver_mobile']); ?>)<?php endif; ?>
    </p>
    <table>
        <thead><tr><th>#</th><th>Product</th><th>Quantity</th><th>Unit</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i => $it): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo esc($it['product_name'] ?? ('#' . $it['product_id'])); ?></td>
                <td><?php echo esc($it['quantity']); ?></td>
                <td><?php echo esc($it['unit']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($dc['remarks']): ?><p><strong>Remarks:</strong> <?php echo nl2br(esc($dc['remarks'])); ?></p><?php endif; ?>
    <p style="margin-top:40px;">Receiver's signature: ______________________</p>
<?php endif; ?>
</body>
</html>
