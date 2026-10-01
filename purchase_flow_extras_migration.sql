-- ============================================================================
-- Purchase flow — loading / unloading photos, quantity on the shop bill,
-- unloading checker name + digital signature, quality-check person + report
-- (1 Oct 2026). ADDITIVE ONLY: one new table, nothing existing is changed.
-- BACKUP first. Safe to run twice (IF NOT EXISTS).
-- ============================================================================

-- 1) Migration
CREATE TABLE IF NOT EXISTS purchase_flow_extras (
  pr_id INT NOT NULL PRIMARY KEY,
  loading_photo_doc_id INT DEFAULT NULL,
  unloading_photo_doc_id INT DEFAULT NULL,
  shop_bill_lines TEXT DEFAULT NULL,            -- JSON: [{po_item_id, bill_qty}]
  shop_bill_total DECIMAL(14,2) DEFAULT NULL,
  unload_checker_name VARCHAR(150) DEFAULT NULL,
  unload_signature_doc_id INT DEFAULT NULL,
  unload_signed_at DATETIME DEFAULT NULL,
  qc_inspector_name VARCHAR(150) DEFAULT NULL,
  qc_report TEXT DEFAULT NULL,
  qc_report_doc_id INT DEFAULT NULL,
  qc_reported_at DATETIME DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Verification (expect 1 row: purchase_flow_extras)
SHOW TABLES LIKE 'purchase_flow_extras';

-- 3) Rollback (only if needed — removes just this new table and what was saved in it;
--    the photos / signature files themselves stay in Documents)
-- DROP TABLE purchase_flow_extras;
