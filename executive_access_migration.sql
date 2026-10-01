-- ============================================================================
-- Valluvam Team Executive access (1 Oct 2026) — ADDITIVE ONLY.
-- Gives the "Valluvam Team Executive" role the permissions its pages need:
-- sales orders, manual sales, credit sale, sales returns, fulfilment, credit notes,
-- customer 360, sales channels, inventory, stock in/out, stock movement, stock by
-- warehouse, transfers, counts, batches, warehouses + locations, raw materials,
-- repacking, stock valuation, stock adjustment (request only), purchase requests,
-- purchase orders (view), assets, waste (report only), customers, suppliers.
-- NOT given: approvals, accounts / ledger / payments / expenses.
-- Nothing is deleted or changed; running it twice is safe (INSERT IGNORE).
-- BACKUP first:  mysqldump valluvam-db admin_role_permissions > backup_role_permissions.sql
-- ============================================================================

-- 1) Migration
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p
  ON p.perm_key IN ('purchase.view', 'purchase.create', 'warehouses.view', 'sales_orders.view', 'sales_orders.create', 'sales_orders.edit', 'manual_sales.create', 'credit_sales.view', 'credit_sales.create', 'invoices.view', 'dc.view', 'sales_return.create', 'sales.fulfilment', 'sales.channels', 'customer.view', 'customers.view', 'inventory.view', 'stock_in.create', 'stock_out.create', 'stock_count.create', 'stock_adjust.create', 'warehouse.view', 'warehouse.transfer', 'warehouse.locations', 'warehouses.create', 'raw_materials.manage', 'pnl.view', 'assets.view', 'assets.create', 'assets.edit', 'waste.view', 'waste.create', 'suppliers.view', 'suppliers.create', 'supplier.profile', 'documents.view', 'documents.upload', 'notifications.view', 'reports.erp')
WHERE r.name = 'Valluvam Team Executive';

-- 2) Verification (expect 39 or more)
SELECT r.name, COUNT(*) AS permissions FROM admin_role_permissions rp JOIN admin_roles r ON r.id = rp.role_id
WHERE r.name = 'Valluvam Team Executive' GROUP BY r.name;

-- 3) Rollback (only if needed — removes just the permissions this file added)
-- DELETE rp FROM admin_role_permissions rp JOIN admin_roles r ON r.id = rp.role_id
-- WHERE r.name = 'Valluvam Team Executive' AND rp.perm_key IN ('purchase.view', 'purchase.create', 'warehouses.view', 'sales_orders.view', 'sales_orders.create', 'sales_orders.edit', 'manual_sales.create', 'credit_sales.view', 'credit_sales.create', 'invoices.view', 'dc.view', 'sales_return.create', 'sales.fulfilment', 'sales.channels', 'customer.view', 'customers.view', 'inventory.view', 'stock_in.create', 'stock_out.create', 'stock_count.create', 'stock_adjust.create', 'warehouse.view', 'warehouse.transfer', 'warehouse.locations', 'warehouses.create', 'raw_materials.manage', 'pnl.view', 'assets.view', 'assets.create', 'assets.edit', 'waste.view', 'waste.create', 'suppliers.view', 'suppliers.create', 'supplier.profile', 'documents.view', 'documents.upload', 'notifications.view', 'reports.erp')
--   AND rp.perm_key NOT IN ('purchase.view','purchase.create','warehouses.view');
