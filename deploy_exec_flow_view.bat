@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php deploy_exec_flow_view.bat
git commit -m "Purchase flow: Executive sees only request, transport (read), unloading, quality check"
git push
pause
