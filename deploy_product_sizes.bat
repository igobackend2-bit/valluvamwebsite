@echo off
cd /d "%~dp0"
echo Staging changed files...
git add new_product.php assets\js\new_product\quantity-picker.js assets\js\new_product\product-sizes.js assets\db_query\admin\product_sizes.php assets\js\product_detail\product_detail.js assets\db_query\product_detail\product_detail_query.php
echo.
echo Committing...
git commit -m "Admin: manage sizes per product (own price + discount); product page shows only those sizes"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
