<?php
// Bulk ADD NEW products from an uploaded CSV or Excel (.xlsx) file, for the
// Products page. This is deliberately separate from Import Stock on
// Inventory Overview (which only updates EXISTING products' stock) — this
// one creates brand-new product_details rows.
//
// Expected columns (header names are flexible, case-insensitive):
//   Product Name (required), Category (required, must match an existing
//   category), Quantity (required, e.g. "500g"/"1kg"/"750ml"/"1L" — same
//   rule the New Product form enforces), Price (required), Discount Price
//   (optional), Stock (optional, numeric), Rating (optional, 1-5),
//   Description (optional), Benefits (optional).
//
// A row whose product name already exists is SKIPPED, not overwritten —
// bulk-editing existing products isn't what this does; use the product
// editor or Import Stock for that. A row whose category doesn't match an
// existing category is also skipped rather than silently inventing a new
// category. Nothing here renames or alters an existing product.
//
// No image can come from a spreadsheet — imported products start with no
// photo (same "No Image" placeholder the catalog already shows) and the
// admin can add one later by editing the product.
//
// PDF is not supported, same reasoning as Import Stock: no PDF-table
// library is installed on this server, and guessing at a PDF's layout is
// not safe for creating live catalog entries.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

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
    echo json_encode(['status' => 'error', 'message' => 'PDF files are not supported for automatic product creation — the layout of a PDF can\'t be read reliably enough to trust with new catalog entries. Please save/export the same list as CSV or Excel (.xlsx) and upload that instead.']);
    exit;
}

