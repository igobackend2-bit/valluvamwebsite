@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php admin/includes/role_access.php deploy_l1_view.bat
git commit -m "L1 Sourcing: only sourcing pages and flow steps"
git push
pause
