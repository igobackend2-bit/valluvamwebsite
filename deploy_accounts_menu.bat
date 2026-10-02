@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/role_access.php deploy_accounts_menu.bat
git commit -m "Accounts Team: only accounts and payment pages"
git push
pause
