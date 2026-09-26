-- ============================================================================
-- Valluvam ERP — Foundation migration (run FIRST, before any other ERP SQL)
-- Adds: multi-user admin auth, roles/permissions, audit log, warehouses,
-- suppliers, and a generic document-numbering helper table.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS throughout.
-- ============================================================================

-- ─── admin_users / admin_roles / admin_permissions ─────────────────────────
CREATE TABLE IF NOT EXISTS admin_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO admin_roles (id, name, description) VALUES
  (1, 'Super Admin', 'Full access to every module and setting'),
  (2, 'Manager', 'Operational access: sales, inventory, accounts — no user/role management'),
  (3, 'Staff', 'Day-to-day data entry: sales orders, stock, waste reporting');

CREATE TABLE IF NOT EXISTS admin_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  perm_key VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO admin_permissions (perm_key, description) VALUES
  ('dashboard.view', 'View dashboard'),
  ('sales_orders.view', 'View sales orders'), ('sales_orders.create', 'Create sales orders'),
  ('sales_orders.edit', 'Edit sales orders'), ('sales_orders.cancel', 'Cancel sales orders'),
  ('dc.view', 'View delivery challans'), ('dc.create', 'Create delivery challans'), ('dc.edit', 'Edit delivery challans'),
  ('manual_sales.create', 'Create manual sales entries'),
  ('inventory.view', 'View inventory'), ('inventory.adjust', 'Adjust stock'),
  ('stock_in.create', 'Create stock in records'), ('stock_out.create', 'Create stock out records'),
  ('invoices.view', 'View invoices'), ('invoices.create', 'Create invoices'), ('invoices.cancel', 'Cancel invoices'),
  ('assets.view', 'View assets'), ('assets.create', 'Create assets'), ('assets.edit', 'Edit assets'),
  ('accounts.view', 'View accounts'), ('accounts.create', 'Create account transactions'),
  ('waste.view', 'View waste records'), ('waste.create', 'Create waste records'), ('waste.approve', 'Approve waste records'),
  ('customers.view', 'View customers'), ('suppliers.view', 'View suppliers'), ('suppliers.create', 'Create/edit suppliers'),
  ('warehouses.view', 'View warehouses'), ('warehouses.create', 'Create/edit warehouses'),
  ('reports.view', 'View reports'), ('audit_logs.view', 'View audit logs'),
  ('settings.view', 'View/edit settings'), ('users.manage', 'Manage admin users, roles and permissions');

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id INT NOT NULL,
  perm_key VARCHAR(100) NOT NULL,
  PRIMARY KEY (role_id, perm_key),
  CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_arp_perm FOREIGN KEY (perm_key) REFERENCES admin_permissions (perm_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Super Admin: every permission
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key) SELECT 1, perm_key FROM admin_permissions;
-- Manager: everything except users.manage
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key) SELECT 2, perm_key FROM admin_permissions WHERE perm_key <> 'users.manage';
-- Staff: day-to-day operational set
INSERT IGNORE INTO admin_role_permissions (role_id, perm_key) VALUES
  (3,'dashboard.view'),(3,'sales_orders.view'),(3,'sales_orders.create'),
  (3,'dc.view'),(3,'dc.create'),(3,'manual_sales.create'),
  (3,'inventory.view'),(3,'stock_in.create'),(3,'stock_out.create'),
  (3,'invoices.view'),(3,'waste.view'),(3,'waste.create'),
  (3,'customers.view'),(3,'suppliers.view'),(3,'warehouses.view'),(3,'reports.view');

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  role_id INT NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_users_role FOREIGN KEY (role_id) REFERENCES admin_roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: no default user is seeded here with a fixed password — see the
-- separate one-time "01_seed_super_admin.sql" delivered alongside this file,
-- which hashes YOUR chosen password before inserting the first Super Admin.

