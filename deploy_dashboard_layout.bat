@echo off
cd /d "%~dp0"
echo Staging dashboard layout files...
git add "admin\includes\sidebar.php"
git add "admin\assets\role_dash.js"
git add "admin\assets\admin_mobile.js"
git add "assets\db_query\admin\role_dash_api.php"
git add "deploy_dashboard_layout.bat"
echo.
git commit -m "Role dashboards: professional layout, grouped KPIs, side-by-side lists, phone menu"
git push
echo.
echo Done. Press any key to close this window.
pause
