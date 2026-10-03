<?php
// FIX (3 Oct 2026): Stock Out download — CSV or Excel (.xlsx), item by item, for a week / month / any date range.
// Covers every way stock leaves: manual Stock Out documents, delivery challans, website orders, manual / credit sales,
// repacking, adjustments (stock ledger), and older stock_history rows. Read-only.
// GET: period = today | this_week | last_week | last7 | this_month | last_month | last30 | custom (date_from, date_to)
//      reference_type (optional), warehouse_id (optional), format = csv | xlsx
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.view');

$period = (string)($_GET['period'] ?? 'this_month');
$today = date('Y-m-d');
switch ($period) {
    case 'today':      $from = $to = $today; break;
    case 'this_week':  $from = date('Y-m-d', strtotime('monday this week')); $to = $today; break;
    case 'last_week':  $from = date('Y-m-d', strtotime('monday last week')); $to = date('Y-m-d', strtotime('sunday last week')); break;
    case 'last7':      $from = date('Y-m-d', strtotime('-6 days')); $to = $today; break;
    case 'last_month': $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('first day of last month')); break;
    case 'last30':     $from = date('Y-m-d', strtotime('-29 days')); $to = $today; break;
    case 'custom':
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? $_GET['date_to'] : $today;
        break;
    default:           $period = 'this_month'; $from = date('Y-m-01'); $to = $today;
}
if ($to < $from) [$from, $to] = [$to, $from];
$refType = trim((string)($_GET['reference_type'] ?? ''));
$whId = (int)($_GET['warehouse_id'] ?? 0);
$format = ($_GET['format'] ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv';

$label = ['delivery_challan' => 'Delivery challan', 'website_order' => 'Website order', 'manual_sale' => 'Manual sale', 'credit_sale' => 'Credit sale', 'sales_order' => 'Sales order',
          'internal_transfer' => 'Internal transfer', 'damage' => 'Damage', 'waste' => 'Waste', 'repack' => 'Repacking', 'adjustment' => 'Adjustment', 'other' => 'Other'];
$rows = [];
try {
    // 1. Stock Out documents with their lines
    $sql = "SELECT so.stock_out_number, so.stock_out_date, so.created_at, so.reference_type, so.reference_number, w.name AS wh, so.vehicle_number, so.customer_name,
                   so.reason, so.authorized_by, so.created_by, soi.product_id, soi.sku, soi.quantity, soi.unit, p.product_name
            FROM stock_outs so JOIN stock_out_items soi ON soi.stock_out_id = so.id
            LEFT JOIN product_details p ON p.id = soi.product_id LEFT JOIN warehouses w ON w.id = so.warehouse_id
            WHERE so.stock_out_date BETWEEN ? AND ?";
    $p = [$from, $to];
    if ($refType !== '') { $sql .= " AND so.reference_type = ?"; $p[] = $refType; }
    if ($whId) { $sql .= " AND so.warehouse_id = ?"; $p[] = $whId; }
    $st = $pdo->prepare($sql . " ORDER BY so.stock_out_date, so.id, soi.id");
    $st->execute($p);
    $docs = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $docs[$r['stock_out_number']] = true;
        if ($r['reference_number']) $docs[$r['reference_number']] = true;   // DC ledger rows carry the DC number
        $rows[] = [$r['stock_out_date'], substr((string)$r['created_at'], 11, 5), $r['stock_out_number'], $label[$r['reference_type']] ?? $r['reference_type'], (string)$r['reference_number'],
                   (string)$r['wh'], (string)($r['product_name'] ?: '#' . $r['product_id']), (string)($r['sku'] ?: 'PRD-' . $r['product_id']), (float)$r['quantity'], (string)($r['unit'] ?: 'pcs'),
                   (string)$r['customer_name'], (string)$r['vehicle_number'], (string)$r['reason'], (string)$r['authorized_by'], (string)$r['created_by']];
    }
    // 2. Stock ledger rows that have no Stock Out document (website orders, sales, repacking, adjustments …)
    try {
        $sql = "SELECT sm.created_at, sm.reference_type, sm.reference_number, w.name AS wh, sm.product_id, sm.sku, sm.quantity, sm.reason, sm.created_by, p.product_name,
                       (SELECT TRIM(CONCAT(o.first_name, ' ', o.last_name)) FROM orders o WHERE sm.reference_type = 'website_order' AND o.receipt = sm.reference_number LIMIT 1) AS web_customer,
                       (SELECT o.order_status FROM orders o WHERE sm.reference_type = 'website_order' AND o.receipt = sm.reference_number LIMIT 1) AS web_status
                FROM stock_movements sm LEFT JOIN product_details p ON p.id = sm.product_id LEFT JOIN warehouses w ON w.id = sm.warehouse_id
                WHERE sm.movement_type = 'stock_out' AND DATE(sm.created_at) BETWEEN ? AND ?";
        $p = [$from, $to];
        if ($refType !== '') { $sql .= " AND sm.reference_type = ?"; $p[] = $refType; }
        if ($whId) { $sql .= " AND sm.warehouse_id = ?"; $p[] = $whId; }
        $st = $pdo->prepare($sql . " ORDER BY sm.created_at, sm.id");
        $st->execute($p);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['reference_number'] && isset($docs[$r['reference_number']])) continue;   // already listed from its document
            $rows[] = [substr($r['created_at'], 0, 10), substr($r['created_at'], 11, 5), (string)$r['reference_number'], $label[$r['reference_type']] ?? (string)$r['reference_type'], (string)$r['reference_number'],
                       (string)$r['wh'], (string)($r['product_name'] ?: '#' . $r['product_id']), (string)($r['sku'] ?: 'PRD-' . $r['product_id']), abs((float)$r['quantity']), 'pcs',
                       (string)$r['web_customer'], '', (string)$r['reason'] . ($r['web_status'] === 'cancelled' ? ' — order cancelled, put back in stock' : ''), '', (string)$r['created_by']];
        }
    } catch (PDOException $e) { error_log('[export stock outs ledger] ' . $e->getMessage()); }
    // 3. Older stock_history rows
    if ($refType === '' || $refType === 'other') {
        try {
            $st = $pdo->prepare("SELECT sh.created_at, sh.product_id, sh.quantity_change, sh.reason, sh.admin_username, p.product_name FROM stock_history sh LEFT JOIN product_details p ON p.id = sh.product_id
                                 WHERE sh.change_type = 'stock_out' AND DATE(sh.created_at) BETWEEN ? AND ? ORDER BY sh.created_at");
            $st->execute([$from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r)
                $rows[] = [substr($r['created_at'], 0, 10), substr($r['created_at'], 11, 5), '', 'Other', '', 'Main Warehouse', (string)($r['product_name'] ?: '#' . $r['product_id']), 'PRD-' . $r['product_id'],
                           abs((float)$r['quantity_change']), 'pcs', '', '', (string)$r['reason'], '', (string)$r['admin_username']];
        } catch (PDOException $e) { /* legacy table not present */ }
    }
} catch (PDOException $e) {
    error_log('Error exporting stock outs: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'Could not build the stock-out file.';
    exit;
}
usort($rows, fn($a, $b) => strcmp($a[0] . $a[1], $b[0] . $b[1]));
$head = ['Date', 'Time', 'Stock out / document no.', 'Type', 'Reference no.', 'Warehouse', 'Product', 'SKU', 'Quantity', 'Unit', 'Customer', 'Vehicle', 'Reason', 'Authorized by', 'Entered by'];

// summary by product (Excel 2nd sheet)
$sum = [];
foreach ($rows as $r) { $k = $r[6] . '|' . $r[7]; $sum[$k] ??= [$r[6], $r[7], 0, 0, []]; $sum[$k][2] += $r[8]; $sum[$k][3]++; $sum[$k][4][$r[3]] = true; }
uasort($sum, fn($a, $b) => $b[2] <=> $a[2]);
$sumRows = array_map(fn($s) => [$s[0], $s[1], $s[2], $s[3], implode(', ', array_keys($s[4]))], array_values($sum));

$name = 'stock_out_' . $from . '_to_' . $to;
if ($format === 'xlsx' && class_exists('ZipArchive')) {
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $col = function (int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; };
    $sheet = function (array $title, array $head, array $data, array $widths) use ($esc, $col): string {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        foreach ($widths as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        $x .= '</cols><sheetData>';
        $all = array_merge([$title, [], $head], $data);
        foreach ($all as $ri => $row) {
            $x .= '<row r="' . ($ri + 1) . '">';
            foreach ($row as $ci => $v) {
                $ref = $col($ci) . ($ri + 1); $s = $ri === 0 ? ' s="1"' : ($ri === 2 ? ' s="2"' : '');
                $x .= is_int($v) || is_float($v) ? '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>' : '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
            }
            $x .= '</row>';
        }
        return $x . '</sheetData></worksheet>';
    };
    $tmp = tempnam(sys_get_temp_dir(), 'sox');
    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Stock out details" sheetId="1" r:id="rId1"/><sheet name="By product" sheetId="2" r:id="rId2"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="13"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1C5034"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" applyFont="1"/><xf fontId="2" fillId="2" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
    $title = 'Valluvam — Stock out ' . date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to)) . ' · ' . count($rows) . ' line(s), ' . array_sum(array_column($rows, 8)) . ' qty';
    $z->addFromString('xl/worksheets/sheet1.xml', $sheet([$title], $head, $rows, [11, 7, 20, 16, 20, 18, 30, 14, 10, 7, 20, 13, 34, 16, 16]));
    $z->addFromString('xl/worksheets/sheet2.xml', $sheet(['Stock out by product · ' . date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to))], ['Product', 'SKU', 'Total quantity out', 'Lines', 'Types'], $sumRows, [34, 14, 18, 8, 40]));
    $z->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // Excel opens Tamil / ₹ correctly
fputcsv($out, $head);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
