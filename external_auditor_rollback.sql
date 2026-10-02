-- Rollback (only if needed, and only when no user has the "External Auditor" role):
-- removes the role's permission links and the role. The permission itself is kept (harmless).
DELETE FROM admin_role_permissions WHERE role_id = (SELECT id FROM (SELECT id FROM admin_roles WHERE name = 'External Auditor') x);
DELETE FROM admin_roles WHERE name = 'External Auditor' AND NOT EXISTS (SELECT 1 FROM admin_users u WHERE u.role_id = admin_roles.id);
