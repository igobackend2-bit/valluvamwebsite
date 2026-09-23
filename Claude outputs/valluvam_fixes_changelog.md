# Valluvam — Fixes applied 23 Sep 2026 (not yet deployed)

These are targeted fixes only; no other code, design, cart, pricing or payment logic was changed.

| File | Fix |
|---|---|
| header.php | Moved the `userStatus` `<script>` from above `<!DOCTYPE html>` into `<head>` (it forced every page into quirks mode). Product-page `<base>` changed from `https://www.valluvamproducts.com/` to `/` (product pages were blank because of CORS). Hid the end-of-page white `#ftco-loader` (the white flash on every click). Removed duplicate CDN popper + Bootstrap 4.0 (pages already load their own copy; two copies made menus open and close on one tap). Bumped the login.js cache version. |
| .htaccess | Returns 404 for `.env`, `.git/`, all dotfiles, `*.md/.sql/.zip/.bat/.lock/.config/.log`, `Dockerfile`, `composer.*`, `.txt` (except robots/llms; contact-form .txt files were public), `vendor/`, the duplicate `valluvam/` copy, the duplicate nested asset folders, any script inside `assets/uploads/`, and the unused/unsafe pages: save, fetch, add_db, add_product, add_dashboard, fix_rice_category, milets, product, cleanup_invalid_users. Tested on Apache: blocked URLs return 404; shop, product pages, robots, llms, sitemap, admin and JS all still load. |
| login.php | Was a blank page that logged the user out. Checkout and order tracking send logged-out customers there. Now redirects to `/?login=1`. |
| assets/js/login/login.js | Opens the login popup straight away when the URL has `?login=1`. |
| new_product.php | Requires the admin login (it was open to anyone). |
| assets/db_query/new_product/new_product_query.php | Add/delete product now requires the admin login; uploads are limited to real JPG/PNG/WEBP/GIF images. |
| assets/db_query/admin/login.php | Username/password read from `.env` (`ADMIN_USERNAME`, `ADMIN_PASSWORD`), with a safer compare. The old values stay as a fallback until you set them. |
| 22 files in assets/db_query + fetch.php | `display_errors` 1 → 0 (PHP errors were shown to visitors and could break JSON responses). The one-word change is the only edit in these files. |

## You must do
1. Deploy: `git add . && git commit -m "Fix flashing, blank product pages, and exposed files" && git push`, then Ctrl+Shift+R.
2. **Rotate every secret that was in the public `.env`**: Razorpay live key/secret, DB password, SMTP (Gmail app) password. Also revoke the access token in `.git/config`'s remote URL. Treat all of them as leaked.
3. Add `ADMIN_USERNAME=...` and `ADMIN_PASSWORD=...` (a strong one) to the server's `.env`.

## Not changed (need your decision)
- Top-bar email `info.thefarmersfactory@gmail.com`: is this correct for Valluvam?
- Canonical/sitemap/llms.txt use `www.`, but the host redirects www to the non-www domain. Pick one in hosting and align.
- Duplicate CSS files per page (100 stylesheet tags on the homepage) and the second jQuery copy: the flicker from these is minor now that the loader is hidden. Cleaning them up means editing every page.
- Flat ₹3 discount in order code; unescaped customer text in order emails.
- Dry Fruits / Millets homepage tile links (database `product_category.link`).
