# Valluvam — Complete ERP: Audit, Gap Analysis & Plan
30 Sep 2026 · **Audit only: no code or database was changed for this report.**
This builds on the Purchase → Profit release that went live today (commit `7f183eb`, migration run on `valluvam-db`, MySQL 8.4.11).

---

## 0. How the system works today (facts the plan depends on)

| Area | How it works today |
|---|---|
| Stack | PHP 8.2 + MySQL 8.4, jQuery + SweetAlert2 admin, Docker php:8.2-apache, deployed with git push |
| Auth / roles | `admin_users`, `admin_roles` (1 Super Admin, 2 Manager, 3 Staff), `admin_permissions` and `admin_role_permissions` (key based); `require_permission()` |
| Numbering | `document_sequences` + `next_document_number()` → PREFIX-YYYY-NNNNNN |
| **Stock** | **One stock figure per product** (`product_details.stock`). Every pack size is its own product row. `warehouse_id` is stored on movements as a label; there is **no stock per warehouse** |
| Stock ledger | `stock_movements` (previous/new stock). Written by: Stock In, Stock Out, adjust, waste, import, out-of-stock, DC dispatch, manual sale, credit sale, website order create and payment verify, and the new ERP modules |
| Costing | **Weighted average** (`costing_engine.php`), replayed from the stock ledger. Landed cost = rate + freight/loading/etc. from the bill. GST is excluded from cost (input credit) |
| Accounting | **Cash-book** only: `accounts_transactions` (income/expense, with payment mode). No chart of accounts, no double entry |
| Sales | Website `orders` (pending → confirmed → packed → dispatched → delivered/cancelled; stock deducted when the order is created). Sales Orders → Delivery Challan (stock out on dispatch) → Invoices (no stock deduction). Manual Sale and Credit Sale (deduct stock) |
| Documents | `erp_documents` plus private folder `assets/erp_docs/` and an authenticated download endpoint. Checks type, size and real image/PDF. File names are random. **Hard delete** today |
| Audit | `audit_logs` (user, action, module, record, old and new values) via `log_audit()` |
| Notifications | `notifications` table is **customer inbox only** (website). Admin has only a dashboard "alerts" endpoint (`get_dashboard_alerts.php`) |
| Channels | No sales-channel field anywhere. No Amazon, Meesho or Zepto data |

---

## 1. Gap analysis (brief section → status)

✅ = done and working · 🟡 = partial · ❌ = missing · ⚠️ = conflicts with the current architecture (decision needed)

### Procurement
| Requirement | Status | Notes |
|---|---|---|
| Purchase Requests | ✅ | request → approve → PO |
| RFQ, Supplier Quotations, Comparison | ❌ | new: rfqs, rfq_items, rfq_suppliers, supplier_quotations(+items); comparison grid; "award" → PO |
| Purchase approval | 🟡 | PR/PO approve buttons exist; move them onto the shared approval engine |
| Purchase Orders | ✅ | all the fields in the brief plus documents. Missing: supplier contact person, terms text (to be added in the new side table) |
| PO amendments | ❌ | new `purchase_order_revisions` table (snapshot + reason). The PO keeps its number and gets rev 1, 2… |
| PO cancel | ✅ | blocked once a GRN exists |
| Purchase Returns | ✅ | reduce stock and payable |
| Debit notes | 🟡 | a return reduces the payable, but there is no numbered debit note document. Add a DN number and a print view |
| Supplier bills | ✅ | duplicate bill number per supplier blocked; landed-cost spread. Statuses: add RECEIVED → VERIFIED → APPROVED steps (today: draft → posted) |
| Supplier payments | ✅ | payment can't exceed balance; also written to Transactions. Add approval, UTR duplicate check and payment proof category |
| Supplier outstanding & ledger | ✅ | Supplier Ledger page |

### Supplier management
| Supplier profile: credit limit, opening balance, contact person, bank IFSC | 🟡 | `suppliers` has name, company, mobile, email, address, GST, terms, bank text. **Plan: new side table `supplier_profiles`** (no ALTER of `suppliers`, no change to save_supplier.php) |
| Supplier detail page (totals, last purchase/payment, history, lead time, price changes, rejected/returned qty, transport cost) | 🟡 | ledger and price history exist. New Supplier 360 page combines them |

### Transport / QC / Batch
| Transport / logistics (transporter, vehicle, driver, LR, consignment, dates, freight/loading/unloading/handling) | 🟡 | charges exist on the bill only. New `inbound_shipments` linked to PO/GRN; charges feed the same landed-cost logic, **counted once** (the bill charge references the shipment) |
| Quality Check module | 🟡 | GRN has rejected qty inline and rejected qty never enters stock. New `quality_checks`: GRN can be posted "QC pending" → **stock goes to Quarantine**, then QC pass/partial/reject releases or rejects it |
| Damaged qty on GRN | ❌ | add to the new QC record (side table, no ALTER) |
| Batch / lot / expiry | 🟡 | `inventory_batches` stores batch, mfg, expiry, rate, landed cost. Remaining qty is **estimated** (oldest sold first) ⚠️ C3 |
| FEFO, expiry alerts, near-expiry, expired | 🟡 | batch/expiry report exists. Alerts ❌ |

