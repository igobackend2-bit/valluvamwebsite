# Rice Products — Add Checklist

Category to select for all of these: **Rice**

Two methods are used, so nothing in the site's code has to change:

- **1kg / 5kg / 10kg** → add through the normal **Add Product** admin page (`new_product.php`), same as always.
- **25kg** → add directly in HeidiSQL, because the Add Product form only allows quantities up to 10kg (10000g) and we are not changing that rule.

For every product, use a **Rating** between 1–5 (pick something like 4.5 unless you have a real rating), and upload the product's photo when the form asks for an image.

---

## 1. Poni Rice
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 92   | 83   |
| 5kg  | `5000g`  | 460  | 415  |
| 10kg | `10000g` | 920  | 830  |
| 25kg | *(DB only — see Step 2 below)* | 2300 | 2075 |

## 2. Idly Rice
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 58   | 53   |
| 5kg  | `5000g`  | 290  | 265  |
| 10kg | `10000g` | 580  | 530  |
| 25kg | *(DB only)* | 1450 | 1325 |

## 3. Basmati
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 148  | 133  |
| 5kg  | `5000g`  | 740  | 665  |
| 10kg | `10000g` | 1480 | 1330 |
| 25kg | *(DB only)* | 3700 | 3325 |

## 4. Raw Rice
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 55   | 50   |
| 5kg  | `5000g`  | 275  | 250  |
| 10kg | `10000g` | 550  | 500  |
| 25kg | *(DB only)* | 1375 | 1250 |

## 5. Mappilai Samba
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 87   | 78   |
| 5kg  | `5000g`  | 435  | 390  |
| 10kg | `10000g` | 870  | 780  |
| 25kg | *(DB only)* | 2175 | 1950 |

## 6. Seeraga Samba
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 218  | 195  |
| 5kg  | `5000g`  | 1090 | 975  |
| 10kg | `10000g` | 2180 | 1950 |
| 25kg | *(DB only)* | 5450 | 4875 |

## 7. Karupu Kavuni
| Size | Quantity to type in form | MRP (Price) | Discounted Price |
|---|---|---|---|
| 1kg  | `1000g`  | 167  | 150  |
| 5kg  | `5000g`  | 835  | 750  |
| 10kg | `10000g` | 1670 | 1500 |
| 25kg | *(DB only)* | 4175 | 3750 |

---

## Step 1 — Add the 1kg / 5kg / 10kg sizes (21 products) through the Add Product form

For each row above marked with a quantity like `1000g`:

1. Go to `valluvamproducts.com/new_product.php` (your admin Add Product page).
2. Click **New Product** (or whatever button opens the add form).
3. Fill in:
   - **Product Name**: e.g. "Poni Rice 1kg" (include the size in the name so customers can tell sizes apart)
   - **Price**: the MRP value from the table
   - **Discounted Price**: the Discounted Price value from the table
   - **Category**: Rice
   - **Quantity**: exactly as shown (e.g. `1000g`, `5000g`, `10000g`)
   - **Rating**: any number 1–5 (e.g. 4.5)
   - **Image**: upload the product photo
   - **Description** / **Benefits**: whatever text you'd like for that product
4. Save.
5. Repeat for every row until all 21 (7 products × 3 sizes) are added.

## Step 2 — Add the 25kg sizes (7 products) directly via HeidiSQL

Since the form won't accept a 25kg quantity, add these rows straight into the `product_details` table using HeidiSQL — the same tool you used earlier for the Rice category fix.

**Important:** First upload each product's image once (you'll have already done this in Step 1 for the 1kg/5kg/10kg versions). Open the admin product list (`products.php`) and check the image filename used for that product (it will look like `1234567890_photo.jpg`) — you'll reuse that same filename for the 25kg row so the picture shows correctly.

In HeidiSQL, clear the query box and run one INSERT per product, replacing `IMAGE_FILENAME_HERE` with the real filename you found:

```sql
INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Poni Rice 25kg', 2300, 2075, 'Rice', '25000g', 4.5, 'Poni Rice - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');

INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Idly Rice 25kg', 1450, 1325, 'Rice', '25000g', 4.5, 'Idly Rice - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');

INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Basmati 25kg', 3700, 3325, 'Rice', '25000g', 4.5, 'Basmati Rice - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');

INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Raw Rice 25kg', 1375, 1250, 'Rice', '25000g', 4.5, 'Raw Rice - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');

INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Mappilai Samba 25kg', 2175, 1950, 'Rice', '25000g', 4.5, 'Mappilai Samba - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');

INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Seeraga Samba 25kg', 5450, 4875, 'Rice', '25000g', 4.5, 'Seeraga Samba - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');

INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
VALUES ('Karupu Kavuni 25kg', 4175, 3750, 'Rice', '25000g', 4.5, 'Karupu Kavuni - 25kg pack', 'Premium quality rice', 'IMAGE_FILENAME_HERE');
```

Run each one (select the line, press `F9`), one at a time, after replacing the image filename. Each should say "1 row affected."

## Step 3 — Verify

Run this in HeidiSQL to see everything you've added:
```sql
SELECT id, product_name, price, dis_price, quantity, image FROM product_details WHERE category = 'Rice' ORDER BY id;
```
You should see 28 rows total. Then check the live site's Rice category page and shop page to confirm they all display correctly.
