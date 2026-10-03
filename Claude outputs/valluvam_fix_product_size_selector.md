# Valluvam — Product page size selector fix (30 Sep 2026, not yet deployed)

**Problem:** The product page showed two size rows. "Size" lists the real packs the admin created. "SELECT WEIGHT" always showed 250g–25kg with *estimated* prices, but Add to Cart still added only the one pack being viewed, at that pack's own price. A customer could see a 25kg price and be charged for 500g.

**Fix:**

| File | Exact change |
|---|---|
| assets/js/product_detail/product_detail.js | The "Select Weight / Select Volume" calculator row is no longer shown. The price is always the pack's own admin price and discount price (strike-through MRP + % OFF badge). If a pack has no discount price, the MRP is shown instead of "₹null". |
| assets/db_query/product_detail/product_detail_query.php | Size buttons are sorted by real amount (250g, 500g, 1kg, 5kg). Before, "1kg" came before "250g". |

No other code was changed (verified by diff). No database change is needed. Syntax checks pass, and the page was tested with sample data.

## How admin controls the sizes (already supported)
In Admin → Add/Edit Product, create **one product entry per pack size**:
- Same category, same name + size at the end, e.g. `Valluvam Honey 250g` and `Valluvam Honey 500g`
- Quantity field: `250g`, `500g`, `1kg`, `500ml`, `1L`, etc.
- Its own Price (MRP) and Discount Price
- Only the packs you create appear as Size buttons. To offer only 250g, create only the 250g entry; no Size row is shown then.
- To remove a size, delete that pack's entry.

## Deploy
Double-click `deploy_product_size_fix.bat`, then press Ctrl+Shift+R on a product page.
