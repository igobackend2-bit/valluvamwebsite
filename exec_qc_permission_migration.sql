-- ============================================================================
-- Executive does the quality check after unloading (2 Oct 2026)
-- Additive only: gives the "Valluvam Team Executive" role the existing qc.manage permission.
-- Safe to run more than once (INSERT IGNORE).
-- ============================================================================
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, 'qc.manage' FROM admin_roles r
WHERE r.name = 'Valluvam Team Executive' AND EXISTS (SELECT 1 FROM admin_permissions p WHERE p.perm_key = 'qc.manage');
