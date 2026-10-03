# Valluvam Products — Admin Panel User Guide

*valluvamproducts.com/admin · version of 1 October 2026*

---

## 1. Getting started

| Item | Details |
|---|---|
| Login | `valluvamproducts.com/admin` → user name + password |
| Refresh after an update | Press **Ctrl + F5** once so the newest menus and pages load |
| Menu | Left sidebar, grouped into Sales, Inventory, Purchases, Accounts, Assets, Waste, Customers, Suppliers, Reports & admin |
| Badges | Red number next to **Approvals** = items waiting for you · next to **Notifications** = unread alerts |
| Who sees what | Each role sees only the pages and buttons it has permission for |

### Roles

| Role | Can do |
|---|---|
| **Super Admin** | Everything |
| **Manager** | Purchase requests only (Manager approval) — other pages redirect to the dashboard |
| **Backend** | Final approval of purchase requests, purchase orders, purchasing, receiving, suppliers |
| **Normal Admin** | Raise and follow their own purchase requests |
| **Accounts** | Bills, payments, expenses, accounting, reports |
| **Warehouse** | Goods receipts, quality checks, transfers, stock counts, batches |
| **Purchase** | RFQs, quotations, transport, supplier profiles |
| **Sales** | Sales entry, fulfilment, customers, returns |
| **Viewer** | Read-only reports |
| **Staff** | Day-to-day entries (documents, QC, stock counts, fulfilment) |

Roles and their permissions are changed in **Reports & admin → Admin Users**.

---

## 2. Dashboard

**Dashboard** shows today's sales, orders, low stock, pending approvals, payables, receivables, stock value and other key figures. Click any card to open the page behind it.

---

## 3. Sales

| Page | What it is for | Main actions |
|---|---|---|
| **Sales Orders** | Orders from shops / B2B customers | Create, convert to invoice / delivery challan, print |
| **Manual Sales Entry** | Counter or phone sales entered by hand | Add sale, payment mode, print |
| **Credit Sale** | Sales given on credit | Record sale, record payments later |
| **Invoices** | Tax invoices | Create, print, record payment, cancel |
| **Website Orders** | Orders placed on valluvamproducts.com | View customer details, update status |
| **Sales Returns** | Goods returned by customers | Good stock back to inventory, damaged stock not; refund or credit |
| **Fulfilment** | Pick → pack → dispatch → deliver log | Mark each stage, add tracking / AWB |
| **Credit Notes** | One numbered credit note per sales return | View, print |
| **Customer 360** | Everything for one customer | Ledger, sales, payments, returns, outstanding |
| **Sales Channels** | Website vs offline vs B2B | Sales, cost and profit per channel |

---

## 4. Inventory

| Page | What it is for |
|---|---|
| **Products** | Product list, prices, pack sizes, photos, website visibility |
| **Categories** | Product categories shown on the website |
| **Homepage** | Banners and sections on the website home page |
| **Inventory** | Stock overview of all products |
| **Stock In / Stock Out** | Manual stock entries (purchases normally come through Goods Receipts) |
| **Stock Movement** | Every stock change with date, reason and user |
| **Stock by Warehouse** | Available, damaged, rejected, expired stock per warehouse; quarantine waiting for QC |
| **Stock Transfers** | Move stock between warehouses (send → in transit → receive) |
| **Stock Counts** | Physical counting; differences are posted after approval |
| **Batches & Expiry** | Every batch with remaining quantity and expiry date (earliest expiry sold first) |
| **Warehouses** | Warehouse list |
| **Warehouse Locations** | Zone → rack → shelf → bin codes |
| **Raw Materials** | Bulk items (kg / L) that are repacked into packs |
| **Repacking** | Turn bulk raw material into sellable packs (cost flows into the packs) |
| **Stock Adjustments** | Damage, missing or found stock — needs a reason and approval |
| **Stock Valuation** | Stock value at weighted average cost, opening costs, checks |

---

## 5. Purchases — the complete flow

### 5.1 Pages

| Page | What it is for |
|---|---|
| **Purchase Dashboard** | What needs attention in purchasing, receiving and payables |
| **Purchase Requests** | Ask for stock to be bought (Manager → Backend approval) |
| **Purchase Flow** | One page that guides a request from quotations to quality check (see 5.2) |
| **RFQ & Quotations** | Formal request for quotation to many suppliers and comparison |
| **Purchase Orders** | Orders to suppliers, with competitor quotation comparison |
| **Transport / Logistics** | Incoming shipments, vehicle, LR / tracking, transport cost |
| **Goods Receipts (GRN)** | Receive goods against a PO — only accepted quantity goes into stock |
| **Quality Check** | Inspect goods before they go into stock |
| **Purchase Invoices** | Supplier bills; freight and loading added to landed cost |
| **Purchase Returns** | Return goods to the supplier |
| **Debit Notes** | Numbered debit note for each purchase return |
| **Purchase Payments** | Payments to suppliers (also recorded in Accounts → Transactions) |
| **Purchase History** | What was bought, from whom, at what price |

