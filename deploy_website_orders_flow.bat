@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/orders.php admin/stock_out.php admin/includes/role_access.php assets/db_query/admin/get_orders.php assets/db_query/admin/get_order_items.php assets/db_query/admin/update_order_status.php assets/db_query/admin/get_stock_outs.php assets/db_query/admin/export_stock_outs.php assets/db_query/admin/sales_ext_api.php assets/db_query/admin/role_dash_api.php deploy_website_orders_flow.bat
git commit -m "Website orders: roles, cancel puts stock back, COD collected, unpaid online orders locked; Stock Out: website orders shown, no duplicates, CSV/Excel download by week/month"
git push
pause
