-- ============================================================================
-- Coupons that work on the website + Account Requests from My Profile (3 Oct 2026)
-- Additive only: creates missing tables / adds missing columns. Nothing is changed or deleted.
-- Safe to run more than once.
-- ============================================================================
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    description VARCHAR(255) NULL,
    discount_type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_uses INT NULL,
    used_count INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coupon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- if the coupons table already existed without these columns, add them
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coupons' AND COLUMN_NAME = 'used_count');
SET @s := IF(@c = 0, 'ALTER TABLE coupons ADD COLUMN used_count INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coupons' AND COLUMN_NAME = 'is_active');
SET @s := IF(@c = 0, 'ALTER TABLE coupons ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

-- the coupon used on each website order
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'coupon_code');
SET @s := IF(@c = 0, 'ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(40) NULL, ADD COLUMN coupon_discount DECIMAL(10,2) NULL', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

-- customers' account requests (also created automatically on the first request)
CREATE TABLE IF NOT EXISTS account_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_type VARCHAR(30) NOT NULL,
    details TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_notes VARCHAR(500) NULL,
    resolved_by VARCHAR(100) NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ar_user (user_id), KEY idx_ar_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
