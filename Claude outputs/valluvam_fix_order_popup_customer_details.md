# Valluvam — Admin order popup now shows customer details (30 Sep 2026, not yet deployed)

**Request:** Admin → Orders → click an order / eye icon. The "Products in order" popup showed only products. It now also shows who placed the order and where it goes.

**Root cause:** `assets/db_query/admin/get_order_items.php` returned only `order_items`, and `renderOrderItemsModal()` in `admin/orders.php` only drew the product table.

| File | Exact change |
|---|---|
| assets/db_query/admin/get_order_items.php | After the items query, also reads that order's row from `orders` and returns an `order` object (whitelisted fields; `razorpay_signature` never sent). |
| admin/orders.php | Passes `data.order` to `renderOrderItemsModal()`. The popup adds a details block above the product table: Customer, Email (click to mail), Phone (click to call), Delivery address, Order date, Order status, Payment method + status, Razorpay payment ID (online orders), Amount charged. Empty fields are hidden. |

No other code was changed. Verified by diff: 1 hunk in the endpoint, 4 small hunks in orders.php. Original line endings kept. `php -l` and JS syntax check pass. Tested the popup with sample data, including HTML escaping.

## Deploy
Double-click `deploy_order_popup_customer_details.bat`, wait for the host to deploy, then press Ctrl+Shift+R on Admin → Orders and open any order.

## Noticed, not changed
- The `orders` table has no `user_id`, so an order can't be linked to a customer account. Name, email and phone come from the checkout form.
- "Grand total" in the popup is the sum of item lines. "Amount charged" is what the customer actually paid (it includes any delivery charge or discount), so the two can differ.
