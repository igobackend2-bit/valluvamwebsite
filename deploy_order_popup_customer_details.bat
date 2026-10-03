@echo off
cd /d "%~dp0"
echo Staging changed files...
git add admin\orders.php assets\db_query\admin\get_order_items.php
echo.
echo Committing...
git commit -m "Admin orders popup: show customer name, email, phone, delivery address and payment details"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
