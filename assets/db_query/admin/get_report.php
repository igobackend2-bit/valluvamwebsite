<?php
// Single flexible report endpoint. Switches on `type` and queries the
// relevant table, which may belong to another module's migration that
// hasn't run yet — each branch is wrapped individually so one missing
// table doesn't break the others.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'reports.view');

$type      = trim($_GET['type'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');

$dateFromTs = $date_from !== '' ? $date_from . ' 00:00:00' : null;
$dateToTs   = $date_to !== '' ? $date_to . ' 23:59:59' : null;

function run_report_query(PDO $pdo, string $sql, array $params): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function not_installed_response(string $note = "This module isn't installed yet — run its migration first.") {
    echo json_encode(['status' => 'success', 'rows' => [], 'note' => $note]);
    exit;
}

if ($type === '') {
    echo json_encode(['status' => 'error', 'message' => 'Report type is required']);
    exit;
}

try {
    switch ($type) {

        case 'sales_report':
        case 'sales_order_report':
            try {
                $sql = "SELECT id, so_number, customer_name, customer_mobile, status, total_amount, order_date, created_at
                        FROM sales_orders WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND order_date >= ?"; $params[] = $date_from; }
                if ($dateToTs)   { $sql .= " AND order_date <= ?"; $params[] = $date_to; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'invoice_report':
            try {
                $sql = "SELECT id, invoice_number, customer_name, status, total_amount, invoice_date, created_at
                        FROM invoices WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND invoice_date >= ?"; $params[] = $date_from; }
                if ($dateToTs)   { $sql .= " AND invoice_date <= ?"; $params[] = $date_to; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'payment_report':
            try {
                $sql = "SELECT id, transaction_type, amount, method, reference, transaction_date, created_at
                        FROM accounts_transactions WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND transaction_date >= ?"; $params[] = $date_from; }
                if ($dateToTs)   { $sql .= " AND transaction_date <= ?"; $params[] = $date_to; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'inventory_report':
            try {
                $sql = "SELECT id, product_name, stock FROM product_details ORDER BY product_name ASC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, [])]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'stock_in_report':
            try {
                $sql = "SELECT id, product_id, quantity, warehouse_id, reason, created_by, created_at
                        FROM stock_ins WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND created_at >= ?"; $params[] = $dateFromTs; }
                if ($dateToTs)   { $sql .= " AND created_at <= ?"; $params[] = $dateToTs; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'stock_out_report':
            try {
                $sql = "SELECT id, product_id, quantity, warehouse_id, reason, created_by, created_at
                        FROM stock_outs WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND created_at >= ?"; $params[] = $dateFromTs; }
                if ($dateToTs)   { $sql .= " AND created_at <= ?"; $params[] = $dateToTs; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'stock_movement_report':
            try {
                $sql = "SELECT id, product_id, change_type, quantity_change, resulting_stock, reason, created_at
                        FROM stock_movements WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND created_at >= ?"; $params[] = $dateFromTs; }
                if ($dateToTs)   { $sql .= " AND created_at <= ?"; $params[] = $dateToTs; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'warehouse_report':
            try {
                $sql = "SELECT id, name, code, location, manager_name, status FROM warehouses ORDER BY id ASC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, [])]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'customer_outstanding_report':
        case 'supplier_payable_report':
            // Aging logic belongs to the Accounts module and isn't wired
            // up here yet — see build summary.
            echo json_encode([
                'status' => 'success',
                'rows' => [],
                'note' => 'Not yet available — requires the Accounts module\'s aging logic, planned for a future pass.'
            ]);
            break;

        case 'expense_report':
            try {
                $sql = "SELECT id, category, amount, description, transaction_date, created_at
                        FROM accounts_transactions WHERE transaction_type = 'expense'";
                $params = [];
                if ($dateFromTs) { $sql .= " AND transaction_date >= ?"; $params[] = $date_from; }
                if ($dateToTs)   { $sql .= " AND transaction_date <= ?"; $params[] = $date_to; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'income_report':
            try {
                $sql = "SELECT id, category, amount, description, transaction_date, created_at
                        FROM accounts_transactions WHERE transaction_type = 'income'";
                $params = [];
                if ($dateFromTs) { $sql .= " AND transaction_date >= ?"; $params[] = $date_from; }
                if ($dateToTs)   { $sql .= " AND transaction_date <= ?"; $params[] = $date_to; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'asset_report':
            try {
                $sql = "SELECT id, asset_name, category, purchase_date, purchase_value, status, created_at
                        FROM assets WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND purchase_date >= ?"; $params[] = $date_from; }
                if ($dateToTs)   { $sql .= " AND purchase_date <= ?"; $params[] = $date_to; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        case 'waste_report':
            try {
                $sql = "SELECT id, product_id, quantity, reason, status, created_by, created_at
                        FROM waste_records WHERE 1=1";
                $params = [];
                if ($dateFromTs) { $sql .= " AND created_at >= ?"; $params[] = $dateFromTs; }
                if ($dateToTs)   { $sql .= " AND created_at <= ?"; $params[] = $dateToTs; }
                $sql .= " ORDER BY id DESC LIMIT 1000";
                echo json_encode(['status' => 'success', 'rows' => run_report_query($pdo, $sql, $params)]);
            } catch (PDOException $e) {
                not_installed_response();
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown report type']);
    }
} catch (Throwable $e) {
    error_log("Error running report ($type): " . $e->getMessage());
    echo json_encode(['status' => 'success', 'rows' => [], 'note' => "This module isn't installed yet — run its migration first."]);
}
