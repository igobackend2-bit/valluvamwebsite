@echo off
cd /d "%~dp0"
echo Staging competitor-quotation files...
git add "po_competitor_quotes_migration.sql"
git add "assets\db_query\admin\po_quotes_api.php"
git add "admin\assets\po_quotes.js"
git add "admin\purchase_orders.php"
git add "deploy_po_quotes.bat"
echo.
git commit -m "Purchase orders: compare up to 3 competitor quotations (Excel/CSV import, lowest price / fastest delivery highlights)"
git push
echo.
echo Done. Press any key to close this window.
pause
