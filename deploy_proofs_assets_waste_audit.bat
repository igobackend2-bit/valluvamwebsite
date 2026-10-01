@echo off
cd /d "%~dp0"
echo Staging changed files...
git add "purchase_flow_extras_migration.sql"
git add "admin\assets\po_quotes.js"
git add "admin\purchase_orders.php"
git add "admin\rfqs.php"
git add "admin\purchase_flow.php"
git add "admin\assets\pf_extra.js"
git add "admin\assets\page_plus.js"
git add "admin\includes\sidebar.php"
git add "admin\includes\role_access.php"
git add "assets\db_query\admin\po_quotes_api.php"
git add "assets\db_query\admin\pf_extra_api.php"
git add "assets\db_query\admin\page_plus_api.php"
git add "deploy_proofs_assets_waste_audit.bat"
echo.
git commit -m "PO shows chosen shop, comparison on RFQ page; delivery photos, bill qty, signature, QC report, all proofs; assets assign; waste approval; readable audit log; profile chip"
git push
echo.
echo Done. Press any key to close this window.
pause
