-- should return 5 rows for External Auditor
SELECT r.name, rp.perm_key FROM admin_roles r JOIN admin_role_permissions rp ON rp.role_id = r.id WHERE r.name = 'External Auditor' ORDER BY rp.perm_key;
