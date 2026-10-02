@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add assets/db_query/admin/purchase_flow_api.php assets/db_query/admin/role_dash_api.php assets/db_query/admin/pr_stage_lib.php admin/purchase_flow.php deploy_l1_buyer.bat
git commit -m "Purchase flow: the L1 who got the quotation buys the goods"
git push
pause
