@echo off
cd /d "%~dp0"
git add "assets\db_query\admin\purchase_api.php" "deploy_advance_to_bill.bat"
git commit -m "Purchase bill uses the PO advance paid in the purchase flow (no double payment)"
git push
pause
