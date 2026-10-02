@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_flow.php assets/db_query/admin/purchase_flow_api.php deploy_labour_charges.bat
git commit -m "Charges: persons x rate for loading/unloading, litres x rate for diesel, per-type fields"
git push
pause
