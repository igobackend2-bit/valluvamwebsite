-- ============================================================================
-- STOCK LIFECYCLE (2 Oct 2026) — ADDITIVE ONLY. Safe to run twice.
-- Loading check + machine weight → transport (internal / courier) → unloading →
-- QC → stock in, controlled stock out, sales-return QC, damage approval,
-- opening stock, purchase-return dispatch, monthly audit (weights + digital
-- checks + proofs) and executive stock handover.
--
-- REUSES (not changed): purchase_orders, goods_receipts, quality_checks,
-- inbound_shipments, stock_ins / stock_outs / stock_movements (the one stock
-- ledger), warehouse_stock (location buckets), inventory_batches, stock_counts,
-- sales_returns, purchase_returns, courier_services, warehouses,
-- warehouse_locations, erp_documents, approval_requests, audit_logs.
--
-- ADDS: 14 new tables (all prefixed sf_), 12 permissions, 4 roles, 4 approval
-- rules, 2 courier names (only if missing), 2 locations (only if missing).
-- No existing table is altered, no column renamed, nothing deleted.
--
-- BACKUP FIRST (HeidiSQL: right-click the database → Export database as SQL),
-- or:  mysqldump valluvam-db > backup_before_stock_lifecycle.sql
-- Works on MySQL 8.x and MariaDB 10.4+.
-- ============================================================================

