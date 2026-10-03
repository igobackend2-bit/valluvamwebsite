# Valluvam Admin — Purchase → Profit: Audit & Implementation Plan
30 Sep 2026 · Status: **AUDIT + PLAN ONLY — no code has been changed.** Waiting for approval.

Stack (actual, verified in code): plain PHP 8.2 + MySQL (PDO), jQuery + SweetAlert2 admin UI, `admin/*.php` pages + `assets/db_query/admin/*.php` JSON endpoints. There is **no Supabase / Firebase / React / hooks / state library**, so those parts of the brief don't apply.

---

## 1. Existing feature map (verified)

| Area | Page | Endpoints | Tables | Status |
|---|---|---|---|---|
| Auth / roles | admin/login.php, includes/check_admin.php | admin/login.php, auth_helper.php (`require_admin_session`, `require_permission`, `log_audit`, `next_document_number`) | admin_users, admin_roles, admin_permissions, admin_role_permissions | Working. Role-based permission keys (`module.action`). Super Admin (role 1) passes everything. |
| Audit log | audit_logs.php | get_audit_logs.php, `log_audit()` | audit_logs | Working. Will be reused. |
| Doc numbering | — | `next_document_number()` | document_sequences | Working (PREFIX-YYYY-NNNNNN). Will be reused. |
| Products | products.php, /new_product.php | new_product_query.php, get_products.php, product_sizes.php | product_details (`stock` INT = sellable packs, `min/max/reorder_level`) | Working. **Stock is one global number per product (not per warehouse).** Each pack size is its own product row. |
| Stock In | stock_in.php, print_stock_in.php | save_stock_in.php, complete_stock_in.php, get_stock_ins.php, import_stock.php | stock_ins (supplier_id, purchase_reference, warehouse_id, status draft→completed), stock_in_items (qty, batch_number, mfg, expiry, **purchase_rate**) | Working. Stock increases only on **Complete**, and a `stock_movements` row is written. Purchase rate is stored but not used for any costing. `attachment_note` is free text (no upload). |
| Stock Out | stock_out.php | save_stock_out.php | stock_outs, stock_out_items | Working. Deducts stock and writes stock_movements. |
| Stock ledger | stock_movements.php | get_stock_movements.php | stock_movements (signed qty, previous/new stock, reference) | Working. This is the inventory ledger. |
| Inventory | inventory_overview.php, inventory.php | get_inventory_overview.php, mark_out_of_stock.php, adjust_stock.php | — | Working. |
| Warehouses | warehouses.php | save/get_warehouses.php | warehouses | Working (master data; stock is not split per warehouse). |
| Suppliers | suppliers.php | save/get/delete_supplier.php | suppliers (name, company, mobile, email, address, **gst_number, payment_terms, bank_details**) | Working. No purchase history, payable or documents. |
| Sales Orders / DC | sales_orders.php, delivery_challans.php | save_sales_order.php, save_delivery_challan.php, invoice_helper.php | sales_orders(+items), delivery_challans(+items) | Working. An invoice can be auto-generated from an SO. |
| Invoices | invoices.php, print_invoice.php | save_invoice, record_invoice_payment, cancel_invoice | invoices (grand_total, amount_paid, status), invoice_items | Working. **Invoices do not deduct stock.** |
| Manual Sales | manual_sales.php | save_manual_sale.php | manual_sales(+items), `stock_deducted` flag | Working. Stock is deducted by a separate "deduct" action. |
| Credit Sale | credit_sale.php | save_credit_sale, record_credit_sale_payment, credit_sale_helper | credit_sales, credit_sale_items, credit_sale_payments (created at runtime) | Working. Customer receivable per credit sale. |
| Website Orders | orders.php | order/create.php, order/payment/verify.php | orders, order_items | Working. Stock is deducted when the order is placed (COD) or paid (Razorpay). |
| Transactions (Accounts) | accounts.php | save/get/cancel_accounts_transaction.php | accounts_transactions (type income/expense/payment_received/payment_made/refund/adjustment, category, party, amount, mode, account, status) | Working. **This is a cash-book, not double-entry.** Expenses already fit here (type `expense` + category). |
| Waste | waste.php | save_waste_record, approve_waste | waste_records (type, product, qty, warehouse, reason, estimated_value, status) | Working. Stock is deducted on approval. |
| Assets | assets.php, asset_detail.php | save/get/assign/maintenance | assets, asset_assignments, asset_maintenance | Working. Not touched. |
| Reports | reports.php | get_report.php (sales, invoices, payments, inventory, stock in/out/movement, warehouse, expense, income, asset) | — | Partly working (see bugs below). No purchase, P&L, COGS or valuation reports. |
| Dashboard | index.php | dashboard_stats.php, get_dashboard_alerts.php | — | Working. No purchase, payable, profit or stock-value KPIs. |
| Customers / Leads / Reviews / Feedback / Coupons / Account Requests / Homepage / Settings / My Account / Admin Users | as named | as named | as named | Working. **Not touched by this plan.** |
| File upload | — | Only product images (new_product_query.php) and homepage images | — | **No document upload infrastructure** exists for invoices or bills. |

