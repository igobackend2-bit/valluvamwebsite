@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add cart.php checkout.php profile.php assets/db_query/order/create.php assets/db_query/order/create_razorpay_order.php assets/db_query/order/payment/verify.php assets/db_query/admin/get_customers.php assets/db_query/admin/get_customer_details.php assets/db_query/admin/get_account_requests.php assets/db_query/admin/resolve_account_request.php assets/db_query/admin/get_leads.php assets/db_query/admin/update_lead_status.php assets/db_query/admin/get_reviews.php assets/db_query/admin/moderate_review.php assets/db_query/admin/get_feedback.php assets/db_query/admin/get_coupons.php assets/db_query/admin/save_coupon.php assets/db_query/admin/delete_coupon.php assets/js/cart/coupon.js assets/db_query/coupon_helper.php assets/db_query/cart/coupon_query.php assets/db_query/profile/account_request_query.php coupons_requests_migration.sql coupons_requests_verify.sql coupons_requests_rollback.sql deploy_coupons_requests.bat
git commit -m "Coupons work on the website (cart, checkout, COD, online), Account Requests from My Profile with Inbox reply, customer pages limited to their teams"
git push
pause
