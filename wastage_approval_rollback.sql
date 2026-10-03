-- switches the new approvals off again (the pages fall back to the previous single approval / ask to run the migration)
DELETE FROM approval_policies WHERE module IN ('waste_admin','stock_damage_mgr','stock_damage_admin','expiry_dispose_mgr','expiry_dispose_admin','expiry_dispose_ceo');
DELETE p FROM admin_role_permissions p JOIN admin_roles r ON r.id = p.role_id WHERE r.name = 'Admin' AND p.perm_key = 'waste.view';
