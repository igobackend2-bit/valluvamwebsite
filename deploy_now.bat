@echo off
cd /d "%~dp0"
echo Staging changed files...
git add admin\includes\sidebar.php admin\categories.php assets\db_query\admin\delete_category.php
echo.
echo Committing...
git commit -m "Update admin menu and categories"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
