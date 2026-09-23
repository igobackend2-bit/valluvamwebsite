# Farmers Factory (famersfactory.com) — Admin & Main Site Review, and What It Means for Valluvam

I checked both the live site and the actual GitHub repo you gave me (`igobackend2-bit/ffwebsite`, a public repo — I cloned it directly). Before anything else: I found a live, serious security problem on this site that needs attention regardless of any other plans. I'm putting that first.

---

## URGENT — Farmers Factory's admin panel currently has no real security

The repo itself contains an internal audit document (`CODE_AUDIT_2026-06-25.md`) that already found this, and I checked the live code — as of the most recent commit (September 5, 2026, about a week ago), **none of it has been fixed yet**:

1. **A live database master key is committed in the repo, in plain text, across 10 files.** This key bypasses all access restrictions — anyone with this key has full read/write/delete access to every table in Farmers Factory's database (orders, customers, everything). Because this is a public GitHub repository, that key is currently exposed to anyone who looks at the repo.
2. **The real admin login (email + password) is hardcoded directly in the website's own code**, which is sent to every visitor's browser. Anyone who opens their browser's developer console on the site can log in as admin using that code, without ever seeing a login screen.
3. **The "admin password" is stored in the database in a way anyone can read**, even without logging in.
4. **Getting into the admin area itself is done with a browser cookie that anyone can set by hand** — it doesn't actually check who you are on the server.

Put simply: right now, anyone who finds this GitHub repository (which is public) or knows how to open their browser's developer tools can get full admin access to Farmers Factory's live database. This is not a "nice to have" fix — this is the kind of thing that should be handled immediately by whoever manages that site's hosting/database (rotating the database key, removing the hardcoded login, and fixing how the admin area checks who's logged in). I have not used or shared the exposed key, and I'd strongly recommend not leaving this repo public until it's fixed.

I'm flagging this because it's part of the same IGO Group family as Valluvam, and it's a live risk today, not a future-improvement item.

---

## What Farmers Factory's admin panel has that Valluvam's doesn't

Setting the security issue aside, the actual feature set of this admin panel is far more complete than Valluvam's, and it's a genuinely useful reference for what a "professional" admin looks like. Farmers Factory's admin has 16 sections: **Dashboard, Orders, Products, Customers, Leads/Inquiries, Inventory, Coupons, Farmers (supplier info), Reviews (with moderation), Banners, Notifications, Post-Delivery Feedback, Farm Stories, Live Streams, Settings, and Account-Deletion Requests.**

Valluvam's current admin (I checked this directly too) has only **4: Dashboard, Login, Orders, and Products.**

Specifically missing from Valluvam's admin, benchmarked against this reference:

