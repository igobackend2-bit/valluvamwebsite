-- Supplier shop-owner, payment and GST details. Run once in phpMyAdmin before
-- deploying the updated supplier / purchase-order screens. Do not run twice.
ALTER TABLE suppliers
  ADD COLUMN owner_name VARCHAR(150) DEFAULT NULL AFTER company_name,
  ADD COLUMN account_holder_name VARCHAR(150) DEFAULT NULL AFTER bank_details,
  ADD COLUMN bank_name VARCHAR(150) DEFAULT NULL AFTER account_holder_name,
  ADD COLUMN bank_account_number VARCHAR(34) DEFAULT NULL AFTER bank_name,
  ADD COLUMN bank_ifsc VARCHAR(11) DEFAULT NULL AFTER bank_account_number,
  ADD COLUMN upi_id VARCHAR(150) DEFAULT NULL AFTER bank_ifsc;
