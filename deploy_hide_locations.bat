@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/sidebar.php admin/stock_operations.php deploy_hide_locations.bat
git commit -m "Admin menu: hide Warehouse Locations (not used) and the Location stock tab"
git push
pause