-- ---------------------------------------------------------------- 1) consignment = one dispatch of a PO
CREATE TABLE IF NOT EXISTS sf_consignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consignment_number VARCHAR(40) NOT NULL,
    po_id INT NOT NULL,
    supplier_id INT NULL,
    invoice_ref VARCHAR(80) NULL,
    loading_location VARCHAR(150) NULL,
    destination_warehouse_id INT NOT NULL,
    status ENUM('ready_for_loading','loaded','in_transit','delivered','received','qc_pending','qc_approved','qc_hold','qc_failed','stocked','cancelled') NOT NULL DEFAULT 'ready_for_loading',
    loading_date DATE NULL, loading_time TIME NULL, loaded_by VARCHAR(150) NULL, checked_by VARCHAR(150) NULL, loading_remarks VARCHAR(500) NULL,
    loaded_at DATETIME NULL, loaded_user VARCHAR(100) NULL,
    transport_type ENUM('internal','courier') NULL,
    shipment_id INT NULL,
    vehicle_name VARCHAR(80) NULL, vehicle_number VARCHAR(30) NULL, driver_name VARCHAR(150) NULL, driver_employee_id VARCHAR(40) NULL, driver_phone VARCHAR(20) NULL,
    courier_service_id INT NULL, courier_name VARCHAR(150) NULL, courier_service_type VARCHAR(60) NULL, tracking_number VARCHAR(80) NULL, courier_phone VARCHAR(20) NULL,
    pickup_location VARCHAR(150) NULL,
    departure_date DATE NULL, departure_time TIME NULL, expected_arrival_date DATE NULL, expected_arrival_time TIME NULL,
    actual_arrival_date DATE NULL, actual_arrival_time TIME NULL, transport_remarks VARCHAR(500) NULL,
    unload_warehouse_id INT NULL, unload_location_id INT NULL, receiver_name VARCHAR(150) NULL, receiver_employee_id VARCHAR(40) NULL,
    unload_date DATE NULL, unload_time TIME NULL, unload_remarks VARCHAR(500) NULL, unloaded_at DATETIME NULL, unloaded_user VARCHAR(100) NULL,
    grn_id INT NULL, qc_id INT NULL, stock_in_id INT NULL,
    qc_result ENUM('pass','partial','hold','fail') NULL, qc_by VARCHAR(150) NULL, qc_at DATETIME NULL, qc_remarks VARCHAR(500) NULL,
    stocked_at DATETIME NULL, stocked_by VARCHAR(100) NULL,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_cns_number (consignment_number), KEY idx_sf_cns_po (po_id), KEY idx_sf_cns_status (status), KEY idx_sf_cns_grn (grn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- differences are GENERATED columns: calculated by the database, can never be typed over
CREATE TABLE IF NOT EXISTS sf_consignment_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consignment_id INT NOT NULL,
    po_item_id INT NULL,
    item_type ENUM('product','raw_material') NOT NULL,
    item_id INT NOT NULL,
    sku VARCHAR(100) NULL, unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    batch_number VARCHAR(60) NULL, manufacturing_date DATE NULL, expiry_date DATE NULL,
    ordered_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    loaded_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit_weight_kg DECIMAL(14,4) NULL,
    expected_weight DECIMAL(14,3) NULL,
    actual_weight DECIMAL(14,3) NULL,
    physical_count DECIMAL(14,3) NULL,
    weight_diff DECIMAL(14,3) GENERATED ALWAYS AS (actual_weight - expected_weight) STORED,
    weight_diff_pct DECIMAL(9,3) GENERATED ALWAYS AS (CASE WHEN expected_weight > 0 THEN ROUND((actual_weight - expected_weight) / expected_weight * 100, 3) ELSE NULL END) STORED,
    count_diff DECIMAL(14,3) GENERATED ALWAYS AS (physical_count - loaded_qty) STORED,
    unloaded_qty DECIMAL(14,3) NULL,
    unload_weight DECIMAL(14,3) GENERATED ALWAYS AS (unloaded_qty * unit_weight_kg) STORED,
    unload_machine_weight DECIMAL(14,3) NULL,
    unload_physical_count DECIMAL(14,3) NULL,
    unload_qty_diff DECIMAL(14,3) GENERATED ALWAYS AS (unloaded_qty - loaded_qty) STORED,
    unload_weight_diff DECIMAL(14,3) GENERATED ALWAYS AS (unload_machine_weight - unloaded_qty * unit_weight_kg) STORED,
    transit_weight_diff DECIMAL(14,3) GENERATED ALWAYS AS (unload_machine_weight - actual_weight) STORED,
    invoice_qty DECIMAL(14,3) NULL,
    qc_machine_weight DECIMAL(14,3) NULL, qc_physical_count DECIMAL(14,3) NULL,
    qc_packaging ENUM('ok','damaged','na') NULL, qc_condition ENUM('good','fair','poor') NULL, qc_grade VARCHAR(20) NULL,
    qc_accepted DECIMAL(14,3) NULL, qc_rejected DECIMAL(14,3) NULL, qc_damaged DECIMAL(14,3) NULL,
    qc_shortage DECIMAL(14,3) GENERATED ALWAYS AS (GREATEST(COALESCE(invoice_qty, loaded_qty) - unloaded_qty, 0)) STORED,
    qc_excess DECIMAL(14,3) GENERATED ALWAYS AS (GREATEST(unloaded_qty - COALESCE(invoice_qty, loaded_qty), 0)) STORED,
    qc_checklist TEXT NULL,
    qc_line_result ENUM('pass','partial','hold','fail') NULL,
    qc_remarks VARCHAR(255) NULL,
    grn_item_id INT NULL,
    KEY idx_sf_cl_cns (consignment_id), KEY idx_sf_cl_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 2) product / category specific QC checks (master)
