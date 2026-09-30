# Valluvam Products — Complete ERP: Implementation & Change Report

Date: 1 Oct 2026 · Follows `valluvam_complete_erp_audit_and_gap_analysis.md` (audit + gap analysis).
Decisions used: warehouse **sub-ledger**, **add a journal layer** for accounting, channels **website + offline only** (no marketplace settlement), **all phases A–D**.

## 1. What was built (only the gaps)

| Area | Added |
|---|---|
| Procurement | RFQs → supplier quotations → comparison → PO (linked); PO amendments with revisions and approval; PO print; inbound shipments/transport (vehicle, LR, freight, payments); QC on goods receipts (accept / reject / damaged); debit notes auto-issued on purchase returns; supplier 360 (profile, credit limit, rating, ledger, documents) |
| Approvals | One Approvals inbox for PR (Manager → Backend chain), PO, PO amendments, stock adjustments, stock counts, manual journals; optional rules (with ₹ limits) for bills, supplier payments, purchase returns, sales returns, expenses. Approve/reject re-runs the original module action, so the module's own checks still apply |
| Notifications | Admin notifications (approvals waiting, low stock, expiring batches, overdue bills/receivables, unreconciled bank lines), sidebar badges |
| Documents v2 | Categories, versions, archive/restore (no delete), "all documents" page, linked-document chains (PR → RFQ → PO → GRN → bill → payment → return) |
| Warehouse | Per-warehouse stock sub-ledger (available / damaged / rejected / expired buckets), stock transfers with in-transit, stock counts with approval, bin locations, FEFO batch allocation and batch page |
| Accounting | Chart of accounts (43 system accounts), automatic double-entry journals from bills, payments, sales, receipts, returns, expenses and stock; manual journals (with approval + reverse); general ledger; trial balance / P&L / balance sheet / cash flow; AP/AR ageing; GST summary; bank & cash accounts with statement import and reconciliation; financial periods (close/reopen); new expenses module |
| Sales | Sales channels (website / offline / B2B), fulfilment log (pick → pack → dispatch → deliver), credit notes on sales returns, customer 360, transaction trace, ERP reports centre |

## 2. Merge with your Manager → Backend purchase-request workflow

While I was working, four of the files I change were also edited on your computer (`purchase_api.php`, `sidebar.php`, `purchase_orders.php`, `purchase_requests.php`), and `erp_helper.php`, `check_admin.php` and `purchase_request_approval_workflow.sql` were added or changed. I **kept all your changes** and merged mine on top (3-way merge):

- `purchase_api.php`: your two-stage PR chain, permissions and ownership rules unchanged. My hooks now follow your chain: on submit → Approvals request "manager approval" (`purchase.manager_approve`); on manager approve → closed and a "backend / final approval" request opened (`purchase.backend_approve`); backend approve/reject, manager reject or cancel close it.
- `sidebar.php`: your combined "Sales Orders & Manual Sales" menu and scroll-to-active script kept; my ERP links and badge script added.
- `purchase_orders.php`: your "Add new supplier / shop" kept; my Amend / Transport / Print / extras added.
- `purchase_requests.php`: your Manager/Backend buttons kept; my "Ask for quotations (RFQ)" shows only for backend approvers on approved requests.
- `erp_ext.php` defines `erp_can()` only if your `erp_helper.php` has not already defined it (same behaviour).
- The migration **does not** grant any new permissions to the Manager role (your `check_admin.php` limits Managers to purchase requests). Your "Backend" role, if present, gets the procurement add-ons (RFQ, transport, QC, supplier profile, documents, notifications, approvals view, warehouse view, trace).
- The approval rule for purchase orders uses `purchase.backend_approve`, matching your `po_approve` permission.

Your `erp_helper.php`, `check_admin.php` and `purchase_request_approval_workflow.sql` are not changed by me. They are listed in the deploy script only so that they are committed with the merged files that depend on them.

Note (your code, not changed): `pr_save` still only lets you edit `draft`/`rejected` requests, but the page offers Edit on `manager_rejected`/`backend_rejected`. Saving those shows "Only draft or rejected requests can be edited." You may want to add the two new statuses to that check.

## 3. Database plan (additive only)

- `erp_complete_migration.sql` — 40 new tables (`CREATE TABLE IF NOT EXISTS`, all indexed), seeds (43 accounts, 2 bank/cash accounts, 3 channels, 12 approval rules, 30 permissions, 5 roles: Accounts, Warehouse, Purchase, Sales, Viewer). No existing table is altered, renamed or dropped; no existing data is deleted. Safe to run twice.
- `erp_complete_verify.sql` — read-only checks. Expected: 40 tables, 43 / 2 / 3 / 12, 30 permissions, and your existing record counts unchanged.
- `erp_complete_rollback.sql` — drops **only** the 40 new tables and removes only the new permissions/roles/settings. Existing data is not touched.

Order: `erp_purchase_migration.sql` (already run) → `purchase_request_approval_workflow.sql` (yours, run once) → `erp_complete_migration.sql` → `erp_complete_verify.sql`.

## 4. Files

**Changed (15)**, all insert-only hooks. Everything else in each file is unchanged:

