function parseQuantityInfo(q) {
    q = String(q || '').trim().toLowerCase();
    var m = q.match(/([\d.]+)\s*(kg|g|gm|gram|grams|ml|l)?/);
    if (!m || !m[1]) return null;
    var num = parseFloat(m[1]);
    var unit = m[2] || 'g';
    if (unit === 'kg') return { type: 'weight', kg: num };
    if (unit === 'ml') return { type: 'volume', litres: num / 1000 };
    if (unit === 'l') return { type: 'volume', litres: num };
    // g / gm / gram / grams
    return { type: 'weight', kg: num / 1000 };
}

function formatVolumeLabel(litres) {
    if (litres < 1) return Math.round(litres * 1000) + 'ml';
    var rounded = Math.round(litres * 100) / 100;
    return rounded + 'L';
}

function loadProduct(idOrSlug, pushSlug) {
    $.ajax({
        url: 'assets/db_query/product_detail/product_detail_query.php',
        type: 'GET',
        data: {
            id: idOrSlug
        },
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let p = res.data.product;
                let similar = res.data.similar;
                let variants = res.data.variants || [];

                // Keep the page's global productId (used by the wishlist button and
                // by header.js) pointed at whichever size is currently shown.
                productId = String(p.id);
                if (pushSlug) {
                    try {
                        history.pushState({}, '', productUrl(p.category, p.product_name));
                    } catch (e) { /* pushState unsupported - address bar just won't update, page still works */ }
                }

                let hasDiscount = p.dis_price && p.price && parseFloat(p.dis_price) < parseFloat(p.price);
                let discountPercent = hasDiscount ? Math.round((1 - p.dis_price / p.price) * 100) : 0;
                let discountBadge = hasDiscount ? `<span class="pd-discount-badge">${discountPercent}% OFF</span>` : '';
                // Was appending a literal ".0" to whatever rating value came back from the
                // database, so a stored "4.5" displayed as "4.5.0". parseFloat + toFixed(1)
                // formats any stored value (e.g. "4", "4.5") to one decimal place correctly.
                let ratingValue = p.rating ? parseFloat(p.rating).toFixed(1) : null;
                let ratingBlock = ratingValue ? `<div class="pd-rating">${'<ion-icon name="star"></ion-icon>'.repeat(Math.round(ratingValue))}<span>${ratingValue}</span></div>` : '';
                let benefitsList = (p.benefits || '').split(',').map(b => b.trim()).filter(Boolean);

                // Size selector: only shown when this product has sibling size variants
                // (e.g. the same rice available as 1kg / 5kg / 10kg / 25kg as separate,
                // individually-priced products in the admin panel). Picking one swaps the
                // price/details on this same page (no reload), using the exact price set
                // for that size in admin.
                let sizeSelectorHtml = '';
                if (variants.length > 1) {
                    sizeSelectorHtml = `
                        <div class="pd-size-row">
                            <label>Size</label>
                            <div class="pd-size-options">
                                ${variants.map(v => `
                                    <button type="button" class="pd-size-btn${v.is_current ? ' active' : ''}" data-id="${v.id}" data-slug="${v.slug}">${v.quantity}</button>
                                `).join('')}
                            </div>
                        </div>`;
                }

                // Weight/volume calculator: lets the shopper pick a preset amount and see
                // an automatically-calculated price for it, based on this product's own
                // price-per-kg (for products sold by weight, like rice) or price-per-litre
                // (for products sold by volume, like oils). Replaces the plain 1/2/3
                // quantity counter. Note: this total is a display estimate only - Add to
                // Cart / Buy it Now still add this exact listed product at its own listed
                // price and pack size (same as the old quantity counter did).
                let qInfo = parseQuantityInfo(p.quantity);
                let presets = null, defaultPreset = null, rate = null, labelFn = null, rowLabel = 'Select Weight';

                if (qInfo && qInfo.type === 'weight' && parseFloat(p.dis_price)) {
                    presets = [1, 2, 5, 10, 25];
                    rate = parseFloat(p.dis_price) / qInfo.kg;
                    labelFn = function (w) { return w + 'kg'; };
                    rowLabel = 'Select Weight';
                    defaultPreset = presets.reduce(function (closest, w) {
                        return Math.abs(w - qInfo.kg) < Math.abs(closest - qInfo.kg) ? w : closest;
                    }, presets[0]);
                } else if (qInfo && qInfo.type === 'volume' && parseFloat(p.dis_price)) {
                    presets = [0.25, 0.5, 1, 2, 5];
                    rate = parseFloat(p.dis_price) / qInfo.litres;
                    labelFn = formatVolumeLabel;
                    rowLabel = 'Select Volume';
                    defaultPreset = presets.reduce(function (closest, w) {
                        return Math.abs(w - qInfo.litres) < Math.abs(closest - qInfo.litres) ? w : closest;
                    }, presets[0]);
                }

                let defaultCalcTotal = (rate !== null && defaultPreset !== null) ? Math.round(rate * defaultPreset) : null;
                let defaultPriceUnitLabel = (presets && labelFn && defaultPreset !== null) ? labelFn(defaultPreset) : p.quantity;

                // Stock / availability (see build_stock_info() in product_detail_query.php).
                // p.in_stock / p.low_stock / p.physical_stock_label are computed server-side
                // from product_details.stock and the pack-size text in `quantity`.
                let inStock = p.in_stock !== false;
                let stockBadgeHtml = !inStock
                    ? `<span class="pd-stock-badge pd-out-of-stock">Out of Stock</span>`
                    : (p.low_stock ? `<span class="pd-stock-badge pd-low-stock">Only ${p.physical_stock_label} left</span>` : '');
                let buyDisabledAttr = !inStock ? 'disabled aria-disabled="true"' : '';

                let quantitySectionHtml;
                if (presets) {
                    quantitySectionHtml = `
                        <div class="pd-weight-row">
                            <label>${rowLabel}</label>
                            <div class="pd-weight-options" data-rate="${rate}">
                                ${presets.map(w => `
                                    <button type="button" class="pd-weight-btn${w === defaultPreset ? ' active' : ''}" data-amount="${w}" data-label="${labelFn(w)}">${labelFn(w)}</button>
                                `).join('')}
                            </div>
                        </div>`;
                } else {
                    // Fallback for a product whose quantity text we can't parse into a
                    // weight or volume (e.g. "1 pack") - keep the original plain counter.
                    quantitySectionHtml = `
                        <div class="pd-qty-row">
                            <label>Quantity</label>
                            <div class="pd-stepper">
                                <button type="button" class="pd-qty-minus" aria-label="Decrease quantity">&minus;</button>
                                <span class="pd-qty-value" data-unit-price="${p.dis_price}">1</span>
                                <button type="button" class="pd-qty-plus" aria-label="Increase quantity">+</button>
                            </div>
                        </div>`;
                }

                // Build main product HTML
                let html = `
                <div class="pd-wrap">
                    <div class="pd-gallery">
                        <div class="pd-image-frame">
                            <img src="assets/uploads/${p.image}" class="pd-image" alt="${p.product_name}" />
                            <span class="pd-zoom-hint"><ion-icon name="search-outline"></ion-icon> Hover to zoom</span>
                        </div>
                    </div>
                    <div class="pd-info">
                        <span class="pd-category">${p.category ? p.category : 'Valluvam'}</span>
                        <h1 class="pd-title">${p.product_name}</h1>
                        ${ratingBlock}
                        <div class="pd-price-row">
                            ${hasDiscount ? `<span class="pd-price-old">&#8377;${p.price}</span>` : ''}
                            <span class="pd-price-new">&#8377;${defaultCalcTotal !== null ? defaultCalcTotal : p.dis_price}</span>
                            ${defaultPriceUnitLabel ? `<span class="pd-price-unit">/ ${defaultPriceUnitLabel}</span>` : ''}
                            ${discountBadge}
                        </div>
                        ${stockBadgeHtml ? `<p class="pd-price-hint">${stockBadgeHtml}</p>` : ''}
                        <p class="pd-price-hint">Inclusive of all taxes</p>
                        ${sizeSelectorHtml}
                        ${quantitySectionHtml}
                        <div class="pd-actions">
                            <button class="pd-btn pd-btn-cart cart" id="add-to-cart" data-id="${p.id}" ${buyDisabledAttr} aria-label="Add ${p.product_name} to cart">${!inStock ? 'Out of Stock' : '<ion-icon name="cart-outline"></ion-icon> Add to Cart'}</button>
                            <a href="${!inStock ? '#' : 'cart.php'}" class="pd-btn pd-btn-buy buy${!inStock ? ' pd-btn-disabled' : ''}" data-id="${p.id}" ${buyDisabledAttr} aria-label="Buy ${p.product_name} now">${!inStock ? 'Out of Stock' : '<ion-icon name="flash-outline"></ion-icon> Buy it Now'}</a>
                            <button class="pd-btn-wishlist wishlist wishlist-btn" data-product-id="${productId}" aria-label="Add ${p.product_name} to wishlist" title="Add to wishlist"><ion-icon name="heart-outline"></ion-icon></button>
                        </div>
                        <div class="pd-trust">
                            <div class="pd-trust-item"><ion-icon name="ribbon-outline"></ion-icon> Quality Products</div>
                            <div class="pd-trust-item"><ion-icon name="lock-closed-outline"></ion-icon> Secure Payment</div>
                            <div class="pd-trust-item"><ion-icon name="rocket-outline"></ion-icon> Pan-India Delivery</div>
                            <div class="pd-trust-item"><ion-icon name="call-outline"></ion-icon> Customer Support</div>
                        </div>
                    </div>
                </div>

                <div class="pd-details-section">
                    <div class="pd-details-card">
                        <div class="pd-details-desc">
                            <h2>Product Information</h2>
                            <p>${p.description}</p>
                        </div>
                        <div>
                            <div class="pd-specs">
                                <div class="pd-specs-row">
                                    <span class="pd-specs-label">Category</span>
                                    <span class="pd-specs-value">${p.category ? p.category : '-'}</span>
                                </div>
                                <div class="pd-specs-row">
                                    <span class="pd-specs-label">Pack Size</span>
                                    <span class="pd-specs-value">${p.quantity ? p.quantity : '-'}</span>
                                </div>
                            </div>
                            ${benefitsList.length ? `
                            <div class="pd-highlights">
                                <h3>Highlights</h3>
                                ${benefitsList.map(b => `
                                    <div class="d-flex align-items-center">
                                        <span class="dot"></span>
                                        <span class="bullet-text">${b}</span>
                                    </div>
                                `).join('')}
                            </div>` : ''}
                        </div>
                    </div>
                </div>

                <div class="pd-reviews-section" data-product-id="${p.id}">
                    <h3>Customer Reviews</h3>
                    <div id="pd-reviews-list"><p class="pd-cell-sub">Loading reviews…</p></div>
                    <div class="pd-review-form">
                        <h4>Write a review</h4>
                        <div class="pd-review-stars" id="pd-review-stars" data-rating="0">
                            ${[1, 2, 3, 4, 5].map(n => `<span class="pd-star-input" data-star="${n}">&#9734;</span>`).join('')}
                        </div>
                        <input type="text" id="pd-review-name" class="pd-review-input" placeholder="Your name (optional)">
                        <textarea id="pd-review-text" class="pd-review-input" rows="3" placeholder="Share your experience with this product…"></textarea>
                        <button type="button" class="pd-btn pd-btn-cart" id="pd-review-submit">Submit Review</button>
                    </div>
                </div>

                <div class="pd-similar">
                    <h3>You may also like</h3>
                    <div class="pd-similar-grid">
                        ${similar.map(s => `
                            <a href="${productUrl(p.category, s.product_name)}" class="pd-similar-card">
                                <div class="pd-similar-img-frame">
                                    <img src="assets/uploads/${s.image}" alt="${s.product_name}" loading="lazy">
                                </div>
                                <div class="pd-similar-name">${s.product_name}</div>
                                <div class="pd-similar-price">&#8377;${s.dis_price}</div>
                            </a>
                        `).join('')}
                    </div>
                </div>
                `;

                $('#product-details-container').html(html);
                injectProductSchema(p, hasDiscount ? p.dis_price : p.price, ratingValue);
                injectStockAndReviewStyles();
                loadProductReviews(p.id);
            } else {
                $('#product-details-container').html('<p>Product not found.</p>');
            }
        }
    });
}

// Adds/updates a schema.org Product+Offer JSON-LD block for this page so search engines
// can show price/availability rich results. Frontend-only: reuses data already returned
// by the existing product_detail_query.php call above, no new backend endpoint. There is
// no real stock/inventory field in the product data yet, so availability is left as
// "InStock" (the same assumption the page's own Add to Cart button already makes) rather
// than invented. ratingValue is only the single stored rating field (see the ratingBlock
// fix above) - not a real review count - so no aggregateRating is added here; that should
// wait until a genuine review system exists.
function injectProductSchema(p, effectivePrice, ratingValue) {
    var existing = document.getElementById('pd-product-schema');
    if (existing) existing.remove();

    var schema = {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": p.product_name,
        "description": p.description || undefined,
        "category": p.category || undefined,
        "image": p.image ? (window.location.origin + '/assets/uploads/' + p.image) : undefined,
        "offers": {
            "@type": "Offer",
            "priceCurrency": "INR",
            "price": effectivePrice,
            "availability": "https://schema.org/InStock",
            "url": window.location.href
        }
    };

    var script = document.createElement('script');
    script.type = 'application/ld+json';
    script.id = 'pd-product-schema';
    script.text = JSON.stringify(schema);
    document.head.appendChild(script);
}

$(document).ready(function () {
    loadProduct(productId, null);
});

// Size button: swap to that size's real price/details on this same page, no reload.
$(document).on("click", ".pd-size-btn", function (e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.hasClass("active")) return;
    loadProduct($btn.attr("data-id"), $btn.attr("data-slug"));
});

// Weight/volume button: customer picks a preset amount and the Total below is
// automatically calculated from this product's own price-per-kg or price-per-litre.
$(document).on("click", ".pd-weight-btn", function () {
    var $btn = $(this);
    var $wrap = $btn.closest(".pd-weight-options");
    $wrap.find(".pd-weight-btn").removeClass("active");
    $btn.addClass("active");
    var amount = parseFloat($btn.attr("data-amount")) || 0;
    var rate = parseFloat($wrap.attr("data-rate")) || 0;
    var label = $btn.attr("data-label") || "";
    var total = Math.round(rate * amount);
    var $info = $btn.closest(".pd-info");
    $info.find(".pd-price-new").text("₹" + total);
    $info.find(".pd-price-unit").text("/ " + label);
});

$(document).on("click", ".buy", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $btn = $(this);

    if ($btn.hasClass("pd-btn-disabled") || $btn.attr("aria-disabled") === "true") {
        return; // Out of stock — Buy it Now is visually disabled, block the click too.
    }

    // Use attr so we get the value from DOM (avoids .data() cache); product id from template data-id="${p.id}"
    var productId = $btn.attr("data-id");
    var cartUrl = $btn.attr("href") || "cart.php";
    if (productId !== undefined && productId !== null) productId = String(productId).trim();

    if (!productId || isNaN(parseInt(productId, 10))) {
        if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
            SwalHelper.ecommerce.cartError("Product not found. Please try again.");
        } else {
            alert("Product not found.");
        }
        return;
    }

    if (typeof isLoggedIn === "function" && !isLoggedIn()) {
        if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
            SwalHelper.ecommerce.notLoggedIn().then(function () {
                $("#popupForm").fadeIn();
            });
        } else {
            alert("Please login to continue");
            $("#popupForm").fadeIn();
        }
        return;
    }

    $btn.css("pointer-events", "none").text("Adding...");

    $.ajax({
        url: "assets/db_query/cart/cart_query.php?action=add",
        type: "POST",
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        data: "product_id=" + encodeURIComponent(productId),
        dataType: "json",
        success: function (res) {
            if (res && res.status === "success") {
                if (typeof loadCartCount === "function") loadCartCount();
                if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
                    SwalHelper.ecommerce.addedToCart().then(function () {
                        window.location.href = cartUrl;
                    });
                } else {
                    window.location.href = cartUrl;
                }
            } else if (res && res.status === "not_logged_in") {
                $btn.css("pointer-events", "").text("Buy it Now");
                if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
                    SwalHelper.ecommerce.notLoggedIn().then(function () {
                        $("#popupForm").fadeIn();
                    });
                } else {
                    alert("Please login to continue");
                    $("#popupForm").fadeIn();
                }
            } else {
                $btn.css("pointer-events", "").text("Buy it Now");
                if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
                    SwalHelper.ecommerce.cartError((res && res.message) ? res.message : "Could not add to cart.");
                } else {
                    alert((res && res.message) ? res.message : "Could not add to cart.");
                }
            }
        },
        error: function (xhr) {
            $btn.css("pointer-events", "").text("Buy it Now");
            if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
                SwalHelper.ecommerce.cartError("Request failed. Please try again.");
            } else {
                alert("Request failed. Please try again.");
            }
        }
    });
});
// Add-to-cart handler removed - now handled globally in header.js to prevent duplicate execution
// Login check is handled in header.js via session check on server side

// Plain quantity stepper - only present as a fallback when a product's pack
// size text couldn't be parsed into a weight/volume (see quantitySectionHtml above).
$(document).on("click", ".pd-qty-minus, .pd-qty-plus", function () {
    var $wrap = $(this).closest(".pd-stepper");
    var $value = $wrap.find(".pd-qty-value");
    var qty = parseInt($value.text(), 10) || 1;
    var unitPrice = parseFloat($value.attr("data-unit-price")) || 0;

    if ($(this).hasClass("pd-qty-plus")) {
        qty += 1;
    } else if (qty > 1) {
        qty -= 1;
    }

    $value.text(qty);
    $wrap.closest(".pd-info").find(".pd-total-value").text("₹" + (unitPrice * qty).toFixed(2).replace(/\.00$/, ""));
});

// Click-to-zoom toggle for touch/click devices (hover already zooms on
// desktop via CSS). Frontend-only, single existing product image.
$(document).on("click", ".pd-image-frame", function () {
    $(this).toggleClass("pd-zoomed");
});

// ===== Product reviews (see get_product_reviews.php / submit_review.php) =====

function escapePdHtml(str) {
    return String(str == null ? "" : str).replace(/[&<>"']/g, function (m) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m];
    });
}

function starsHtml(rating) {
    rating = Math.round(Number(rating) || 0);
    var out = "";
    for (var i = 1; i <= 5; i++) out += i <= rating ? "&#9733;" : "&#9734;";
    return out;
}

function loadProductReviews(productId) {
    $.ajax({
        url: "assets/db_query/product_detail/get_product_reviews.php",
        type: "GET",
        data: { product_id: productId },
        dataType: "json",
        success: function (res) {
            var $list = $("#pd-reviews-list");
            if (!$list.length) return;
            if (res.status !== "success" || !res.reviews || res.reviews.length === 0) {
                $list.html('<p class="pd-cell-sub">No reviews yet — be the first to review this product.</p>');
                return;
            }
            var html = res.average_rating
                ? '<p class="pd-review-avg"><span class="pd-review-stars-readonly">' + starsHtml(res.average_rating) + '</span> ' + res.average_rating + ' out of 5 (' + res.count + ' review' + (res.count === 1 ? '' : 's') + ')</p>'
                : '';
            html += res.reviews.map(function (r) {
                return '<div class="pd-review-card">' +
                    '<div class="pd-review-stars-readonly">' + starsHtml(r.rating) + '</div>' +
                    '<div class="pd-review-name">' + escapePdHtml(r.reviewer_name || "Anonymous") + '</div>' +
                    '<p class="pd-review-text">' + escapePdHtml(r.review_text) + '</p>' +
                    '</div>';
            }).join('');
            $list.html(html);
        },
        error: function () {
            $("#pd-reviews-list").html('<p class="pd-cell-sub">Could not load reviews right now.</p>');
        }
    });
}

$(document).on("click", ".pd-star-input", function () {
    var $wrap = $(this).closest(".pd-review-stars");
    var rating = parseInt($(this).attr("data-star"), 10) || 0;
    $wrap.attr("data-rating", rating);
    $wrap.find(".pd-star-input").each(function () {
        var starVal = parseInt($(this).attr("data-star"), 10);
        $(this).html(starVal <= rating ? "&#9733;" : "&#9734;").toggleClass("is-filled", starVal <= rating);
    });
});

$(document).on("click", "#pd-review-submit", function () {
    var $btn = $(this);
    var $section = $btn.closest(".pd-reviews-section");
    var productIdForReview = $section.attr("data-product-id");
    var rating = parseInt($section.find("#pd-review-stars").attr("data-rating"), 10) || 0;
    var reviewerName = $section.find("#pd-review-name").val();
    var reviewText = ($section.find("#pd-review-text").val() || "").trim();

    if (!rating) {
        if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
            SwalHelper.ecommerce.cartError("Please select a star rating.");
        } else {
            alert("Please select a star rating.");
        }
        return;
    }
    if (!reviewText) {
        if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
            SwalHelper.ecommerce.cartError("Please write a short review.");
        } else {
            alert("Please write a short review.");
        }
        return;
    }

    $btn.prop("disabled", true).text("Submitting…");

    $.ajax({
        url: "assets/db_query/product_detail/submit_review.php",
        type: "POST",
        data: {
            product_id: productIdForReview,
            rating: rating,
            reviewer_name: reviewerName,
            review_text: reviewText
        },
        dataType: "json",
        success: function (res) {
            $btn.prop("disabled", false).text("Submit Review");
            if (res.status === "success") {
                $section.find("#pd-review-name").val("");
                $section.find("#pd-review-text").val("");
                $section.find("#pd-review-stars").attr("data-rating", 0).find(".pd-star-input").html("&#9734;").removeClass("is-filled");
                loadProductReviews(productIdForReview);
                if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
                    SwalHelper.ecommerce.addedToCart && SwalHelper.close ? SwalHelper.close() : null;
                }
                alert(res.message || "Thank you for your review!");
            } else {
                alert(res.message || "Could not submit review.");
            }
        },
        error: function () {
            $btn.prop("disabled", false).text("Submit Review");
            alert("Request failed. Please try again.");
        }
    });
});

// One-time CSS for the stock badges and the review section — kept here
// (rather than a shared stylesheet) so this feature stays self-contained.
function injectStockAndReviewStyles() {
    if (document.getElementById("pdStockReviewStyles")) return;
    var style = document.createElement("style");
    style.id = "pdStockReviewStyles";
    style.textContent = `
        .pd-stock-badge{display:inline-block;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;margin-bottom:4px;}
        .pd-out-of-stock{background:#fdecea;color:#c0392b;}
        .pd-low-stock{background:#fff6e0;color:#a8720a;}
        .pd-btn[disabled],.pd-btn-disabled{opacity:.55;cursor:not-allowed;pointer-events:none;}
        .pd-reviews-section{margin:30px 0;padding:24px;background:#fafaf8;border-radius:10px;}
        .pd-reviews-section h3{margin-bottom:14px;}
        .pd-review-avg{font-weight:600;margin-bottom:12px;}
        .pd-review-card{padding:14px 0;border-top:1px solid #e5e1d8;}
        .pd-review-stars-readonly{color:#e0a415;letter-spacing:2px;}
        .pd-review-name{font-weight:600;font-size:14px;margin-top:4px;}
        .pd-review-text{margin:4px 0 0;color:#5a5650;}
        .pd-review-form{margin-top:20px;padding-top:16px;border-top:1px solid #e5e1d8;}
        .pd-review-stars{font-size:26px;color:#c9c4b8;cursor:pointer;letter-spacing:4px;margin-bottom:10px;}
        .pd-star-input{cursor:pointer;}
        .pd-star-input.is-filled{color:#e0a415;}
        .pd-review-input{display:block;width:100%;max-width:420px;margin-bottom:10px;padding:9px 12px;border:1px solid #d8d3c6;border-radius:6px;font-size:14px;}
        textarea.pd-review-input{resize:vertical;}
    `;
    document.head.appendChild(style);
}
