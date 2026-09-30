-- ============================================================================
-- Valluvam Admin — Complete ERP (phases A–D)                     1 Oct 2026
--
-- SAFE / ADDITIVE ONLY:
--   * Only CREATE TABLE IF NOT EXISTS and INSERT IGNORE.
--   * No ALTER, DROP, TRUNCATE, UPDATE or DELETE on any existing table.
--   * Safe to run more than once.
-- BEFORE RUNNING: HeidiSQL → Tools → Export database as SQL (structure + data).
-- Requires erp_purchase_migration.sql (already run on 30 Sep 2026).
-- ============================================================================

-- =============================== A. PROCUREMENT ============================
CREATE TABLE IF NOT EXISTS rfqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfq_number VARCHAR(40) NOT NULL UNIQUE,
  rfq_date DATE NOT NULL,
  due_date DATE DEFAULT NULL,
  pr_id INT DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  status ENUM('draft','sent','quoted','awarded','closed','cancelled') NOT NULL DEFAULT 'draft',
  terms TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rfq_status (status),
  INDEX idx_rfq_date (rfq_date),
  INDEX idx_rfq_pr (pr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rfq_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfq_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  target_rate DECIMAL(14,4) DEFAULT NULL,
  specs VARCHAR(255) DEFAULT NULL,
  INDEX idx_rfqi_rfq (rfq_id),
  CONSTRAINT fk_rfqi_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rfq_suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfq_id INT NOT NULL,
  supplier_id INT NOT NULL,
  status ENUM('invited','quoted','declined','awarded','not_awarded') NOT NULL DEFAULT 'invited',
  sent_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_rfq_supplier (rfq_id, supplier_id),
  INDEX idx_rfqs_supplier (supplier_id),
  CONSTRAINT fk_rfqs_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supplier_quotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_number VARCHAR(40) NOT NULL UNIQUE,
  rfq_id INT DEFAULT NULL,
  supplier_id INT NOT NULL,
  supplier_quote_ref VARCHAR(80) DEFAULT NULL,
  quote_date DATE NOT NULL,
  valid_until DATE DEFAULT NULL,
  delivery_days INT DEFAULT NULL,
  payment_terms VARCHAR(150) DEFAULT NULL,
  freight_terms VARCHAR(150) DEFAULT NULL,
  freight_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('received','accepted','partially_accepted','rejected','cancelled') NOT NULL DEFAULT 'received',
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sq_rfq (rfq_id),
  INDEX idx_sq_supplier (supplier_id),
  INDEX idx_sq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supplier_quotation_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT NOT NULL,
  rfq_item_id INT DEFAULT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  remarks VARCHAR(255) DEFAULT NULL,
  awarded TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_sqi_q (quotation_id),
  INDEX idx_sqi_rfq_item (rfq_item_id),
  CONSTRAINT fk_sqi_q FOREIGN KEY (quotation_id) REFERENCES supplier_quotations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which RFQ / quotation a purchase order came from (no change to purchase_orders)
CREATE TABLE IF NOT EXISTS procurement_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL UNIQUE,
  rfq_id INT DEFAULT NULL,
  quotation_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pl_rfq (rfq_id),
  INDEX idx_pl_q (quotation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PO amendments: the PO keeps its number, each change is a numbered revision
CREATE TABLE IF NOT EXISTS purchase_order_revisions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  revision_no INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  old_snapshot LONGTEXT NOT NULL,
  new_snapshot LONGTEXT NOT NULL,
  old_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  new_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  prev_status VARCHAR(30) NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  requested_by VARCHAR(100) DEFAULT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by VARCHAR(100) DEFAULT NULL,
  decided_at DATETIME DEFAULT NULL,
  decision_remarks VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY uniq_po_rev (po_id, revision_no),
  INDEX idx_por_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transport / logistics for incoming goods
CREATE TABLE IF NOT EXISTS inbound_shipments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shipment_number VARCHAR(40) NOT NULL UNIQUE,
  po_id INT DEFAULT NULL,
  grn_id INT DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  transporter_name VARCHAR(150) DEFAULT NULL,
  transport_company VARCHAR(150) DEFAULT NULL,
  transport_mode ENUM('road','rail','air','sea','courier','own_vehicle','other') NOT NULL DEFAULT 'road',
  vehicle_number VARCHAR(30) DEFAULT NULL,
  driver_name VARCHAR(150) DEFAULT NULL,
  driver_phone VARCHAR(20) DEFAULT NULL,
  lr_number VARCHAR(80) DEFAULT NULL,
  consignment_number VARCHAR(80) DEFAULT NULL,
  dispatch_date DATE DEFAULT NULL,
  expected_arrival DATE DEFAULT NULL,
  actual_arrival DATE DEFAULT NULL,
  freight_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  loading_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  unloading_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  handling_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  cost_treatment ENUM('landed','expense','supplier_paid') NOT NULL DEFAULT 'landed',
  cost_applied TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = already added to landed cost (never twice)
  cost_applied_at DATETIME DEFAULT NULL,
  amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('planned','in_transit','arrived','cancelled') NOT NULL DEFAULT 'planned',
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_shp_po (po_id),
  INDEX idx_shp_grn (grn_id),
  INDEX idx_shp_supplier (supplier_id),
  INDEX idx_shp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inbound_shipment_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  payment_mode ENUM('cash','bank_transfer','upi','cheque','card','other') NOT NULL DEFAULT 'bank_transfer',
  reference_number VARCHAR(100) DEFAULT NULL,
  accounts_transaction_id INT DEFAULT NULL,
  status ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_shpp_shp (shipment_id),
  CONSTRAINT fk_shpp_shp FOREIGN KEY (shipment_id) REFERENCES inbound_shipments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quality check on a DRAFT goods receipt (stock is added only after QC, when the GRN is posted)
CREATE TABLE IF NOT EXISTS quality_checks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qc_number VARCHAR(40) NOT NULL UNIQUE,
  grn_id INT NOT NULL,
  status ENUM('pending','passed','partially_passed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  inspection_date DATE DEFAULT NULL,
  inspected_by VARCHAR(150) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  INDEX idx_qc_grn (grn_id),
  INDEX idx_qc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quality_check_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qc_id INT NOT NULL,
  grn_item_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  batch_number VARCHAR(60) DEFAULT NULL,
  received_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  accepted_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  result ENUM('pending','passed','partial','failed') NOT NULL DEFAULT 'pending',
  rejection_reason VARCHAR(255) DEFAULT NULL,
  INDEX idx_qci_qc (qc_id),
  INDEX idx_qci_grn_item (grn_item_id),
  CONSTRAINT fk_qci_qc FOREIGN KEY (qc_id) REFERENCES quality_checks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Debit notes for purchase returns (one per return)
CREATE TABLE IF NOT EXISTS debit_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dn_number VARCHAR(40) NOT NULL UNIQUE,
  purchase_return_id INT NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  pinv_id INT DEFAULT NULL,
  dn_date DATE NOT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('issued','adjusted','refunded','cancelled') NOT NULL DEFAULT 'issued',
  notes VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dn_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extra supplier details (existing suppliers table is not changed)
CREATE TABLE IF NOT EXISTS supplier_profiles (
  supplier_id INT PRIMARY KEY,
  contact_person VARCHAR(150) DEFAULT NULL,
  contact_phone VARCHAR(20) DEFAULT NULL,
  alt_phone VARCHAR(20) DEFAULT NULL,
  pan_number VARCHAR(20) DEFAULT NULL,
  bank_account_name VARCHAR(150) DEFAULT NULL,
  bank_account_last4 VARCHAR(8) DEFAULT NULL,
  bank_ifsc VARCHAR(20) DEFAULT NULL,
  bank_name VARCHAR(150) DEFAULT NULL,
  upi_id VARCHAR(100) DEFAULT NULL,
  credit_limit DECIMAL(14,2) DEFAULT NULL,
  credit_days INT DEFAULT NULL,
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,  -- + we owe the supplier, - supplier owes us
  opening_balance_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Reusable approval engine
CREATE TABLE IF NOT EXISTS approval_policies (
  module VARCHAR(40) PRIMARY KEY,
  label VARCHAR(100) NOT NULL,
  inherent TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = the module always has an approval step
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  min_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  approver_perm VARCHAR(100) NOT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approval_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_number VARCHAR(40) NOT NULL UNIQUE,
  module VARCHAR(40) NOT NULL,
  request_key VARCHAR(80) NOT NULL,
  entity_id INT DEFAULT NULL,
  reference VARCHAR(80) DEFAULT NULL,
  summary VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(14,2) DEFAULT NULL,
  status ENUM('draft','submitted','under_review','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  endpoint VARCHAR(60) DEFAULT NULL,
  approve_payload TEXT DEFAULT NULL,
  reject_payload TEXT DEFAULT NULL,
  submitted_by VARCHAR(100) DEFAULT NULL,
  submitted_at DATETIME DEFAULT NULL,
  reviewed_by VARCHAR(100) DEFAULT NULL,
  decided_by VARCHAR(100) DEFAULT NULL,
  decided_at DATETIME DEFAULT NULL,
  remarks VARCHAR(255) DEFAULT NULL,
  executed_at DATETIME DEFAULT NULL,
  execution_error VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_apr_module_key (module, request_key),
  INDEX idx_apr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approval_actions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  action VARCHAR(20) NOT NULL,
  by_user VARCHAR(100) DEFAULT NULL,
  remarks VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_apa_req (request_id),
  CONSTRAINT fk_apa_req FOREIGN KEY (request_id) REFERENCES approval_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Admin notifications (the customer `notifications` table is not touched)
CREATE TABLE IF NOT EXISTS admin_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alert_key VARCHAR(120) NOT NULL UNIQUE,
  type VARCHAR(40) NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(255) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  target_perm VARCHAR(100) DEFAULT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  INDEX idx_an_status (status, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_notification_reads (
  notification_id INT NOT NULL,
  admin_user_id INT NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id, admin_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Documents v2: category / description / versions / archive (erp_documents is not changed)
CREATE TABLE IF NOT EXISTS erp_document_meta (
  document_id INT PRIMARY KEY,
  category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
  description VARCHAR(255) DEFAULT NULL,
  version INT NOT NULL DEFAULT 1,
  root_document_id INT DEFAULT NULL,               -- first version of this document
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  archived_by VARCHAR(100) DEFAULT NULL,
  archived_at DATETIME DEFAULT NULL,
  archive_reason VARCHAR(255) DEFAULT NULL,
  INDEX idx_edm_cat (category),
  INDEX idx_edm_status (status),
  INDEX idx_edm_root (root_document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================== B. INVENTORY / WAREHOUSE ==================
-- Stock per warehouse and bucket. product_details.stock stays the master total;
-- this sub-ledger follows every stock movement using its existing warehouse_id.
CREATE TABLE IF NOT EXISTS warehouse_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  bucket ENUM('available','damaged','rejected','expired') NOT NULL DEFAULT 'available',
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ws (item_type, item_id, warehouse_id, bucket),
  INDEX idx_ws_wh (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_stock_moves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  bucket ENUM('available','damaged','rejected','expired') NOT NULL DEFAULT 'available',
  quantity DECIMAL(14,3) NOT NULL,                 -- signed
  source ENUM('opening','ledger','bucket','realloc') NOT NULL,
  source_movement_id INT DEFAULT NULL,             -- stock_movements.id / raw_material_movements.id (source = ledger)
  reference_type VARCHAR(30) DEFAULT NULL,
  reference_number VARCHAR(60) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_wsm_ledger (source, item_type, source_movement_id),
  INDEX idx_wsm_item (item_type, item_id, warehouse_id),
  INDEX idx_wsm_ref (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cursors for background sync jobs (warehouse sub-ledger, FEFO allocation, journals)
CREATE TABLE IF NOT EXISTS erp_sync_state (
  name VARCHAR(40) PRIMARY KEY,
  last_id INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_number VARCHAR(40) NOT NULL UNIQUE,
  transfer_date DATE NOT NULL,
  from_warehouse_id INT NOT NULL,
  to_warehouse_id INT NOT NULL,
  status ENUM('draft','in_transit','received','cancelled') NOT NULL DEFAULT 'draft',
  vehicle_number VARCHAR(30) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  sent_by VARCHAR(100) DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  received_by VARCHAR(100) DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_st_status (status),
  INDEX idx_st_date (transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_transfer_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  received_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,          -- average cost when sent (the receipt uses the same cost)
  INDEX idx_sti_t (transfer_id),
  CONSTRAINT fk_sti_t FOREIGN KEY (transfer_id) REFERENCES stock_transfers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_counts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  count_number VARCHAR(40) NOT NULL UNIQUE,
  count_date DATE NOT NULL,
  warehouse_id INT NOT NULL,
  scope VARCHAR(150) DEFAULT NULL,
  status ENUM('draft','submitted','posted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_by VARCHAR(100) DEFAULT NULL,
  posted_by VARCHAR(100) DEFAULT NULL,
  posted_at DATETIME DEFAULT NULL,
  INDEX idx_sc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_count_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  count_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  system_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  counted_qty DECIMAL(14,3) DEFAULT NULL,
  variance DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  variance_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  reason VARCHAR(255) DEFAULT NULL,
  INDEX idx_sci_c (count_id),
  CONSTRAINT fk_sci_c FOREIGN KEY (count_id) REFERENCES stock_counts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional Zone → Rack → Shelf → Bin locations
CREATE TABLE IF NOT EXISTS warehouse_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  warehouse_id INT NOT NULL,
  parent_id INT DEFAULT NULL,
  level ENUM('zone','rack','shelf','bin') NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(150) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_wl (warehouse_id, code),
  INDEX idx_wl_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  location_id INT NOT NULL,
  UNIQUE KEY uniq_il (item_type, item_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FEFO: which batch each stock outflow came from (filled automatically, earliest expiry first)
CREATE TABLE IF NOT EXISTS batch_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  movement_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  allocation_type ENUM('fefo','manual') NOT NULL DEFAULT 'fefo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ba (item_type, movement_id, batch_id),
  INDEX idx_ba_batch (batch_id),
  INDEX idx_ba_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================== C. ACCOUNTING =============================
CREATE TABLE IF NOT EXISTS chart_of_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  account_type ENUM('asset','liability','equity','income','expense') NOT NULL,
  sub_type VARCHAR(40) DEFAULT NULL,
  parent_id INT DEFAULT NULL,
  system_key VARCHAR(40) DEFAULT NULL UNIQUE,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_coa_type (account_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  journal_number VARCHAR(40) NOT NULL UNIQUE,
  entry_date DATE NOT NULL,
  source_module VARCHAR(30) NOT NULL,
  source_type VARCHAR(40) NOT NULL,
  source_id VARCHAR(40) NOT NULL,
  event VARCHAR(40) NOT NULL,
  source_ref VARCHAR(80) DEFAULT NULL,
  narration VARCHAR(255) DEFAULT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('draft','submitted','posted','reversed','cancelled') NOT NULL DEFAULT 'posted',
  reversal_of INT DEFAULT NULL,
  is_manual TINYINT(1) NOT NULL DEFAULT 0,
  created_by VARCHAR(100) DEFAULT NULL,
  approved_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  posted_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_je_source (source_type, source_id, event),   -- never posts the same event twice
  INDEX idx_je_date (entry_date),
  INDEX idx_je_status (status),
  INDEX idx_je_ref (source_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  journal_id INT NOT NULL,
  account_id INT NOT NULL,
  debit DECIMAL(14,2) NOT NULL DEFAULT 0,
  credit DECIMAL(14,2) NOT NULL DEFAULT 0,
  party_type VARCHAR(20) DEFAULT NULL,             -- supplier / customer / transporter
  party_id INT DEFAULT NULL,
  party_name VARCHAR(150) DEFAULT NULL,
  channel VARCHAR(30) DEFAULT NULL,
  memo VARCHAR(255) DEFAULT NULL,
  INDEX idx_jl_journal (journal_id),
  INDEX idx_jl_account (account_id),
  INDEX idx_jl_party (party_type, party_id),
  CONSTRAINT fk_jl_je FOREIGN KEY (journal_id) REFERENCES journal_entries (id) ON DELETE CASCADE,
  CONSTRAINT fk_jl_acc FOREIGN KEY (account_id) REFERENCES chart_of_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  account_type ENUM('cash','bank','wallet') NOT NULL DEFAULT 'bank',
  coa_account_id INT NOT NULL,
  bank_name VARCHAR(150) DEFAULT NULL,
  account_last4 VARCHAR(8) DEFAULT NULL,
  ifsc VARCHAR(20) DEFAULT NULL,
  payment_modes VARCHAR(120) DEFAULT NULL,         -- comma list of modes that land in this account
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_statement_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bank_account_id INT NOT NULL,
  txn_date DATE NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  amount DECIMAL(14,2) NOT NULL,                   -- + money in, - money out
  balance DECIMAL(14,2) DEFAULT NULL,
  line_hash CHAR(40) NOT NULL UNIQUE,              -- the same statement line is never imported twice
  matched_journal_line_id INT DEFAULT NULL,
  status ENUM('unmatched','matched','ignored') NOT NULL DEFAULT 'unmatched',
  matched_by VARCHAR(100) DEFAULT NULL,
  matched_at DATETIME DEFAULT NULL,
  import_batch VARCHAR(40) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bsl_acct (bank_account_id, txn_date),
  INDEX idx_bsl_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  closed_by VARCHAR(100) DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_fp (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Expenses with approval (posted expenses are also written to the existing Transactions)
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_number VARCHAR(40) NOT NULL UNIQUE,
  expense_date DATE NOT NULL,
  account_id INT NOT NULL,                         -- chart_of_accounts (expense)
  category VARCHAR(100) NOT NULL,
  payee VARCHAR(150) DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  amount DECIMAL(14,2) NOT NULL,                   -- before tax
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL,
  payment_status ENUM('paid','unpaid') NOT NULL DEFAULT 'paid',
  payment_mode ENUM('cash','upi','bank_transfer','card','cheque','other') DEFAULT 'cash',
  bank_account_id INT DEFAULT NULL,
  reference_number VARCHAR(100) DEFAULT NULL,
  paid_date DATE DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  shipment_id INT DEFAULT NULL,
  status ENUM('draft','submitted','approved','posted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  accounts_transaction_id INT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by VARCHAR(100) DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  posted_by VARCHAR(100) DEFAULT NULL,
  posted_at DATETIME DEFAULT NULL,
  cancel_reason VARCHAR(255) DEFAULT NULL,
  INDEX idx_exp_date (expense_date),
  INDEX idx_exp_status (status),
  INDEX idx_exp_acct (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================== D. SALES ==================================
CREATE TABLE IF NOT EXISTS sales_channels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  channel_type ENUM('website','offline','b2b','marketplace','other') NOT NULL DEFAULT 'offline',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Channel tag for a sale (default: website orders = website, everything else = offline)
CREATE TABLE IF NOT EXISTS sales_channel_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_type ENUM('invoice','manual_sale','credit_sale','website_order') NOT NULL,
  source_id INT NOT NULL,
  channel_id INT NOT NULL,
  set_by VARCHAR(100) DEFAULT NULL,
  set_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_scm (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pick / pack / dispatch log on existing sales orders and website orders
CREATE TABLE IF NOT EXISTS fulfilment_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_type ENUM('sales_order','website_order') NOT NULL,
  source_id INT NOT NULL,
  stage ENUM('confirmed','picked','packed','dispatched','delivered','cancelled','returned') NOT NULL,
  tracking_ref VARCHAR(100) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  by_user VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fl_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit notes for sales returns (one per return)
CREATE TABLE IF NOT EXISTS credit_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cn_number VARCHAR(40) NOT NULL UNIQUE,
  sales_return_id INT NOT NULL UNIQUE,
  customer_name VARCHAR(150) DEFAULT NULL,
  customer_mobile VARCHAR(20) DEFAULT NULL,
  source_type VARCHAR(20) DEFAULT NULL,
  source_id INT DEFAULT NULL,
  cn_date DATE NOT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  refunded DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('issued','refunded','cancelled') NOT NULL DEFAULT 'issued',
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cn_mobile (customer_mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================== SEED DATA =================================
INSERT IGNORE INTO sales_channels (code, name, channel_type) VALUES
  ('website', 'Website', 'website'), ('offline', 'Offline / counter', 'offline'), ('b2b', 'B2B / wholesale', 'b2b');

INSERT IGNORE INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm) VALUES
  ('purchase_request', 'Purchase requests (manager approval)', 1, 1, 0, 'purchase.manager_approve'),
  ('purchase_request_final', 'Purchase requests (backend / final approval)', 1, 1, 0, 'purchase.backend_approve'),
  ('purchase_order', 'Purchase orders', 1, 1, 0, 'purchase.backend_approve'),
  ('po_amendment', 'Purchase order amendments', 1, 1, 0, 'purchase.approve'),
  ('stock_adjustment', 'Stock adjustments', 1, 1, 0, 'stock_adjust.approve'),
  ('stock_count', 'Stock counts', 1, 1, 0, 'stock_count.approve'),
  ('manual_journal', 'Manual journal entries', 1, 1, 0, 'accounting.approve'),
  ('purchase_invoice', 'Purchase bills (post)', 0, 0, 0, 'purchase_invoice.approve'),
  ('purchase_payment', 'Supplier payments', 0, 0, 0, 'purchase_payment.approve'),
  ('purchase_return', 'Purchase returns', 0, 0, 0, 'purchase_return.approve'),
  ('sales_return', 'Sales returns / refunds', 0, 0, 0, 'sales_return.approve'),
  ('expense', 'Expenses', 0, 0, 0, 'expense.approve');

-- Chart of accounts (system accounts used by automatic journals)
INSERT IGNORE INTO chart_of_accounts (code, name, account_type, sub_type, system_key, is_system) VALUES
  ('1000', 'Cash in hand', 'asset', 'cash', 'cash', 1),
  ('1010', 'Bank', 'asset', 'bank', 'bank', 1),
  ('1100', 'Accounts receivable (customers)', 'asset', 'receivable', 'ar', 1),
  ('1200', 'Inventory — product packs', 'asset', 'inventory', 'inventory', 1),
  ('1210', 'Inventory — raw materials', 'asset', 'inventory', 'inventory_raw', 1),
  ('1220', 'Stock in transit (between warehouses)', 'asset', 'inventory', 'inventory_transit', 1),
  ('1300', 'GST input credit', 'asset', 'tax', 'gst_input', 1),
  ('1500', 'Fixed assets', 'asset', 'fixed', 'fixed_assets', 1),
  ('2000', 'Accounts payable (suppliers)', 'liability', 'payable', 'ap', 1),
  ('2010', 'Goods received not invoiced', 'liability', 'payable', 'grni', 1),
  ('2020', 'Transport / freight payable', 'liability', 'payable', 'freight_payable', 1),
  ('2030', 'Other payables (unpaid expenses)', 'liability', 'payable', 'other_payable', 1),
  ('2100', 'GST output payable', 'liability', 'tax', 'gst_output', 1),
  ('2900', 'Suspense (to be reviewed)', 'liability', 'other', 'suspense', 1),
  ('3000', 'Opening balance equity', 'equity', 'equity', 'obe', 1),
  ('3100', 'Owner capital', 'equity', 'equity', 'capital', 1),
  ('3200', 'Retained earnings', 'equity', 'equity', 'retained', 1),
  ('4000', 'Sales', 'income', 'sales', 'sales', 1),
  ('4050', 'Sales returns', 'income', 'sales', 'sales_returns', 1),
  ('4100', 'Other income', 'income', 'other', 'other_income', 1),
  ('5000', 'Cost of goods sold', 'expense', 'cogs', 'cogs', 1),
  ('5010', 'Purchase price variance', 'expense', 'cogs', 'ppv', 1),
  ('5100', 'Inventory write-off (waste / damage)', 'expense', 'cogs', 'inv_loss', 1),
  ('5110', 'Stock adjustments (net)', 'expense', 'cogs', 'stock_adjust', 1),
  ('5200', 'Repacking clearing (packing cost absorbed)', 'expense', 'cogs', 'repack', 1),
  ('5300', 'Direct purchases (not stocked)', 'expense', 'cogs', 'purchase_expense', 1),
  ('5310', 'Freight & purchase charges (not in stock cost)', 'expense', 'cogs', 'freight_expense', 1),
  ('6000', 'Rent', 'expense', 'operating', 'exp_rent', 1),
  ('6010', 'Electricity', 'expense', 'operating', 'exp_electricity', 1),
  ('6020', 'Salary & wages', 'expense', 'operating', 'exp_salary', 1),
  ('6030', 'Transport', 'expense', 'operating', 'exp_transport', 1),
  ('6040', 'Packaging', 'expense', 'operating', 'exp_packaging', 1),
  ('6050', 'Marketing', 'expense', 'operating', 'exp_marketing', 1),
  ('6060', 'Advertising', 'expense', 'operating', 'exp_advertising', 1),
  ('6070', 'Delivery', 'expense', 'operating', 'exp_delivery', 1),
  ('6080', 'Software', 'expense', 'operating', 'exp_software', 1),
  ('6090', 'Maintenance', 'expense', 'operating', 'exp_maintenance', 1),
  ('6100', 'Bank charges', 'expense', 'operating', 'exp_bank', 1),
  ('6110', 'Office expenses', 'expense', 'operating', 'exp_office', 1),
  ('6120', 'Warehouse expenses', 'expense', 'operating', 'exp_warehouse', 1),
  ('6130', 'Machinery expenses', 'expense', 'operating', 'exp_machinery', 1),
  ('6990', 'Other expenses', 'expense', 'operating', 'other_expense', 1),
  ('6995', 'Rounding differences', 'expense', 'operating', 'rounding', 1);

INSERT IGNORE INTO bank_accounts (name, account_type, coa_account_id, payment_modes, is_default)
  SELECT 'Cash', 'cash', id, 'cash', 0 FROM chart_of_accounts WHERE system_key = 'cash';
INSERT IGNORE INTO bank_accounts (name, account_type, coa_account_id, payment_modes, is_default)
  SELECT 'Main bank', 'bank', id, 'bank_transfer,upi,card,cheque,other', 1 FROM chart_of_accounts WHERE system_key = 'bank';

-- =============================== PERMISSIONS & ROLES =======================
INSERT IGNORE INTO admin_permissions (perm_key, description) VALUES
  ('rfq.manage', 'RFQs and supplier quotations'),
  ('shipment.manage', 'Inbound transport / logistics'),
  ('qc.manage', 'Quality checks on goods receipts'),
  ('supplier.profile', 'Edit supplier profile (credit limit, bank, opening balance)'),
  ('purchase_invoice.approve', 'Approve purchase bills'),
  ('purchase_payment.approve', 'Approve supplier payments'),
  ('purchase_return.approve', 'Approve purchase returns'),
  ('sales_return.approve', 'Approve sales returns and refunds'),
  ('expense.approve', 'Approve expenses'),
  ('approvals.view', 'See the approvals inbox'),
  ('approvals.manage', 'Change approval rules'),
  ('documents.view', 'View and download documents'),
  ('documents.upload', 'Upload documents'),
  ('documents.archive', 'Archive / restore documents'),
  ('notifications.view', 'See admin notifications'),
  ('warehouse.view', 'See stock by warehouse, batches and expiry'),
  ('warehouse.transfer', 'Transfer stock between warehouses; move damaged / expired stock'),
  ('warehouse.locations', 'Manage zones, racks, shelves and bins'),
  ('stock_count.create', 'Create stock counts'),
  ('stock_count.approve', 'Approve stock counts (posts adjustments)'),
  ('accounting.view', 'View journals, ledgers and financial statements'),
  ('accounting.journal', 'Create manual journal entries'),
  ('accounting.approve', 'Approve / post manual journals, reverse journals'),
  ('accounting.manage', 'Chart of accounts, bank accounts, periods, accounting settings'),
  ('bank.reconcile', 'Import bank statements and reconcile'),
  ('sales.fulfilment', 'Log picking / packing / dispatch'),
  ('sales.channels', 'Manage sales channels and tag sales'),
  ('customer.view', 'Customer 360 view'),
  ('trace.view', 'Transaction trace'),
  ('reports.erp', 'ERP reports');

INSERT IGNORE INTO admin_roles (name, description) VALUES
  ('Accounts', 'Bills, payments, expenses, accounting, reports'),
  ('Warehouse', 'Goods receipts, QC, stock transfers, counts, batches'),
  ('Purchase', 'RFQs, quotations, purchase orders, transport'),
  ('Sales', 'Sales entry, fulfilment, customers, returns'),
  ('Viewer', 'Read-only access to reports');

-- Super Admin passes every check anyway; grant for completeness.
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT 1, perm_key FROM admin_permissions;
-- Manager (role 2): no new grants. purchase_request_approval_workflow.sql limits the
-- Manager to purchase-request review; the ERP layer respects that.
-- Backend role (from purchase_request_approval_workflow.sql, if present): procurement add-ons
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'rfq.manage','shipment.manage','qc.manage','supplier.profile','documents.view','documents.upload','notifications.view',
    'approvals.view','warehouse.view','trace.view')
  WHERE r.name = 'Backend';
-- Staff: day-to-day
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key) VALUES
  (3,'documents.view'),(3,'documents.upload'),(3,'notifications.view'),(3,'warehouse.view'),(3,'qc.manage'),(3,'shipment.manage'),
  (3,'stock_count.create'),(3,'sales.fulfilment'),(3,'customer.view');

INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'dashboard.view','purchase.view','purchase_invoice.create','purchase_payment.create','purchase_invoice.approve','purchase_payment.approve','expense.manage',
    'expense.approve','pnl.view','accounting.view','accounting.journal','accounting.approve','accounting.manage','bank.reconcile','documents.view',
    'documents.upload','documents.archive','notifications.view','approvals.view','customer.view','trace.view','reports.erp','reports.view',
    'credit_sales.view','accounts.view','accounts.create','invoices.view','suppliers.view','warehouse.view')
  WHERE r.name = 'Accounts';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'dashboard.view','purchase.view','grn.create','qc.manage','shipment.manage','inventory.view','stock_in.create','stock_out.create','warehouses.view',
    'warehouse.view','warehouse.transfer','warehouse.locations','stock_count.create','stock_adjust.create','raw_materials.manage','waste.view','waste.create',
    'documents.view','documents.upload','notifications.view','approvals.view','trace.view','dc.view')
  WHERE r.name = 'Warehouse';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'dashboard.view','purchase.view','purchase.create','rfq.manage','shipment.manage','suppliers.view','suppliers.create','supplier.profile',
    'purchase_return.create','inventory.view','warehouse.view','documents.view','documents.upload','notifications.view','approvals.view','trace.view','reports.erp')
  WHERE r.name = 'Purchase';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'dashboard.view','sales_orders.view','sales_orders.create','sales_orders.edit','manual_sales.create','credit_sales.view','credit_sales.create',
    'invoices.view','invoices.create','dc.view','dc.create','customers.view','customer.view','sales_return.create','sales.fulfilment','sales.channels',
    'inventory.view','documents.view','documents.upload','notifications.view','approvals.view','trace.view')
  WHERE r.name = 'Sales';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN (
    'dashboard.view','reports.view','reports.erp','pnl.view','accounting.view','purchase.view','inventory.view','warehouse.view','customer.view',
    'trace.view','documents.view','notifications.view','sales_orders.view','invoices.view','suppliers.view')
  WHERE r.name = 'Viewer';
