@echo off
cd /d "%~dp0"
git add "admin\includes\role_access.php" "assets\db_query\admin\role_dash_api.php" "admin\assets\role_dash.js" "deploy_accounts_dashboard.bat"
git commit -m "Accounts Team: right menu pages, approvals on dashboard, bills to enter, bills to pay, money to collect, transactions"
git push
pause
