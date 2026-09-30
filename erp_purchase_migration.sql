-- ============================================================================
-- Valluvam Admin — Purchase → Inventory costing → Profit & Loss  (30 Sep 2026)
--
-- SAFE / ADDITIVE ONLY:
--   * Only CREATE TABLE IF NOT EXISTS and INSERT IGNORE.
--   * No ALTER, DROP, TRUNCATE, UPDATE or DELETE on any existing table.
--   * Safe to run more than once.
-- Run once in phpMyAdmin (SQL tab) on the live database.
-- ============================================================================

-- ---------- Raw materials (bulk items bought in kg / L, repacked into products)
CREATE TABLE IF NOT EXISTS raw_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  unit ENUM('kg','g','L','ml','pcs') NOT NULL DEFAULT 'kg',
  stock DECIMAL(14,3) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(14,3) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raw_material_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  raw_material_id INT NOT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  movement_type VARCHAR(30) NOT NULL,            -- purchase, repack_consume, purchase_return, adjustment, waste
  quantity DECIMAL(14,3) NOT NULL,               -- signed: + in, - out
  previous_stock DECIMAL(14,3) NOT NULL,
  new_stock DECIMAL(14,3) NOT NULL,
  reference_type VARCHAR(30) DEFAULT NULL,
  reference_number VARCHAR(50) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rmm_item (raw_material_id, created_at),
  CONSTRAINT fk_rmm_rm FOREIGN KEY (raw_material_id) REFERENCES raw_materials (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Purchase requests
CREATE TABLE IF NOT EXISTS purchase_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pr_number VARCHAR(40) NOT NULL UNIQUE,
  request_date DATE NOT NULL,
  required_by DATE DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  requested_by VARCHAR(150) DEFAULT NULL,
  status ENUM('draft','submitted','approved','rejected','converted','cancelled') NOT NULL DEFAULT 'draft',
  notes TEXT DEFAULT NULL,
  approved_by VARCHAR(100) DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pr_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  estimated_rate DECIMAL(14,4) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  INDEX idx_pri_pr (pr_id),
  CONSTRAINT fk_pri_pr FOREIGN KEY (pr_id) REFERENCES purchase_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Purchase orders (creating a PO never changes stock)
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(40) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  pr_id INT DEFAULT NULL,
  po_date DATE NOT NULL,
  expected_delivery_date DATE DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  buyer VARCHAR(150) DEFAULT NULL,
  status ENUM('draft','pending_approval','approved','partially_received','fully_received','cancelled','closed') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  approved_by VARCHAR(100) DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_po_status (status),
  INDEX idx_po_supplier (supplier_id),
  INDEX idx_po_date (po_date),
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_po_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  received_qty DECIMAL(14,3) NOT NULL DEFAULT 0,  -- accepted qty posted through GRNs
  INDEX idx_poi_po (po_id),
  INDEX idx_poi_item (item_type, item_id),
  CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Goods receipts (GRN). Posting a GRN adds ACCEPTED qty to stock through
-- the existing Stock In tables (stock_ins / stock_in_items / stock_movements).
CREATE TABLE IF NOT EXISTS goods_receipts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grn_number VARCHAR(40) NOT NULL UNIQUE,
  po_id INT DEFAULT NULL,
  supplier_id INT NOT NULL,
  received_date DATE NOT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  received_by VARCHAR(150) DEFAULT NULL,
  supplier_challan_no VARCHAR(80) DEFAULT NULL,
  vehicle_number VARCHAR(30) DEFAULT NULL,
  status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
  stock_in_id INT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  posted_by VARCHAR(100) DEFAULT NULL,
  posted_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_grn_status (status),
  INDEX idx_grn_po (po_id),
  INDEX idx_grn_supplier (supplier_id),
  CONSTRAINT fk_grn_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_grn_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  CONSTRAINT fk_grn_po FOREIGN KEY (po_id) REFERENCES purchase_orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grn_id INT NOT NULL,
  po_item_id INT DEFAULT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  ordered_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  received_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  accepted_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  rejected_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,         -- net purchase rate per unit (after discount, excl. recoverable tax)
  batch_number VARCHAR(60) DEFAULT NULL,
  lot_number VARCHAR(60) DEFAULT NULL,
  manufacturing_date DATE DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  qc_status ENUM('pending','passed','partial','failed') NOT NULL DEFAULT 'passed',
  rejection_reason VARCHAR(255) DEFAULT NULL,
  INDEX idx_grni_grn (grn_id),
  INDEX idx_grni_item (item_type, item_id),
  INDEX idx_grni_po_item (po_item_id),
  CONSTRAINT fk_grni_grn FOREIGN KEY (grn_id) REFERENCES goods_receipts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Batch / lot register (one row per accepted GRN line or repack output)
CREATE TABLE IF NOT EXISTS inventory_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  batch_number VARCHAR(60) DEFAULT NULL,
  lot_number VARCHAR(60) DEFAULT NULL,
  manufacturing_date DATE DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  source_type VARCHAR(20) NOT NULL DEFAULT 'grn',   -- grn, repack
  source_id INT DEFAULT NULL,                        -- goods_receipts.id / repack_jobs.id
  source_item_id INT DEFAULT NULL,                   -- goods_receipt_items.id
  received_date DATE NOT NULL,
  qty_received DECIMAL(14,3) NOT NULL,
  qty_returned DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ib_item (item_type, item_id),
  INDEX idx_ib_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Cost ledger used by the weighted-average costing engine.
-- 'receipt' rows carry qty+value for an inflow (matched to the stock movement by
-- reference_number); 'landed'/'price' rows are value-only adjustments.
CREATE TABLE IF NOT EXISTS inventory_cost_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  entry_date DATETIME NOT NULL,
  entry_type ENUM('receipt','landed','price','opening') NOT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  value DECIMAL(14,2) NOT NULL DEFAULT 0,
  reference_type VARCHAR(30) DEFAULT NULL,
  reference_number VARCHAR(50) DEFAULT NULL,
  source_id INT DEFAULT NULL,
  after_movement_id INT DEFAULT NULL,              -- value-only rows: the last ledger row for this item when posted (exact replay order)
  note VARCHAR(255) DEFAULT NULL,
  status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ice_item (item_type, item_id, entry_date),
  INDEX idx_ice_ref (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Purchase invoices / vendor bills
CREATE TABLE IF NOT EXISTS purchase_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pinv_number VARCHAR(40) NOT NULL UNIQUE,
  supplier_invoice_no VARCHAR(80) NOT NULL,
  supplier_id INT NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE DEFAULT NULL,
  po_id INT DEFAULT NULL,
  grn_id INT DEFAULT NULL,
  status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  charges_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  posted_by VARCHAR(100) DEFAULT NULL,
  posted_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_supplier_invoice (supplier_id, supplier_invoice_no),
  INDEX idx_pinv_status (status),
  INDEX idx_pinv_date (invoice_date),
  CONSTRAINT fk_pinv_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_pinv_po FOREIGN KEY (po_id) REFERENCES purchase_orders (id),
  CONSTRAINT fk_pinv_grn FOREIGN KEY (grn_id) REFERENCES goods_receipts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pinv_id INT NOT NULL,
  grn_item_id INT DEFAULT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  allocated_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
  landed_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  INDEX idx_pinvi_pinv (pinv_id),
  INDEX idx_pinvi_item (item_type, item_id),
  CONSTRAINT fk_pinvi_pinv FOREIGN KEY (pinv_id) REFERENCES purchase_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_invoice_charges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pinv_id INT NOT NULL,
  charge_type ENUM('freight','transport','loading','unloading','packaging','other') NOT NULL DEFAULT 'other',
  description VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  add_to_cost TINYINT(1) NOT NULL DEFAULT 1,     -- 1 = direct cost, allocated into landed cost
  INDEX idx_pic_pinv (pinv_id),
  CONSTRAINT fk_pic_pinv FOREIGN KEY (pinv_id) REFERENCES purchase_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Supplier payments
CREATE TABLE IF NOT EXISTS purchase_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_number VARCHAR(40) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  pinv_id INT DEFAULT NULL,                        -- NULL = advance / on-account
  payment_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  payment_mode ENUM('cash','bank_transfer','upi','cheque','card','other') NOT NULL DEFAULT 'bank_transfer',
  account VARCHAR(100) DEFAULT NULL,
  reference_number VARCHAR(100) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  status ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
  accounts_transaction_id INT DEFAULT NULL,
  cancelled_by VARCHAR(100) DEFAULT NULL,
  cancel_reason VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pp_supplier (supplier_id),
  INDEX idx_pp_pinv (pinv_id),
  INDEX idx_pp_date (payment_date),
  CONSTRAINT fk_pp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_pp_pinv FOREIGN KEY (pinv_id) REFERENCES purchase_invoices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Purchase returns / debit notes (with vendor credit note)
CREATE TABLE IF NOT EXISTS purchase_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_number VARCHAR(40) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  po_id INT DEFAULT NULL,
  grn_id INT DEFAULT NULL,
  pinv_id INT DEFAULT NULL,
  return_date DATE NOT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  reason VARCHAR(255) NOT NULL,
  settlement ENUM('credit_note','refund','replacement') NOT NULL DEFAULT 'credit_note',
  credit_note_number VARCHAR(80) DEFAULT NULL,
  total_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  refund_received DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
  stock_out_id INT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  posted_by VARCHAR(100) DEFAULT NULL,
  posted_at DATETIME DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pret_supplier (supplier_id),
  INDEX idx_pret_status (status),
  CONSTRAINT fk_pret_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  grn_item_id INT DEFAULT NULL,
  batch_id INT DEFAULT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,
  line_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  INDEX idx_preti_ret (return_id),
  CONSTRAINT fk_preti_ret FOREIGN KEY (return_id) REFERENCES purchase_returns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Repacking: bulk raw material (kg/L) -> finished product packs
CREATE TABLE IF NOT EXISTS repack_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  repack_number VARCHAR(40) NOT NULL UNIQUE,
  repack_date DATE NOT NULL,
  raw_material_id INT NOT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  consumed_qty DECIMAL(14,3) NOT NULL,
  material_cost DECIMAL(14,2) NOT NULL DEFAULT 0,  -- consumed qty x average cost at the time
  packing_cost DECIMAL(14,2) NOT NULL DEFAULT 0,   -- pouches, labels, labour (direct)
  output_qty_total DECIMAL(14,3) NOT NULL DEFAULT 0, -- in raw material unit
  process_loss_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  stock_in_id INT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rj_rm FOREIGN KEY (raw_material_id) REFERENCES raw_materials (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repack_job_outputs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  repack_id INT NOT NULL,
  product_id INT NOT NULL,
  packs INT NOT NULL,
  pack_size_qty DECIMAL(14,3) NOT NULL,            -- per pack, in raw material unit (e.g. 0.5 kg)
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  batch_number VARCHAR(60) DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  INDEX idx_rjo_repack (repack_id),
  CONSTRAINT fk_rjo_repack FOREIGN KEY (repack_id) REFERENCES repack_jobs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Sales returns
CREATE TABLE IF NOT EXISTS sales_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_number VARCHAR(40) NOT NULL UNIQUE,
  return_date DATE NOT NULL,
  source_type ENUM('invoice','manual_sale','credit_sale','website_order') NOT NULL,
  source_id INT NOT NULL,
  source_number VARCHAR(60) DEFAULT NULL,
  customer_name VARCHAR(150) DEFAULT NULL,
  customer_mobile VARCHAR(20) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  reason VARCHAR(255) NOT NULL,
  settlement ENUM('refund','replacement','credit_note') NOT NULL DEFAULT 'refund',
  refund_mode ENUM('cash','upi','bank_transfer','card','cheque','other') DEFAULT NULL,
  total_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  stock_in_id INT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sret_source (source_type, source_id),
  INDEX idx_sret_date (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  restock_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  stock_condition ENUM('good','damaged','expired','mixed') NOT NULL DEFAULT 'good',
  rate DECIMAL(14,4) NOT NULL DEFAULT 0,
  line_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  INDEX idx_sreti_ret (return_id),
  CONSTRAINT fk_sreti_ret FOREIGN KEY (return_id) REFERENCES sales_returns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Stock adjustments (request -> approve -> ledger)
CREATE TABLE IF NOT EXISTS stock_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adj_number VARCHAR(40) NOT NULL UNIQUE,
  adj_date DATE NOT NULL,
  adjustment_type ENUM('increase','decrease','physical_count','damage','missing','found') NOT NULL,
  item_type ENUM('product','raw_material') NOT NULL DEFAULT 'product',
  item_id INT NOT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  batch_id INT DEFAULT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,       -- signed change (for physical_count = counted - system)
  system_qty DECIMAL(14,3) DEFAULT NULL,
  counted_qty DECIMAL(14,3) DEFAULT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_by VARCHAR(100) DEFAULT NULL,
  approved_by VARCHAR(100) DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_adj_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Document attachments (vendor invoice, receipt, DC, e-way bill, QC, payment proof ...)
CREATE TABLE IF NOT EXISTS erp_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(30) NOT NULL,                -- purchase_order, grn, purchase_invoice, purchase_payment, purchase_return, sales_return, expense, supplier
  entity_id INT NOT NULL,
  doc_type ENUM('vendor_invoice','purchase_receipt','delivery_challan','eway_bill','qc_document','payment_proof','credit_note','expense_receipt','other') NOT NULL DEFAULT 'other',
  file_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) DEFAULT NULL,
  file_size INT DEFAULT NULL,
  uploaded_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_doc_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Permissions (existing role system). Super Admin always passes anyway.
INSERT IGNORE INTO admin_permissions (perm_key, description) VALUES
  ('purchase.view', 'View purchases, GRNs, bills, payments, returns'),
  ('purchase.create', 'Create/edit purchase requests and orders'),
  ('purchase.approve', 'Approve purchase requests and orders'),
  ('grn.create', 'Create and post goods receipts'),
  ('purchase_invoice.create', 'Create and post purchase invoices'),
  ('purchase_payment.create', 'Record supplier payments'),
  ('purchase_return.create', 'Create purchase returns'),
  ('raw_materials.manage', 'Manage raw materials and repacking'),
  ('sales_return.create', 'Create sales returns'),
  ('stock_adjust.create', 'Request stock adjustments'),
  ('stock_adjust.approve', 'Approve stock adjustments'),
  ('expense.manage', 'Record expenses'),
  ('pnl.view', 'View profit & loss, costing and valuation reports'),
  ('credit_sales.view', 'View credit sales'),
  ('credit_sales.create', 'Create credit sales and record their payments');

INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT 1, perm_key FROM admin_permissions;
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
  SELECT 2, perm_key FROM admin_permissions WHERE perm_key <> 'users.manage';
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key) VALUES
  (3,'purchase.view'),(3,'grn.create'),(3,'stock_adjust.create'),(3,'sales_return.create'),
  (3,'credit_sales.view'),(3,'credit_sales.create');
