# Valluvam Admin — Purchase → Profit: Implementation Report
30 Sep 2026 · Built on top of the approved plan (`valluvam_purchase_to_profit_audit_and_plan.md`). Decisions you made: buy **both** ready packs and bulk (repacking), **fix all defects**, **keep the cash-book** accounting, **Phase 1 + 2**.

## 1. Database — run ONCE before using the new pages
File: `erp_purchase_migration.sql` (project root; blocked from the web by .htaccess).
phpMyAdmin → select the Valluvam database → **SQL** tab → paste the whole file → **Go**.
It uses only `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`. There is no ALTER, DROP, UPDATE or DELETE on any existing table, and it is safe to run twice (tested).

New tables: raw_materials, raw_material_movements, purchase_requests(+items), purchase_orders(+items), goods_receipts(+items), inventory_batches, inventory_cost_entries, purchase_invoices(+items, +charges), purchase_payments, purchase_returns(+items), repack_jobs(+outputs), sales_returns(+items), stock_adjustments, erp_documents. It also adds 15 permission keys (plus the missing `credit_sales.*` keys), granted to Super Admin and Manager, with a smaller set for Staff.

## 2. How it connects (nothing rebuilt)
Vendor → Purchase Request → **Purchase Order** (no stock change) → **Goods Receipt** (accepted qty only; posts a *completed* record in the EXISTING Stock In + Stock Movement; rejected qty never enters stock; batch/lot/mfg/expiry kept) → **Purchase Invoice** (vendor bill; freight/loading/etc. spread into landed cost; duplicate bill no. per vendor blocked) → **Payment** (can't exceed the balance; also written to the EXISTING Accounts → Transactions) → **Supplier Ledger** (payable = bills − payments − credit notes + refunds).
Bulk: GRN into a **Raw Material** (kg/L) → **Repacking** → product packs via Stock In, with cost = consumed kg × average cost + packing cost.
Selling uses the EXISTING Sales Orders / DC / Manual / Credit / Invoices / Website orders unchanged.

**Costing:** `costing_engine.php` replays the EXISTING stock ledger (weighted average). Every outflow, from whichever existing module, is valued at the running average cost. That gives COGS, stock valuation, profitability and P&L without touching any sales code. Historical costs are never overwritten.

## 3. New pages (sidebar)
Purchases: Purchase Dashboard, Purchase Requests, Purchase Orders, Goods Receipts, Purchase Invoices, Purchase Returns, Purchase Payments, Purchase History (reports by date/vendor/product/category/warehouse, price history, returns).
Inventory: Raw Materials, Repacking, Stock Adjustments (request → approve), Stock Valuation (valuation, batches & expiry, opening cost, ledger check).
Sales: Sales Returns. Accounts: Expenses (saved as Transactions of type expense), Receivables. Suppliers: Supplier Ledger. Reports: Profit & Loss, Product Profitability.
Dashboard: one new row of KPI cards (net sales, purchases, inventory value, receivables, payables, gross profit, expenses, net P/L, low stock, pending POs).
Every PO / GRN / bill / payment / return / sales return / expense / supplier / repack has a **Documents** panel (vendor invoice, receipt, DC, e-way bill, QC, payment proof, credit note…). Files are stored in `assets/erp_docs/`, which the web can't reach directly; they are served only to logged-in admins.

## 4. Changed existing files (exact)
| File | Why | Preserved |
|---|---|---|
| admin/includes/sidebar.php | +21 lines: new links only | every existing link, order, style |
| admin/index.php | +33 lines: one hidden-until-loaded KPI row + its script | all existing cards, charts, tables |
| record_invoice_payment.php (D1) | accounts insert lacked required transaction_id/date/category → payments never reached Transactions | payment logic unchanged |
| record_credit_sale_payment.php, save_credit_sale.php (D1, D9) | same insert fix; credit-sale stock movement sign made negative | sale/payment logic unchanged |
| get_report.php (D2, D7) | expense/income reports used non-existent columns; supplier-payable & customer-outstanding now real | all other reports unchanged |
| approve_waste.php (D3) | stock-movement insert lacked required columns → waste missing from ledger | approval + stock deduction unchanged |
| save_delivery_challan.php (D4) | dispatch now creates a real Stock Out (it silently failed). Skipped if a Stock Out already references the DC, or if stock is short (DC still saves) | DC save/edit rules unchanged |
| adjust_stock.php (D5) | now needs the existing `inventory.adjust` permission; writes the ledger | adjustment behaviour unchanged |
| save_manual_sale.php (D8) | deduction now writes the ledger; legacy stock_history write can't fail the sale any more | two-step confirm unchanged |

## 5. Testing done (real MySQL/MariaDB, strict mode, fresh database)
24/24 automated checks for Tests 1–8, matching hand calculations to the paisa: PO no stock change; over-receipt blocked; only accepted qty stocked; bill with freight → landed ₹634/unit; payment → outstanding; weighted average ₹671.71 after 2 prices; COGS of 60 packs; purchase return → stock & payable; sales return → only good stock back + refund; waste → stock + ledger; expense → P&L; monthly P&L and inventory roll-forward reconcile exactly; ledger variance zero.
Also tested: PR → approve → PO, bulk GRN (decimal kg) → repack (unit cost 370.43 per 500g pack), permissions (Staff blocked from approvals and stock edits), payment cancel reverses Transactions, bill with payments can't be cancelled, DC dispatch once / short stock, credit & manual sale deductions, document upload/download over HTTP.
Browser: all 18 new pages + dashboard load with no JS errors. Existing pages whose endpoints were available in the test copy load with no errors.

## 6. Files intentionally not changed
Website/storefront (all), products, categories, homepage, customers, leads, reviews, feedback, coupons, account requests, assets, warehouses, suppliers page, stock in/out pages, sales orders, invoices pages, credit/manual sale pages, transactions page, audit logs, admin users, settings, login/auth, admin.css.

## 7. Known limits & assumptions
- **GST on purchases is treated as recoverable (input credit)**, so cost excludes GST. If not, add admin_settings key `erp_tax_in_cost` = 1.
- **Sales don't record which batch was sold**, so batch remaining qty is estimated (oldest sold first).
- **Invoices don't deduct stock** (existing design). If an invoice has no Stock Out/DC, its COGS is *estimated* at average cost and flagged in P&L.
- **Opening stock that existed before this has no cost.** Set it once in Stock Valuation → Opening cost.
- **Only stock-ledger changes are costed.** Any past change made outside the ledger shows in Stock Valuation → Ledger check.
- **Posting date:** the cost timeline uses the posting time (when the GRN or bill was posted), not a back-dated document date.
- **Document files must survive redeploys.** Docker rebuilds replace the app folder, so `assets/erp_docs/` (like the existing `assets/uploads/`) needs persistent storage on the host. Please confirm how uploads survive a deploy today.
- **Mobile layout:** the existing admin has no mobile sidebar, and that wasn't changed. New pages use the same responsive tables and grids.
- **Expense categories:** don't record stock purchases as expenses (P&L warns if a category looks like one).

## 8. Deploy
1. Run `erp_purchase_migration.sql` in phpMyAdmin (step 1).
2. Double-click `deploy_purchase_to_profit.bat` (it adds only the files listed in it) — or run the commands in chat.
3. Log in to admin → Ctrl+Shift+R → Purchases → Purchase Dashboard.
