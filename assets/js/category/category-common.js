// ===== Category page shared helpers — Phase 5 =====
// Loaded on all 7 category pages (dryfruits, nuts, oils, spices, millets,
// rice, combo) right before that page's own script. Presentation-only:
// builds the same card markup/classes as the Phase 3 shop.php redesign so
// cart (.add-to-cart / #add-to-cart + data-id), wishlist (.wishlist-btn +
// data-product-id) and view-details (.product-detail-btn + data-id) keep
// working exactly as before via the existing global handlers in
// assets/js/header/header.js. No AJAX endpoints or data fields are changed
// here — each page's own script still calls its own existing query file.

function buildCategoryProductCard(product) {
    let image = product.image ? "assets/uploads/" + product.image : "images/logo.png";
    let url   = productUrl(product.category, product.product_name);

    // Defensive numeric parse: DB sometimes returns price as "1,099"
    let priceNum    = parseFloat(String(product.price    || 0).replace(/,/g, ""));
    let disPriceNum = parseFloat(String(product.dis_price || 0).replace(/,/g, ""));
    let hasDiscount = disPriceNum > 0 && priceNum > disPriceNum;

    let discountBadge = "";
    let displayPrice  = priceNum;
    let oldPriceHtml  = "";

    if (hasDiscount) {
        let percent = Math.round((1 - disPriceNum / priceNum) * 100);
        if (Number.isFinite(percent) && percent > 0) {
            discountBadge = `<span class="v-product-badge-discount">${percent}% OFF</span>`;
        }
        displayPrice = disPriceNum;
        oldPriceHtml = `<span class="v-product-price-old">&#8377;${priceNum}</span>`;
    }

    // Rating: only shown when the DB returns a genuine value (> 0)
    let ratingHtml = "";
    if (product.rating && parseFloat(product.rating) > 0) {
        let rVal = parseFloat(product.rating).toFixed(1);
        ratingHtml = `<div class="v-product-rating"><i class="fa-solid fa-star"></i> <span>${rVal}</span></div>`;
    }

    let packSize = product.quantity ? `<span class="v-product-pack-size">${product.quantity}</span>` : "";

    return `
    <div class="col-6 col-md-4 col-lg-3 mb-4">
        <div class="v-product-card">
            <div class="v-product-media">
                <a href="${url}" class="d-block w-100 h-100 text-center">
                    <img src="${image}" alt="${product.product_name}" loading="lazy">
                </a>
                ${discountBadge}
                <button type="button" class="v-product-wishlist-btn wishlist-btn" data-product-id="${product.id}" title="Add to Wishlist" aria-label="Add ${product.product_name} to Wishlist">
                    <i class="fa-regular fa-heart"></i>
                </button>
            </div>
            <div class="v-product-body">
                <a href="${url}" class="v-product-name" title="${product.product_name}">
                    ${product.product_name}
                </a>
                <div class="v-product-meta-row">
                    ${ratingHtml}
                    ${packSize}
                </div>
                <div class="v-product-price-row">
                    <span class="v-product-price-current">&#8377;${displayPrice}</span>
                    ${oldPriceHtml}
                </div>
                <button type="button" class="v-product-add-btn add-to-cart" data-id="${product.id}">
                    <i class="fa-solid fa-bag-shopping"></i> Add to Cart
                </button>
            </div>
        </div>
    </div>
    `;
}

function renderCategorySkeleton(containerId, count) {
    count = count || 8;
    let html = "";
    for (let i = 0; i < count; i++) {
        html += `
        <div class="col-md-6 col-lg-3 mb-3">
          <div class="v-cat-skel">
            <div class="v-skel-img"></div>
            <div class="v-skel-line"></div>
            <div class="v-skel-line short"></div>
            <div class="v-skel-btn"></div>
          </div>
        </div>`;
    }
    $(containerId).html(html);
}

function renderCategoryEmptyState(containerId, type, resetHref) {
    let icon = "bag-handle-outline";
    let title = "No products found";
    let text = "We couldn't find any products to show right now.";
    let showReset = false;

    if (type === "search") {
        icon = "search-outline";
        title = "No matching products";
        text = "Try a different keyword, or browse by category above.";
        showReset = true;
    } else if (type === "error") {
        icon = "alert-circle-outline";
        title = "Something went wrong";
        text = "We couldn't load products right now. Please refresh the page and try again.";
    } else {
        icon = "cube-outline";
        title = "No products available yet";
        text = "Please check back soon — new products are added regularly.";
    }

    let html = `
    <div class="v-cat-empty">
      <ion-icon name="${icon}"></ion-icon>
      <h4>${title}</h4>
      <p>${text}</p>
      ${showReset ? `<a href="${resetHref || '#'}" class="v-cat-empty-reset">View All</a>` : ""}
    </div>`;

    $(containerId).html(html);
}

function updateCategoryCount(count, selector) {
    let $el = $(selector);
    if (!$el.length) return;
    if (typeof count !== "number") {
        $el.text("");
        return;
    }
    $el.text(count + (count === 1 ? " product" : " products"));
}
