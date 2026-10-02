@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/grn_stock_api.php admin/assets/grn_stock.js admin/purchase_flow.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/pr_stage_lib.php exec_stock_in_permission_migration.sql exec_stock_in_permission_verify.sql exec_stock_in_permission_rollback.sql deploy_exec_stock_in.bat
git commit -m "Executive: QC then add to stock via Stock In sheet; mark Added to inventory completed"
git push
pause
