@echo off
cd /d "%~dp0"
echo ================================
echo GIT LOG for sidebar.php (last 3)
echo ================================
git log -3 --oneline -- admin\includes\sidebar.php
echo.
echo ================================
echo GIT DIFF for sidebar.php vs HEAD
echo ================================
git diff HEAD -- admin\includes\sidebar.php
echo.
echo ================================
echo Does sidebar.php STILL mention "Sales Orders"?
echo ================================
findstr /C:"Sales Orders" admin\includes\sidebar.php
echo (if nothing printed above, it's already gone from the file)
echo.
echo ================================
echo Does categories.php have the delete button code?
echo ================================
findstr /C:"delete-category" admin\categories.php
echo (if nothing printed above, the delete button code is missing)
echo.
echo ================================
echo Does delete_category.php file exist?
echo ================================
dir assets\db_query\admin\delete_category.php
echo.
echo ================================
echo GIT STATUS for these 3 files specifically
echo ================================
git status admin\includes\sidebar.php admin\categories.php assets\db_query\admin\delete_category.php
echo.
echo ================================
echo REMOTE URL
echo ================================
git remote -v
echo.
echo ================================
echo CURRENT BRANCH
echo ================================
git branch --show-current
echo.
echo Done. Press any key to close.
pause
