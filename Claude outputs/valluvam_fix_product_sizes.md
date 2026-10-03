# Valluvam — Admin-managed sizes per product (30 Sep 2026, not yet deployed)

**Request:** On the product page, show only the sizes admin adds for that specific product (e.g. 100g, 250g, 500g, 1kg), with the right price and discount for each. No default 250g–25kg list. Admin can add, edit and delete sizes per product.

**How it works:** In Admin → Products → edit a product, the new **"Sizes for this product"** box lists every size with its Price, Discount and Stock, and has Add / Edit / Delete. Each size is saved as its own pack entry (e.g. "DRY FIG 500g"), copying the category, image, description and benefits from the product. The website's existing "Size" row shows exactly these sizes, and the cart charges each size's own price. There are no cart, checkout or order changes.

| File | Change |
|---|---|
| assets/db_query/admin/product_sizes.php | **New.** Admin-only list / add / edit / delete of a product's sizes. Blocks duplicate sizes and a discount above the price. Won't delete a size that is in past orders. Every change goes to the audit log. |
| assets/js/new_product/product-sizes.js | **New.** The "Sizes for this product" box in the product form. |
| new_product.php | +2 lines to load it, and the quantity-picker version bump. |
| assets/js/new_product/quantity-picker.js | Button labels only: "Add/Edit/Delete size" → "Add/Edit/Delete option", so they aren't confused with the new box. |
| (earlier, not yet pushed) product_detail.js + product_detail_query.php | Removes the default "SELECT WEIGHT" row and sorts the sizes 250g → 500g → 1kg. |

No other code was changed. Tested: endpoint on a sample database (add/edit/duplicate/discount/delete/order-linked cases), the product page lists only the admin sizes with their own prices, and the admin form was tested in a browser.

## Database
Nothing to run. It uses existing `product_details` columns only.

## Important
A new size starts with **no stock**, so the website shows it as "Out of Stock" until you add stock for it in **Stock In** (the same as any new product).

## Deploy
Double-click `deploy_product_sizes.bat`, then press Ctrl+Shift+R.
