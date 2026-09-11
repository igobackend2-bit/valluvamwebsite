# Valluvam — Footer sections restricted to homepage + category-link issue

## Part 1: Newsletter bar, "Part of the IGO Group" heading, and brand-logo marquee — now homepage only

15 files changed, presentation only:

- **`footer.php`** — the "Part of the IGO Group" heading/tagline and the scrolling brand-logo marquee (the `<section id="brand-slider">` block) are now wrapped in `<?php if (basename($_SERVER['PHP_SELF']) === 'index.php') : ?> ... <?php endif; ?>`, so they only render when the current page is the homepage. Since `footer.php` is included on every page, this one change makes that section disappear everywhere except the homepage automatically.
- **13 files** (`checkout.php`, `cart.php`, `order_tracking.php`, `rice.php`, `milets.php`, `dryfruits.php`, `shop.php`, `nuts.php`, `oils.php`, `spices.php`, `combo.php`, `millets.php`, `wishlist.php`) — each had its own independent copy of the "Subcribe to our Newsletter" block (it wasn't a shared include like the brand marquee, so each page needed its own fix). Removed that block from all 13.
- **`index.php`** — the Newsletter block existed here too, but was wrapped in an HTML comment, so it wasn't actually showing on the homepage at all. I un-commented it (using the same markup and wording as the other pages, since the commented version had slightly different placeholder text) so it now displays properly on the homepage, where you wanted it.

Net result: the Newsletter bar, the "Part of the IGO Group" heading, and the brand-logo marquee now all show **only on the homepage**, and nowhere else on the site.

Nothing else on any of these 15 pages was touched — no backend, cart, checkout, or product logic involved.

## Part 2: Dry Fruits / Millets category links

I traced how the homepage's category tiles decide where "Dry Fruits" and "Millets" link to. They're rendered by `assets/js/index/index.js`, which pulls each tile's destination straight from your database — specifically a `link` field returned by `assets/db_query/index/index_product_query.php?action=category_slider`. The tile's `<a href="...">` uses whatever URL is stored in that database record; it isn't computed or decided by any front-end code.

So if clicking "Dry Fruits" or "Millets" is landing somewhere that shows more than just that category, it's because the `link` value stored for that category in the database is pointing to the wrong page (or a generic shop page instead of `dryfruits.php` / `millets.php`).

This is a database content value, not a file I can edit under this project's scope (no backend/database changes). To fix it: open your admin panel (or wherever category records are managed) and update the "link" field for the Dry Fruits category to point to `dryfruits.php`, and the Millets category to point to `millets.php`. Once those two values are corrected in the database, the homepage tiles will automatically link to the right place — no code change needed on my end.

## Testing done

- `php -l` on all 15 changed files — no syntax errors.
- Diffed every file against a freshly re-staged live copy before committing — confirmed each diff touches only the intended block (the Newsletter section removal/addition, or the footer.php conditional wrap) and nothing else.
- Re-staged all 15 files from your device after committing and byte-for-byte verified every one matches exactly what was intended.

## Next steps

Push with:

```
cd "D:\IGO Groups Websites\Valluvam Products"
git add .
git commit -m "Restrict Newsletter/brand-affiliation sections to homepage only"
git push origin main
```

Once deployed, check a non-homepage page (like the cart or a category page) to confirm the Newsletter bar and "Part of the IGO Group" section are gone, and check the homepage to confirm they're still there (with the Newsletter now actually visible, since it was hidden before).

For the Dry Fruits/Millets links, that fix needs to happen in your database/admin panel as described above — let me know once you've updated those `link` values if you'd like me to verify the homepage tiles are working correctly afterward.
