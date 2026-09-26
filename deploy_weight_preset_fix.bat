@echo off
cd /d "%~dp0"
echo Staging changed files...
git add assets/js/product_detail/product_detail.js
echo.
echo Committing...
git commit -m "Fix product-detail price shown for sub-1kg packs (Honey/Ghee/Dal/Pulses/Seeds/Palm Jaggery) matching their own admin-entered price instead of an extrapolated 1kg estimate"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
