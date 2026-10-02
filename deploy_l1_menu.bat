@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/sidebar.php admin/purchase_flow.php assets/db_query/admin/purchase_flow_api.php deploy_l1_menu.bat
git commit -m "L1 menu: Loading and Unloading for own purchases"
git push
pause
