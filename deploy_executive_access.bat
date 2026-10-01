@echo off
cd /d "%~dp0"
echo Staging changed files...
git add "executive_access_migration.sql"
git add "admin\includes\role_access.php"
git add "admin\stock_in.php"
git add "admin\assets\grn_stock.js"
git add "admin\assets\role_dash.js"
git add "assets\db_query\admin\grn_stock_api.php"
git add "assets\db_query\admin\role_dash_api.php"
git add "assets\db_query\admin\header_ntf_api.php"
git add "deploy_executive_access.bat"
echo.
git commit -m "Executive access to sales, stock, assets, waste, customers, suppliers; received purchases CSV to stock; waste approval for Manager; accounts KPIs"
git push
echo.
echo Done. Press any key to close this window.
pause
