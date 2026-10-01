-- ============================================================================
-- Valluvam roles + role dashboards (1 Oct 2026)
--   Roles: Valluvam Team Executive · Valluvam Team Manager · L1 (Sourcing) ·
--          Admin · CEO · Accounts Team
--   Existing roles are RENAMED (same users, same id, same permissions kept):
--     Normal Admin → Valluvam Team Executive
--     Manager      → Valluvam Team Manager
--     Backend      → Admin
--     Accounts     → Accounts Team
--   No table is created, altered or dropped; no user is changed.
--   Safe to run more than once. BACK UP FIRST. Rollback at the bottom.
-- Needs: purchase_request_approval_workflow.sql, erp_complete_migration.sql,
--        purchase_flow_migration.sql
-- ============================================================================

-- 1) rename the existing roles (skipped when the new name already exists)
UPDATE IGNORE admin_roles SET name = 'Valluvam Team Executive', description = 'Raises purchase requests and follows them' WHERE name = 'Normal Admin';
UPDATE IGNORE admin_roles SET name = 'Valluvam Team Manager', description = 'Step 1 approval of purchase requests (from the dashboard)' WHERE name = 'Manager';
UPDATE IGNORE admin_roles SET name = 'Admin', description = 'Final approval: purchase requests, shop choice and purchase orders (from the dashboard)' WHERE name = 'Backend';
UPDATE IGNORE admin_roles SET name = 'Accounts Team', description = 'Supplier payments with proof, bills, expenses and accounts' WHERE name = 'Accounts';

-- 2) create any role that does not exist yet
INSERT INTO admin_roles (name, description) SELECT 'Valluvam Team Executive', 'Raises purchase requests and follows them' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Valluvam Team Executive');
INSERT INTO admin_roles (name, description) SELECT 'Valluvam Team Manager', 'Step 1 approval of purchase requests (from the dashboard)' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Valluvam Team Manager');
INSERT INTO admin_roles (name, description) SELECT 'L1 (Sourcing)', 'Collects 3 shop quotations, transport, DC, shop bill, unloading check and quality check' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'L1 (Sourcing)');
INSERT INTO admin_roles (name, description) SELECT 'Admin', 'Final approval: purchase requests, shop choice and purchase orders (from the dashboard)' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Admin');
INSERT INTO admin_roles (name, description) SELECT 'CEO', 'Sees everything; approves purchase orders above the CEO limit' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'CEO');
INSERT INTO admin_roles (name, description) SELECT 'Accounts Team', 'Supplier payments with proof, bills, expenses and accounts' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Accounts Team');

-- 3) new permissions
INSERT IGNORE INTO admin_permissions (perm_key, description) VALUES
  ('purchase.manager_approve', 'Manager approval of purchase requests'),
  ('purchase.backend_approve', 'Final backend approval and purchase-order processing'),
  ('flow.source', 'Purchase flow: collect shop quotations, transport, DC, shop bill, unloading check, quality check'),
  ('po.ceo_approve', 'Approve purchase orders above the CEO limit');

-- 4) permissions per role (only adds; nothing is removed)
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('purchase.view','purchase.create','warehouses.view')
  WHERE r.name = 'Valluvam Team Executive';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('purchase.view','purchase.manager_approve')
  WHERE r.name = 'Valluvam Team Manager';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'purchase.view','flow.source','rfq.manage','shipment.manage','grn.create','qc.manage','documents.view','documents.upload',
    'suppliers.view','suppliers.create','warehouses.view','warehouse.view','inventory.view','notifications.view')
  WHERE r.name = 'L1 (Sourcing)';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'purchase.view','purchase.create','purchase.approve','purchase.backend_approve','approvals.view','approvals.manage','documents.view','documents.upload',
    'notifications.view','suppliers.view','suppliers.create','warehouses.view','warehouse.view','inventory.view','trace.view','reports.erp')
  WHERE r.name = 'Admin';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON (p.perm_key LIKE '%.view' OR p.perm_key IN (
    'po.ceo_approve','purchase.approve','purchase.backend_approve','approvals.manage','reports.erp'))
  WHERE r.name = 'CEO';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'purchase.view','purchase_payment.create','purchase_invoice.create','documents.view','documents.upload','notifications.view','suppliers.view',
    'accounting.view','accounts.view','accounts.create','expense.manage','pnl.view','reports.view','dashboard.view')
  WHERE r.name = 'Accounts Team';

-- 5) CEO approval rule + limit (change the amount any time in Admin settings / this row)
INSERT IGNORE INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm) VALUES
  ('po_ceo', 'Purchase orders above the CEO limit', 1, 1, 50000, 'po.ceo_approve');
INSERT IGNORE INTO admin_settings (setting_key, setting_value, description) VALUES
  ('ceo_po_limit', '50000', 'Purchase orders with a total at or above this amount also need CEO approval');

-- 6) check
SELECT r.id, r.name, COUNT(p.perm_key) AS permissions FROM admin_roles r LEFT JOIN admin_role_permissions p ON p.role_id = r.id
 WHERE r.name IN ('Valluvam Team Executive','Valluvam Team Manager','L1 (Sourcing)','Admin','CEO','Accounts Team') GROUP BY r.id, r.name ORDER BY r.id;

-- ---------------------------------------------------------------- ROLLBACK (names back; new rows removed)
-- UPDATE IGNORE admin_roles SET name = 'Normal Admin' WHERE name = 'Valluvam Team Executive';
-- UPDATE IGNORE admin_roles SET name = 'Manager' WHERE name = 'Valluvam Team Manager';
-- UPDATE IGNORE admin_roles SET name = 'Backend' WHERE name = 'Admin';
-- UPDATE IGNORE admin_roles SET name = 'Accounts' WHERE name = 'Accounts Team';
-- DELETE FROM admin_role_permissions WHERE perm_key IN ('flow.source','po.ceo_approve');
-- DELETE FROM admin_permissions WHERE perm_key IN ('flow.source','po.ceo_approve');
-- DELETE FROM approval_policies WHERE module = 'po_ceo';
-- DELETE FROM admin_settings WHERE setting_key = 'ceo_po_limit';
-- (roles 'L1 (Sourcing)' and 'CEO' can be deleted in Admin Users once no user has them)