CREATE TABLE IF NOT EXISTS sf_qc_params (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('all','category','product') NOT NULL DEFAULT 'all',
    category VARCHAR(100) NULL, product_id INT NULL,
    check_name VARCHAR(120) NOT NULL,
    check_type ENUM('yesno','number','text') NOT NULL DEFAULT 'yesno',
    min_value DECIMAL(14,3) NULL, max_value DECIMAL(14,3) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 100,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sf_qcp_scope (scope, category, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 3) controlled stock out (production, sample, executive issue, office use …)
CREATE TABLE IF NOT EXISTS sf_stock_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_number VARCHAR(40) NOT NULL,
    purpose ENUM('production','sample','executive_issue','office_consumption','other') NOT NULL,
    warehouse_id INT NOT NULL,
    issue_date DATE NOT NULL,
    requested_by VARCHAR(150) NOT NULL, received_by VARCHAR(150) NULL, purpose_note VARCHAR(255) NOT NULL, remarks VARCHAR(500) NULL,
    status ENUM('requested','approved','rejected','issued','cancelled') NOT NULL DEFAULT 'requested',
    approved_by VARCHAR(100) NULL, approved_at DATETIME NULL, decision_remarks VARCHAR(255) NULL,
    issued_by VARCHAR(100) NULL, issued_at DATETIME NULL, stock_out_ref VARCHAR(50) NULL,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_iss_number (issue_number), KEY idx_sf_iss_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sf_stock_issue_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    item_type ENUM('product','raw_material') NOT NULL, item_id INT NOT NULL, batch_id INT NULL,
    quantity DECIMAL(14,3) NOT NULL, weight_kg DECIMAL(14,3) NULL, unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    KEY idx_sf_isl_issue (issue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 4) damage: identified → verified → approved (never silent)
CREATE TABLE IF NOT EXISTS sf_damage_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    damage_number VARCHAR(40) NOT NULL,
    source ENUM('stock','sales_return','qc','audit') NOT NULL DEFAULT 'stock', source_ref VARCHAR(60) NULL,
    item_type ENUM('product','raw_material') NOT NULL, item_id INT NOT NULL, batch_id INT NULL, warehouse_id INT NOT NULL,
    quantity DECIMAL(14,3) NOT NULL, weight_kg DECIMAL(14,3) NULL,
    reason VARCHAR(255) NOT NULL, damage_date DATE NOT NULL,
    identified_by VARCHAR(150) NOT NULL, verified_by VARCHAR(150) NULL, verified_at DATETIME NULL, verify_remarks VARCHAR(255) NULL,
    approved_by VARCHAR(100) NULL, approved_at DATETIME NULL, decision_remarks VARCHAR(255) NULL,
    status ENUM('identified','verified','approved','rejected','cancelled') NOT NULL DEFAULT 'identified',
    disposal_status ENUM('pending','disposed','destroyed','returned_to_supplier','restored') NOT NULL DEFAULT 'pending',
    bucket_ref VARCHAR(40) NULL,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_dmg_number (damage_number), KEY idx_sf_dmg_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 5) sales return: physical receiving → QC → existing sales return
CREATE TABLE IF NOT EXISTS sf_return_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(40) NOT NULL,
    source_type ENUM('invoice','manual_sale','credit_sale','website_order') NOT NULL, source_ref VARCHAR(60) NOT NULL,
    customer_name VARCHAR(150) NULL, warehouse_id INT NULL,
    return_date DATE NOT NULL, reason VARCHAR(255) NOT NULL,
    received_by VARCHAR(150) NOT NULL, received_at DATETIME NOT NULL,
    qc_by VARCHAR(150) NULL, qc_at DATETIME NULL, qc_remarks VARCHAR(255) NULL,
    status ENUM('received','qc_done','posted','cancelled') NOT NULL DEFAULT 'received',
    sales_return_id INT NULL, remarks VARCHAR(500) NULL,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_rr_number (receipt_number), KEY idx_sf_rr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sf_return_receipt_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    product_id INT NOT NULL, batch_number VARCHAR(60) NULL,
    quantity DECIMAL(14,3) NOT NULL, weight_kg DECIMAL(14,3) NULL,
    resalable_qty DECIMAL(14,3) NULL, damaged_qty DECIMAL(14,3) NULL, rejected_qty DECIMAL(14,3) NULL,
    qc_result ENUM('resalable','damaged','rejected','mixed') NULL, qc_note VARCHAR(255) NULL,
    KEY idx_sf_rrl_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 6) controlled opening stock