### 5.2 Step-by-step purchase flow

**Step 1 — Purchase request** (*Purchases → Purchase Requests → New*)
- Fill **Request date**, **Needed by**, **Warehouse**, **Requested by** (type the name).
- For each item: product, **quantity** and **unit** — *packs (pcs)*, *g / kg* (gram packs) or *ml / L* (litre packs). The system converts to packs, e.g. **5 kg of a 500 g pack = 10 packs** (rounded up).
- **Submit for approval** → **Manager approves** → **Backend approves**.

**Step 2 — Shop quotations (3 shops)** (*open the request → Purchase flow*, or *Purchases → Purchase Flow*)
- Enter 3 shops side by side: name, contact person, phone, e-mail, GSTIN, bank (holder, bank, account no., IFSC), UPI, payment terms, delivery days, freight, valid-till date and the rate + GST % of every item.
- Or **Import Excel / CSV** — everything is filled automatically. Use **Download Excel template** for the layout. PDF / Word / photo quotations are attached as documents.
- Highlights: **Lowest price** (green), **Fastest delivery** (blue), **★ Best choice** (both), lowest rate per item, warnings for missing bank / UPI, missing phone, expired quote.
- Click **Use this quotation** on the chosen shop → **Send chosen shop for approval** (fewer than 3 shops needs a reason).

**Step 3 — Shop approval → Purchase order**
- A Backend approver clicks **Approve this shop & create PO** (on the flow page or in **Approvals**).
- The purchase order is created automatically: supplier (new shops are added to Suppliers with bank details), items, rates, GST, freight, expected delivery date. The comparison is attached to the PO.
- The PO goes for **PO approval** → **Approve PO**. Print / PDF shows the shop's bank & UPI details.

**Step 4 — Payment + proof**
- After PO approval the status is **Waiting for payment**. The **Pay to** box shows the shop's bank / UPI details.
- Enter amount, date, mode, UTR / reference and **attach the payment proof** (screenshot or receipt) → **Mark as paid**.

**Step 5 — Transport**
- **Courier**: choose the courier service (DTDC, Blue Dart, Delhivery, Professional, ST Courier, India Post, Ekart, XpressBees, Shadowfax, Ecom Express, Gati, TCI Express, VRL, Shree Maruti, Trackon, or *Other* with the name), tracking / AWB number, delivery person name and phone, dispatch date, **courier receipt / proof**.
- **Internal**: driver name, driver phone, vehicle number, dispatch date.
- A transport record is also created in **Transport / Logistics**.

**Step 6 — Loading DC + shop bill**
- Attach the **loading delivery challan** from the shop.
- Attach the **shop bill**, choose **handwritten** or **system** bill, and type the bill number.

**Step 7 — Unloading + quantity check**
- Attach the **unloading DC** (signed at our warehouse).
- For each item enter **received** and **damaged** quantity. Each line shows **✓ correct** or **short / extra / damaged** in red. Any difference needs a note.
- **Save check & create goods receipt** → a draft GRN is created (stock is not added yet).

**Step 8 — Quality check**
- **Start quality check** → do the check on the Quality Check page (accept / reject quantities).
- After QC, **Post goods receipt** → accepted stock is added to inventory.
- Finally record the shop bill in **Purchase Invoices** for accounts.

The step cards at the top of the Purchase Flow page are coloured: **green** = done, **blue** = to do now, **amber** = waiting / check, **red** = stopped, **grey** = later.

---

## 6. Where to see attached documents and proofs

Every proof is stored with the **purchase order** and can be opened in three places:

1. **Purchases → Purchase Flow** → open the request → each step shows its file as a **✓ file name** link (payment proof in step 4, courier proof in step 5, loading DC and shop bill in step 6, unloading DC in step 7).
2. **Purchases → Purchase Orders** → click the PO → **Documents** section lists every file with type, date and uploader.
3. **Reports & admin → Documents** → filter **Belongs to = Purchase order** and **Category**:
   - Supplier payment proof → payment proofs
   - Transport receipt / LR → courier proofs
   - GRN / delivery challan → loading & unloading DCs
   - Supplier invoice / bill → shop bills
   - Quotation (Belongs to = Purchase request) → shop quotation files

Files are stored privately on the server (`assets/erp_docs/<year>/<month>/`, random names). They cannot be opened by a direct web link — only by logged-in admins through these pages. Files are never deleted: replacing keeps the old version, archived files appear with **show archived**.

