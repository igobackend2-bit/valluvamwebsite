-- Creates the orders / order_items tables expected by:
--   assets/db_query/order/create.php
--   assets/db_query/order/create_razorpay_order.php
--   assets/db_query/order/payment/verify.php
--   assets/db_query/order/get_user_orders.php
--   assets/db_query/admin/get_orders.php, update_order_status.php, dashboard_stats.php
-- Safe to re-run: uses IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt VARCHAR(64) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  state VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  street_address VARCHAR(255) NOT NULL,
  apartment VARCHAR(255) DEFAULT '',
  postcode VARCHAR(20) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  amount_paise INT UNSIGNED NOT NULL,
  payment_method VARCHAR(10) NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  order_status VARCHAR(50) NOT NULL DEFAULT 'ordered',
  razorpay_order_id VARCHAR(64) DEFAULT NULL,
  razorpay_payment_id VARCHAR(64) DEFAULT NULL,
  razorpay_signature VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_receipt (receipt),
  KEY idx_email (email),
  KEY idx_razorpay_order_id (razorpay_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  KEY idx_order_id (order_id),
  KEY idx_product_id (product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES product_details(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
