@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/rfqs.php admin/purchase_flow.php deploy_rfq_waiting.bat
git commit -m "RFQ & Quotations: requests waiting for quotations, click to provide quotation"
git push
pause
