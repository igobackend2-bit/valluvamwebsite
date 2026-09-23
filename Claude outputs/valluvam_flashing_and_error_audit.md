# Valluvam — "Flashing" Escalation + Full Error Audit
Tested live on valluvamproducts.com (desktop + mobile 375px) on 23 Sep 2026, plus a review of the local code. Nothing was changed.

## A. Root causes of the flashing / jumping when a customer clicks

| # | Cause | Evidence | Fixable? |
|---|---|---|---|
| A1 | **White full-screen loader placed at the END of every page.** `<div id="ftco-loader" class="show fullscreen">` sits after the footer (e.g. index.php line ~1196). The browser paints the page first, then reaches this div and covers the page with a white overlay (fixed, z-index 1000, fades in over 0.4s). main.js then removes it and it fades out. Result: content → white flash → content on **every page load / every menu click**. | Loader is element 297 of 320 in body; computed style: white, fixed, full-screen, 0.4s opacity transition. | Yes. Remove the loader div, or move it to the top of `<body>`. Low risk. |
| A2 | **Every page loads CSS and JS 2–6 times.** Each page is 3 HTML documents glued together (`header.php` outputs its own `<html><head><body>`, then the page outputs another). Homepage: **100 stylesheet tags**. style.css ×6, animate.css ×5, and 4 different Font Awesome versions (4.7, 5.11, 5.15, 6.7). CSS arriving in the middle of the body makes the page repaint/restyle as it loads (flash of unstyled content). | Measured in browser: 100 `<link rel=stylesheet>`, 6 inside `<body>`, 27 render-blocking scripts in body. | Yes. Deduplicate into one `<head>` in header.php. Medium effort, needs care. |
| A3 | **jQuery and Bootstrap loaded twice** (jQuery 3.6.0 CDN + jQuery 3.2.1 local; Bootstrap 4.0 CDN + local bootstrap.min.js). Both copies attach click handlers, so one tap can open and immediately close dropdowns/menus. When tested with a tap without hover (like a phone), the "Shop" dropdown opened and closed in the same instant. The hamburger menu also has a third handler in main.js. | Script list captured in browser; mutation log on the Shop dropdown. | Yes. Keep one jQuery + one Bootstrap. |
| A4 | **Every internal link points to `xxx.php`**, which `.htaccess` then 301-redirects to `/xxx`. Every click = 2 page requests, with the address bar changing. Also `www.` is redirected to the non-www domain by the host, while `<base>`, canonical, sitemap and llms.txt all use `www.`. | 65 `.php` links on homepage; navigation to www landed on non-www. | Yes. Change links to clean URLs. |
| A5 | **Products are drawn by AJAX after the page paints**, so each category page first shows an empty grid, then cards pop in and the page jumps. Icons (ionicons from unpkg) also pop in after. | Products AJAX starts ~400ms after first paint. | Partly. Add fixed-height skeleton cards so nothing jumps. |
| A6 | **Scroll hijacking / "running" feeling.** main.js adds a wheel-scroll "dampening" script (added in an earlier session, and not yet confirmed live when it was added), plus Stellar parallax, Scrollax, AOS and `ftco-animate` fade-ins on every section. Together they make scrolling feel like the page is moving on its own. | js/main.js | Yes. Remove the dampening script and reduce animation. |

## B. Broken functionality (customer-facing)

1. **CRITICAL: product detail pages are blank.** header.php outputs `<base href="https://www.valluvamproducts.com/">` on product pages, but the site actually runs on `valluvamproducts.com` (no www). So the product data request, cart count and icon fonts are cross-origin and **blocked by CORS**. Tested `/nuts/almonds`: the page shows only the banner, with no product, price or Add to Cart. Icons show as empty squares. Customers who click any product see an empty page. **Fix:** change the base to `<base href="/">`. One line, and this is the top priority.
2. The site contact email in the top bar is `info.thefarmersfactory@gmail.com`, which is another brand's address.
3. The Dry Fruits / Millets homepage tiles still link wrongly. This is data in the `product_category.link` column (from the earlier session).
4. The flat ₹3 "discount" is hard-coded on every order.
5. The login popup auto-opens after 10s on the homepage (by design, but it adds to the "things jumping" feeling).

## C. Security issues found live (URGENT)

1. **`https://valluvamproducts.com/.env` is publicly downloadable.** It contains the live Razorpay secret, DB password and SMTP password. (I only read the key names, not the values.) **Rotate all of these credentials now**, then block the file.
2. **`/.git/` is publicly downloadable.** `.git/config` contains an access token in the remote URL, and the whole source code and history can be pulled. Revoke that token.
3. `/save` (no login) accepts product edits and file uploads. That's an arbitrary upload, so someone could upload a PHP file and run code on the server.
4. `/assets/db_query/cleanup_invalid_users.php` is public and can delete users via a link.
5. The admin login is hard-coded `admin` / `admin123`.
6. `/fetch` exposes product data and contains hard-coded DB credentials in the source.
7. `README.md`, `Dockerfile`, `composer.json`, `seed_products.sql` and a 117 MB `images.zip` are all publicly served.
8. PHP warnings are shown to visitors (`display_errors=1`, e.g. `/save`).
9. Order emails include unescaped customer input.

## D. Leftover / junk pages live on the site
`/milets`, `/product`, `/add_product` (lorem ipsum), `/new_product`, `/add_dashboard`, `/login` (blank page). These should be removed or blocked.

## E. Recommended fix order
1. Block `.env`, `.git`, `*.md`, `*.sql`, `*.zip`, `Dockerfile`, `composer.*` in `.htaccess`, and **rotate every secret**.
2. Fix `<base href="/">` so product pages work again.
3. Remove or relocate the end-of-page loader (the main white flash).
4. Remove the duplicate jQuery/Bootstrap and duplicate CSS, and make one clean `<head>`.
5. Point links to clean URLs; pick www or non-www and use it everywhere.
6. Lock down `save.php`, the cleanup script and the admin password; delete junk pages; turn off display_errors.
7. Replace the scroll-dampening script and trim the animations; add skeleton loaders for product grids.