CREATE TABLE IF NOT EXISTS sf_opening_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opening_number VARCHAR(40) NOT NULL,
    item_type ENUM('product','raw_material') NOT NULL, item_id INT NOT NULL, warehouse_id INT NOT NULL,
    batch_number VARCHAR(60) NULL, expiry_date DATE NULL,
    quantity DECIMAL(14,3) NOT NULL, weight_kg DECIMAL(14,3) NULL, physical_count DECIMAL(14,3) NULL, unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    unit_cost DECIMAL(14,4) NULL, opening_date DATE NOT NULL,
    entered_by VARCHAR(150) NOT NULL, verified_by VARCHAR(100) NULL, verified_at DATETIME NULL,
    status ENUM('entered','verified','rejected') NOT NULL DEFAULT 'entered',
    posted_ref VARCHAR(50) NULL, remarks VARCHAR(500) NULL, decision_remarks VARCHAR(255) NULL,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_ops_number (opening_number), KEY idx_sf_ops_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 7) purchase return: approval + transport + tracking + dispatch proof
CREATE TABLE IF NOT EXISTS sf_return_dispatches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_number VARCHAR(40) NOT NULL,
    source ENUM('purchase_return','qc_rejected') NOT NULL,
    purchase_return_id INT NULL, grn_id INT NULL, supplier_id INT NULL, warehouse_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    transport_type ENUM('internal','courier') NOT NULL,
    vehicle_number VARCHAR(30) NULL, driver_name VARCHAR(150) NULL, driver_phone VARCHAR(20) NULL,
    courier_service_id INT NULL, courier_name VARCHAR(150) NULL, tracking_number VARCHAR(80) NULL, courier_phone VARCHAR(20) NULL,
    dispatch_date DATE NULL, delivered_date DATE NULL,
    status ENUM('requested','approved','dispatched','delivered','rejected','cancelled') NOT NULL DEFAULT 'requested',
    approved_by VARCHAR(100) NULL, approved_at DATETIME NULL, dispatched_by VARCHAR(100) NULL, dispatched_at DATETIME NULL, bucket_ref VARCHAR(40) NULL,
    remarks VARCHAR(500) NULL, created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_rd_number (dispatch_number), KEY idx_sf_rd_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sf_return_dispatch_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_id INT NOT NULL,
    item_type ENUM('product','raw_material') NOT NULL, item_id INT NOT NULL,
    quantity DECIMAL(14,3) NOT NULL, weight_kg DECIMAL(14,3) NULL,
    KEY idx_sf_rdl_dispatch (dispatch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 8) monthly physical audit on top of the existing stock count
CREATE TABLE IF NOT EXISTS sf_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_number VARCHAR(40) NOT NULL,
    stock_count_id INT NULL,
    audit_month CHAR(7) NOT NULL,
    warehouse_id INT NOT NULL,
    auditor_name VARCHAR(150) NOT NULL, auditor_employee_id VARCHAR(40) NOT NULL,
    audit_date DATE NOT NULL, start_time TIME NULL, end_time TIME NULL,
    status ENUM('planned','in_progress','count_completed','verification_pending','approved','closed','cancelled') NOT NULL DEFAULT 'planned',
    chk_physical_count TINYINT(1) NOT NULL DEFAULT 0, chk_weight TINYINT(1) NOT NULL DEFAULT 0, chk_batch TINYINT(1) NOT NULL DEFAULT 0,
    chk_expiry TINYINT(1) NOT NULL DEFAULT 0, chk_damage TINYINT(1) NOT NULL DEFAULT 0, chk_location TINYINT(1) NOT NULL DEFAULT 0,
    confirmed_by VARCHAR(100) NULL, confirmed_at DATETIME NULL, confirm_remarks VARCHAR(500) NULL,
    closed_by VARCHAR(100) NULL, closed_at DATETIME NULL, remarks VARCHAR(500) NULL,
    created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_aud_number (audit_number), KEY idx_sf_aud_month (audit_month, warehouse_id), KEY idx_sf_aud_count (stock_count_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sf_audit_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    count_item_id INT NOT NULL,
    item_type ENUM('product','raw_material') NOT NULL, item_id INT NOT NULL,
    batch_number VARCHAR(60) NULL, expiry_date DATE NULL,
    system_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    physical_qty DECIMAL(14,3) NULL,
    unit_weight_kg DECIMAL(14,4) NULL,
    system_weight DECIMAL(14,3) GENERATED ALWAYS AS (system_qty * unit_weight_kg) STORED,
    physical_weight DECIMAL(14,3) NULL,
    machine_weight DECIMAL(14,3) NULL,
    damage_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    qty_diff DECIMAL(14,3) GENERATED ALWAYS AS (physical_qty - system_qty) STORED,
    weight_diff DECIMAL(14,3) GENERATED ALWAYS AS (physical_weight - system_qty * unit_weight_kg) STORED,
    diff_pct DECIMAL(9,3) GENERATED ALWAYS AS (CASE WHEN system_qty > 0 THEN ROUND((physical_qty - system_qty) / system_qty * 100, 3) ELSE NULL END) STORED,
    remarks VARCHAR(255) NULL,
    UNIQUE KEY uq_sf_al (audit_id, count_item_id), KEY idx_sf_al_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 9) executive stock handover
