-- ============================================================================
-- Valluvam Team Manager access (1 Oct 2026) — ADDITIVE ONLY.
-- Manager = everything the Executive has + approvals inbox, audit trail,
-- transaction trace, reports, and the approvals a manager gives:
-- purchase requests (step 1), stock adjustments, stock counts, waste,
-- sales returns, purchase returns. NOT given: accounts / ledger / payments / expenses.
-- Nothing is deleted or changed; running it twice is safe (INSERT IGNORE).
-- ============================================================================

-- 1) Migration
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p
  ON p.perm_key IN ('purchase.view','purchase.create','warehouses.view','sales_orders.view','sales_orders.create','sales_orders.edit','manual_sales.create','credit_sales.view','credit_sales.create','invoices.view','dc.view','sales_return.create','sales.fulfilment','sales.channels','customer.view','customers.view','inventory.view','stock_in.create','stock_out.create','stock_count.create','stock_adjust.create','warehouse.view','warehouse.transfer','warehouse.locations','warehouses.create','raw_materials.manage','pnl.view','assets.view','assets.create','assets.edit','waste.view','waste.create','suppliers.view','suppliers.create','supplier.profile','documents.view','documents.upload','notifications.view','reports.erp','purchase.manager_approve','approvals.view','audit_logs.view','trace.view','reports.view','stock_adjust.approve','stock_count.approve','waste.approve','sales_return.approve','purchase_return.approve')
WHERE r.name = 'Valluvam Team Manager';

-- 2) Verification
SELECT r.name, COUNT(*) AS permissions FROM admin_role_permissions rp JOIN admin_roles r ON r.id = rp.role_id
WHERE r.name = 'Valluvam Team Manager' GROUP BY r.name;
