-- should show 6 rules and 1 Admin permission row
SELECT module, label, approver_perm, enabled FROM approval_policies
WHERE module IN ('waste_admin','stock_damage_mgr','stock_damage_admin','expiry_dispose_mgr','expiry_dispose_admin','expiry_dispose_ceo');
SELECT r.name, p.perm_key FROM admin_role_permissions p JOIN admin_roles r ON r.id = p.role_id WHERE r.name = 'Admin' AND p.perm_key = 'waste.view';
