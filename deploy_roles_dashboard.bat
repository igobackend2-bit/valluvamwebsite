@echo off
cd /d "%~dp0"
echo Staging roles + dashboards...
git add "roles_dashboard_migration.sql"
git add "admin\includes\role_access.php"
git add "admin\includes\check_admin.php"
git add "admin\includes\sidebar.php"
git add "admin\index.php"
git add "admin\assets\role_dash.js"
git add "admin\purchase_requests.php"
git add "admin\purchase_orders.php"
git add "admin\purchase_flow.php"
git add "assets\db_query\admin\role_dash_api.php"
git add "assets\db_query\admin\purchase_api.php"
git add "assets\db_query\admin\procurement_api.php"
git add "assets\db_query\admin\purchase_flow_api.php"
git add "deploy_roles_dashboard.bat"
echo.
git commit -m "Roles (Executive, Team Manager, L1, Admin, CEO, Accounts Team), role dashboards with tasks and KPIs, approvals from dashboard, CEO approval for big POs"
git push
echo.
echo Done. Press any key to close this window.
pause
