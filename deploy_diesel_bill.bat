@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/purchase_flow_api.php admin/purchase_flow.php transport_charge_route_migration.sql transport_charge_route_verify.sql deploy_diesel_bill.bat
git commit -m "Transport charges: courier charge for courier, diesel bill with route for internal vehicle"
git push
pause
