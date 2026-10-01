@echo off
cd /d "%~dp0"
echo Staging header notification files...
git add "admin\includes\sidebar.php"
git add "admin\assets\header_ntf.js"
git add "assets\db_query\admin\header_ntf_api.php"
git add "deploy_header_notifications.bat"
echo.
git commit -m "Header notification bell: approvals for each role, sound, red unread count, click opens the exact page"
git push
echo.
echo Done. Press any key to close this window.
pause
