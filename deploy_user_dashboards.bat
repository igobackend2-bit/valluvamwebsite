@echo off
cd /d "%~dp0"
echo Staging dashboards-per-user files...
git add "user_dashboards_migration.sql"
git add "admin\includes\role_access.php"
git add "admin\includes\check_admin.php"
git add "admin\includes\sidebar.php"
git add "admin\admin_users.php"
git add "assets\db_query\admin\role_dash_api.php"
git add "assets\db_query\admin\user_dashboards_api.php"
git add "assets\db_query\admin\auth_helper.php"
git add "assets\db_query\admin\erp_helper.php"
git add "deploy_user_dashboards.bat"
echo.
git commit -m "Admin Users: Super Admin chooses dashboards per user (permissions and pages follow)"
git push
echo.
echo Done. Press any key to close this window.
pause
