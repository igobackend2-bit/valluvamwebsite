@echo off
REM FIX (3 Oct 2026): ERP Reports only shows reports the user can run; menu link hidden for teams with none; password error text
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/erp_reports_center.php admin/includes/sidebar.php admin/includes/role_access.php assets/db_query/admin/change_own_password.php deploy_reports_admin_menu.bat
git commit -m "Reports & admin menu: ERP Reports per permission, safer password error"
git push
pause
