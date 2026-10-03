-- Only removes the two coupon columns added to orders (coupons and account_requests tables are kept with their data).
ALTER TABLE orders DROP COLUMN coupon_code, DROP COLUMN coupon_discount;
