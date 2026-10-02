@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/purchase_flow_api.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/pr_stage_lib.php assets/db_query/admin/stock_flow_api.php admin/purchase_flow.php admin/stock_audit.php sf_audit_signoff_migration.sql sf_audit_signoff_verify.sql sf_audit_signoff_rollback.sql exec_qc_permission_migration.sql exec_qc_permission_verify.sql exec_qc_permission_rollback.sql deploy_qc_audit.bat
git commit -m "L1 buyer unloads; transport tracking on dashboards; Executive QC; monthly auditor sign-off"
git push
pause
