@echo off
cd /d "%~dp0"
echo Staging CEO / Admin dashboard files...
git add "admin\includes\role_access.php"
git add "assets\db_query\admin\role_dash_api.php"
git add "admin\assets\role_dash.js"
git add "deploy_ceo_admin_dashboard.bat"
echo.
git commit -m "CEO and Admin dashboards: only their own menu, approvals waiting with approver, approval history, pipeline, transactions"
git push
echo.
echo Done. Press any key to close this window.
pause
