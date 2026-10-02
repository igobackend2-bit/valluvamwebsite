@echo off
cd /d "%~dp0"
rem STOCK LIFECYCLE (2 Oct 2026): loading + machine weight, transport, unloading, QC, stock in, stock out, damage, returns, daily stock, monthly audit, executive handover
rem Run stock_lifecycle_migration.sql once in HeidiSQL BEFORE or right after this push (pages show a message until it is run).
git add "stock_lifecycle_migration.sql" "stock_lifecycle_verify.sql" "stock_lifecycle_rollback.sql" "deploy_stock_lifecycle.bat"
git add "assets/db_query/admin/stock_flow_api.php" "assets/db_query/admin/erp_ext.php" "assets/db_query/admin/approvals_api.php"
git add "admin/stock_flow.php" "admin/stock_operations.php" "admin/daily_stock.php" "admin/stock_audit.php" "admin/stock_handover.php"
git add "admin/includes/stock_flow_common.php" "admin/includes/role_access.php" "admin/includes/sidebar.php"
git commit -m "Stock lifecycle: loading check + weights, transport, unloading, QC, stock in/out, damage, returns, daily stock, monthly audit, executive handover"
git push
pause