if (!in_array($ext, ['csv', 'xlsx'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unsupported file type "' . htmlspecialchars($ext) . '". Please upload a .csv or .xlsx file.']);
    exit;
}

function import_products_read_csv(string $path): array {
    $rows = [];
    $fh = fopen($path, 'r');
    if ($fh === false) {
        throw new Exception('Could not open the uploaded CSV file.');
    }
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

// Minimal, dependency-free .xlsx reader — same approach as Import Stock's,
// duplicated here (not shared) so this file has no dependency on that one.
function import_products_read_xlsx(string $path): array {
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
    $rawRows = $ext === 'csv' ? import_products_read_csv($tmpPath) : import_products_read_xlsx($tmpPath);
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

// Map headers -> column index. Header row is required here (unlike Import
// Stock's 2-column fallback) since there are too many possible fields to
// guess a fixed column order safely.
$headerMap = [
    'name'        => ['product name', 'product', 'name', 'item', 'item name'],
    'category'    => ['category'],
    'quantity'    => ['quantity', 'qty', 'size', 'pack size'],
    'price'       => ['price', 'mrp'],
    'dis_price'   => ['discount price', 'dis_price', 'sale price', 'offer price'],
    'stock'       => ['stock', 'stock qty', 'available stock'],
    'rating'      => ['rating'],
    'description' => ['description', 'desc'],
    'benefits'    => ['benefits', 'benefit'],
];
$colIndex = [];
foreach ($rawRows[0] as $idx => $cell) {
    $h = strtolower(trim((string)$cell));
    foreach ($headerMap as $field => $aliases) {
        if (!isset($colIndex[$field]) && in_array($h, $aliases, true)) {
            $colIndex[$field] = $idx;
        }
    }
}

$missingRequired = array_diff(['name', 'category', 'quantity', 'price'], array_keys($colIndex));
if (count($missingRequired) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'The file needs a header row with at least these columns: Product Name, Category, Quantity, Price. Missing: ' . implode(', ', $missingRequired) . '.']);
    exit;
}

// Existing products (for duplicate detection) and categories (for matching).
$existingNames = [];
$nameStmt = $pdo->query("SELECT LOWER(TRIM(product_name)) AS k FROM product_details");
foreach ($nameStmt->fetchAll(PDO::FETCH_COLUMN) as $k) {
    $existingNames[$k] = true;
}

$categoryLookup = [];
try {
    $catStmt = $pdo->query("SELECT category_name FROM product_category");
    foreach ($catStmt->fetchAll(PDO::FETCH_COLUMN) as $cat) {
        $categoryLookup[mb_strtolower(trim($cat))] = $cat;
    }
} catch (PDOException $e) {
    error_log('import_products: could not load categories: ' . $e->getMessage());
}

$quantityRegex = '/^(\d+(?:\.\d+)?)\s*(g|kg|ml|l)$/i';
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

$toInsert = [];
$skippedExisting = [];
$skippedUnknownCategory = [];
$invalid = [];

for ($i = 1; $i < count($rawRows); $i++) {
    $row = $rawRows[$i];
    $get = function ($field) use ($row, $colIndex) {
        return isset($colIndex[$field]) ? trim((string)($row[$colIndex[$field]] ?? '')) : '';
    };

    $name = $get('name');
    if ($name === '') continue; // fully blank row, skip silently

    $categoryRaw = $get('category');
    $quantityRaw = $get('quantity');
    $priceRaw = $get('price');

    $problems = [];
    if ($priceRaw === '' || !is_numeric($priceRaw)) $problems[] = 'missing/invalid Price';
    if ($quantityRaw === '' || !preg_match($quantityRegex, $quantityRaw)) $problems[] = 'missing/invalid Quantity (needs e.g. "500g", "1kg", "750ml", "1L")';
    if ($categoryRaw === '') $problems[] = 'missing Category';

    if (count($problems) > 0) {
        $invalid[] = ['name' => $name, 'reason' => implode('; ', $problems)];
        continue;
    }

    $nameKey = mb_strtolower(trim($name));
    if (isset($existingNames[$nameKey])) {
        $skippedExisting[] = $name;
        continue;
    }

    $categoryKey = mb_strtolower(trim($categoryRaw));
    if (!isset($categoryLookup[$categoryKey])) {
        $skippedUnknownCategory[] = ['name' => $name, 'category' => $categoryRaw];
        continue;
    }

    $disPriceRaw = $get('dis_price');
    $stockRaw = $get('stock');
    $ratingRaw = $get('rating');

    $rating = is_numeric($ratingRaw) ? (float)$ratingRaw : 5;
    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    $toInsert[] = [
        'name' => $name,
        'price' => (float)$priceRaw,
        'dis_price' => is_numeric($disPriceRaw) ? (float)$disPriceRaw : null,
        'category' => $categoryLookup[$categoryKey],
        'quantity' => $quantityRaw,
        'rating' => $rating,
        'description' => $get('description') ?: null,
        'benefits' => $get('benefits') ?: null,
        'stock' => is_numeric($stockRaw) ? (int)round((float)$stockRaw) : 0,
    ];
    // Prevent two rows in the same file both trying to create the same name.
    $existingNames[$nameKey] = true;
}

if (count($toInsert) === 0) {
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'filename' => $originalName,
            'total_rows' => count($rawRows) - 1,
            'created_count' => 0,
            'skipped_existing' => $skippedExisting,
            'skipped_unknown_category' => $skippedUnknownCategory,
            'invalid' => $invalid,
        ],
        'created' => [],
    ]);
    exit;
}

try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image, stock)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?)");
    $created = [];
    foreach ($toInsert as $p) {
        $ins->execute([$p['name'], $p['price'], $p['dis_price'], $p['category'], $p['quantity'], $p['rating'], $p['description'], $p['benefits'], $p['stock']]);
        $newId = (int)$pdo->lastInsertId();
        $created[] = ['id' => $newId, 'name' => $p['name'], 'category' => $p['category'], 'price' => $p['price'], 'quantity' => $p['quantity']];
        log_audit($pdo, 'create', 'products', $newId, null, ['product_name' => $p['name'], 'source' => 'bulk import: ' . $originalName]);
    }
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("import_products: failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to save the imported products: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'summary' => [
        'filename' => $originalName,
        'total_rows' => count($rawRows) - 1,
        'created_count' => count($created),
        'skipped_existing' => $skippedExisting,
        'skipped_unknown_category' => $skippedUnknownCategory,
        'invalid' => $invalid,
    ],
    'created' => $created,
]);
