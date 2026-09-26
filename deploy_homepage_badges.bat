@echo off
cd /d "%~dp0"
echo Staging changed files...
git add css\home-redesign.css index.php assets\js\index\index.js
echo.
echo Committing...
git commit -m "Upgrade-homepage-trust-badges-and-add-New-product-badge"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
