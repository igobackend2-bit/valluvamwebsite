@echo off
cd /d "%~dp0"
echo Staging changed files...
git add assets\db_query\product_detail\submit_review.php assets\db_query\product_detail\get_product_reviews.php assets\db_query\order\submit_feedback.php assets\db_query\order\get_order_feedback_status.php assets\db_query\product_detail\product_detail_query.php assets\js\product_detail\product_detail.js assets\js\order_tracking\order_tracking.js assets\db_query\order\create.php assets\db_query\admin\get_inventory_overview.php admin\inventory_overview.php admin\reviews.php assets\db_query\admin\moderate_review.php
echo.
echo Committing...
git commit -m "Add-product-reviews-delivery-feedback-and-stock-availability"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
