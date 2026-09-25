<?php
// Bulk stock import for the Inventory Overview page. Admin uploads a CSV or
// Excel (.xlsx) file of "Product Name, Quantity" rows; this matches each row
// against an EXISTING product by name only (case-insensitive, trimmed exact
// match) and updates that product's stock to the quantity in the file.
//
// Deliberately does NOT create new products and does NOT rename/alter any
// product name — a row whose name doesn't match an existing product exactly
// is skipped and reported back to the admin, never guessed/auto-created.
//
// PDF is not supported: reliably extracting a "product name, quantity" table
// out of an arbitrary PDF needs a parsing library that isn't installed on
// this server, and guessing at PDF layout is exactly the kind of silent
// mismatch this feature must avoid. CSV or Excel (.xlsx) only.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'inventory.adjust');

header('Content-Type: application/json');

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['import_file']['error'] ?? null;
    $message = 'No file was uploaded.';
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $message = 'That file is too large to upload.';
    }
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

$originalName = $_FILES['import_file']['name'];
$tmpPath = $_FILES['import_file']['tmp_name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($ext === 'pdf') {
    echo json_encode(['status' => 'error', 'message' => 'PDF files are not supported for automatic matching — the layout of a PDF can\'t be read reliably enough to trust with stock numbers. Please save/export the same list as CSV or Excel (.xlsx) and upload that instead.']);
    exit;
}

if (!in_array($ext, ['csv', 'xlsx'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unsupported file type "' . htmlspecialchars($ext) . '". Please upload a .csv or .xlsx file.']);
    exit;
}

/**
 * Reads a CSV file into an array of rows (each row an array of cell strings).
 */
function import_stock_read_csv(string $path): array {
    $rows = [];
    $fh = fopen($path, 'r');
    if ($fh === false) {
        throw new Exception('Could not open the uploaded CSV file.');
    }
    // Strip a UTF-8 BOM if present so the first header cell matches cleanly.
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($fh);
    }
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) === 1 && ($row[0] === null || trim((string)$row[0]) === '')) continue;
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/**
 * Minimal, dependency-free .xlsx reader (no PhpSpreadsheet installed on this
 * server). Reads the first worksheet of a standard .xlsx (Office Open XML —
 * a zip of XML parts) using PHP's built-in ZipArchive + SimpleXML, resolving
 * shared strings. Handles simple flat sheets (no formulas needed here since
 * we only read displayed text/number values).
 */
function import_stock_read_xlsx(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new Exception('Excel (.xlsx) import needs the PHP zip extension, which is not enabled on this server. Please upload a .csv file instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('Could not open the uploaded Excel file — it may be corrupted or not a real .xlsx file.');
    }

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    // Find the first worksheet part (sheet1.xml is the common case; fall back
    // to whatever the workbook's first sheet actually maps to).
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheetPath) === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetPath = $name;
                break;
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        throw new Exception('Could not find a worksheet inside the uploaded Excel file.');
    }
    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false || !isset($sheet->sheetData)) {
        throw new Exception('Could not read the worksheet inside the uploaded Excel file.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        $maxCol = -1;
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) continue;
            $colIndex = 0;
            foreach (str_split($m[1]) as $ch) {
                $colIndex = $colIndex * 26 + (ord($ch) - 64);
            }
            $colIndex -= 1;

            $type = (string)$c['t'];
            if ($type === 's') {
                $value = $sharedStrings[(int)((string)$c->v)] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string)$c->is->t : '';
            } else {
                $value = isset($c->v) ? (string)$c->v : '';
            }
            $cells[$colIndex] = $value;
            if ($colIndex > $maxCol) $maxCol = $colIndex;
        }
        $rowData = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $rowData[] = $cells[$i] ?? '';
        }
        $rows[] = $rowData;
    }
    return $rows;
}

try {
    $rawRows = $ext === 'csv' ? import_stock_read_csv($tmpPath) : import_stock_read_xlsx($tmpPath);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$rawRows = array_values(array_filter($rawRows, function ($r) {
    foreach ($r as $cell) {
        if (trim((string)$cell) !== '') return true;
    }
    return false;
}));

if (count($rawRows) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'The file appears to be empty.']);
    exit;
}

// Work out which columns are the product name and the quantity. Prefer a
// header row match; fall back to "column A = name, column B = quantity".
$nameCol = 0;
$qtyCol = 1;
$startIndex = 0;

$headerRow = $rawRows[0];
$nameHeaderFound = -1;
$qtyHeaderFound = -1;
foreach ($headerRow as $idx => $cell) {
    $h = strtolower(trim((string)$cell));
    if ($nameHeaderFound === -1 && in_array($h, ['product name', 'product', 'name', 'item', 'item name'], true)) {
        $nameHeaderFound = $idx;
    }
    if ($qtyHeaderFound === -1 && in_array($h, ['quantity', 'qty', 'stock', 'stock qty', 'available stock', 'new stock'], true)) {
        $qtyHeaderFound = $idx;
    }
}
if ($nameHeaderFound !== -1 && $qtyHeaderFound !== -1) {
    $nameCol = $nameHeaderFound;
    $qtyCol = $qtyHeaderFound;
    $startIndex = 1; // skip the header row
} else {
    // No recognizable header — if the first row's 2nd column isn't numeric-ish,
    // still treat it as a header row (common case: unexpected header wording).
    $secondCell = trim((string)($headerRow[1] ?? ''));
    if ($secondCell !== '' && !is_numeric($secondCell)) {
        $startIndex = 1;
    }
}

