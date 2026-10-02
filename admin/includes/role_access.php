<?php
// ============================================================================
// Page access per role (added 1 Oct 2026)
// Roles listed here may open only these admin pages; every other role (Super
// Admin, Warehouse, Sales, …) is limited by its permissions only.
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
        // Executive (1 Oct 2026): sales, inventory, warehouse, purchase requests/orders, assets, waste, customers, suppliers — no accounts, no approvals
        'executive' => ['label' => 'Valluvam Team Executive — sales, stock, purchase requests, assets, waste, customers, suppliers', 'perms' => ['purchase.view', 'purchase.create', 'warehouses.view', 'sales_orders.view', 'sales_orders.create', 'sales_orders.edit', 'manual_sales.create', 'credit_sales.view', 'credit_sales.create', 'invoices.view', 'dc.view', 'sales_return.create', 'sales.fulfilment', 'sales.channels', 'customer.view', 'customers.view', 'inventory.view', 'stock_in.create', 'stock_out.create', 'stock_count.create', 'stock_adjust.create', 'warehouse.view', 'warehouse.transfer', 'warehouse.locations', 'warehouses.create', 'raw_materials.manage', 'pnl.view', 'assets.view', 'assets.create', 'assets.edit', 'waste.view', 'waste.create', 'suppliers.view', 'suppliers.create', 'supplier.profile', 'documents.view', 'documents.upload', 'notifications.view', 'reports.erp'],
                        'pages' => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'print_erp.php', 'sales_orders.php', 'print_sales_order.php', 'manual_sales.php', 'credit_sale.php', 'orders.php', 'sales_returns.php', 'fulfilment.php', 'credit_notes.php', 'customer_360.php', 'sales_channels.php', 'products.php', 'categories.php', 'homepage.php', 'inventory_overview.php', 'stock_in.php', 'print_stock_in.php', 'stock_out.php', 'print_stock_out.php', 'stock_movements.php', 'warehouse_stock.php', 'stock_transfers.php', 'stock_counts.php', 'batches.php', 'warehouses.php', 'warehouse_locations.php', 'raw_materials.php', 'repacking.php', 'stock_valuation.php', 'stock_adjustments.php', 'assets.php', 'asset_detail.php', 'waste.php', 'customers.php', 'account_requests.php', 'leads.php', 'reviews.php', 'feedback.php', 'coupons.php', 'suppliers.php', 'supplier_ledger.php', 'supplier_360.php', 'documents.php', 'notifications.php']],
        // Manager (1 Oct 2026): everything the Executive has + approvals, audit trail, reports, goods receipts / QC / transport — no accounts
        'manager'   => ['label' => 'Valluvam Team Manager — everything the Executive sees + approvals, audit, reports',
                        'perms' => ['purchase.view', 'purchase.manager_approve', 'approvals.view', 'audit_logs.view', 'trace.view', 'reports.view', 'reports.erp',
                                    'stock_adjust.approve', 'stock_count.approve', 'waste.approve', 'sales_return.approve', 'purchase_return.approve'],
                        'pages' => ['approvals.php', 'audit_logs.php', 'transaction_trace.php', 'erp_reports_center.php', 'reports.php', 'purchase_dashboard.php', 'purchase_history.php',
                                    'goods_receipts.php', 'quality_checks.php', 'shipments.php', 'rfqs.php', 'invoices.php', 'print_invoice.php', 'delivery_challans.php', 'print_dc.php']],
        'l1'        => ['label' => 'L1 (Sourcing) — 3 quotations, transport, DC, unloading, QC',
                        'perms' => ['purchase.view', 'flow.source', 'rfq.manage', 'shipment.manage', 'grn.create', 'qc.manage', 'documents.view', 'documents.upload', 'suppliers.view', 'suppliers.create', 'warehouses.view', 'warehouse.view', 'inventory.view', 'notifications.view'],
                        'pages' => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'shipments.php', 'goods_receipts.php', 'quality_checks.php', 'suppliers.php', 'supplier_360.php', 'documents.php', 'notifications.php', 'print_erp.php', 'rfqs.php']],
        'admin'     => ['label' => 'Admin — final approval of requests, shop choice and POs',
                        'perms' => ['purchase.view', 'purchase.create', 'purchase.approve', 'purchase.backend_approve', 'approvals.view', 'approvals.manage', 'documents.view', 'documents.upload', 'notifications.view', 'suppliers.view', 'suppliers.create', 'warehouses.view', 'warehouse.view', 'inventory.view', 'trace.view', 'reports.erp'],
                        'pages' => ['purchase_dashboard.php', 'purchase_requests.php', 'purchase_flow.php', 'rfqs.php', 'purchase_orders.php', 'shipments.php', 'goods_receipts.php', 'quality_checks.php', 'purchase_invoices.php', 'purchase_returns.php', 'debit_notes.php', 'purchase_payments.php', 'purchase_history.php', 'suppliers.php', 'supplier_ledger.php', 'supplier_360.php', 'approvals.php', 'documents.php', 'notifications.php', 'print_erp.php', 'transaction_trace.php', 'erp_reports_center.php', 'stock_in.php', 'print_stock_in.php', 'warehouse_locations.php']],   // Stock In: Admin adds received purchases to stock
        'ceo'       => ['label' => 'CEO — approve big POs, company KPIs',
                        'perms' => ['po.ceo_approve', 'purchase.view', 'purchase.approve', 'purchase.backend_approve', 'approvals.view', 'approvals.manage', 'reports.erp', 'reports.view', 'pnl.view', 'accounting.view', 'inventory.view', 'dashboard.view', 'trace.view', 'documents.view', 'notifications.view', 'suppliers.view', 'customer.view'],
                        'pages' => ['approvals.php', 'purchase_flow.php', 'purchase_orders.php', 'purchase_dashboard.php', 'purchase_history.php', 'profit_loss.php', 'financial_statements.php', 'product_profitability.php', 'erp_reports_center.php', 'reports.php', 'transaction_trace.php', 'ap_ar_aging.php', 'stock_valuation.php', 'supplier_360.php', 'customer_360.php', 'accounts.php', 'documents.php', 'notifications.php', 'print_erp.php', 'audit_logs.php']],
        'accounts'  => ['label' => 'Accounts Team — supplier payments with proof, bills, accounts',
                        'perms' => ['purchase.view', 'purchase_payment.create', 'purchase_invoice.create', 'documents.view', 'documents.upload', 'notifications.view', 'suppliers.view', 'accounting.view', 'accounts.view', 'accounts.create', 'expense.manage', 'pnl.view', 'reports.view', 'dashboard.view', 'warehouses.view', 'invoices.view', 'credit_sales.view', 'customer.view'],
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
    if (in_array('manager', $keys, true)) $keys[] = 'executive';   // the Manager dashboard includes the Executive one
    foreach ($keys as $k) if (in_array($perm, $defs[$k]['perms'], true)) return true;
    return false;
}
/**
 * Pages opened by the permissions the Super Admin ticked for this role in
 * Admin Users → Roles & permissions (1 Oct 2026). Page-limited roles (Executive,
 * Manager, L1, Admin, CEO, Accounts) get these on top of their own pages.
 */
