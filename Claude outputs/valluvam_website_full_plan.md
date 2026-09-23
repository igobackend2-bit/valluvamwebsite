# Valluvam Products Website — Full Fix & Build Plan (Benchmarked Against Real Competitors)

Site reviewed live today: https://valluvamproducts.com/
Compared against three real, currently-operating competitors in the same category — **Happilo** (nuts/dry fruits), **Two Brothers Organic Farms** (farm-to-home oils, rice, ghee, spices — the closest match to Valluvam's exact product mix), and general findings on the category.

This is analysis and planning only. Nothing on the live site has been changed.

---

## 1. What I checked this site against

| Feature | Happilo | Two Brothers Organic Farms | Valluvam (this site) |
|---|---|---|---|
| Selectable weight/pack size with price change | Yes ("From ₹" pricing) | Yes (Atta: 10kg/5kg/2kg, Ghee: 5L/1L/500ml/250ml) | **Yes — already working correctly** (1kg–25kg selector updates price live) |
| Written customer reviews with count | Yes ("3763 reviews" shown per product) | Yes ("4.89 \| 2438 Reviews") | **No — only a bare number rating, no written reviews, no count** |
| Certifications / lab reports shown | "FSSAI Certified", "100% Vegetarian" badges | "Certified Glyphosate-Free", "FSSC 22000", lab reports linked | **No FSSAI badge, no lab report, no certification shown anywhere** |
| Loyalty / membership program | Not present | Yes — "Collective Membership ₹149/3 months + 15% off every order" | **No loyalty or membership program** |
| Subscription / auto-reorder | Not present | Not confirmed | **No** — a gap for both, but a real opportunity given rice/oil are repeat-purchase staples |
| Pincode delivery checker | Not present | Not confirmed | **No** — only a generic "Pan-India Delivery" claim |
| Stock indicator | Not confirmed prominently | Not confirmed | **No — nothing on product cards or product page** |
| WhatsApp click-to-chat | Not confirmed | Not confirmed | **Yes — floating WhatsApp widget already present and working** |
| Social links | Instagram feed embedded | Present | **Yes — Instagram linked in footer** |
| Wholesale/bulk ordering path | Buried in nav | Not a focus | **Yes — dedicated, well-organized wholesale/B2B section already exists (retailers, hotels, caterers, distributors, resellers) — this is already better than both competitors** |
| Order tracking | Not confirmed | Not confirmed | Has a "Track Your Order" link in footer — **present, good** |
| Secure payment messaging | Present | Present | **Yes — Razorpay badge and messaging already clear** |

**Bottom line:** Valluvam is already ahead of these competitors on two things — the weight/price selector works correctly, and the wholesale/B2B section is more organized than what Happilo or Two Brothers show. What's missing is almost entirely the trust layer that food-category shoppers specifically look for: visible certifications (FSSAI, lab checks), real written reviews with counts, a loyalty/repeat-purchase mechanism, and a couple of confirmed live bugs described below that hurt the browsing experience before a shopper even reaches a product.

---

## 2. Confirmed bugs and gaps, checked live today

1. **All 14 homepage category cards read identically as "Explore Category"** with no visible category name — a shopper sees a grid of product photos (rice bags, an oil bottle, spice jars, etc.) with the same generic button text under each, and has to guess which card is "Rice" vs "Spices" vs "Oils" from the photo alone. There's no visible label.
2. **A large empty white gap** appears between the hero banner and the "Quality Products / Fresh Packaging / Secure Checkout / Pan-India Delivery" strip on first load — looks like a spacing/layout issue, not intentional whitespace.
3. **Product rating displays as "4.5.0"** on the product detail page (checked on Seeraga Samba) instead of a normal "4.5" or "4.5 ★" — reads as a formatting bug, not a real rating format.
4. **The sticky header (logo + search bar) takes up a very large share of the visible screen** on a narrower/mobile-width view, pushing all actual content down — worth a dedicated mobile-view QA pass to confirm how much of the screen this consumes on real phones.
5. **No written reviews anywhere** — the homepage shows four testimonial quotes (Nirmal, Pari, Santhosh, Sanjay) with no photos, dates, order verification, or way to tell if they're real customers or placeholder copy; product pages show only a number rating with nothing behind it.
6. **No FSSAI license number, quality certification, or lab-report link** shown anywhere on the site, despite this being a food business — this is one of the strongest trust signals in this exact category and both competitors lead with it.
7. **No stock status** ("In Stock" / "Low Stock" / "Sold Out") on product cards or the product page.
8. **No pincode-based delivery checker** — only a generic "Pan-India Delivery" claim, with no way for a shopper to confirm serviceability or estimated delivery time for their own location before ordering.
9. **No loyalty, rewards, or subscription/auto-reorder option** — a real gap for a repeat-purchase category (rice, oil, spices are things people reorder monthly), and something Two Brothers Organic Farms already monetizes with its membership program.

---

## 3. The full "build fresh" plan, phased

### Phase 1 — Fix confirmed bugs (do these first, they're broken today, not just missing)
- Fix the "4.5.0" rating display bug on product pages.
- Fix the empty white gap below the hero banner.
- Investigate and reduce the sticky header's screen footprint on narrow/mobile viewports.
- Add a visible category name label to each of the 14 homepage category cards, so shoppers don't have to guess from the photo alone.

### Phase 2 — Add the trust layer this category expects (low effort, high impact)
- Add an FSSAI license number badge/footer mention, plus any relevant quality certification Valluvam already holds — both benchmarked competitors lead with this and it's expected in the food category.
- Add real written customer reviews (name, photo optional, verified-purchase tag, written text) with a visible count next to the star rating on every product page — matching the pattern Happilo (3763 reviews) and Two Brothers (2438 reviews) both use to build confidence.
- Add stock status labels to product cards and the product page.
- Add a pincode-based delivery/serviceability checker on the product page, since "Pan-India Delivery" alone doesn't tell a shopper their own timeline.

### Phase 3 — Build repeat-purchase mechanics (this is where Valluvam can pull ahead)
- Launch a simple loyalty/rewards or membership program — Two Brothers already proves this works in this exact category ("₹149/3 months + 15% off every order"); given rice/oil/spices are naturally repeat-purchase items, this is a strong fit.
- Add a subscription/auto-reorder option for staples (e.g., "reorder every 30 days" for rice or oil) — neither competitor checked has this yet, so it would be a genuine point of difference rather than catch-up.
- Add simple bulk-pricing tiers visible directly on the wholesale page (e.g., "10kg+ = ₹X/kg, 25kg+ = ₹Y/kg") so a business buyer doesn't have to enquire just to see pricing — this builds on the wholesale section that's already Valluvam's strongest existing advantage over both competitors.

### Phase 4 — Round out the product page
- Add "customers who bought this also bought" cross-sell (the "You may also like" block already exists — this would make it smarter/contextual rather than just showing other items in the same category).
- Consider showing simple sourcing/traceability info per product (where the rice/oil/spice batch came from, when it was packed) — this plays to the same "purity and tradition" story already on the About section, the way Two Brothers uses lab reports and glyphosate-free certification to back up similar claims.

### Phase 5 — Verify what already exists but wasn't fully checked
- Confirm guest checkout works (not verified in this pass since the cart was empty during testing) — if login is mandatory before checkout, that should be treated as a Phase 2 item instead.
- Confirm the "Order Status" / "Track Your Order" links in the footer actually return real tracking info.
- Confirm the four homepage testimonials are real, attributed customers, and consider adding order-verification badges to them if so.

---

## 4. Suggested order to hand to a developer

1. Phase 1 — these are live bugs today, cheapest to fix, and visible on the very first screen every visitor sees.
2. Phase 2 — trust signals that directly affect whether a first-time buyer completes a purchase in this category.
3. Phase 3 — the biggest revenue opportunity, since it turns one-time buyers into repeat buyers and strengthens the wholesale channel that's already working well.
4. Phase 4 — polish once the fundamentals are solid.
5. Phase 5 — a quick click-through QA pass, can happen anytime in parallel.

Let me know which phase or specific item you want scoped into actual code changes first, and I'll put together the implementation details for just that piece.