### Inventory & warehouse
| Stock in/out/movement | ✅ | existing modules |
| Stock adjustment (approval) | ✅ | |
| Stock transfer between warehouses | ❌ ⚠️ C1 | there is no per-warehouse stock to transfer |
| Zone → Rack → Shelf → Bin | ❌ ⚠️ C1 | optional; only once per-warehouse stock exists |
| Stock count (cycle count → variance → adjustment) | ❌ | new, posts through the existing adjustment approval |
| Reserved / available stock | 🟡 | website orders deduct on create, so there is nothing to reserve. Sales Orders deduct at DC. Plan: **reporting-only** "committed" qty (open sales orders), no change to storefront |
| Damaged / rejected / expired stock buckets | ❌ | new quarantine/bucket sub-ledger (with C1) |
| Inventory valuation & ledger | ✅ | Stock Valuation + ledger check |

### Sales, fulfilment, returns, receivables
| Sales modules connected to COGS/profit | ✅ | engine reads every existing outflow |
| Picking / packing / dispatch / delivery | 🟡 | website orders have packed/dispatched/delivered; sales orders have DC. Add an optional pick/pack log on Sales Orders (new table, no new order system) |
| Sales returns (inspection, credit note, refund) | ✅ | good qty back to stock; rest not sellable. Add credit note number and print |
| Customer ledger / payment report / outstanding | 🟡 | Receivables page exists. Add Customer 360 (sales, paid, returns, refunds, ledger) |

### Money
| Expense module | 🟡 | Expenses page posts straight to Transactions. Missing: expense number, tax, approval, category list from the brief. Plan: new `expenses` table (draft → approve → posts to Transactions) |
| **Chart of accounts, journals, GL, AP/AR, bank/cash, reconciliation, periods, closing** | ❌ ⚠️ C2 | you chose "cash-book" last time |
| Tax (GST) report | 🟡 | invoices carry tax amounts; no GST summary (output vs input). New report only; no change to tax logic |
| Marketplace channel + settlement | ❌ ⚠️ C4 | |
| Profit & Loss | ✅ | net sales − COGS = GP − expenses = NP |
| Product profitability | ✅ | category & channel profitability ❌ (category: easy; channel needs C4) |
| COGS report | 🟡 | inside P&L. Add a dedicated drill-down |
| Cash flow | ❌ | from Transactions (cash-book) or journals (C2) |

### Cross-cutting
| Documents (upload from PC, private, typed) | ✅ | Add: the brief's 17 categories, **archive instead of hard delete**, versions, description, "All Documents" page, **linked visibility** (a bill's docs also show on its PO/GRN/payment) |
| Approval workflow (reusable) | ❌ | one `approval_requests` engine (DRAFT/SUBMITTED/UNDER_REVIEW/APPROVED/REJECTED/CANCELLED) used by PO, bill, payment, expense, returns, adjustments |
| Admin notifications | ❌ | new `admin_notifications` (the customer `notifications` table is not touched) + a bell in the admin header, filled by one alert generator |
| Audit | ✅ | reused. Add a filter for the ERP modules |
| Management dashboard | 🟡 | KPI row exists. Add sales today, collections, supplier payments, COGS, expiring stock, pending approvals |
| **Transaction trace (sale → … → supplier → landed cost → COGS → GP)** | 🟡 | item trace exists per product. Missing: a chain view per sale line (needs C3 for exact batch) |
| Search / filter / pagination | 🟡 | ERP lists filter client-side, capped at 500 rows. Move to server-side paging for large lists |
| Idempotency / atomic / no hard delete of financials | ✅ | transactions, reference guards, cancel instead of delete — kept for all new work |

---

## 2. Conflicts — need your decision before I build these

**C1 · Stock per warehouse.** Stock is one number per product, and 13 existing files rely on that (storefront, stock in/out, sales). Real warehouse stock would mean rewriting them, and the brief forbids that.
*Proposed:* keep `product_details.stock` as the master. Add a **warehouse stock sub-ledger** (`warehouse_stock`, `warehouse_stock_moves`) that follows every movement using its existing `warehouse_id`, plus Stock Transfer (moves between warehouses, total unchanged) and quarantine/damaged/expired buckets. Old stock is set once with an "opening allocation" screen. Storefront is unchanged.

**C2 · Double-entry accounting.** Last time you chose to keep the cash-book. This brief asks for chart of accounts, journals, GL and reconciliation.
*Proposed:* an **additive journal layer**. New `chart_of_accounts`, `journal_entries`, `journal_lines` (debit = credit enforced). A poster creates journals automatically from the source records (purchase bill, supplier payment, invoice, website order, manual/credit sale, customer payment, expense, COGS, returns). A unique key (source_type, source_id, event) blocks duplicates. **Transactions page and `accounts_transactions` are not changed.** Journals start from a go-live date plus opening balances (optional back-fill of history).

