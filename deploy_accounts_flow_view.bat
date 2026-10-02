@echo off
cd /d "%~dp0"
rem FIX (2 Oct 2026): Accounts Team sees only request, PO and payment steps in Purchase Flow
git add "admin/purchase_flow.php" "deploy_accounts_flow_view.bat"
git commit -m "Purchase flow: Accounts Team sees only request, PO and payment steps"
git push
pause
