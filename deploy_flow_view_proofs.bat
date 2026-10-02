@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php assets/db_query/admin/role_dash_api.php deploy_flow_view_proofs.bat
git commit -m "Purchase flow: close saved steps, open unloading, view attached proofs, unloading checker on dashboards"
git push
pause
