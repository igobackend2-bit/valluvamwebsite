@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/purchase_flow_api.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/header_ntf_api.php admin/purchase_flow.php admin/includes/sidebar.php admin/assets/upload_preview.js transport_charge_migration.sql transport_charge_verify.sql transport_charge_rollback.sql deploy_courier_charges.bat
git commit -m "Preview before upload; courier charges with bill, Manager/Admin approval, Accounts pays"
git push
pause