| File | Why |
|---|---|
| `assets/db_query/admin/purchase_api.php` | Approvals open/close for PR/PO; QC hook on GRN save/post; bill posting can go through approval; duplicate UTR/reference block on supplier payments; optional payment/return approval; debit note on purchase return |
| `assets/db_query/admin/inventory_ops_api.php` | Approvals for stock adjustments; optional sales-return approval; credit note on sales return |
| `assets/db_query/admin/costing_engine.php` | Optional audit trail for journals; transfer / quality buckets; opening-estimate retro flag (no double count) |
| `assets/db_query/admin/erp_report_lib.php` | Supplier opening balances; website revenue only for paid/COD orders; expense GST not counted as cost; transfers in transit; FEFO batches; pending PR statuses incl. `manager_approved` |
| `assets/db_query/admin/erp_reports.php` | Dashboard extras (loads `erp_ext.php` only if present) |
| `assets/db_query/admin/erp_docs.php` | Documents v2 (categories, versions, archive/restore, related chain, per-entity permission, real content-type checks) |
| `admin/assets/erp.js` | Shared helpers: documents v2, pager, tabs, KPI, extra status badges |
| `admin/includes/sidebar.php` | New ERP menu links + badges (merged with your changes) |
| `admin/index.php` | 8 extra dashboard cards (hidden until the new tables exist) |
| `admin/purchase_orders.php` | Amend, Transport, Print, RFQ/approval/credit-limit panel (merged) |
| `admin/purchase_requests.php` | RFQ button (merged) |
| `admin/goods_receipts.php` | QC and transport panel |
| `admin/purchase_returns.php` | Debit-note link |
| `admin/profit_loss.php` | Stock-in-transit row |
| `admin/expenses.php` | Uses the new expenses module (GST, approval, documents); old cash-book expenses still shown in a tab |

**New (39):** 28 pages in `admin/` (approvals, notifications, documents, rfqs, shipments, quality_checks, debit_notes, supplier_360, warehouse_stock, stock_transfers, stock_counts, warehouse_locations, batches, chart_of_accounts, journals, general_ledger, financial_statements, ap_ar_aging, gst_summary, bank_accounts, financial_periods, sales_channels, credit_notes, fulfilment, customer_360, transaction_trace, erp_reports_center, print_erp); 8 back-end files in `assets/db_query/admin/` (erp_ext, accounting_engine, procurement_api, approvals_api, notifications_api, warehouse_api, accounting_api, sales_ext_api); 3 SQL files.

## 5. Rules kept

- No duplicate postings: journals are unique per (source type, source id, event); warehouse moves and batch allocations are synced by cursor/NOT EXISTS checks; payments check reference/UTR per supplier.
- Posted financial records are never hard-deleted: they are reversed or cancelled. Documents are archived, not deleted.
- Documents are stored outside public access (`assets/erp_docs` is denied by .htaccess) and are served only through the permission-checked download. Files are checked by extension, real content signature and size, and are saved under a random name with no user path.
- Closed periods: postings dated in a closed period go to the first open day (the original date stays on the document).
- Costing: weighted average (the existing engine). Inventory journals are posted per day from the engine and correct themselves if an earlier document changes. Goods received but not billed go to GRNI clearing; purchase-return price differences go to purchase price variance.

## 6. Testing (test copy, MariaDB 10.11)

- Base + your PR workflow SQL + migration applied twice: 0 errors.
- Regression suite 106/106, first-release suite 24/24, PR-chain suite 8/8. The PR-chain suite covers: Approvals inbox manager → backend → approved; direct approve/reject from the PR page closes the inbox request; Manager blocked from accounting APIs.
- Rollback → re-migrate → verify: OK, and existing data counts are unchanged.
- Every new page and the merged pages load in a browser with no JS or API errors. Tested flows: document upload / version / archive / fake-PDF rejection, expense posting, Staff permission block, PO "add new supplier" alongside Amend/Transport.
- GL P&L equals the operational P&L. The only differences are purchase price variance and closed-period date moves.

## 7. Deploy

1. **Back up the live database** (HeidiSQL → Export database as SQL, structure + data) and keep the file.
2. If not already done: run `purchase_request_approval_workflow.sql` once.
3. Run `erp_complete_migration.sql`, then `erp_complete_verify.sql` and compare the results with section 3.
4. Double-click `deploy_complete_erp.bat` in the project folder (git add → commit → push).

## 8. First use

1. **Accounting → Periods & settings:** set the *ledger start date*. Automatic journals start from this date and stay off until it is set.
2. Enter supplier opening balances (Supplier 360) and check the stock valuation opening cost.
3. **Approvals → Rules:** switch on the optional rules you want (bills, payments, returns, expenses) and set ₹ limits.
4. **Admin users:** assign the new roles (Accounts, Warehouse, Purchase, Sales, Viewer) as needed. Managers keep your PR-only restriction.
5. Bank accounts: set opening balances and import a statement to reconcile.

## 9. Known limits

- Batches/expiry are tracked globally per product (as before), not per warehouse; FEFO allocates globally.
- Website orders carry no GST split in the existing data, so they post to sales at the gross amount.
- No marketplace settlement module (you chose website + offline).
- The Purchase role cannot create purchase orders under your permission model (`po_save` needs `purchase.backend_approve`). Use your Backend role for PO work, or grant that permission to Purchase.
- Existing pages that show test-copy-only errors (for example missing endpoints in the test copy) were not changed.
