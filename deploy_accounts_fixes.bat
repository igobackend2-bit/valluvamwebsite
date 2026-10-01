@echo off
cd /d "%~dp0"
git add "accounts_team_access_migration.sql" "assets\db_query\admin\record_invoice_payment.php" "assets\db_query\admin\record_credit_sale_payment.php" "admin\includes\role_access.php" "deploy_accounts_fixes.bat"
git commit -m "Accounts: record customer payments, credit-sale payment message fix, warehouse filter access"
git push
pause
