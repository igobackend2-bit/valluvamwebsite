@echo off
cd /d "%~dp0"
echo Staging changed files...
git add "manager_access_migration.sql"
git add "admin\includes\role_access.php"
git add "admin\assets\role_dash.js"
git add "assets\db_query\admin\role_dash_api.php"
git add "deploy_manager_access.bat"
echo.
git commit -m "Manager: all Executive pages + approvals, audit trail, reports; stock adjustment/count/return approvals on the dashboard"
git push
echo.
echo Done. Press any key to close this window.
pause
