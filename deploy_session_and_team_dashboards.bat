@echo off
cd /d "%~dp0"
echo Staging changed files...
git add "assets\db_query\admin\role_dash_api.php"
git add "admin\assets\role_dash.js"
git add "admin\assets\header_ntf.js"
git add "deploy_session_and_team_dashboards.bat"
echo.
git commit -m "Fix: clear message when the admin login has ended; Executive and Manager dashboards show approvals, history and pipeline"
git push
echo.
echo Done. Press any key to close this window.
pause
