@echo off
cd /d "%~dp0"
echo Staging changed files...
git add "admin\includes\role_access.php"
git add "deploy_role_permission_pages.bat"
echo.
git commit -m "Fix: permissions ticked for a role now open their pages and menu links"
git push
echo.
echo Done. Press any key to close this window.
pause