-- ─── audit_logs ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT DEFAULT NULL,
  username VARCHAR(100) DEFAULT NULL,
  action VARCHAR(50) NOT NULL,
  module VARCHAR(50) NOT NULL,
  record_id VARCHAR(50) DEFAULT NULL,
  old_value TEXT DEFAULT NULL,
  new_value TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_module (module, created_at),
  INDEX idx_audit_user (admin_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── warehouses ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(30) NOT NULL UNIQUE,
  location VARCHAR(255) DEFAULT NULL,
  manager_name VARCHAR(150) DEFAULT NULL,
  contact VARCHAR(30) DEFAULT NULL,
  capacity VARCHAR(100) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO warehouses (id, name, code, location, status) VALUES
  (1, 'Main Warehouse', 'WH-MAIN', 'Primary store location', 'active');

-- ─── suppliers ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name VARCHAR(150) NOT NULL,
  company_name VARCHAR(150) DEFAULT NULL,
  mobile VARCHAR(20) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  gst_number VARCHAR(30) DEFAULT NULL,
  payment_terms VARCHAR(150) DEFAULT NULL,
  bank_details TEXT DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── document_sequences: generic auto-numbering (SO-2026-000001 style) ────
CREATE TABLE IF NOT EXISTS document_sequences (
  doc_type VARCHAR(20) PRIMARY KEY,
  year INT NOT NULL,
  last_number INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ============================================================================
-- Run this ONCE, after 00_foundation.sql, to create your first Super Admin
-- login. Username: admin   Temporary password: fc3b37b2b8d7
--
-- IMPORTANT: log in with this once, then change the password immediately
-- from Admin → Settings → My Account (or Admin Users, as Super Admin) —
-- the new admin login screen supports changing your own password.
-- Safe to re-run: INSERT IGNORE, so it won't overwrite an existing 'admin'.
-- ============================================================================

INSERT IGNORE INTO admin_users (username, password_hash, full_name, role_id, status)
VALUES ('admin', '$2y$12$GsdA/.GtTatMENuDSjtCNeRGvl1jbsy/BlZcIMggvMDGX2sNbCDUi', 'Super Admin', 1, 'active');
-- ============================================================================
-- Valluvam ERP — Sales Flow migration (Sales Orders, Delivery Challans,
-- Manual Sales, Invoices). Run AFTER 00_foundation.sql.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS throughout.
--
-- NOTE: sales_orders is a SEPARATE, parallel entity from the live storefront
-- `orders`/`order_items` (Razorpay checkout) tables — those are untouched.
-- This module covers offline/B2B/phone sales entered by admin staff.
-- ============================================================================

-- ─── sales_orders / sales_order_items ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  so_number VARCHAR(40) NOT NULL UNIQUE,
  order_date DATE NOT NULL,
  customer_id INT DEFAULT NULL,
  customer_name VARCHAR(150) DEFAULT NULL,
  customer_mobile VARCHAR(20) DEFAULT NULL,
  customer_email VARCHAR(150) DEFAULT NULL,
  customer_address VARCHAR(255) DEFAULT NULL,
  billing_address VARCHAR(255) DEFAULT NULL,
  shipping_address VARCHAR(255) DEFAULT NULL,
  salesperson VARCHAR(150) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  payment_terms VARCHAR(150) DEFAULT NULL,
  delivery_terms VARCHAR(150) DEFAULT NULL,
  expected_delivery_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  internal_notes TEXT DEFAULT NULL,
  status ENUM('draft','confirmed','processing','ready_for_dispatch','dispatched','delivered','completed','cancelled') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_by VARCHAR(100) DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_so_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  INDEX idx_so_status (status),
  INDEX idx_so_date (order_date),
  INDEX idx_so_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sales_order_id INT NOT NULL,
  product_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_soi_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES product_details (id),
  INDEX idx_soi_order (sales_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── delivery_challans / delivery_challan_items ────────────────────────────
CREATE TABLE IF NOT EXISTS delivery_challans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dc_number VARCHAR(40) NOT NULL UNIQUE,
  sales_order_id INT DEFAULT NULL,
  customer_name VARCHAR(150) DEFAULT NULL,
  customer_mobile VARCHAR(20) DEFAULT NULL,
  customer_email VARCHAR(150) DEFAULT NULL,
  delivery_address VARCHAR(255) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  vehicle_number VARCHAR(30) DEFAULT NULL,
  driver_name VARCHAR(150) DEFAULT NULL,
  driver_mobile VARCHAR(20) DEFAULT NULL,
  dispatch_date DATE DEFAULT NULL,
  expected_delivery_date DATE DEFAULT NULL,
  delivery_status ENUM('draft','ready','loaded','dispatched','in_transit','delivered','cancelled') NOT NULL DEFAULT 'draft',
  remarks TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_dc_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_dc_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  INDEX idx_dc_status (delivery_status),
  INDEX idx_dc_so (sales_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delivery_challan_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_challan_id INT NOT NULL,
  product_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  CONSTRAINT fk_dci_dc FOREIGN KEY (delivery_challan_id) REFERENCES delivery_challans (id) ON DELETE CASCADE,
  CONSTRAINT fk_dci_product FOREIGN KEY (product_id) REFERENCES product_details (id),
  INDEX idx_dci_dc (delivery_challan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── manual_sales / manual_sale_items ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS manual_sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_number VARCHAR(40) NOT NULL UNIQUE,
  sales_date DATE NOT NULL,
  customer_name VARCHAR(150) DEFAULT NULL,
  customer_mobile VARCHAR(20) DEFAULT NULL,
  customer_address VARCHAR(255) DEFAULT NULL,
  payment_mode ENUM('cash','upi','bank_transfer','card','credit','other') NOT NULL DEFAULT 'cash',
  payment_status ENUM('paid','partially_paid','pending','credit') NOT NULL DEFAULT 'paid',
  salesperson VARCHAR(150) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ms_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  INDEX idx_ms_date (sales_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS manual_sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  manual_sale_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_msi_sale FOREIGN KEY (manual_sale_id) REFERENCES manual_sales (id) ON DELETE CASCADE,
  CONSTRAINT fk_msi_product FOREIGN KEY (product_id) REFERENCES product_details (id),
  INDEX idx_msi_sale (manual_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── invoices / invoice_items ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(40) NOT NULL UNIQUE,
  invoice_date DATE NOT NULL,
  customer_id INT DEFAULT NULL,
  customer_name VARCHAR(150) DEFAULT NULL,
  customer_mobile VARCHAR(20) DEFAULT NULL,
  customer_email VARCHAR(150) DEFAULT NULL,
  billing_address VARCHAR(255) DEFAULT NULL,
  shipping_address VARCHAR(255) DEFAULT NULL,
  sales_order_id INT DEFAULT NULL,
  dc_number VARCHAR(40) DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  payment_mode ENUM('cash','upi','bank_transfer','card','credit','other') DEFAULT NULL,
  status ENUM('draft','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE SET NULL,
  INDEX idx_inv_status (status),
  INDEX idx_inv_date (invoice_date),
  INDEX idx_inv_so (sales_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_ii_product FOREIGN KEY (product_id) REFERENCES product_details (id),
  INDEX idx_ii_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ============================================================================
-- Valluvam ERP — Inventory Operations migration (Stock In / Stock Out /
-- centralized Stock Movement History / warehouse-aware inventory).
-- Run AFTER 00_foundation.sql. Safe to re-run: CREATE TABLE IF NOT EXISTS
-- throughout. Does NOT touch the existing `stock_history` table — that
-- keeps working untouched for the existing manual "Adjust stock" feature on
-- admin/inventory.php. `stock_movements` below is the new, generalized
-- source of truth that Stock In / Stock Out (and future modules) write to.
-- ============================================================================

-- ─── stock_movements: centralized, append-only movement ledger ────────────
-- IMMUTABLE BY DESIGN: nothing in this build ever UPDATEs or DELETEs a row
-- here. The only future exception the brief allows is a "Super Admin
-- correction" feature (NOT built in this pass) — and even that must always
-- INSERT a new correction row (movement_type = 'correction') referencing the
-- original, never edit or remove the original row.
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  movement_type ENUM('stock_in','stock_out','sale','purchase','transfer','adjustment','damage','waste','return','correction') NOT NULL,
  product_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  quantity INT NOT NULL,              -- signed: positive = increase, negative = decrease
  previous_stock INT NOT NULL,
  new_stock INT NOT NULL,
  reference_type VARCHAR(30) DEFAULT NULL,   -- e.g. 'stock_in','stock_out','delivery_challan','manual_sale','waste','adjustment'
  reference_number VARCHAR(50) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sm_product (product_id, created_at),
  INDEX idx_sm_warehouse (warehouse_id),
  INDEX idx_sm_type (movement_type),
  CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES product_details (id),
  CONSTRAINT fk_sm_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── stock_ins / stock_in_items: Stock In (Load In) ────────────────────────
CREATE TABLE IF NOT EXISTS stock_ins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_in_number VARCHAR(50) NOT NULL UNIQUE,
  stock_in_date DATE NOT NULL,
  supplier_id INT DEFAULT NULL,
  purchase_reference VARCHAR(100) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  received_by VARCHAR(150) DEFAULT NULL,
  vehicle_number VARCHAR(30) DEFAULT NULL,
  remarks TEXT DEFAULT NULL,
  attachment_note VARCHAR(255) DEFAULT NULL, -- placeholder free-text; no file upload in this pass
  status ENUM('draft','received','verified','completed','cancelled') NOT NULL DEFAULT 'draft',
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_si_status (status),
  INDEX idx_si_warehouse (warehouse_id),
  CONSTRAINT fk_si_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_si_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_in_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_in_id INT NOT NULL,
  product_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity INT NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  batch_number VARCHAR(60) DEFAULT NULL,
  manufacturing_date DATE DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  purchase_rate DECIMAL(12,2) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sii_stock_in (stock_in_id),
  INDEX idx_sii_product (product_id),
  CONSTRAINT fk_sii_stock_in FOREIGN KEY (stock_in_id) REFERENCES stock_ins (id) ON DELETE CASCADE,
  CONSTRAINT fk_sii_product FOREIGN KEY (product_id) REFERENCES product_details (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── stock_outs / stock_out_items: Stock Out (Load Out) ────────────────────
CREATE TABLE IF NOT EXISTS stock_outs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_out_number VARCHAR(50) NOT NULL UNIQUE,
  stock_out_date DATE NOT NULL,
  reference_type ENUM('sales_order','delivery_challan','manual_sales','internal_transfer','damage','waste','other') NOT NULL DEFAULT 'other',
  reference_number VARCHAR(50) DEFAULT NULL,
  warehouse_id INT NOT NULL DEFAULT 1,
  vehicle_number VARCHAR(30) DEFAULT NULL,
  customer_name VARCHAR(150) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  authorized_by VARCHAR(150) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_so_reference (reference_type),
  INDEX idx_stkout_warehouse (warehouse_id),
  CONSTRAINT fk_stkout_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_out_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_out_id INT NOT NULL,
  product_id INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity INT NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_soi_stock_out (stock_out_id),
  INDEX idx_stkout_items_product (product_id),
  CONSTRAINT fk_soi_stock_out FOREIGN KEY (stock_out_id) REFERENCES stock_outs (id) ON DELETE CASCADE,
  CONSTRAINT fk_stkout_items_product FOREIGN KEY (product_id) REFERENCES product_details (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: `product_details.min_stock_level` / `max_stock_level` / `reorder_level`
-- are added defensively at runtime (ALTER TABLE ... ADD COLUMN wrapped in a
-- try/catch, same pattern as the existing `stock` column in adjust_stock.php)
-- from assets/db_query/admin/get_inventory_overview.php, not here — matching
-- this codebase's existing defensive-migration style.
-- ============================================================================
-- Valluvam ERP — Asset Management, Accounts Management, Waste Management
-- Run AFTER 00_foundation.sql (and independent of 10_sales_flow.sql / the
-- stock-management migration — reference_type/reference_number fields are
-- free text, not FKs, so load order with those modules does not matter).
-- Safe to re-run: CREATE TABLE IF NOT EXISTS throughout.
-- ============================================================================

-- ─── assets ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id VARCHAR(40) NOT NULL UNIQUE,
  asset_name VARCHAR(150) NOT NULL,
  asset_category VARCHAR(100) NOT NULL,
  asset_type VARCHAR(100) DEFAULT NULL,
  purchase_date DATE DEFAULT NULL,
  purchase_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_value DECIMAL(12,2) DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  serial_number VARCHAR(100) DEFAULT NULL,
  model_number VARCHAR(100) DEFAULT NULL,
  location VARCHAR(150) DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  assigned_employee VARCHAR(150) DEFAULT NULL,
  warranty_start_date DATE DEFAULT NULL,
  warranty_end_date DATE DEFAULT NULL,
  document_note VARCHAR(255) DEFAULT NULL,
  status ENUM('active','assigned','under_maintenance','damaged','lost','disposed','retired') NOT NULL DEFAULT 'active',
  asset_condition ENUM('new','good','fair','damaged','critical') NOT NULL DEFAULT 'new',
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_asset_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  INDEX idx_asset_status (status),
  INDEX idx_asset_category (asset_category),
  INDEX idx_asset_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── asset_assignments (history log) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS asset_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  assigned_to VARCHAR(150) NOT NULL,
  assigned_date DATE NOT NULL,
  returned_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_asgn_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
  INDEX idx_asgn_asset (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── asset_maintenance (history log) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS asset_maintenance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  maintenance_date DATE NOT NULL,
  description TEXT NOT NULL,
  cost DECIMAL(12,2) DEFAULT NULL,
  performed_by VARCHAR(150) DEFAULT NULL,
  next_due_date DATE DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maint_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
  INDEX idx_maint_asset (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── accounts_transactions ─────────────────────────────────────────────────
-- reference_type/reference_number are free text (e.g. 'sales_order'/'SO-2026-000042',
-- 'invoice'/'INV-2026-000012') deliberately NOT foreign keys, so this table can
-- receive entries referencing sales_orders/invoices/manual_sales/stock_ins/
-- stock_outs regardless of whether those modules' migrations have run yet.
CREATE TABLE IF NOT EXISTS accounts_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id VARCHAR(40) NOT NULL UNIQUE,
  date DATE NOT NULL,
  type ENUM('income','expense','payment_received','payment_made','refund','adjustment') NOT NULL,
  category VARCHAR(100) NOT NULL,
  reference_type VARCHAR(30) DEFAULT NULL,
  reference_number VARCHAR(50) DEFAULT NULL,
  party_name VARCHAR(150) DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_mode ENUM('cash','upi','bank_transfer','card','cheque','other') NOT NULL DEFAULT 'cash',
  account VARCHAR(100) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  status ENUM('completed','pending','cancelled') NOT NULL DEFAULT 'completed',
  created_by VARCHAR(100) DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_txn_type (type),
  INDEX idx_txn_date (date),
  INDEX idx_txn_status (status),
  INDEX idx_txn_reference (reference_type, reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── waste_records ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS waste_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  waste_id VARCHAR(40) NOT NULL UNIQUE,
  date DATE NOT NULL,
  waste_type ENUM('product_waste','damaged_stock','expired_stock','production_waste','packaging_waste','other') NOT NULL,
  product_id INT DEFAULT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  quantity INT DEFAULT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  warehouse_id INT NOT NULL DEFAULT 1,
  reason VARCHAR(255) NOT NULL,
  estimated_value DECIMAL(10,2) DEFAULT NULL,
  disposal_method VARCHAR(150) DEFAULT NULL,
  status ENUM('reported','approved','processed','disposed','cancelled') NOT NULL DEFAULT 'reported',
  approved_by VARCHAR(150) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_waste_product FOREIGN KEY (product_id) REFERENCES product_details (id) ON DELETE SET NULL,
  CONSTRAINT fk_waste_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
  INDEX idx_waste_status (status),
  INDEX idx_waste_type (waste_type),
  INDEX idx_waste_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