- **Inventory / stock management** — Farmers Factory tracks real stock per product, automatically deducts it when an order is placed, prevents overselling with proper database locking, and automatically restores stock if an order is cancelled. This is the exact feature you asked me to add. It's a genuinely solid pattern to copy: it needs (a) a new stock-quantity column on the product table, (b) an admin screen to view/edit stock per product, (c) logic that reduces stock on order and restores it on cancellation. This is real backend + database work, not a frontend-only change — consistent with what I flagged earlier when your bigger refinement brief asked for stock status without wanting schema changes.
- **Coupons** — a discount-code system with admin management. Valluvam has no coupon system at all today.
- **Customers (CRM)** — a dedicated customer list/lookup in admin, separate from just seeing names on orders.
- **Leads/Inquiries tracking** — captures signups and contact-form/B2B enquiries into one manageable list, with a manual "converted to customer" flag.
- **Reviews with moderation** — an actual review system (submit, approve/reject, display), which is the single most important missing piece I flagged for Valluvam earlier (Valluvam currently has 4 hardcoded homepage testimonials and no real review system at all).
- **Banners management** — Farmers Factory's homepage banners are editable from the admin panel; Valluvam's are hardcoded directly in `index.php`, meaning every banner text change has needed a code edit and deploy (exactly what we've been doing manually in this conversation).
- **Post-delivery feedback system** — an automatic survey email after delivery, with results in the admin dashboard.
- **Notifications** — an in-admin alert system (e.g., a sound alert for new orders, per their latest commit "Use loud alarm-style siren for new order alerts").
- **Settings page** — a central place for site-wide configuration instead of values hardcoded across multiple files.
- **Account-deletion requests** — a compliance feature for handling customer data-deletion requests.

---

## What Farmers Factory's main (customer-facing) site has that's worth noting for Valluvam

- **Real, working stock badges** ("SOLD OUT", "NOT IN STOCK", "ADD TO BASKET") are live on the homepage today — proof this pattern works end-to-end once the inventory backend exists. This directly answers the stock-status question from your earlier brief: it's very achievable, it just needs the inventory system built first, in the same order Farmers Factory did it.
- **Discount badges show the actual rupee amount saved** ("SAVE ₹27"), not just a percentage — a small but nice touch worth considering for Valluvam's product cards.
- **A dedicated FSSAI license page** (`/fssai`) — directly relevant to something I flagged as missing on Valluvam (no visible FSSAI/certification info anywhere).
- **More complete legal/trust page set**: delivery info, help center, returns policy, cookie policy — Valluvam has some of these but not all (no dedicated delivery-info or cookie-policy page, for example).
- **A real post-purchase review submission flow**, not just static testimonials.
- Interestingly, **Farmers Factory's own storefront already sells "Valluvam Products" as one of its three categories** (alongside Vegetables and Fruits) — worth knowing, since it means these two sites are already commercially linked, not just visually similar IGO Group siblings.

---

## Important caveat: don't copy everything from this reference blindly

The repo's own audit documents are refreshingly honest about real problems, and they're worth knowing before treating this as a perfect template:

- A **"Harvest Impact" sustainability widget** on product pages shows carbon/water/mileage figures that are, by the repo's own admission, **placeholder numbers — one of them is literally randomized on every page load**, not real measured data. This is a pattern to avoid, not copy — Valluvam should never show fabricated environmental or health claims dressed up as real data.
- **Outgoing order emails may land in spam**, because they're sent through a personal Gmail account rather than a properly verified business domain.
- The "leads" (signups/enquiries) list doesn't automatically mark someone as a converted customer once they order — that's still a manual admin step there too.
- The codebase itself has real technical debt (dozens of one-off patch/debug scripts left in the repo root, a single 113KB translation file with a history of silent duplicate-key bugs, two different deployment configs that disagree with each other).

None of this changes the recommendation to build Valluvam's inventory/coupons/reviews/CRM features — it just means "build it properly, the way the good parts of this reference do it," not "copy this codebase wholesale."

---

## Combined plan: what to implement for Valluvam, admin + main site

### Admin pages — phased
1. **Inventory/stock management** (the one you specifically asked for): add a stock-quantity column to the product table, an admin screen to set/edit it per product, and logic to reduce stock on order placement and restore it on cancellation, following the oversell-safe pattern Farmers Factory uses (row-locking so two simultaneous orders can't both buy the last unit). This unlocks real stock badges on the main site as a follow-on step.
2. **Reviews with moderation** — a real submission + admin-approval system, replacing the 4 hardcoded homepage testimonials. This also unlocks the per-product review counts and ratings flagged as missing in earlier reviews of Valluvam.
3. **Coupons** — an admin-managed discount-code system, feeding into the cart page.
4. **Banners management** — move the homepage hero banner content out of hardcoded PHP and into an admin-editable screen, so future banner/text changes don't need a code deploy (directly solves the workflow we've been doing manually throughout this conversation).
5. **Customers (CRM) + Leads** — a dedicated customer list and an inquiries/leads tracker (capturing signups and contact-form/B2B enquiries in one place).
6. **Settings page** — centralize site-wide values (contact info, social links, etc.) that are currently hardcoded across multiple files, so they can be updated in one place.
7. **Notifications** — an in-admin alert for new orders.

### Main site — phased
1. Once inventory exists: add real stock badges ("In Stock" / "Sold Out") to product cards and the product page.
2. Once reviews exist: show real per-product ratings and written reviews, and only then add review-based structured data (`AggregateRating`) — matching the caveat from the earlier review of your bigger refinement brief.
3. Add an FSSAI license page/badge, following Farmers Factory's pattern.
4. Add a coupon field to the cart once the coupon system exists in admin.
5. Fill out the legal/trust page set (delivery info, cookie policy) to match.
6. Everything from the earlier "professional refinement" review (header mega-menu, announcement bar, hero, category cards, product cards, Quick View, Rice sorting, footer, SEO/structured data, design system, mobile) stays valid and can proceed independently of this admin work, since none of it depends on the inventory/reviews/coupon systems.

### Sequencing recommendation
Do the frontend-only refinements (from the earlier review) first — they're safe, fast, and don't touch the database. In parallel or after, tackle Admin items 1-2 (inventory, reviews) first since they unlock the most-requested main-site features (real stock badges, real reviews) that were previously blocked. Coupons, CRM/leads, banners-management, settings, and notifications can follow in any order based on which saves you the most manual work day-to-day.

Let me know which piece you want scoped in detail first — my suggestion would be inventory, since you asked for it directly and it's also the one Farmers Factory's own repo proves works well when built correctly (with the oversell protection included, not a naive stock counter).
