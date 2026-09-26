-- ============================================================
-- Valluvam Profile System - Run in Supabase/MySQL SQL Editor
-- ============================================================

-- 1. Add missing columns to users table (safe, uses IF NOT EXISTS)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS full_name   VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS avatar_url  VARCHAR(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS gender      ENUM('male','female','other','prefer_not_to_say') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS dob         DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS updated_at  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- 2. Create saved_addresses table
CREATE TABLE IF NOT EXISTS saved_addresses (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  label         VARCHAR(50)  NOT NULL DEFAULT 'Home',  -- Home / Work / Other
  full_name     VARCHAR(120) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,
  street        VARCHAR(255) NOT NULL,
  apartment     VARCHAR(100) DEFAULT NULL,
  city          VARCHAR(100) NOT NULL,
  state         VARCHAR(100) NOT NULL,
  postcode      VARCHAR(20)  NOT NULL,
  country       VARCHAR(80)  NOT NULL DEFAULT 'India',
  is_default    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create notifications table (Inbox)
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('order','promo','system','review') NOT NULL DEFAULT 'system',
  title       VARCHAR(200) NOT NULL,
  body        TEXT         NOT NULL,
  link        VARCHAR(500) DEFAULT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create wishlist table (if not exists)
CREATE TABLE IF NOT EXISTS wishlist (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_product (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify
SELECT 'users columns' AS check_name, COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
UNION ALL
SELECT 'saved_addresses', 'exists' FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saved_addresses'
UNION ALL
SELECT 'notifications', 'exists' FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications';
