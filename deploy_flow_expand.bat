@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php deploy_flow_expand.bat
git commit -m "Purchase flow: expandable step cards"
git push
pause
