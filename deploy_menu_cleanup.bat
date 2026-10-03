@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/includes/sidebar.php admin/stock_operations.php deploy_menu_cleanup.bat
git commit -m "Admin menu: remove repeated options (one place each for stock in, stock out, counts, receivables, P&L, reports, supplier ledger)"
git push
pause
