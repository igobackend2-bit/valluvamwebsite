@echo off
cd /d "%~dp0"
echo Staging changed files...
git add new_product.php assets\js\new_product\quantity-picker.js
echo.
echo Committing...
git commit -m "Admin product form: Unit + Size dropdowns for Quantity with add/edit/delete sizes"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