function role_perm_page_map(): array {
    return [
        'sales_orders.' => ['sales_orders.php', 'print_sales_order.php'], 'manual_sales.' => ['manual_sales.php'], 'credit_sales.' => ['credit_sale.php'],
        'invoices.' => ['invoices.php', 'print_invoice.php'], 'dc.' => ['delivery_challans.php', 'print_dc.php'], 'sales_return.' => ['sales_returns.php', 'credit_notes.php'],
        'sales.fulfilment' => ['fulfilment.php'], 'sales.channels' => ['sales_channels.php'], 'customer.view' => ['customer_360.php'], 'customers.view' => ['customers.php'],
        'inventory.view' => ['inventory_overview.php', 'stock_movements.php', 'stock_valuation.php'], 'inventory.adjust' => ['inventory_overview.php'],
        'stock_in.' => ['stock_in.php', 'print_stock_in.php'], 'stock_out.' => ['stock_out.php', 'print_stock_out.php'], 'stock_count.' => ['stock_counts.php'], 'stock_adjust.' => ['stock_adjustments.php'],
        'warehouse.view' => ['warehouse_stock.php', 'batches.php'], 'warehouse.transfer' => ['stock_transfers.php'], 'warehouse.locations' => ['warehouse_locations.php'], 'warehouses.' => ['warehouses.php'],
        'raw_materials.' => ['raw_materials.php', 'repacking.php'], 'assets.' => ['assets.php'], 'waste.' => ['waste.php'],
        'expense.' => ['expenses.php'], 'accounts.' => ['accounts.php', 'receivables.php'], 'accounting.' => ['chart_of_accounts.php', 'journals.php', 'general_ledger.php', 'financial_statements.php', 'ap_ar_aging.php', 'gst_summary.php', 'financial_periods.php'],
        'bank.' => ['bank_accounts.php'], 'pnl.view' => ['profit_loss.php', 'product_profitability.php'], 'reports.view' => ['reports.php'], 'reports.erp' => ['erp_reports_center.php'], 'trace.view' => ['transaction_trace.php'],
        'approvals.' => ['approvals.php'], 'notifications.view' => ['notifications.php'], 'documents.' => ['documents.php'], 'audit_logs.view' => ['audit_logs.php'], 'settings.view' => ['settings.php'],
        'suppliers.' => ['suppliers.php', 'supplier_360.php'], 'supplier.profile' => ['supplier_360.php'],
        'purchase.view' => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'purchase_history.php', 'purchase_dashboard.php', 'print_erp.php'], 'purchase.create' => ['purchase_requests.php'],
        'stockflow.' => ['stock_flow.php', 'stock_operations.php', 'daily_stock.php', 'stock_audit.php', 'stock_handover.php'],   // stock lifecycle (2 Oct 2026)
        'rfq.manage' => ['rfqs.php'], 'shipment.manage' => ['shipments.php'], 'grn.create' => ['goods_receipts.php'], 'qc.manage' => ['quality_checks.php'],
        'purchase_invoice.' => ['purchase_invoices.php'], 'purchase_payment.' => ['purchase_payments.php', 'supplier_ledger.php'], 'purchase_return.' => ['purchase_returns.php', 'debit_notes.php'],
    ];
}
function role_perm_pages(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $pdo = $GLOBALS['pdo'] ?? null; $rid = (int)($_SESSION['admin_role_id'] ?? 0);
    if (!$pdo instanceof PDO || $rid <= 1) return $cache;
    try { $st = $pdo->prepare("SELECT perm_key FROM admin_role_permissions WHERE role_id = ?"); $st->execute([$rid]); $perms = $st->fetchAll(PDO::FETCH_COLUMN); }
    catch (PDOException $e) { return $cache; }
    foreach (role_perm_page_map() as $prefix => $pages)
        foreach ($perms as $p) if ($p === $prefix || (substr($prefix, -1) === '.' && strpos($p, $prefix) === 0)) { $cache = array_merge($cache, $pages); break; }
    return $cache = array_values(array_unique($cache));
}
/** Allowed pages for the role, or null when the role is not page-limited. */
function role_access_pages(string $roleName, array $userDash = []): ?array {
    $common = ['index.php', 'my_account.php', 'logout.php'];
    $pages = [
        'executive' => role_dash_defs()['executive']['pages'],
        'manager'   => array_merge(role_dash_defs()['executive']['pages'], role_dash_defs()['manager']['pages']),
        'l1'        => ['purchase_requests.php', 'purchase_flow.php', 'purchase_orders.php', 'shipments.php', 'goods_receipts.php', 'quality_checks.php',
                        'suppliers.php', 'supplier_360.php', 'documents.php', 'notifications.php', 'print_erp.php', 'rfqs.php'],
        // Admin and CEO see only their own dashboard + these pages (added 1 Oct 2026)
        'admin'     => role_dash_defs()['admin']['pages'],
        'ceo'       => role_dash_defs()['ceo']['pages'],
        'accounts'  => ['purchase_flow.php', 'purchase_orders.php', 'purchase_payments.php', 'purchase_invoices.php', 'supplier_ledger.php', 'supplier_360.php',
                        'accounts.php', 'expenses.php', 'receivables.php', 'chart_of_accounts.php', 'journals.php', 'general_ledger.php', 'financial_statements.php',
                        'ap_ar_aging.php', 'bank_accounts.php', 'gst_summary.php', 'financial_periods.php', 'profit_loss.php', 'documents.php', 'notifications.php', 'print_erp.php',
                        // (1 Oct 2026) money side of returns and sales: refunds / credits, customer invoices, approvals of expenses / bills / payments
                        'purchase_returns.php', 'debit_notes.php', 'invoices.php', 'print_invoice.php', 'credit_notes.php', 'approvals.php', 'transaction_trace.php', 'erp_reports_center.php', 'reports.php'],
    ];
    $k = role_access_key($roleName);
    $base = isset($pages[$k]) ? array_merge($common, $pages[$k]) : null;
    if ($base === null && $userDash && $k === '') $base = $common;   // other roles (Staff, Sales …) given dashboards: limited to those dashboards
    // extra dashboards given by the Super Admin open their pages too (only matters for page-limited roles)
    if ($base !== null && $userDash) foreach ($userDash as $d) $base = array_merge($base, role_dash_defs()[$d]['pages'] ?? [], $d === 'manager' ? role_dash_defs()['executive']['pages'] : []);
    if ($base !== null) {   // + pages of the permissions ticked for the role
        $extra = role_perm_pages();
        if (in_array($k, ['executive', 'manager'], true))   // Executive / Manager: everything except the accounts pages
            $extra = array_diff($extra, ['expenses.php', 'accounts.php', 'receivables.php', 'chart_of_accounts.php', 'journals.php', 'general_ledger.php', 'financial_statements.php', 'ap_ar_aging.php',
                                         'gst_summary.php', 'financial_periods.php', 'bank_accounts.php', 'profit_loss.php', 'product_profitability.php', 'purchase_payments.php', 'supplier_ledger.php', 'purchase_invoices.php']);
        if ($k === 'accounts')   // Accounts Team: not the request / sourcing pages — they start once a PO exists (1 Oct 2026)
            $extra = array_diff($extra, ['purchase_requests.php', 'rfqs.php', 'shipments.php', 'goods_receipts.php', 'quality_checks.php', 'warehouses.php']);
        $base = array_merge($base, $extra);
    }
    return $base === null ? null : array_values(array_unique($base));
}
}
