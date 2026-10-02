@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/warehouse_stock.php assets/db_query/admin/warehouse_flow_api.php deploy_warehouse_flow.bat
git commit -m "Stock by Warehouse: coming, unloaded (not in stock yet), transfers, latest movements"
git push
pause
