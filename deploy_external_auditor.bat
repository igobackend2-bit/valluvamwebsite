@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/role_access.php assets/db_query/admin/stock_flow_api.php admin/stock_audit.php assets/db_query/admin/role_dash_api.php external_auditor_migration.sql external_auditor_verify.sql external_auditor_rollback.sql deploy_external_auditor.bat
git commit -m "External Auditor role + dashboard; audit reports on Manager/Admin/CEO dashboards"
git push
pause
