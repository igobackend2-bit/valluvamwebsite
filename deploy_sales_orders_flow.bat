@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/sales_orders.php admin/includes/role_access.php assets/db_query/admin/get_sales_orders.php assets/db_query/admin/get_sales_order.php assets/db_query/admin/save_sales_order.php assets/db_query/admin/cancel_sales_order.php assets/db_query/admin/save_delivery_challan.php assets/db_query/admin/sales_ext_api.php assets/db_query/admin/role_dash_api.php deploy_sales_orders_flow.bat
git commit -m "Sales orders: role-based actions, DC and fulfilment move the order status, safe cancel with invoice, DC/invoice columns, dashboards"
git push
pause
