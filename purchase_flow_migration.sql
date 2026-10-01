-- ============================================================================
-- Purchase flow (request → 3 shop quotations → approval → PO → payment proof →
-- transport / courier → DCs & shop bill → unloading check → quality check)
--                                                                    1 Oct 2026
-- SAFE / ADDITIVE ONLY: new tables + reference rows. No existing table is
-- altered, renamed or emptied. Safe to run more than once.
-- Needs erp_complete_migration.sql (approval_policies). BACK UP FIRST.
-- ============================================================================

-- One row per purchase request that goes through the guided flow
CREATE TABLE IF NOT EXISTS purchase_flows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pr_id INT NOT NULL,
  po_id INT DEFAULT NULL,
  quote_status ENUM('collecting','submitted','approved','rejected') NOT NULL DEFAULT 'collecting',
  selected_slot TINYINT DEFAULT NULL,
  quote_submitted_by VARCHAR(100) DEFAULT NULL,
  quote_submitted_at DATETIME DEFAULT NULL,
  quote_decided_by VARCHAR(100) DEFAULT NULL,
  quote_decided_at DATETIME DEFAULT NULL,
  quote_remarks VARCHAR(255) DEFAULT NULL,
  fewer_quotes_reason VARCHAR(255) DEFAULT NULL,
  payment_id INT DEFAULT NULL,
  payment_proof_doc_id INT DEFAULT NULL,
  delivery_mode ENUM('internal','courier') DEFAULT NULL,
  courier_service_id INT DEFAULT NULL,
  courier_name VARCHAR(150) DEFAULT NULL,
  tracking_number VARCHAR(80) DEFAULT NULL,
  driver_name VARCHAR(150) DEFAULT NULL,
  driver_phone VARCHAR(20) DEFAULT NULL,
  vehicle_number VARCHAR(30) DEFAULT NULL,
  dispatch_date DATE DEFAULT NULL,
  shipment_id INT DEFAULT NULL,
  courier_proof_doc_id INT DEFAULT NULL,
  loading_dc_doc_id INT DEFAULT NULL,
  unloading_dc_doc_id INT DEFAULT NULL,
  shop_bill_doc_id INT DEFAULT NULL,
  shop_bill_type ENUM('handwritten','system') DEFAULT NULL,
  shop_bill_number VARCHAR(60) DEFAULT NULL,
  unload_check TEXT DEFAULT NULL,
  unload_ok TINYINT(1) DEFAULT NULL,
  unload_checked_by VARCHAR(100) DEFAULT NULL,
  unload_checked_at DATETIME DEFAULT NULL,
  grn_id INT DEFAULT NULL,
  qc_id INT DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pf_pr (pr_id),
  INDEX idx_pf_po (po_id),
  INDEX idx_pf_status (quote_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shop quotations collected for a purchase request (up to 3)
CREATE TABLE IF NOT EXISTS pr_quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pr_id INT NOT NULL,
  slot TINYINT NOT NULL,
  supplier_id INT DEFAULT NULL,
  supplier_name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(150) DEFAULT NULL,
  mobile VARCHAR(20) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  gst_number VARCHAR(15) DEFAULT NULL,
  account_holder_name VARCHAR(150) DEFAULT NULL,
  bank_name VARCHAR(150) DEFAULT NULL,
  bank_account_number VARCHAR(34) DEFAULT NULL,
  bank_ifsc VARCHAR(11) DEFAULT NULL,
  upi_id VARCHAR(150) DEFAULT NULL,
  delivery_days INT DEFAULT NULL,
  payment_terms VARCHAR(150) DEFAULT NULL,
  freight DECIMAL(14,2) NOT NULL DEFAULT 0,
  valid_till DATE DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_selected TINYINT(1) NOT NULL DEFAULT 0,
  source_file VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_prq_slot (pr_id, slot),
  INDEX idx_prq_pr (pr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pr_quote_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  item_type ENUM('product','raw_material') DEFAULT NULL,
  item_id INT DEFAULT NULL,
  item_name VARCHAR(255) NOT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit VARCHAR(30) DEFAULT NULL,
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,
  tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  INDEX idx_prqi_quote (quote_id),
  CONSTRAINT fk_prqi_quote FOREIGN KEY (quote_id) REFERENCES pr_quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The quantity + unit the requester typed (e.g. 50 kg), next to the packs stored on the request line
CREATE TABLE IF NOT EXISTS pr_item_units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pr_id INT NOT NULL,
  pr_item_id INT NOT NULL,
  input_qty DECIMAL(14,3) NOT NULL,
  input_unit VARCHAR(10) NOT NULL,
  pack_size DECIMAL(14,3) DEFAULT NULL,
  pack_unit VARCHAR(10) DEFAULT NULL,
  packs DECIMAL(14,3) NOT NULL,
  UNIQUE KEY uniq_piu_item (pr_item_id),
  INDEX idx_piu_pr (pr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Courier services for the transport step (edit / add rows as needed)
CREATE TABLE IF NOT EXISTS courier_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  tracking_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO courier_services (name, tracking_url, sort_order) VALUES
  ('DTDC', 'https://www.dtdc.in/tracking.asp', 10),
  ('Blue Dart', 'https://www.bluedart.com/tracking', 20),
  ('Delhivery', 'https://www.delhivery.com/track/package/{n}', 30),
  ('Professional Couriers', 'https://www.tpcindia.com/', 40),
  ('ST Courier', 'https://stcourier.com/track/shipment', 50),
  ('India Post (Speed Post)', 'https://www.indiapost.gov.in/', 60),
  ('Ekart', 'https://ekartlogistics.com/shipmenttrack/{n}', 70),
  ('XpressBees', 'https://www.xpressbees.com/shipment/tracking?awbNo={n}', 80),
  ('Shadowfax', 'https://tracker.shadowfax.in/#/track/{n}', 90),
  ('Ecom Express', 'https://ecomexpress.in/tracking/?awb_field={n}', 100),
  ('Gati', 'https://www.gati.com/track-by-docket/', 110),
  ('TCI Express', 'https://www.tciexpress.in/trackingdocket.aspx', 120),
  ('VRL Logistics', 'https://www.vrlgroup.in/track_consignment.aspx', 130),
  ('Shree Maruti Courier', 'https://www.shreemaruti.com/track-your-shipment/', 140),
  ('Trackon', 'https://trackon.in/', 150),
  ('Other (type the name)', NULL, 999);

-- Approval rule for "which shop" (same approver as final purchase approval)
INSERT IGNORE INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm) VALUES
  ('pr_quotation', 'Shop quotation (which shop to buy from)', 1, 1, 0, 'purchase.backend_approve');

-- ---------------------------------------------------------------- VERIFY
-- SELECT COUNT(*) AS tables_found FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('purchase_flows','pr_quotes','pr_quote_items','pr_item_units','courier_services');   -- expect 5
-- SELECT COUNT(*) AS couriers FROM courier_services;                                                    -- expect 16
-- SELECT * FROM approval_policies WHERE module = 'pr_quotation';                                       -- expect 1 row
-- ---------------------------------------------------------------- ROLLBACK (removes only what this file added)
-- DELETE FROM approval_policies WHERE module = 'pr_quotation';
-- DROP TABLE IF EXISTS pr_quote_items; DROP TABLE IF EXISTS pr_quotes; DROP TABLE IF EXISTS pr_item_units;
-- DROP TABLE IF EXISTS purchase_flows; DROP TABLE IF EXISTS courier_services;
