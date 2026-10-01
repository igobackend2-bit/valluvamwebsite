<?php
// ============================================================================
// Page access per role (added 1 Oct 2026)
// Roles listed here may open only these admin pages; every other role (Super
// Admin, Admin, CEO, Warehouse, Sales, …) is limited by its permissions only.
// The old role names are kept so users who are still logged in keep working.
// ============================================================================
if (!function_exists('role_access_pages')) {
function role_access_key(string $roleName): string {
    $map = [
        'Valluvam Team Executive' => 'executive', 'Normal Admin' => 'executive',
        'Valluvam Team Manager' => 'manager', 'Manager' => 'manager',
        'L1 (Sourcing)' => 'l1',
        'Admin' => 'admin', 'Backend' => 'admin',
        'CEO' => 'ceo',
        'Accounts Team' => 'accounts', 'Accounts' => 'accounts',
        'Super Admin' => 'super',
    ];
    return $map[$roleName] ?? '';
}
/** The six dashboards a Super Admin can give a user (1 Oct 2026): label, permissions the dashboard needs, pages it opens. */
function role_dash_defs(): array {
    return [
        'executive' => ['label' => 'Valluvam Team Executive — raise purchase requests', 'perms' => ['purchase.view', 'purchase.create', 'warehouses.view'],
                        'pages' => ['purchase_requests.php', 'purchase_flow.php']],
        'manager'   => ['label' => 'Valluvam Team Manager — step 1 approval of requests', 'perms' => ['purchase.view', 'purchase.manager_approve'],
                        'pages' => ['purchase_requests.php', 'purchase_flow.php']],
        'l1'        => ['label' => 'L1 (Sourcing) — 3 quotations, transport, DC, unloading, QC',
                        'perms' => ['purchase.view', 'flow.source', 'rfq.manage', 'shipment.manage', 'grn.create', 'qc.manage', 'documents.view', 'documents.upload', 'suppliers.view', 'suppliers.create', 'warehouses.view', 'warehouse.view', 'inventory.view', 'notifications.view'],
                        'pages' => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'shipments.php', 'goods_receipts.php', 'quality_checks.php', 'suppliers.php', 'supplier_360.php', 'documents.php', 'notifications.php', 'print_erp.php', 'rfqs.php']],
        'admin'     => ['label' => 'Admin — final approval of requests, shop choice and POs',
                        'perms' => ['purchase.view', 'purchase.create', 'purchase.approve', 'purchase.backend_approve', 'approvals.view', 'approvals.manage', 'documents.view', 'documents.upload', 'notifications.view', 'suppliers.view', 'suppliers.create', 'warehouses.view', 'warehouse.view', 'inventory.view', 'trace.view', 'reports.erp'],
                        'pages' => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'approvals.php', 'suppliers.php', 'supplier_360.php', 'documents.php', 'notifications.php', 'print_erp.php', 'transaction_trace.php', 'erp_reports_center.php', 'purchase_dashboard.php']],
        'ceo'       => ['label' => 'CEO — approve big POs, company KPIs',
                        'perms' => ['po.ceo_approve', 'purchase.view', 'purchase.approve', 'purchase.backend_approve', 'approvals.view', 'approvals.manage', 'reports.erp', 'reports.view', 'pnl.view', 'accounting.view', 'inventory.view', 'dashboard.view', 'trace.view', 'documents.view', 'notifications.view', 'suppliers.view', 'customer.view'],
                        'pages' => ['purchase_orders.php', 'purchase_flow.php', 'approvals.php', 'profit_loss.php', 'financial_statements.php', 'erp_reports_center.php', 'transaction_trace.php', 'reports.php', 'product_profitability.php', 'documents.php', 'notifications.php', 'print_erp.php', 'purchase_dashboard.php']],
        'accounts'  => ['label' => 'Accounts Team — supplier payments with proof, bills, accounts',
                        'perms' => ['purchase.view', 'purchase_payment.create', 'purchase_invoice.create', 'documents.view', 'documents.upload', 'notifications.view', 'suppliers.view', 'accounting.view', 'accounts.view', 'accounts.create', 'expense.manage', 'pnl.view', 'reports.view', 'dashboard.view'],
                        'pages' => ['purchase_flow.php', 'purchase_orders.php', 'purchase_payments.php', 'purchase_invoices.php', 'supplier_ledger.php', 'supplier_360.php', 'accounts.php', 'expenses.php', 'receivables.php', 'chart_of_accounts.php', 'journals.php', 'general_ledger.php', 'financial_statements.php', 'ap_ar_aging.php', 'bank_accounts.php', 'gst_summary.php', 'financial_periods.php', 'profit_loss.php', 'documents.php', 'notifications.php', 'print_erp.php']],
    ];
}
/** Dashboards a Super Admin gave this user (empty = the role's normal dashboard). Cached per request. */
function user_dash_keys(?PDO $pdo, int $userId): array {
    static $cache = [];
    if (!$pdo || $userId <= 0) return [];
    if (!array_key_exists($userId, $cache)) {
        try { $st = $pdo->prepare("SELECT dash_key FROM admin_user_dashboards WHERE user_id = ?"); $st->execute([$userId]); $cache[$userId] = array_values(array_intersect(array_keys(role_dash_defs()), $st->fetchAll(PDO::FETCH_COLUMN))); }
        catch (PDOException $e) { $cache[$userId] = []; }   // table not installed yet
    }
    return $cache[$userId];
}
/** true when one of the user's given dashboards includes the permission. */
function user_dash_has_perm(?PDO $pdo, string $perm): bool {
    $keys = user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0));
    $defs = role_dash_defs();
    foreach ($keys as $k) if (in_array($perm, $defs[$k]['perms'], true)) return true;
    return false;
}
/** Allowed pages for the role, or null when the role is not page-limited. */
function role_access_pages(string $roleName, array $userDash = []): ?array {
    $common = ['index.php', 'my_account.php', 'logout.php'];
    $pages = [
        'executive' => ['purchase_requests.php', 'purchase_flow.php'],
        'manager'   => ['purchase_requests.php', 'purchase_flow.php'],
        'l1'        => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'shipments.php', 'goods_receipts.php', 'quality_checks.php',
                        'suppliers.php', 'supplier_360.php', 'documents.php', 'notifications.php', 'print_erp.php', 'rfqs.php'],
        'accounts'  => ['purchase_flow.php', 'purchase_orders.php', 'purchase_payments.php', 'purchase_invoices.php', 'supplier_ledger.php', 'supplier_360.php',
                        'accounts.php', 'expenses.php', 'receivables.php', 'chart_of_accounts.php', 'journals.php', 'general_ledger.php', 'financial_statements.php',
                        'ap_ar_aging.php', 'bank_accounts.php', 'gst_summary.php', 'financial_periods.php', 'profit_loss.php', 'documents.php', 'notifications.php', 'print_erp.php'],
    ];
    $k = role_access_key($roleName);
    $base = isset($pages[$k]) ? array_merge($common, $pages[$k]) : null;
    if ($base === null && $userDash && $k === '') $base = $common;   // other roles (Staff, Sales …) given dashboards: limited to those dashboards
    // extra dashboards given by the Super Admin open their pages too (only matters for page-limited roles)
    if ($base !== null && $userDash) foreach ($userDash as $d) $base = array_merge($base, role_dash_defs()[$d]['pages'] ?? []);
    return $base === null ? null : array_values(array_unique($base));
}
}
