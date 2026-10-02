-- should return 1 row: Valluvam Team Executive | qc.manage
SELECT r.name, rp.perm_key FROM admin_role_permissions rp JOIN admin_roles r ON r.id = rp.role_id WHERE r.name = 'Valluvam Team Executive' AND rp.perm_key = 'qc.manage';