CREATE TABLE IF NOT EXISTS sf_handovers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    handover_number VARCHAR(40) NOT NULL,
    period_from DATE NOT NULL, period_to DATE NOT NULL, warehouse_id INT NULL,
    prepared_by VARCHAR(100) NOT NULL, prepared_at DATETIME NOT NULL,
    checked_by VARCHAR(100) NULL, checked_at DATETIME NULL,
    executive_name VARCHAR(150) NOT NULL, handover_date DATE NOT NULL, handover_time TIME NOT NULL,
    snapshot LONGTEXT NOT NULL,
    status ENUM('prepared','checked','acknowledged','cancelled') NOT NULL DEFAULT 'prepared',
    ack_by VARCHAR(100) NULL, ack_at DATETIME NULL, ack_remarks VARCHAR(500) NULL, remarks VARCHAR(500) NULL,
    UNIQUE KEY uq_sf_hnd_number (handover_number), KEY idx_sf_hnd_period (period_from, period_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- 10) permissions (existing permission system)
INSERT IGNORE INTO admin_permissions (perm_key, description) VALUES
 ('stockflow.view', 'Stock lifecycle: view consignments, daily stock, audits, handovers'),
 ('stockflow.loading', 'Stock lifecycle: loading check, machine weight, transport'),
 ('stockflow.receive', 'Stock lifecycle: unloading / receiving, opening stock entry'),
 ('stockflow.qc', 'Stock lifecycle: QC inspection (purchase receipts, sales returns)'),
 ('stockflow.issue', 'Stock lifecycle: stock out requests, damage reports, return dispatch'),
 ('stockflow.returns', 'Stock lifecycle: sales return physical receiving'),
 ('stockflow.approve', 'Stock lifecycle: approve stock out, damage, opening stock, corrections, dispatch'),
 ('stockflow.audit', 'Stock lifecycle: monthly audit counting, weights, digital checks, proofs'),
 ('stockflow.audit_approve', 'Stock lifecycle: review / close monthly audits'),
 ('stockflow.handover', 'Stock lifecycle: prepare and check executive handovers'),
 ('stockflow.handover_ack', 'Stock lifecycle: acknowledge an executive handover (read-only reports)'),
 ('stockflow.config', 'Stock lifecycle: QC checklist master');

-- 4 team roles that did not exist yet (existing roles are not changed)
INSERT INTO admin_roles (name, description) SELECT 'Warehouse Manager', 'Warehouse approval, stock verification, adjustments, audit review' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Warehouse Manager');
INSERT INTO admin_roles (name, description) SELECT 'QC Team', 'QC inspection and QC approval' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'QC Team');
INSERT INTO admin_roles (name, description) SELECT 'Transport / Dispatch', 'Loading, transportation and delivery' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Transport / Dispatch');
INSERT INTO admin_roles (name, description) SELECT 'Data Team', 'Physical counting, digital checking and audit reports' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'Data Team');

