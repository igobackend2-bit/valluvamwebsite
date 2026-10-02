-- ============================================================================
-- Internal vehicle diesel bill: from / to location, vehicle, distance (2 Oct 2026)
-- Additive only: adds 4 empty columns to pf_transport_charges. Safe to run more than once.
-- ============================================================================
SET @t := 'pf_transport_charges';
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'from_location');
SET @s := IF(@c = 0, 'ALTER TABLE pf_transport_charges ADD COLUMN from_location VARCHAR(150) NULL DEFAULT NULL', 'SELECT 1'); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'to_location');
SET @s := IF(@c = 0, 'ALTER TABLE pf_transport_charges ADD COLUMN to_location VARCHAR(150) NULL DEFAULT NULL', 'SELECT 1'); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'vehicle_number');
SET @s := IF(@c = 0, 'ALTER TABLE pf_transport_charges ADD COLUMN vehicle_number VARCHAR(30) NULL DEFAULT NULL', 'SELECT 1'); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'distance_km');
SET @s := IF(@c = 0, 'ALTER TABLE pf_transport_charges ADD COLUMN distance_km DECIMAL(10,1) NULL DEFAULT NULL', 'SELECT 1'); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
