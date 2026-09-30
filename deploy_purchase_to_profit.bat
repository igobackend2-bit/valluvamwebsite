@echo off
cd /d "%~dp0"
echo Staging purchase / costing / P^&L files...
git add "erp_purchase_migration.sql"
git add "assets\db_query\admin\erp_helper.php"
git add "assets\db_query\admin\costing_engine.php"
git add "assets\db_query\admin\purchase_api.php"
git add "assets\db_query\admin\inventory_ops_api.php"
git add "assets\db_query\admin\erp_docs.php"
git add "assets\db_query\admin\erp_reports.php"
git add "assets\db_query\admin\erp_report_lib.php"
git add "assets\erp_docs\.htaccess"
git add "assets\erp_docs\index.html"
git add "admin\includes\erp_page.php"
git add "admin\assets\erp.css"
git add "admin\assets\erp.js"
git add "admin\purchase_dashboard.php"
git add "admin\purchase_requests.php"
git add "admin\purchase_orders.php"
git add "admin\goods_receipts.php"
git add "admin\purchase_invoices.php"
git add "admin\purchase_payments.php"
git add "admin\purchase_returns.php"
git add "admin\purchase_history.php"
git add "admin\supplier_ledger.php"
git add "admin\raw_materials.php"
git add "admin\repacking.php"
git add "admin\sales_returns.php"
git add "admin\stock_adjustments.php"
git add "admin\expenses.php"
git add "admin\stock_valuation.php"
git add "admin\product_profitability.php"
git add "admin\profit_loss.php"
git add "admin\receivables.php"
git add "assets\db_query\admin\record_invoice_payment.php"
git add "assets\db_query\admin\save_credit_sale.php"
git add "assets\db_query\admin\record_credit_sale_payment.php"
git add "assets\db_query\admin\get_report.php"
git add "assets\db_query\admin\approve_waste.php"
git add "assets\db_query\admin\save_delivery_challan.php"
git add "assets\db_query\admin\adjust_stock.php"
git add "assets\db_query\admin\save_manual_sale.php"
git add "admin\includes\sidebar.php"
git add "admin\index.php"
git add "deploy_purchase_to_profit.bat"
echo.
echo Committing...
git commit -m "Admin: purchase-to-profit workflow (PO, GRN, bills, payments, returns, costing, P&L) + ledger/accounting fixes"
echo.
echo Pushing...
git push
echo.
echo Done. Press any key to close this window.
pause