## 2. Missing (verified absent in code and schema)
Purchase Requests, Purchase Orders, GRN (with accepted/rejected qty), Purchase Invoices (vendor bills), Purchase Payments, Supplier payable/ledger, Purchase Returns / Credit Notes, Document attachments, Batch/lot *availability* tracking, Landed cost, Inventory costing (weighted average), COGS, Stock valuation, Sales Returns, Stock Adjustment page with reasons + approval (ledgered), Product profitability, P&L, Purchase reports, Management KPIs.

## 3. Existing silent defects found during the audit (not changed — need your approval)
These matter because the new reports would inherit wrong numbers.

| # | File | Problem | Effect today |
|---|---|---|---|
| D1 | record_invoice_payment.php, save_credit_sale.php, record_credit_sale_payment.php | Insert into `accounts_transactions` without the required `transaction_id`, `date`, `category` | Customer payments **never reach Transactions** (the error is swallowed). |
| D2 | get_report.php (expense_report, income_report) | Uses columns `transaction_type`, `transaction_date`, but the real columns are `type`, `date` | Expense and Income reports always say "not installed". |
| D3 | approve_waste.php | stock_movements insert is missing the required `previous_stock`/`new_stock` | Stock is deducted, but the waste **never appears in the Stock Movement ledger**. |
| D4 | save_delivery_challan.php | Inserts `product_id`/`quantity` into `stock_outs`, which has no such columns | "Dispatched" DC never creates a Stock Out (fails silently). |
| D5 | adjust_stock.php | Writes to a `stock_history` table instead of `stock_movements`, and has no permission check | Adjustments aren't in the ledger. |
| D6 | credit_sale / save_credit_sale.php | Uses `credit_sales.*` permissions that were never seeded | Only Super Admin can use Credit Sale. |
| D7 | get_report.php | customer_outstanding / supplier_payable reports are placeholders | Empty. |

## 4. Design decisions & conflicts with the brief (need your answer)
1. **Units (biggest question).** Stock is counted in *packs of each product row* (e.g. "Cashew 500g" = 1 unit). Vendors usually sell in **kg**. Either:
   a) buy directly in the product's own packs (simple, fits today's stock), or
   b) buy **bulk raw material in kg**, then a **Repacking/Production** step converts kg into packs. This needs a raw-material item and a repack module (bigger).
2. **Accounting depth.** The system has a cash-book (Transactions), not a double-entry ledger. Proposal: keep it. Purchase payments post as `payment_made`, expenses stay `expense`, and receivables/payables/COGS/stock value are calculated from source documents. The Dr/Cr entries in the brief would need a new journal table (a larger, separate step).
3. **Warehouse stock.** Stock is global per product. Proposal: keep it (the website depends on it). Per-warehouse and per-batch quantities are kept in a new batch table for GRN-received stock only.
4. **COGS without touching sales code.** Proposal: one central **costing engine** that replays `stock_movements` in date order. Inflows are valued at GRN landed cost (or Stock In purchase_rate), and every outflow (website, manual, credit, stock out, waste, return) is valued at the running **weighted average**. This means no existing sales file needs changing. P&L uses the period formula (Opening + Purchases + Direct costs − Returns − Closing). Products with stock but no known cost need a one-time **opening cost** entry.
5. **Revenue sources.** Invoices + Manual Sales + Credit Sales + Website Orders (paid, non-cancelled). Sales Orders are counted only through their invoices, to avoid double counting.

