<?php
// One-off migration runner: creates the 'orders' and 'order_items' tables
// if they don't already exist. Upload to the site root, view once, then
// DELETE this file — it has no auth and shouldn't be left on a live server.

require_once __DIR__ . '/assets/db_query/config.php';

header('Content-Type: text/plain');

$sqlFile = __DIR__ . '/create_orders_tables.sql';
if (!is_file($sqlFile)) {
    echo "create_orders_tables.sql not found next to this script. Upload both files.\n";
    exit;
}

$sql = file_get_contents($sqlFile);
// Split on semicolons that end a statement (simple split is fine for this file).
$statements = array_filter(array_map('trim', explode(';', $sql)));

try {
    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        $pdo->exec($stmt);
    }
    echo "Done.\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "'orders' table exists: " . (in_array('orders', $tables, true) ? "YES" : "NO") . "\n";
    echo "'order_items' table exists: " . (in_array('order_items', $tables, true) ? "YES" : "NO") . "\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
