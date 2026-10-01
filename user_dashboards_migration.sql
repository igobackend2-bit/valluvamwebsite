-- ============================================================================
-- Dashboards per admin user (1 Oct 2026)
-- The Super Admin chooses, per user, which dashboards they can use:
-- executive · manager · l1 · admin · ceo · accounts. A dashboard also gives
-- that user the permissions and pages the dashboard needs.
-- No rows for a user = the user's role decides (as before).
-- SAFE / ADDITIVE ONLY: one new table. Safe to run more than once.
-- Rollback: DROP TABLE IF EXISTS admin_user_dashboards;
-- ============================================================================
CREATE TABLE IF NOT EXISTS admin_user_dashboards (
  user_id INT NOT NULL,
  dash_key VARCHAR(20) NOT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, dash_key),
  INDEX idx_aud_key (dash_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- check (expect 1)
SELECT COUNT(*) AS table_found FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_dashboards';
