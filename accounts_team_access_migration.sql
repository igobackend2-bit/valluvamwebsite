-- ============================================================================
-- Accounts Team access (1 Oct 2026) — ADDITIVE ONLY. Safe to run twice.
-- warehouses.view: the warehouse filter on Purchase Orders / Purchase History
-- (it showed a permission error for the Accounts Team).
-- ============================================================================
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('warehouses.view')
WHERE r.name = 'Accounts Team';

SELECT r.name, COUNT(*) AS permissions FROM admin_role_permissions rp JOIN admin_roles r ON r.id = rp.role_id WHERE r.name = 'Accounts Team' GROUP BY r.name;
