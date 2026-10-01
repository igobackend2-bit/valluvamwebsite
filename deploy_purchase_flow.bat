@echo off
cd /d "%~dp0"
echo Staging purchase-flow files...
git add "purchase_flow_migration.sql"
git add "admin\purchase_flow.php"
git add "admin\purchase_requests.php"
git add "admin\print_erp.php"
git add "admin\includes\sidebar.php"
git add "admin\assets\erp.js"
git add "admin\assets\po_quotes.js"
git add "assets\db_query\admin\purchase_flow_api.php"
git add "assets\db_query\admin\pf_lib.php"
git add "assets\db_query\admin\pq_lib.php"
git add "assets\db_query\admin\po_quotes_api.php"
git add "assets\db_query\admin\purchase_api.php"
git add "assets\db_query\admin\approvals_api.php"
git add "assets\db_query\admin\erp_helper.php"
git add "deploy_purchase_flow.bat"
echo.
git commit -m "Purchase flow: units on requests, 3 shop quotations + approval, PO from approved shop, payment proof, courier/internal transport, DC and shop bill, unloading check, QC"
git push
echo.
echo Done. Press any key to close this window.
pause