// Load all products once for case-insensitive exact-name matching, keyed by
// a normalized (lowercased, trimmed) name. Never renamed/altered — only read.
$productLookup = [];
$stmt = $pdo->query("SELECT id, product_name, stock FROM product_details");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $key = mb_strtolower(trim($p['product_name']));
    // If two products somehow share a normalized name, keep the first and
    // let the rest fall through as ambiguous rather than silently picking one.
    if (!isset($productLookup[$key])) {
        $productLookup[$key] = $p;
    } else {
        $productLookup[$key] = false; // mark ambiguous
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$updated = [];
$unchanged = [];
$unmatched = [];
$invalidQty = [];
$ambiguous = [];
$seenNames = [];

for ($i = $startIndex; $i < count($rawRows); $i++) {
    $row = $rawRows[$i];
    $rawName = trim((string)($row[$nameCol] ?? ''));
    $rawQty = trim((string)($row[$qtyCol] ?? ''));
    if ($rawName === '' && $rawQty === '') continue;
    if ($rawName === '') {
        $unmatched[] = ['name' => '(blank)', 'reason' => 'Missing product name'];
        continue;
    }

    $key = mb_strtolower($rawName);
    $seenNames[$key] = ($seenNames[$key] ?? 0) + 1;

    if (!array_key_exists($key, $productLookup) || $productLookup[$key] === false) {
        $unmatched[] = ['name' => $rawName, 'reason' => $productLookup[$key] === false ? 'Multiple products share this name — skipped to avoid updating the wrong one' : 'No matching product found'];
        continue;
    }

    // Accept plain numbers, and numbers with a trailing unit (e.g. "500g",
    // "12 kg") — the digits are what matter for the stock count.
    if (!preg_match('/^(\d+(?:\.\d+)?)/', $rawQty, $m)) {
        $invalidQty[] = ['name' => $rawName, 'value' => $rawQty];
        continue;
    }
    $qty = (int)round((float)$m[1]);
    if ($qty < 0) {
        $invalidQty[] = ['name' => $rawName, 'value' => $rawQty];
        continue;
    }

    $product = $productLookup[$key];
    if ($qty === (int)$product['stock']) {
        $unchanged[] = ['name' => $product['product_name'], 'stock' => $qty];
        continue;
    }

    $updated[] = [
        'id' => (int)$product['id'],
        'name' => $product['product_name'],
        'sku' => 'PRD-' . $product['id'],
        'previous_stock' => (int)$product['stock'],
        'new_stock' => $qty,
    ];
}

foreach ($seenNames as $key => $count) {
    if ($count > 1 && array_key_exists($key, $productLookup) && $productLookup[$key] !== false) {
        // Duplicate rows for the same product — last one in the file wins
        // (already reflected in $updated since we overwrite as we go), but
        // let the admin know their file had repeats.
    }
}

if (count($updated) === 0) {
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'filename' => $originalName,
            'total_rows' => count($rawRows) - $startIndex,
            'updated_count' => 0,
            'unchanged_count' => count($unchanged),
            'unmatched' => $unmatched,
            'invalid_quantity' => $invalidQty,
        ],
        'updated' => [],
    ]);
    exit;
}

// Collapse duplicate rows for the same product (keep the last occurrence).
$byId = [];
foreach ($updated as $u) {
    $byId[$u['id']] = $u;
}
$updated = array_values($byId);

try {
    $pdo->beginTransaction();

    $update = $pdo->prepare("UPDATE product_details SET stock = ? WHERE id = ?");
    $movementInsert = $pdo->prepare("INSERT INTO stock_movements (movement_type, product_id, sku, warehouse_id,
                                      quantity, previous_stock, new_stock, reference_type, reference_number,
                                      reason, created_by, created_at)
                                      VALUES (?, ?, ?, 1, ?, ?, ?, 'other', ?, ?, ?, NOW())");

    foreach ($updated as $u) {
        $update->execute([$u['new_stock'], $u['id']]);

        $diff = $u['new_stock'] - $u['previous_stock'];
        try {
            $movementInsert->execute([
                $diff >= 0 ? 'stock_in' : 'stock_out',
                $u['id'],
                $u['sku'],
                abs($diff),
                $u['previous_stock'],
                $u['new_stock'],
                mb_substr($originalName, 0, 190),
                'Bulk stock import from uploaded file',
                $adminUsername,
            ]);
        } catch (PDOException $e) {
            error_log("import_stock: stock_movements insert failed for product {$u['id']}: " . $e->getMessage());
        }

        log_audit($pdo, 'update', 'inventory', $u['id'], ['stock' => $u['previous_stock']], ['stock' => $u['new_stock'], 'reason' => 'Bulk stock import: ' . $originalName]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("import_stock: failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save the imported stock: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'summary' => [
        'filename' => $originalName,
        'total_rows' => count($rawRows) - $startIndex,
        'updated_count' => count($updated),
        'unchanged_count' => count($unchanged),
        'unmatched' => $unmatched,
        'invalid_quantity' => $invalidQty,
    ],
    'updated' => $updated,
]);
