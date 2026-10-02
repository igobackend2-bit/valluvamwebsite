@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/purchase_flow_api.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/header_ntf_api.php assets/db_query/admin/approvals_api.php assets/db_query/admin/pr_stage_lib.php admin/purchase_flow.php admin/rfqs.php pr_quote_manager_migration.sql pr_quote_manager_verify.sql pr_quote_manager_rollback.sql deploy_quote_manager.bat
git commit -m "Shop quotation: Manager approves first, then Admin; PO by Admin; then Accounts"
git push
pause
