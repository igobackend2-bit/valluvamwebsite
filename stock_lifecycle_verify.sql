-- ============================================================================
-- STOCK LIFECYCLE (2 Oct 2026) — checks. Read-only: nothing here changes data.
-- ============================================================================

-- ---------------------------------------------------------------- A) BEFORE the migration
-- A1. Server version (MySQL 8.x or MariaDB 10.4+ needed for the calculated columns)
SELECT VERSION() AS server_version;

-- A2. The existing tables the stock lifecycle reuses — expect 22 rows
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
 ('purchase_orders','purchase_order_items','goods_receipts','goods_receipt_items','quality_checks','quality_check_items','inbound_shipments','courier_services',
  'stock_ins','stock_in_items','stock_outs','stock_out_items','stock_movements','warehouse_stock','warehouse_stock_moves','inventory_batches','stock_counts',
  'stock_count_items','sales_returns','purchase_returns','erp_documents','approval_policies') ORDER BY TABLE_NAME;

-- A3. Nothing named sf_% yet (expect 0 on the first run; 14 if it was already run — then it is safe to run again)
SELECT COUNT(*) AS sf_tables_already_there FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'sf\_%';

-- A4. Locations and couriers today (the migration only ADDS Vanagram / Head Office / DHL / FedEx when missing)
SELECT id, name, code, status FROM warehouses ORDER BY id;
SELECT id, name FROM courier_services ORDER BY sort_order;

-- A5. Roles today (the migration only ADDS Warehouse Manager / QC Team / Transport / Dispatch / Data Team when missing)
SELECT id, name FROM admin_roles ORDER BY id;

-- ---------------------------------------------------------------- B) AFTER the migration
-- B1. expect 14
SELECT COUNT(*) AS sf_tables FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'sf\_%';
-- B2. expect 12
SELECT COUNT(*) AS stockflow_permissions FROM admin_permissions WHERE perm_key LIKE 'stockflow.%';
-- B3. permissions per role
SELECT r.name AS role, COUNT(*) AS stockflow_permissions FROM admin_role_permissions rp JOIN admin_roles r ON r.id = rp.role_id WHERE rp.perm_key LIKE 'stockflow.%' GROUP BY r.name ORDER BY r.name;
-- B4. expect 4
SELECT module, label, approver_perm FROM approval_policies WHERE module IN ('stock_issue','stock_damage','opening_stock','return_dispatch');
-- B5. the new locations / couriers
SELECT id, name, code FROM warehouses WHERE code IN ('WH-VNG','HO');
SELECT id, name FROM courier_services WHERE name LIKE 'DHL%' OR name LIKE 'FedEx%';
-- B6. calculated columns are present (expect 9 on consignment lines, 4 on audit lines)
SELECT TABLE_NAME, COUNT(*) AS calculated_columns FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sf_consignment_lines','sf_audit_lines') AND GENERATION_EXPRESSION IS NOT NULL AND GENERATION_EXPRESSION <> '' GROUP BY TABLE_NAME;