## 5. Proposed architecture (new, additive)

### Database — one migration file, `CREATE TABLE IF NOT EXISTS` only, no ALTER/DROP on existing tables
- `purchase_requests`, `purchase_request_items`
- `purchase_orders`, `purchase_order_items` (status draft/pending_approval/approved/partially_received/fully_received/cancelled/closed)
- `goods_receipts`, `goods_receipt_items` (ordered/received/accepted/rejected qty, rate, batch, lot, mfg, expiry, QC status, rejection reason, `stock_in_id` link)
- `purchase_invoices`, `purchase_invoice_items`, `purchase_invoice_charges` (freight/transport/loading/unloading/packaging/other)
- `purchase_payments`
- `purchase_returns`, `purchase_return_items` (with credit-note number/amount)
- `inventory_batches` (product, warehouse, batch, lot, mfg, expiry, supplier, GRN item, qty received/available, landed unit cost)
- `product_opening_costs` (one-time opening cost per product)
- `sales_returns`, `sales_return_items` (restocked vs damaged qty)
- `stock_adjustments` (reason, qty, approval)
- `erp_documents` (entity type/id, doc type, file path, uploaded by). Files go in `assets/purchase_docs/`, where PHP execution is blocked.
- New permission keys via `INSERT IGNORE` (purchase.view/create/edit/approve, grn.create/approve, purchase_invoice.view, purchase_payment.create, purchase_return.create, sales_return.create, stock_adjust.create/approve, expense.manage, pnl.view), granted to Super Admin and Manager.

### Reused, not rebuilt
The GRN **posts through the existing Stock In tables** (creates a completed `stock_ins` record + items + `stock_movements`, same SQL as complete_stock_in.php) → Stock In, Stock Movement and Inventory pages show purchases automatically. The existing Suppliers, Transactions (expenses), Audit Logs, permissions, document numbers, admin.css and sidebar layout are all reused.

### New files (approx.)
- Pages (admin/): purchase_dashboard, purchase_requests, purchase_orders, goods_receipts, purchase_invoices, purchase_payments, purchase_returns, purchase_history, supplier_ledger, sales_returns, stock_adjustments, stock_valuation, product_profitability, profit_loss, purchase_reports
- Endpoints (assets/db_query/admin/): one per module (save/get/approve/cancel), `erp_documents.php`, `costing_engine.php` (all financial math centralised, DECIMAL/rounded to paise), `purchase_helper.php`
- `erp_purchase_migration.sql`

### Existing files that would change (minimal)
| File | Change | Preserved |
|---|---|---|
| admin/includes/sidebar.php | Add a "PURCHASES" group + new report links | All current links, order, styling |
| admin/index.php | *(optional, phase 2)* add KPI cards | Existing cards/charts |
| D1–D7 files | *Only if you approve* the bug fixes above | Everything else in those files |

## 6. Phasing (recommended)
- **Phase 1:** migration, documents, PR → PO (approval) → GRN (accept/reject, batches, posts to Stock In) → Purchase Invoice (landed cost) → Payments → Supplier ledger/outstanding → Purchase Returns/credit notes → Purchase history and reports.
- **Phase 2:** costing engine, stock valuation, COGS, product profitability, sales returns, stock adjustments, expense report, P&L, dashboard KPIs.
- Each phase ends with a test run on a copy of the schema (sample data covering Tests 1–8) and a regression check of every existing module.

## 7. What I need from you
1. Units: packs (a) or bulk kg + repacking (b)?
2. Approve fixing defects D1–D7? (each is a small, isolated fix)
3. Accounting: keep cash-book integration (recommended) or also add a double-entry journal?
4. Approve Phase 1 to start?
5. Please export the **live database structure** (phpMyAdmin → Export → "Structure only") and share it, so the migration matches the real tables. The local SQL files may differ from production.
