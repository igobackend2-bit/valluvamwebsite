@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_requests.php assets/db_query/admin/purchase_api.php assets/db_query/admin/pr_stage_lib.php deploy_pr_stage.bat
git commit -m "Purchase requests: Other location, tick-box item picker, exact live stage in status"
git push
pause
