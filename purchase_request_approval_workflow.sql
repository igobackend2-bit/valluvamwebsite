-- Run once in phpMyAdmin after erp_purchase_migration.sql.
-- Adds a Manager -> Backend approval chain for purchase requests.
ALTER TABLE purchase_requests
  MODIFY COLUMN status ENUM('draft','submitted','manager_approved','approved','manager_rejected','backend_rejected','converted','cancelled') NOT NULL DEFAULT 'draft',
  ADD COLUMN manager_approved_by VARCHAR(100) DEFAULT NULL AFTER approved_at,
  ADD COLUMN manager_approved_at DATETIME DEFAULT NULL AFTER manager_approved_by,
  ADD COLUMN backend_approved_by VARCHAR(100) DEFAULT NULL AFTER manager_approved_at,
  ADD COLUMN backend_approved_at DATETIME DEFAULT NULL AFTER backend_approved_by;

INSERT IGNORE INTO admin_permissions (perm_key, description) VALUES
  ('purchase.manager_approve', 'Manager approval of purchase requests'),
  ('purchase.backend_approve', 'Final backend approval and purchase-order processing');

INSERT INTO admin_roles (name, description)
SELECT 'Normal Admin', 'Raises and tracks own purchase requests'
WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Normal Admin');
INSERT INTO admin_roles (name, description)
SELECT 'Manager', 'Reviews purchase requests before backend approval'
WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Manager');
INSERT INTO admin_roles (name, description)
SELECT 'Backend', 'Final purchase approval and purchasing operations'
WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Backend');

INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p
WHERE r.name = 'Normal Admin' AND p.perm_key IN ('purchase.view','purchase.create','warehouses.view');
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p
WHERE r.name = 'Manager' AND p.perm_key IN ('purchase.view','purchase.manager_approve');
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p
WHERE r.name = 'Backend' AND p.perm_key IN ('purchase.view','purchase.create','purchase.backend_approve','grn.create','purchase_invoice.create','purchase_payment.create','purchase_return.create','suppliers.view','suppliers.create','warehouses.view');
