-- Rollback (only if needed): takes the quality-check permission away from the Executive role again.
DELETE FROM admin_role_permissions WHERE perm_key = 'qc.manage' AND role_id = (SELECT id FROM admin_roles WHERE name = 'Valluvam Team Executive');
