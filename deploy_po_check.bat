@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/purchase_flow_api.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/pr_stage_lib.php admin/purchase_flow.php admin/includes/role_access.php pr_po_check_migration.sql pr_po_check_verify.sql pr_po_check_rollback.sql deploy_po_check.bat
git commit -m "L1 only quotations; Accounts: PO checked, then paid with proof"
git push
pause
