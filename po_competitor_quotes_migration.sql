-- ============================================================================
-- Purchase order — competitor quotations (up to 3 suppliers per PO)   1 Oct 2026
--
-- SAFE / ADDITIVE ONLY: two new tables, nothing else is changed.
-- Safe to run more than once. BACK UP THE DATABASE FIRST.
-- ============================================================================
CREATE TABLE IF NOT EXISTS po_competitor_quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
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
  UNIQUE KEY uniq_pcq_slot (po_id, slot),
  INDEX idx_pcq_po (po_id),
  INDEX idx_pcq_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS po_competitor_quote_items (
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
  INDEX idx_pcqi_quote (quote_id),
  INDEX idx_pcqi_item (item_type, item_id),
  CONSTRAINT fk_pcqi_quote FOREIGN KEY (quote_id) REFERENCES po_competitor_quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- VERIFY
-- SELECT COUNT(*) AS tables_found FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('po_competitor_quotes','po_competitor_quote_items');   -- expect 2
-- ---------------------------------------------------------------- ROLLBACK (removes only these two tables)
-- DROP TABLE IF EXISTS po_competitor_quote_items;
-- DROP TABLE IF EXISTS po_competitor_quotes;
