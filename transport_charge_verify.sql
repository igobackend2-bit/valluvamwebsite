-- should return: 1 row (table) and 2 rows (rules)
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pf_transport_charges';
SELECT module, approver_perm, enabled FROM approval_policies WHERE module IN ('transport_charge_mgr','transport_charge');
