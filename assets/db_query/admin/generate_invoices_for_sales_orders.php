<?php
// One-off backfill: scans every non-draft, non-cancelled sales order that
// has no active invoice yet and auto-generates one for each — used by the
// "Generate from Sales Orders" button on the Invoices page so pre-existing
// sales orders (created before auto-invoicing existed) get billed too.
// New sales orders no longer need this — save_sales_order.php auto-invoices
// them the moment they're saved as confirmed/processing/etc.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/invoice_helper.php';

require_permission($pdo, 'invoices.create');

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

try {
    $stmt = $pdo->query("SELECT so.id FROM sales_orders so
                          WHERE so.status NOT IN ('draft', 'cancelled')
                          AND NOT EXISTS (
                              SELECT 1 FROM invoices i WHERE i.sales_order_id = so.id AND i.status != 'cancelled'
                          )
                          ORDER BY so.id ASC");
    $soIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("generate_invoices_for_sales_orders: lookup failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to look up sales orders: ' . $e->getMessage()]);
    exit;
}

$created = [];
$skipped = 0;
foreach ($soIds as $soId) {
    $result = auto_generate_invoice_for_sales_order($pdo, (int)$soId, $adminUsername);
    if ($result) {
        $created[] = $result['invoice_number'];
    } else {
        $skipped++;
    }
}

echo json_encode([
    'status' => 'success',
    'created_count' => count($created),
    'created' => $created,
    'skipped_count' => $skipped,
]);
