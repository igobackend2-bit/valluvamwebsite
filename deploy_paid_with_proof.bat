@echo off
cd /d "%~dp0"
git add "assets\db_query\admin\role_dash_api.php" "admin\assets\role_dash.js" "assets\db_query\admin\header_ntf_api.php" "deploy_paid_with_proof.bat"
git commit -m "Paid purchases with payment proof on Executive, Manager, Admin, CEO dashboards and bell"
git push
pause