Allowed files: PDF, JPG, PNG, WEBP, Word, Excel, CSV — up to 10 MB each.

---

## 7. Approvals and notifications

- **Approvals** (Reports & admin) is one inbox for: purchase requests (Manager and Backend), shop quotation choice, purchase orders, PO amendments, stock adjustments, stock counts, manual journals, and — if switched on — bills, supplier payments, purchase / sales returns and expenses.
- **Approve** re-runs the original action, so all checks still apply. **Reject** needs a reason.
- **Approvals → Rules**: switch optional approvals on and set ₹ limits.
- **Notifications**: approvals waiting, low stock, expiring batches, overdue bills, overdue customers, pending receipts / QC / bills.

---

## 8. Accounts

| Page | What it is for |
|---|---|
| **Transactions** | Cash book — all money in and out |
| **Expenses** | Expenses with GST, payee, receipt and approval |
| **Receivables** | What customers still owe |
| **Chart of Accounts** | Ledger accounts with balances |
| **Journal Entries** | Automatic double-entry journals + manual journals (with approval / reverse) |
| **General Ledger** | All entries of one account with running balance |
| **Financial Statements** | Trial balance, Profit & Loss, Balance Sheet, Cash Flow |
| **Payables & Receivables** | Ageing of what we owe and what is owed to us |
| **Bank & Cash** | Bank / cash accounts, statement import, reconciliation |
| **GST Summary** | Output GST vs input GST per month (working summary for filing) |
| **Periods & Settings** | Ledger start date, payment accounts, close / reopen months |

**First-time setup:** Periods & Settings → set the **ledger start date** (automatic journals stay off until set) · Bank & Cash → opening balances · Supplier 360 → supplier opening balances.

---

## 9. Assets, waste, customers, suppliers

| Page | What it is for |
|---|---|
| **Assets** | Company assets, assignment, maintenance |
| **Waste Records** | Damaged / wasted stock with approval |
| **Customers** | Customer list from the website |
| **Account Requests** | Customer account requests |
| **Leads** | Enquiries (e.g. B2B) |
| **Reviews / Feedback** | Product reviews (moderate) and customer feedback |
| **Coupons** | Discount coupons for the website |
| **Suppliers** | Supplier list with owner, phone, GSTIN, bank account, IFSC, UPI |
| **Supplier Ledger** | Purchases, payments, returns and balance per supplier |
| **Supplier 360** | Everything about one supplier: purchases, payments, quality, price changes, lead time, documents, credit limit |

---

## 10. Reports & admin

| Page | What it is for |
|---|---|
| **Reports** | Sales and stock reports |
| **Profit & Loss** | Profit from recorded sales, stock cost and expenses |
| **Product Profitability** | Revenue, cost and margin per product |
| **ERP Reports** | Sales, COGS, category profit, payments, GRN, QC, returns, audit — with CSV export |
| **Transaction Trace** | Follow a sale back to the supplier, batch, GRN, PO, bill, transport and profit |
| **Approvals / Notifications / Documents** | See sections 6 and 7 |
| **Audit Logs** | Who changed what and when |
| **Admin Users** | Users, roles and permissions |
| **My Account** | Change your own password |
| **Settings** | General website / admin settings |

---

## 11. Daily, weekly and monthly routine

**Daily**
- Check **Approvals** and **Notifications**.
- Enter sales, payments received and expenses.
- Move open purchases forward in **Purchase Flow** (payment → transport → unloading → QC).
- Post goods receipts after QC.

**Weekly**
- Review **Payables & Receivables** and follow up overdue amounts.
- Check **Batches & Expiry** and low stock.

**Monthly**
- **Bank & Cash** → import the bank statement and reconcile.
- Check **GST Summary**, **Financial Statements**, **Profit & Loss**.
- Do a **Stock Count** if needed, then **Periods & Settings → close the month**.

---

## 12. Common messages

| Message | What to do |
|---|---|
| "Add at least one item with a quantity" | Choose an item and enter quantity > 0; press Ctrl + F5 and try again |
| "Choose one of these units …" | The unit doesn't fit the pack (e.g. kg for a ml pack) — choose a listed unit |
| "Collect 3 shop quotations, or give the reason …" | Add a third shop or type the reason |
| "Attach the payment proof" | Choose the screenshot / receipt file before **Mark as paid** |
| "Attach the unloading DC first" | Upload the unloading DC in step 7 |
| "The quantity does not match the order — write what was short …" | Type what was short / extra / damaged |
| "You do not have permission …" | Ask the Super Admin to give your role the permission |
| A new page or button is missing | Press **Ctrl + F5**; check your role |