**C3 · Exact batch on every sale (FEFO, batch traceability).** The existing sales screens (5 files) don't ask which batch was sold.
*Proposed:* **automatic FEFO allocation**. A new allocator reads each sale movement and assigns it to batches, earliest expiry first. Results go in `batch_allocations`, which gives exact batch balances and traceability without touching sales code. Expired batches are flagged, but sales are not blocked, since that would change sales screens.

**C4 · Marketplaces.** There is no channel data today.
*Proposed:* new `sales_channels` (Website, Offline, and any marketplaces you use) and a channel tag on sales via a side table, plus a Marketplace Settlements module (gross, commission, fees, shipping, refunds, TDS/TCS, net, bank receipt, reconciliation). Tell me which marketplaces you actually sell on; if none, I skip this.

---

## 3. Database plan (additive only)
No DROP, DELETE, TRUNCATE, RENAME or ALTER of existing tables. Only `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`, and every new table has indexes on number, party, product, date, status and entity reference. Each phase ships with **migration SQL, rollback SQL** (drops only the new empty tables) **and verification SQL**.

New tables (about 30), by phase:
- **A:** rfqs, rfq_items, rfq_suppliers, supplier_quotations, supplier_quotation_items, purchase_order_revisions, inbound_shipments, quality_checks, quality_check_items, debit_notes, supplier_profiles, approval_requests, approval_actions, admin_notifications, erp_document_links (plus doc archive/version via a new `erp_document_meta` side table)
- **B:** warehouse_locations (zone/rack/shelf/bin, optional), warehouse_stock, warehouse_stock_moves, stock_transfers(+items), stock_counts(+items), batch_allocations
- **C:** chart_of_accounts, journal_entries, journal_lines, bank_accounts, bank_reconciliations, financial_periods, expenses (with approval)
- **D:** sales_channels, sales_channel_map, marketplace_settlements(+lines), fulfilment_logs, credit_notes
- New roles through INSERT IGNORE: Accounts, Warehouse, Purchase, Sales, Viewer (existing roles unchanged)

**Before each migration:** HeidiSQL → Tools → Export database as SQL (structure + data) → save the .sql file. Then run the migration.

**Read-only schema check (please run and send me the result):**
```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('suppliers','warehouses','product_details','orders','order_items','invoices','invoice_items',
    'sales_orders','delivery_challans','accounts_transactions','credit_sales','manual_sales','customers','users','audit_logs')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```
(Right-click the result grid → Export grid rows → CSV, then attach it.) This confirms the live columns before anything new is joined to them.

---

## 4. Files
**Existing files that will change (insert-only, minimal):**
- `admin/includes/sidebar.php` — new menu links only
- `admin/index.php` — extra KPI cards in the existing ERP row
- admin header include — notification bell (one include line)
- my own ERP files from the last release (`erp_docs.php` for archive/versions/links, `purchase_api.php` for revision/approval hooks, `expenses.php`, `erp_report_lib.php`, `erp.js`)

**Not touched:** the website/storefront, auth/login, products, categories, orders, invoices, sales orders, DC, manual/credit sale, stock in/out, suppliers page, Transactions page, settings.

**New files:** about 25 admin pages and about 8 API files, all in the existing `erp_*` pattern and style.

---

## 5. Build order
| Phase | Contents | Size |
|---|---|---|
| **A · Procurement completion** | RFQ → quotations → comparison → award PO · PO revisions · transport · QC + quarantine · debit notes · Supplier 360 · approval engine · admin notifications · documents v2 (categories, archive, versions, linked view, All Documents) | large |
| **B · Inventory** (C1, C3) | warehouse stock + transfers + opening allocation · stock count · FEFO batch allocation · expiry alerts · bins (optional) | large |
| **C · Accounting** (C2) | chart of accounts, auto journals, GL, trial balance, AP/AR aging, bank/cash accounts, reconciliation, expense approval, GST summary, cash flow, periods/closing | large |
| **D · Sales & channels** (C4) | channels, marketplace settlement, pick/pack log, credit notes, Customer 360, category/channel profitability, COGS report, full sale → supplier trace, server-side paging | medium |

Each phase: build → test on a fresh copy of the schema in strict mode (the same method as last time) → regression-check the existing pages → report + deploy .bat + SQL.

## 6. Risks
- Warehouse and batch splits for **old** stock are estimates until you set opening allocations.
- Journals are only as complete as their sources. Invoices with no stock-out keep using estimated COGS (as today).
- Document files need persistent storage on the server (still to confirm).
- Several large phases: each is shipped and verified separately to keep the risk contained.

## 7. End-to-end test plan
The 32-step script from the brief runs at the end of phase D. Each phase also re-runs the existing 24 purchase-to-profit checks plus its own checks: no duplicate stock moves, journals, payments or docs on double submit; debit = credit; P&L reconciles with GL; stock roll-forward matches.
