-- ============================================================================
-- Executive adds checked goods to stock (Stock In: download sheet → upload) (2 Oct 2026)
-- Additive only: gives the "Valluvam Team Executive" role the existing grn.create permission.
-- Safe to run more than once (INSERT IGNORE).
-- ============================================================================
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, 'grn.create' FROM admin_roles r
WHERE r.name = 'Valluvam Team Executive' AND EXISTS (SELECT 1 FROM admin_permissions p WHERE p.perm_key = 'grn.create');
