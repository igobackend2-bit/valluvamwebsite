-- ============================================================================
-- Accounts Team "PO checked" step before payment (2 Oct 2026)
-- Additive only: adds 2 empty columns to purchase_flows. Nothing is changed or deleted.
-- Safe to run more than once.
-- ============================================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_flows' AND COLUMN_NAME = 'po_checked_by');
SET @s := IF(@c = 0, 'ALTER TABLE purchase_flows ADD COLUMN po_checked_by VARCHAR(100) NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_flows' AND COLUMN_NAME = 'po_checked_at');
SET @s := IF(@c = 0, 'ALTER TABLE purchase_flows ADD COLUMN po_checked_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
