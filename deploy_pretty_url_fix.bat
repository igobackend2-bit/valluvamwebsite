@echo off
cd /d "%~dp0"
echo Staging changed files...
git add .htaccess productdetail.php
echo.
echo Committing...
git commit -m "Fix product-detail pretty URLs for any category, not just the original 7 (matches .htaccess fix)"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
