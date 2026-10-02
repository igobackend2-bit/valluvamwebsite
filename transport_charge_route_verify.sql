-- should return 4 rows
SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pf_transport_charges' AND COLUMN_NAME IN ('from_location','to_location','vehicle_number','distance_km');
