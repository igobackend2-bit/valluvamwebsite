@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php deploy_qc_complete.bat
git commit -m "Purchase flow: complete the quality check inline (no longer stuck on pending)"
git push
pause
