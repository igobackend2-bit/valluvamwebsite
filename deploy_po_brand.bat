@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/purchase_orders.php admin/purchase_requests.php admin/print_erp.php admin/assets/erp.js admin/assets/erp.css images/logo-doc.png deploy_po_brand.bat
git commit -m "Purchase request / PO: Valluvam logo header and aligned document view"
git push
pause
