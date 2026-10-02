-- Rollback (only if needed): the Executive can no longer add received goods to stock.
DELETE FROM admin_role_permissions WHERE perm_key = 'grn.create' AND role_id = (SELECT id FROM admin_roles WHERE name = 'Valluvam Team Executive');
