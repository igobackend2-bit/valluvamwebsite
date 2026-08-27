# Valluvam Website

E-commerce site for Valluvam — millets, nuts, oils, dry fruits, spices and combo packs — built in plain PHP with a MySQL backend, Razorpay checkout, and a small custom admin panel. No framework, no build step for the frontend.

## Tech stack

- **Backend:** PHP 8.2, PDO/MySQLi against MySQL
- **Payments:** Razorpay (`razorpay/razorpay` via Composer)
- **Email:** PHPMailer (`phpmailer/phpmailer`)
- **Frontend:** Server-rendered PHP pages, Bootstrap, jQuery, AOS, Owl Carousel
- **Deployment:** Docker (`php:8.2-apache`), Apache with `mod_rewrite`

## Run it locally

\```bash
composer install
cp .env.sample .env   # if present — otherwise create .env, see Configuration below
\```

Point Apache/PHP's document root at the project root (`index.php` lives there), or use PHP's built-in server:

\```bash
php -S localhost:8000
\```

Import `seed_products.sql` into your local database to get a working `product_details` / `product_category` schema with sample data that matches the images already in `assets/uploads`.

### Docker

\```bash
docker build -t valluvam-website .
docker run -p 8080:80 valluvam-website
\```

The `Dockerfile` installs Composer dependencies in a build stage, copies the app into `php:8.2-apache`, enables `mod_rewrite`, and points Apache's document root at `/app`.

## Configuration

Database and payment credentials are read from environment variables via a `.env` file at the project root, loaded by `assets/db_query/load_env.php` (a tiny hand-rolled parser — no external dotenv dependency). `.env` is git-ignored; create your own with:

\```
DB_HOST=localhost
DB_NAME=valluvamdb
DB_USER=root
DB_PASS=

RAZORPAY_MODE=test          # or "live"
RAZORPAY_KEY_ID=...
RAZORPAY_KEY_SECRET=...
RAZORPAY_LIVE_KEY_ID=...
RAZORPAY_LIVE_KEY_SECRET=...
APP_ENV=development         # "production" also forces live Razorpay mode
\```

`assets/db_query/razorpay.php` falls back to bundled Razorpay **test** keys if `.env` values aren't set, so the site still works out of the box in test mode — set `RAZORPAY_MODE=live` (or `APP_ENV=production`) with real live keys before going to production.

Database connection defaults (used if the corresponding env var is unset) live in `assets/db_query/config.php`: host `localhost`, database `valluvamdb`, user `root`, empty password — override all of these via `.env` for anything other than local dev.

## Structure

\```
index.php, about.php, shop.php, product.php, productdetail.php,
cart.php, checkout.php, wishlist.php, combo.php,
millets.php, nuts.php, oils.php, dryfruits.php, spices.php,   Storefront pages
b2b-wholesale.php, blog.php, contact.php, service.php,
privacy.php, term.php, return.php, review.php,
order_tracking.php, login.php, logout.php

header.php, footer.php, popup.php                              Shared layout includes

admin/                                                          Custom admin panel
├── index.php, login.php, logout.php
├── products.php, orders.php
└── includes/    check_admin.php, sidebar.php

api/order/                                                      Order + payment endpoints
├── create.php
└── payment/

assets/
├── db_query/    Per-feature PHP/AJAX endpoints (cart, checkout, order,
│                 product_detail, search, wishlist, b2b, combo, login,
│                 contact, header, index, shop, admin, ...), plus
│                 config.php, load_env.php, razorpay.php
├── uploads/      User/admin-uploaded images (product photos, etc.)
├── thumbnail/     Generated/product thumbnail images
├── b2b_enquiries/ B2B enquiry form submissions (needs write permission)
└── js/            Frontend JS specific to this site's features

css/, js/, fonts/, scss/    Bootstrap + vendor plugin assets, template CSS/JS
images/, video/              Static site imagery and video assets
seed_products.sql            Local dev seed data for product_details / product_category
composer.json                 razorpay/razorpay, phpmailer/phpmailer
\```

## Known issues / cleanup notes

- **Nested duplicate copies:** `valluvam/` at the repo root contains a near-complete second copy of the entire site, and `valluvam/valluvam/` inside that contains a *third* copy. These look like accidental duplicate uploads rather than intentional structure — worth confirming which copy is actually deployed and removing the others, both to shrink the repo (currently ~850 MB, driven heavily by `images/`) and to avoid editing the wrong copy by mistake.
- **Duplicate/near-duplicate image files:** `images/` has many files repeated as `"name - Copy.jpg"`, `"name - Copy - Copy.jpg"`, etc. — candidates for cleanup.
- `PATH_VERIFICATION.md` documents a past pass of fixing hardcoded Windows paths to `__DIR__`-relative ones and lists a pre-go-live checklist (file permissions on `assets/uploads/` and `assets/b2b_enquiries/`, live Razorpay keys, `composer install` on the server, session/error-log config) — worth re-checking before any new deploy.
- No automated tests and no frontend build step; CSS/JS assets are edited and served as-is.
