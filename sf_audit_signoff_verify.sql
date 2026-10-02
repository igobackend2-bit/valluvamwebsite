-- should return 1 row: sf_audit_signoffs
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sf_audit_signoffs';
