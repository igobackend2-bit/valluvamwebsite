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
/** Allowed pages for the role, or null when the role is not page-limited. */
function role_access_pages(string $roleName): ?array {
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
    return isset($pages[$k]) ? array_merge($common, $pages[$k]) : null;
}
}
