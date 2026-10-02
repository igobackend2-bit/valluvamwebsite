@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/raw_materials.php assets/db_query/admin/rm_flow_api.php deploy_raw_materials_flow.bat
git commit -m "Raw materials: received, used in repacking, packs made, repacking history"
git push
pause
