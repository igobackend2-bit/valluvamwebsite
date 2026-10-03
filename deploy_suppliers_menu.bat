@echo off
REM FIX (3 Oct 2026): one Suppliers menu option; Supplier 360 opens from each supplier row
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/suppliers.php admin/includes/sidebar.php deploy_suppliers_menu.bat
git commit -m "Suppliers: one menu option, Supplier 360 button per supplier"
git push
pause
