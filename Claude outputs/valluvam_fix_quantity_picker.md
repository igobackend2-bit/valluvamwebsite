# Valluvam — Admin Quantity: Unit + Size dropdowns (30 Sep 2026, not yet deployed)

**Request:** In the product form (Admin → Products → edit), replace the free-text Quantity box with a unit choice (g / kg / ml / L) and a dropdown of sizes for that unit (e.g. L → 1, 2, 5, 10). Admin can add any size (e.g. 100 L), edit or delete sizes, and the saved size shows on the website directly.

| File | Exact change |
|---|---|
| assets/js/new_product/quantity-picker.js | **New file.** Hides the old Quantity text box and shows Unit + Size dropdowns with Add / Edit / Delete size buttons. Writes the chosen value (e.g. "200g", "10L") into the existing hidden Quantity field. The size lists are saved in the existing `admin_settings` table (key `quantity_presets_v1`) through the existing admin settings endpoints. |
| new_product.php | +2 lines: loads the new script. |

Nothing else was changed. The product save endpoint, the validation, the database columns and the website pages are untouched. The website reads `product_details.quantity` exactly as before, so a saved size shows on the site straight away.

Default sizes (until admin changes them): g 50/100/200/250/500 · kg 1/2/5/10/25 · ml 100/200/250/500 · L 1/2/5/10.

Deleting a size removes it from the dropdown list only. Products already saved with that size keep it.

Tested in a browser: editing a 200g product preselects g + 200; picking L + 5 saves "5L"; adding 100 L, editing 100→20 and deleting 20 all saved correctly; Save product sends the chosen size.

## Database
Nothing to run. It uses the existing `admin_settings` table, the same one the Homepage editor saves into.

## Deploy
Double-click `deploy_quantity_picker.bat`, then open Admin → Products → edit a product and press Ctrl+Shift+R.

## Noticed, not changed
- In `new_product.js`, the first click on "Save product" only validates. The actual save happens on the second click, and each later save can send the product more than once, because a submit handler is nested inside another. This existed before this change.
- On a slow connection, the category list can finish loading after a product opens for editing and clear the selected Category.
