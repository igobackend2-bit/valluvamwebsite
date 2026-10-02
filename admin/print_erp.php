<?php
// Printable RFQ / purchase order / debit note / credit note / journal voucher (added 1 Oct 2026). Read-only.
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

function pe($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function pm($v) { return '₹' . number_format((float)$v, 2); }
function pq($v) { $n = (float)$v; return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.'); }
function prow(PDO $pdo, string $sql, array $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function prows(PDO $pdo, string $sql, array $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function pcan(PDO $pdo, string $perm): bool {
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) return true;
    return (bool)prow($pdo, "SELECT 1 x FROM admin_role_permissions WHERE role_id = ? AND perm_key = ?", [(int)($_SESSION['admin_role_id'] ?? 0), $perm]);
}
function pitem(PDO $pdo, string $type, int $id): string {
    if ($type === 'product') { $r = prow($pdo, "SELECT product_name, quantity FROM product_details WHERE id = ?", [$id]); return $r ? $r['product_name'] . ($r['quantity'] && stripos($r['product_name'], (string)$r['quantity']) === false ? ' (' . $r['quantity'] . ')' : '') : '#' . $id; }
    $r = prow($pdo, "SELECT name FROM raw_materials WHERE id = ?", [$id]); return $r ? $r['name'] : '#' . $id;
}

$type = (string)($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$need = ['rfq' => 'purchase.view', 'po' => 'purchase.view', 'dn' => 'purchase.view', 'cn' => 'customer.view', 'jv' => 'accounting.view'][$type] ?? null;
if (!$need) { http_response_code(404); exit('Unknown document.'); }
if (!pcan($pdo, $need)) { http_response_code(403); exit('You do not have permission to see this document.'); }
$store = 'Valluvam Products';
$doc = null; $title = ''; $party = []; $meta = []; $cols = []; $rows = []; $totals = []; $note = '';
try {
    if ($type === 'rfq') {
        $doc = prow($pdo, "SELECT r.*, w.name AS wh, w.location FROM rfqs r LEFT JOIN warehouses w ON w.id = r.warehouse_id WHERE r.id = ?", [$id]);
        if ($doc) {
            $title = 'Request for Quotation'; $meta = ['RFQ no.' => $doc['rfq_number'], 'Date' => $doc['rfq_date'], 'Reply by' => $doc['due_date'] ?: '—', 'Deliver to' => trim($doc['wh'] . ' ' . $doc['location'])];
            $party = array_map(function ($s) { return $s['supplier_name']; }, prows($pdo, "SELECT s.supplier_name FROM rfq_suppliers x JOIN suppliers s ON s.id = x.supplier_id WHERE x.rfq_id = ?", [$id]));
            $cols = ['#', 'Item', 'Quantity', 'Specification', 'Your rate / unit', 'GST %', 'Delivery days'];
            foreach (prows($pdo, "SELECT * FROM rfq_items WHERE rfq_id = ? ORDER BY id", [$id]) as $i => $l) $rows[] = [$i + 1, pitem($pdo, $l['item_type'], (int)$l['item_id']), pq($l['quantity']) . ' ' . $l['unit'], $l['specs'], '', '', ''];
            $note = $doc['terms'];
        }
    } elseif ($type === 'po') {
        $doc = prow($pdo, "SELECT po.*, s.supplier_name, s.company_name, s.address, s.gst_number, s.mobile, w.name AS wh, w.location FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN warehouses w ON w.id = po.warehouse_id WHERE po.id = ?", [$id]);
        if ($doc) {
            $rev = prow($pdo, "SELECT MAX(revision_no) n FROM purchase_order_revisions WHERE po_id = ? AND status = 'approved'", [$id]);
            $title = 'Purchase Order' . ($rev && $rev['n'] ? ' (revision ' . $rev['n'] . ')' : '');
            $meta = ['PO no.' => $doc['po_number'], 'Date' => $doc['po_date'], 'Expected' => $doc['expected_delivery_date'] ?: '—', 'Deliver to' => trim($doc['wh'] . ' ' . $doc['location']), 'Status' => $doc['status'], 'Approved by' => $doc['approved_by'] ?: '—'];
            $party = [$doc['supplier_name'], $doc['company_name'], $doc['address'], $doc['gst_number'] ? 'GSTIN ' . $doc['gst_number'] : '', $doc['mobile']];
            // supplier bank / UPI for payment (1 Oct 2026) — shown only when saved on the supplier
            $sb = prow($pdo, "SELECT * FROM suppliers WHERE id = ?", [$doc['supplier_id']]) ?: [];
            $bank = array_filter(['A/c holder ' . ($sb['account_holder_name'] ?? ''), 'Bank ' . ($sb['bank_name'] ?? ''), 'A/c no. ' . ($sb['bank_account_number'] ?? ''), 'IFSC ' . ($sb['bank_ifsc'] ?? ''), 'UPI ' . ($sb['upi_id'] ?? '')],
                                 fn($v) => !preg_match('/^(A\/c holder|Bank|A\/c no\.|IFSC|UPI) $/', $v));
            if ($bank) $party[] = 'Pay to — ' . implode(' · ', $bank);
            $cols = ['#', 'Item', 'Qty', 'Rate', 'Discount', 'GST', 'Total'];
            foreach (prows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$id]) as $i => $l) $rows[] = [$i + 1, pitem($pdo, $l['item_type'], (int)$l['item_id']), pq($l['quantity']) . ' ' . $l['unit'], pm($l['rate']), pm($l['discount_amount']), rtrim(rtrim($l['tax_percent'], '0'), '.') . '%', pm($l['line_total'])];
            $totals = ['Subtotal' => pm($doc['subtotal']), 'Discount' => pm($doc['discount_total']), 'GST' => pm($doc['tax_total']), 'Other charges' => pm($doc['other_charges']), 'Total' => pm($doc['grand_total'])];
            $note = $doc['notes'];
        }
    } elseif ($type === 'dn') {
        $doc = prow($pdo, "SELECT d.*, s.supplier_name, s.company_name, s.address, s.gst_number, r.return_number, r.return_date, r.reason, r.settlement, g.grn_number, pi.supplier_invoice_no
                           FROM debit_notes d JOIN suppliers s ON s.id = d.supplier_id JOIN purchase_returns r ON r.id = d.purchase_return_id LEFT JOIN goods_receipts g ON g.id = r.grn_id LEFT JOIN purchase_invoices pi ON pi.id = d.pinv_id WHERE d.id = ?", [$id]);
        if ($doc) {
            $title = 'Debit Note'; $meta = ['Debit note no.' => $doc['dn_number'], 'Date' => $doc['dn_date'], 'Against return' => $doc['return_number'], 'GRN' => $doc['grn_number'] ?: '—', 'Your invoice' => $doc['supplier_invoice_no'] ?: '—', 'Settlement' => str_replace('_', ' ', $doc['settlement'])];
            $party = [$doc['supplier_name'], $doc['company_name'], $doc['address'], $doc['gst_number'] ? 'GSTIN ' . $doc['gst_number'] : ''];
            $cols = ['#', 'Item', 'Qty', 'Rate', 'Value'];
            foreach (prows($pdo, "SELECT * FROM purchase_return_items WHERE return_id = ? ORDER BY id", [$doc['purchase_return_id']]) as $i => $l) $rows[] = [$i + 1, pitem($pdo, $l['item_type'], (int)$l['item_id']), pq($l['quantity']) . ' ' . $l['unit'], pm($l['rate']), pm($l['line_value'])];
            $totals = ['Total debited' => pm($doc['total'])];
            $note = 'Reason: ' . $doc['reason'];
        }
    } elseif ($type === 'cn') {
        $doc = prow($pdo, "SELECT c.*, r.return_number, r.return_date, r.source_number, r.reason, r.settlement, r.total_value FROM credit_notes c JOIN sales_returns r ON r.id = c.sales_return_id WHERE c.id = ?", [$id]);
        if ($doc) {
            $title = 'Credit Note'; $meta = ['Credit note no.' => $doc['cn_number'], 'Date' => $doc['cn_date'], 'Against' => $doc['source_number'], 'Return' => $doc['return_number'], 'Settlement' => $doc['settlement'] === 'refund' ? 'Refunded' : 'Credit to account'];
            $party = [$doc['customer_name'], $doc['customer_mobile']];
            $cols = ['#', 'Item', 'Qty', 'Rate', 'Value'];
            foreach (prows($pdo, "SELECT i.*, p.product_name FROM sales_return_items i LEFT JOIN product_details p ON p.id = i.product_id WHERE i.return_id = ?", [$doc['sales_return_id']]) as $i => $l) $rows[] = [$i + 1, $l['product_name'], pq($l['quantity']), pm($l['rate']), pm($l['line_value'])];
            $totals = ['Goods value' => pm($doc['total_value']), 'Credit / refund' => pm($doc['total'])];
            $note = 'Reason: ' . $doc['reason'];
        }
    } elseif ($type === 'jv') {
        $doc = prow($pdo, "SELECT * FROM journal_entries WHERE id = ?", [$id]);
        if ($doc) {
            $title = 'Journal Voucher'; $meta = ['Journal no.' => $doc['journal_number'], 'Date' => $doc['entry_date'], 'Source' => $doc['source_module'] . ' · ' . $doc['source_ref'], 'Status' => $doc['status'], 'Created by' => $doc['created_by'], 'Approved by' => $doc['approved_by'] ?: '—'];
            $cols = ['Account', 'Party / memo', 'Debit', 'Credit'];
            foreach (prows($pdo, "SELECT l.*, a.code, a.name FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE l.journal_id = ? ORDER BY l.id", [$id]) as $l)
                $rows[] = [$l['code'] . ' ' . $l['name'], trim(($l['party_name'] ?: '') . ' ' . (strpos((string)$l['memo'], 'k=') === 0 ? '' : $l['memo'])), $l['debit'] > 0 ? pm($l['debit']) : '', $l['credit'] > 0 ? pm($l['credit']) : ''];
            $totals = ['Total' => pm($doc['total'])];
            $note = $doc['narration'];
        }
    }
} catch (PDOException $e) { error_log('[print_erp] ' . $e->getMessage()); $doc = null; }
if (!$doc) { http_response_code(404); exit('Document not found.'); }
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= pe($title . ' ' . reset($meta)) ?></title>
<style>
 body { font-family: "Segoe UI", Arial, sans-serif; color: #1f1d1a; margin: 0; background: #f4f1ea; }
 .sheet { max-width: 820px; margin: 24px auto; background: #fff; padding: 32px 36px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
 h1 { font-size: 20px; margin: 0; color: #1c5034; } .top { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #1c5034; padding-bottom: 12px; margin-bottom: 16px; }
 .brand { font-weight: 800; font-size: 18px; } .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 18px; font-size: 13px; margin-bottom: 14px; }
 .meta span { display: block; color: #6b6459; font-size: 11px; text-transform: uppercase; } table { width: 100%; border-collapse: collapse; font-size: 13px; }
 th { text-align: left; background: #f4f1ea; padding: 7px 8px; font-size: 12px; } td { padding: 7px 8px; border-bottom: 1px solid #eee; } .tot { margin-top: 12px; margin-left: auto; width: 320px; font-size: 13px; }
 .tot div { display: flex; justify-content: space-between; padding: 4px 0; } .tot div:last-child { font-weight: 800; border-top: 1px solid #ccc; } .note { font-size: 12.5px; color: #444; margin-top: 16px; white-space: pre-line; }
 .sign { display: flex; justify-content: space-between; margin-top: 48px; font-size: 12px; color: #6b6459; } .btn { position: fixed; top: 12px; right: 12px; padding: 8px 14px; background: #1c5034; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
 .brand-wrap { display: flex; align-items: center; gap: 12px; } .logo { height: 58px; width: auto; } .tag { font-size: 11.5px; color: #6b6459; } .top { align-items: center; } th.n, td.n { text-align: right; white-space: nowrap; } /* FIX (2 Oct 2026): Valluvam logo */
 @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; } .btn { display: none; } }
 @media (max-width: 640px) { .sheet { padding: 18px; margin: 0; } .meta { grid-template-columns: 1fr 1fr; } }
</style></head><body>
<button class="btn" onclick="window.print()">Print</button>
<div class="sheet">
  <div class="top"><div class="brand-wrap"><img class="logo" src="../images/logo-doc.png" alt="Valluvam" onerror="this.src='../images/logo.png'"><div><div class="brand"><?= pe($store) ?></div><div class="tag">As Pure As Nature</div></div></div><div style="text-align:right"><h1><?= pe($title) ?></h1></div></div>
  <?php if ($party): ?><div style="font-size:13px;margin-bottom:12px;"><strong><?= $type === 'rfq' ? 'To suppliers:' : 'To:' ?></strong><br><?= implode('<br>', array_map('pe', array_filter($party))) ?></div><?php endif; ?>
  <div class="meta"><?php foreach ($meta as $k => $v): ?><div><span><?= pe($k) ?></span><?= pe($v) ?></div><?php endforeach; ?></div>
  <?php $numCols = array_map(fn($c) => in_array($c, ['Qty', 'Quantity', 'Rate', 'Discount', 'GST', 'Total', 'Value', 'Debit', 'Credit'], true), $cols);   // FIX (2 Oct 2026): numbers right-aligned ?>
  <table><thead><tr><?php foreach ($cols as $k => $c): ?><th<?= $numCols[$k] ? ' class="n"' : '' ?>><?= pe($c) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr><?php foreach (array_values($r) as $k => $c): ?><td<?= !empty($numCols[$k]) ? ' class="n"' : '' ?>><?= pe($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
  <?php if ($totals): ?><div class="tot"><?php foreach ($totals as $k => $v): ?><div><span><?= pe($k) ?></span><span><?= pe($v) ?></span></div><?php endforeach; ?></div><?php endif; ?>
  <?php if ($note): ?><div class="note"><?= pe($note) ?></div><?php endif; ?>
  <div class="sign"><span>Prepared by: <?= pe($_SESSION['admin_username'] ?? '') ?></span><span>Authorised signatory</span></div>
</div></body></html>
