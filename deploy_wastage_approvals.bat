@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/sidebar.php admin/stock_operations.php admin/assets/page_plus.js admin/approvals.php admin/warehouse_stock.php assets/db_query/admin/approve_waste.php assets/db_query/admin/get_waste_records.php assets/db_query/admin/erp_ext.php assets/db_query/admin/approvals_api.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/stock_flow_api.php assets/db_query/admin/warehouse_api.php assets/db_query/admin/purchase_flow_api.php wastage_approval_migration.sql wastage_approval_verify.sql wastage_approval_rollback.sql deploy_wastage_approvals.bat
git commit -m "Approvals with proof: wastage and damage (Manager then Admin), expired stock disposal (Manager, Admin, CEO); purchase proofs before unloading; menu icons"
git push
pause
