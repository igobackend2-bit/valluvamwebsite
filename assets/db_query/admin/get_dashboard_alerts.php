<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_admin_session();

$alerts = [];

function push_alerts(PDO $pdo, array &$alerts, string $sql, string $icon, string $level, callable $label) {
    try {
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $alerts[] = ['icon' => $icon, 'level' => $level, 'label' => $label($row)];
        }
    } catch (PDOException $e) {
        // Module not migrated yet — skip silently.
    }
}

push_alerts($pdo, $alerts,
    "SELECT product_name, stock FROM product_details WHERE stock = 0",
    'fa-triangle-exclamation', 'danger', fn($r) => "Out of stock: " . $r['product_name']);

push_alerts($pdo, $alerts,
    "SELECT product_name, stock FROM product_details WHERE stock > 0 AND stock <= 10",
    'fa-box', 'amber', fn($r) => "Low stock: " . $r['product_name'] . " ({$r['stock']} left)");

push_alerts($pdo, $alerts,
    "SELECT so_number FROM sales_orders WHERE status = 'draft'",
    'fa-file-lines', 'neutral', fn($r) => "Sales order in draft: " . $r['so_number']);

push_alerts($pdo, $alerts,
    "SELECT dc_number FROM delivery_challans WHERE delivery_status IN ('draft','ready')",
    'fa-truck', 'amber', fn($r) => "DC awaiting dispatch: " . $r['dc_number']);

push_alerts($pdo, $alerts,
    "SELECT invoice_number, due_date FROM invoices WHERE payment_status IN ('issued','partially_paid') AND due_date IS NOT NULL AND due_date < CURDATE()",
    'fa-clock', 'danger', fn($r) => "Overdue invoice: " . $r['invoice_number']);

push_alerts($pdo, $alerts,
    "SELECT invoice_number FROM invoices WHERE payment_status = 'draft'",
    'fa-file-invoice', 'neutral', fn($r) => "Invoice pending issue: " . $r['invoice_number']);

push_alerts($pdo, $alerts,
    "SELECT waste_id FROM waste_records WHERE status = 'reported'",
    'fa-trash', 'amber', fn($r) => "Waste pending approval: " . $r['waste_id']);

push_alerts($pdo, $alerts,
    "SELECT asset_name, warranty_end_date FROM assets WHERE warranty_end_date IS NOT NULL AND warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
    'fa-shield-halved', 'amber', fn($r) => "Warranty expiring soon: " . $r['asset_name']);

// Cap so the panel never explodes on a busy day.
$alerts = array_slice($alerts, 0, 25);

echo json_encode(['status' => 'success', 'alerts' => $alerts]);
