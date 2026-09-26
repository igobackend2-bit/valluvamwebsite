@echo off
cd /d "%~dp0"
echo Staging changed files...
git add .htaccess
echo.
echo Committing...
git commit -m "Fix-product-URLs-for-any-category-not-just-the-original-7"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