-- role → permissions (INSERT IGNORE: nothing already given is touched)
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key LIKE 'stockflow.%' WHERE r.name IN ('Super Admin', 'Admin');
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.approve', 'stockflow.audit_approve', 'stockflow.handover') WHERE r.name IN ('Valluvam Team Manager', 'CEO');
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.handover_ack') WHERE r.name = 'Valluvam Team Executive';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.receive', 'stockflow.issue', 'stockflow.returns', 'stockflow.audit',
       'grn.create', 'warehouse.view', 'stock_count.create', 'documents.view', 'documents.upload') WHERE r.name = 'Warehouse';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.receive', 'stockflow.issue', 'stockflow.returns', 'stockflow.approve', 'stockflow.audit', 'stockflow.audit_approve', 'stockflow.handover',
       'purchase.view', 'grn.create', 'inventory.view', 'inventory.adjust', 'warehouse.view', 'warehouse.transfer', 'warehouse.locations', 'warehouses.view', 'stock_count.create', 'stock_count.approve',
       'stock_adjust.create', 'stock_adjust.approve', 'approvals.view', 'approvals.manage', 'documents.view', 'documents.upload', 'notifications.view', 'audit_logs.view') WHERE r.name = 'Warehouse Manager';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.qc', 'qc.manage', 'purchase.view', 'warehouse.view', 'documents.view', 'documents.upload', 'notifications.view') WHERE r.name = 'QC Team';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.loading', 'purchase.view', 'shipment.manage', 'documents.view', 'documents.upload', 'notifications.view') WHERE r.name = 'Transport / Dispatch';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.audit', 'stockflow.handover', 'stock_count.create', 'warehouse.view', 'inventory.view', 'documents.view', 'documents.upload', 'notifications.view') WHERE r.name = 'Data Team';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.loading') WHERE r.name = 'Purchase';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.returns', 'stockflow.qc') WHERE r.name = 'Sales';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.loading', 'stockflow.receive', 'stockflow.qc') WHERE r.name = 'L1 (Sourcing)';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key = 'stockflow.view' WHERE r.name = 'Accounts Team';

-- ---------------------------------------------------------------- 11) approval rules (show in the existing Approvals page)
INSERT IGNORE INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm) VALUES
 ('stock_issue', 'Stock out (production, sample, executive issue, office use)', 1, 1, 0, 'stockflow.approve'),
 ('stock_damage', 'Damaged stock', 1, 1, 0, 'stockflow.approve'),
 ('opening_stock', 'Opening stock', 1, 1, 0, 'stockflow.approve'),
 ('return_dispatch', 'Purchase return dispatch', 1, 1, 0, 'stockflow.approve');

-- ---------------------------------------------------------------- 12) master data — only added when missing
INSERT INTO courier_services (name, tracking_url, is_active, sort_order) SELECT 'DHL', 'https://www.dhl.com/in-en/home/tracking.html?tracking-id={n}', 1, 160 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM courier_services WHERE name LIKE 'DHL%');
INSERT INTO courier_services (name, tracking_url, is_active, sort_order) SELECT 'FedEx', 'https://www.fedex.com/fedextrack/?trknbr={n}', 1, 170 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM courier_services WHERE name LIKE 'FedEx%');
INSERT INTO warehouses (name, code, location, status) SELECT 'Vanagram', 'WH-VNG', 'Vanagram, Chennai', 'active' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM warehouses WHERE name = 'Vanagram' OR code = 'WH-VNG');
INSERT INTO warehouses (name, code, location, status) SELECT 'Head Office', 'HO', 'Head Office', 'active' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM warehouses WHERE name = 'Head Office' OR code = 'HO');
