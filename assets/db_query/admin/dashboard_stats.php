<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Small helper: run a scalar query, return $default if the table/column
// doesn't exist yet (ERP migrations not run) instead of failing the whole
// dashboard.
function safe_scalar(PDO $pdo, string $sql, $default = 0) {
    try {
        $stmt = $pdo->query($sql);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : $val;
    } catch (PDOException $e) {
        return $default;
    }
}

try {
    // ── Existing website-order stats (untouched logic) ─────────────────
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN order_status VARCHAR(50) DEFAULT 'ordered'");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }

    $checkColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
    $hasOrderStatus = $checkColumn->rowCount() > 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    if ($hasOrderStatus) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE COALESCE(order_status, 'ordered') IN ('ordered', 'packed', 'couriered')");
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    }
    $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    if ($hasOrderStatus) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE COALESCE(order_status, 'ordered') = 'delivered'");
    } else {
        $stmt = $pdo->query("SELECT 0 as total");
    }
    $delivered_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM product_details");
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // ── ERP module stats (each gracefully defaults to 0/empty if that
    //    module's migration hasn't been run yet) ────────────────────────
    $stats = [
        'total_orders' => (int)$total_orders,
        'pending_orders' => (int)$pending_orders,
        'delivered_orders' => (int)$delivered_orders,
        'total_products' => (int)$total_products,

        'total_sales_orders'   => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM sales_orders"),
        'pending_sales_orders' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE status IN ('draft','confirmed','processing','ready_for_dispatch','dispatched')"),
        'completed_sales_orders' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE status IN ('delivered','completed')"),
        'cancelled_sales_orders' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE status = 'cancelled'"),

        'total_invoices'   => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status <> 'cancelled'"),
        'pending_invoices' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status IN ('draft','issued','partially_paid','overdue')"),
        'paid_invoices'    => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status = 'paid'"),
        'outstanding_amount' => (float)safe_scalar($pdo, "SELECT COALESCE(SUM(grand_total - amount_paid),0) FROM invoices WHERE payment_status NOT IN ('paid','cancelled')"),

        'low_stock_products' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM product_details WHERE stock IS NOT NULL AND stock > 0 AND stock < 20"),
        'out_of_stock_products' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM product_details WHERE stock IS NOT NULL AND stock = 0"),
        'stock_in_today'  => (int)safe_scalar($pdo, "SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE movement_type='stock_in' AND DATE(created_at) = CURDATE()"),
        'stock_out_today' => (int)safe_scalar($pdo, "SELECT COALESCE(ABS(SUM(quantity)),0) FROM stock_movements WHERE movement_type='stock_out' AND DATE(created_at) = CURDATE()"),

        'delivery_challans_pending' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM delivery_challans WHERE delivery_status NOT IN ('delivered','cancelled')"),
        'manual_sales_today' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM manual_sales WHERE DATE(sales_date) = CURDATE()"),

        'total_assets' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM assets WHERE status NOT IN ('disposed','retired')"),
        'asset_value'  => (float)safe_scalar($pdo, "SELECT COALESCE(SUM(current_value),0) FROM assets WHERE status NOT IN ('disposed','retired')"),

        'total_income'  => (float)safe_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM accounts_transactions WHERE type IN ('income','payment_received') AND status='completed'"),
        'total_expense' => (float)safe_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM accounts_transactions WHERE type IN ('expense','payment_made') AND status='completed'"),

        'waste_records_pending' => (int)safe_scalar($pdo, "SELECT COUNT(*) FROM waste_records WHERE status = 'reported'"),

        'monthly_sales' => (float)safe_scalar($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales_orders WHERE status NOT IN ('cancelled','draft') AND MONTH(order_date)=MONTH(CURDATE()) AND YEAR(order_date)=YEAR(CURDATE())"),
    ];
    $stats['accounts_balance'] = round($stats['total_income'] - $stats['total_expense'], 2);

    echo json_encode(['status' => 'success', 'stats' => $stats]);
} catch (PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch statistics']);
}
