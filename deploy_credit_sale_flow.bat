@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/credit_sale.php admin/includes/role_access.php assets/db_query/admin/get_credit_sales.php assets/db_query/admin/get_credit_sale.php assets/db_query/admin/save_credit_sale.php assets/db_query/admin/record_credit_sale_payment.php assets/db_query/admin/accounting_engine.php assets/db_query/admin/accounting_api.php assets/db_query/admin/role_dash_api.php deploy_credit_sale_flow.bat
git commit -m "Credit sale: role-based actions, no overselling, no overpayment, accounts only after confirm, dashboards"
git push
pause
