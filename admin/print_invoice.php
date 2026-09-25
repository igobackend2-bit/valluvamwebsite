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

// Small amount-in-words helper (Indian numbering: lakh/crore) — a standard
// part of a "proper" tax invoice format. Pure function, no DB/schema touch.
function number_to_indian_words(float $num): string {
    $num = round($num);
    if ($num == 0) return 'Zero';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
              'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $twoDigits = function ($n) use ($ones, $tens) {
        if ($n < 20) return $ones[$n];
        return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };
    $threeDigits = function ($n) use ($twoDigits) {
        $s = '';
        if ($n >= 100) { $s .= $twoDigits(intdiv($n, 100)) . ' Hundred '; $n %= 100; }
        $s .= $twoDigits($n);
        return trim($s);
    };
    $n = (int)$num;
    $crore = intdiv($n, 10000000); $n %= 10000000;
    $lakh = intdiv($n, 100000); $n %= 100000;
    $thousand = intdiv($n, 1000); $n %= 1000;
    $hundred = $n;
    $parts = [];
    if ($crore) $parts[] = $threeDigits($crore) . ' Crore';
    if ($lakh) $parts[] = $threeDigits($lakh) . ' Lakh';
    if ($thousand) $parts[] = $threeDigits($thousand) . ' Thousand';
    if ($hundred) $parts[] = $threeDigits($hundred);
    return trim(implode(' ', $parts)) ?: 'Zero';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?php echo $invoice ? esc($invoice['invoice_number']) : ''; ?> — <?php echo esc($storeName); ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, Helvetica, sans-serif; color:#2a2a2a; margin:0; background:#e9e9e9; }
    .sheet { max-width:850px; margin:24px auto; background:#fff; padding:36px 40px; box-shadow:0 0 12px rgba(0,0,0,0.12); }
    .print-head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #1c5034; padding-bottom:16px; margin-bottom:20px; }
    .brand { display:flex; align-items:center; gap:14px; }
    .brand img { height:60px; width:auto; object-fit:contain; }
    .brand h1 { margin:0; color:#1c5034; font-size:22px; }
    .brand .tagline { font-size:12px; color:#777; margin-top:2px; }
    .inv-title { text-align:right; }
    .inv-title h2 { margin:0 0 6px; color:#1c5034; font-size:20px; letter-spacing:1px; }
    .inv-meta-table { font-size:13px; }
    .inv-meta-table td { padding:2px 0 2px 12px; text-align:right; }
    .inv-meta-table td:first-child { color:#777; padding-left:0; }
    .status-pill { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .status-paid { background:#e3f3e9; color:#1c5034; }
    .status-issued, .status-partially_paid { background:#fff4dd; color:#8a6100; }
    .status-draft { background:#eee; color:#555; }
    .status-overdue, .status-cancelled { background:#fbe4e1; color:#a8442f; }
    .addresses { display:flex; justify-content:space-between; gap:24px; margin-bottom:22px; }
    .addr-block { flex:1; }
    .addr-block h3 { margin:0 0 6px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#1c5034; border-bottom:1px solid #ddd; padding-bottom:4px; }
    .addr-block .name { font-weight:600; font-size:14px; }
    .addr-block .line { font-size:13px; color:#444; margin-top:2px; }
    table.items { width:100%; border-collapse:collapse; margin-top:6px; }
    table.items thead th { background:#1c5034; color:#fff; font-size:11px; text-transform:uppercase; letter-spacing:.4px; padding:9px 8px; text-align:left; }
    table.items thead th.num { text-align:right; }
    table.items tbody td { border-bottom:1px solid #e6e6e6; padding:8px; font-size:13px; }
    table.items tbody td.num { text-align:right; }
    table.items tbody tr:nth-child(even) { background:#f8f9f7; }
    .totals-wrap { display:flex; justify-content:flex-end; margin-top:14px; }
    .totals { width:290px; font-size:13.5px; }
    .totals .row { display:flex; justify-content:space-between; padding:4px 0; }
    .totals .row.grand { border-top:2px solid #1c5034; margin-top:6px; padding-top:8px; font-size:16px; font-weight:700; color:#1c5034; }
    .totals .row.balance { font-weight:700; color:#a8442f; }
    .words { margin-top:10px; font-size:12.5px; color:#555; font-style:italic; }
    .notes { margin-top:22px; font-size:13px; background:#f8f9f7; border-left:3px solid #1c5034; padding:10px 14px; }
    .footer { margin-top:36px; display:flex; justify-content:space-between; align-items:flex-end; }
    .footer .thanks { font-size:13px; color:#555; }
    .footer .sign { text-align:center; font-size:12px; color:#555; }
    .footer .sign .sign-line { margin-top:44px; border-top:1px solid #999; padding-top:4px; width:170px; }
    .gen-note { text-align:center; font-size:11px; color:#999; margin-top:26px; }
    .btn-print { margin:20px auto 0; display:block; padding:9px 20px; background:#1c5034; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
    @media print {
        body { background:#fff; }
        .sheet { box-shadow:none; margin:0; padding:0; max-width:none; }
        .btn-print { display:none; }
    }
</style>
</head>
<body>
<?php if (!$invoice): ?>
    <div class="sheet"><p>Invoice not found.</p></div>
<?php else:
    $statusKey = esc($invoice['status']);
    $balanceDue = (float)$invoice['grand_total'] - (float)$invoice['amount_paid'];
?>
    <div class="sheet">
        <div class="print-head">
            <div class="brand">
                <img src="../images/logo.png" alt="<?php echo esc($storeName); ?> logo">
                <div>
                    <h1><?php echo esc($storeName); ?></h1>
                    <div class="tagline">valluvamproducts.com</div>
                </div>
            </div>
            <div class="inv-title">
                <h2>TAX INVOICE</h2>
                <table class="inv-meta-table" align="right">
                    <tr><td>Invoice #</td><td><strong><?php echo esc($invoice['invoice_number']); ?></strong></td></tr>
                    <tr><td>Date</td><td><?php echo esc($invoice['invoice_date']); ?></td></tr>
                    <?php if ($invoice['due_date']): ?><tr><td>Due date</td><td><?php echo esc($invoice['due_date']); ?></td></tr><?php endif; ?>
                    <tr><td>Status</td><td><span class="status-pill status-<?php echo $statusKey; ?>"><?php echo esc(str_replace('_',' ', $invoice['status'])); ?></span></td></tr>
                </table>
            </div>
        </div>

        <div class="addresses">
            <div class="addr-block">
                <h3>Bill To</h3>
                <div class="name"><?php echo esc($invoice['customer_name']); ?></div>
                <?php if ($invoice['customer_mobile']): ?><div class="line">Mobile: <?php echo esc($invoice['customer_mobile']); ?></div><?php endif; ?>
                <?php if ($invoice['customer_email']): ?><div class="line">Email: <?php echo esc($invoice['customer_email']); ?></div><?php endif; ?>
                <?php if ($invoice['billing_address']): ?><div class="line"><?php echo nl2br(esc($invoice['billing_address'])); ?></div><?php endif; ?>
            </div>
            <?php if ($invoice['shipping_address'] && $invoice['shipping_address'] !== $invoice['billing_address']): ?>
            <div class="addr-block">
                <h3>Ship To</h3>
                <div class="line"><?php echo nl2br(esc($invoice['shipping_address'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <table class="items">
            <thead><tr><th>#</th><th>Product</th><th class="num">Qty</th><th>Unit</th><th class="num">Rate</th><th class="num">Discount</th><th class="num">Tax</th><th class="num">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo esc($it['product_name'] ?? ('#' . $it['product_id'])); ?></td>
                    <td class="num"><?php echo esc($it['quantity']); ?></td>
                    <td><?php echo esc($it['unit']); ?></td>
                    <td class="num">₹<?php echo number_format((float)$it['rate'], 2); ?></td>
                    <td class="num">₹<?php echo number_format((float)$it['discount'], 2); ?></td>
                    <td class="num"><?php echo number_format((float)$it['tax'], 2); ?>%</td>
                    <td class="num">₹<?php echo number_format((float)$it['line_total'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-wrap">
            <div class="totals">
                <div class="row"><span>Subtotal</span><span>₹<?php echo number_format((float)$invoice['subtotal'], 2); ?></span></div>
                <div class="row"><span>Tax</span><span>₹<?php echo number_format((float)$invoice['tax_amount'], 2); ?></span></div>
                <div class="row grand"><span>Grand total</span><span>₹<?php echo number_format((float)$invoice['grand_total'], 2); ?></span></div>
                <div class="row"><span>Amount paid</span><span>₹<?php echo number_format((float)$invoice['amount_paid'], 2); ?></span></div>
                <div class="row balance"><span>Balance due</span><span>₹<?php echo number_format($balanceDue, 2); ?></span></div>
            </div>
        </div>
        <div class="words">Amount in words: Rupees <?php echo esc(number_to_indian_words((float)$invoice['grand_total'])); ?> Only</div>

        <?php if ($invoice['notes']): ?><div class="notes"><strong>Notes:</strong> <?php echo nl2br(esc($invoice['notes'])); ?></div><?php endif; ?>

        <div class="footer">
            <div class="thanks">Thank you for your business!</div>
            <div class="sign"><div class="sign-line">Authorized signatory</div></div>
        </div>
        <div class="gen-note">This is a computer-generated invoice from <?php echo esc($storeName); ?>.</div>
    </div>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
<?php endif; ?>
</body>
</html>
