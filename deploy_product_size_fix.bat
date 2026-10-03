@echo off
cd /d "%~dp0"
echo Staging changed files...
git add assets\js\product_detail\product_detail.js assets\db_query\product_detail\product_detail_query.php
echo.
echo Committing...
git commit -m "Product page: remove duplicate Select Weight calculator, show only admin-created sizes with their own price and discount"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
