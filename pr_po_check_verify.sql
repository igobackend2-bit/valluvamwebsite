-- should return 2 rows: po_checked_by, po_checked_at
SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_flows' AND COLUMN_NAME IN ('po_checked_by','po_checked_at');
