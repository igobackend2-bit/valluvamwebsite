@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/manual_sales.php admin/includes/role_access.php assets/db_query/admin/get_manual_sales.php assets/db_query/admin/get_manual_sale.php assets/db_query/admin/save_manual_sale.php assets/db_query/admin/record_manual_sale_payment.php assets/db_query/admin/accounting_engine.php assets/db_query/admin/accounting_api.php assets/db_query/admin/role_dash_api.php deploy_manual_sales_flow.bat
git commit -m "Manual sales: role-based actions, no overselling, accounts only after confirm, record payment received, dashboards"
git push
pause
