@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/role_access.php assets/db_query/admin/purchase_api.php admin/purchase_orders.php admin/purchase_flow.php deploy_l1_po.bat
git commit -m "L1 buyer: own purchase orders in menu; after transport go to loading step"
git push
pause
