@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php assets/db_query/admin/purchase_flow_api.php assets/db_query/admin/role_dash_api.php deploy_flow_stages.bat
git commit -m "Purchase flow: waiting for loading / unloading stages and counts"
git push
pause
