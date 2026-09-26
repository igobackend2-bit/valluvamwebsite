// === Step 1: Include this in your shop.js ===
$(document).ready(function () {

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // ✅ If empty, show all dryfruits again
        if (query.length === 0) {
            loadshop();
            return;
        }

        // ✅ Optional: require 2 chars
        if (query.length < 2) {
            $("#product-shop").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderSkeleton("#product-shop");

        $.ajax({
            url: "assets/db_query/shop/shop_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_shop", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderEmptyState("#product-shop", "search");
                    return;
                }
                renderProducts(res.data, "#product-shop");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderEmptyState("#product-shop", "error");
            }
        });
    });

    // ✅ Load all dryfruits products
    function loadshop() {
        renderSkeleton("#product-shop");
        $.ajax({
            url: "assets/db_query/shop/shop_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "shop_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderEmptyState("#product-shop", "empty");
                    return;
                }
                renderProducts(res.data, "#product-shop");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderEmptyState("#product-shop", "error");
            }
        });
    }

    // ✅ Single renderer used for both search + all products
    function renderProducts(products, containerId) {
        let html = "";

        products.forEach(function (product) {
            html += buildProductCard(product);
        });

        $(containerId).html(html);
    }

    fetchShopProducts();
});

// -------------------------------------------------------------------
// Premium card template — matches the .v-product-card system used on
// the homepage (index.js renderValluvamProductCard). All functional
// hooks preserved exactly:
//   • .add-to-cart + data-id        → cart (header.js global handler)
//   • .wishlist-btn + data-product-id → wishlist (header.js)
//   • href to productUrl()          → product detail page
// No price, discount or rating data is fabricated; only rendered when
// present and valid in the API response.
// -------------------------------------------------------------------
function buildProductCard(product) {
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

// -------------------------------------------------------------------
// Loading skeleton (frontend-only, shown while an AJAX call is in
// flight; replaced automatically once the real markup is rendered).
// -------------------------------------------------------------------
function renderSkeleton(containerId, count) {
    count = count || 8;
    let html = "";
    for (let i = 0; i < count; i++) {
        html += `
        <div class="col-md-6 col-lg-3 mb-3">
          <div class="v-shop-skel">
            <div class="v-skel-img"></div>
            <div class="v-skel-line"></div>
            <div class="v-skel-line short"></div>
            <div class="v-skel-btn"></div>
          </div>
        </div>`;
    }
    $(containerId).html(html);
}

// -------------------------------------------------------------------
// Empty / no-results / error states (frontend presentation only — the
// underlying search/catalog logic is unchanged).
// -------------------------------------------------------------------
function renderEmptyState(containerId, type) {
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
        title = "No products available";
        text = "Please check back soon — new products are added regularly.";
    }

    let html = `
    <div class="v-shop-empty">
      <ion-icon name="${icon}"></ion-icon>
      <h4>${title}</h4>
      <p>${text}</p>
      ${showReset ? '<a href="shop.php" class="v-shop-empty-reset">View All Products</a>' : ""}
    </div>`;

    $(containerId).html(html);
}

function fetchShopProducts() {
    renderSkeleton("#product-shop");
    $.ajax({
        url: 'assets/db_query/shop/shop_query.php?action=all_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildProductCard(product);
                });
                $('#product-shop').html(html);
            } else if (res.status === 'success') {
                renderEmptyState('#product-shop', 'empty');
            } else {
                renderEmptyState('#product-shop', 'error');
            }
        },
        error: function () {
            renderEmptyState('#product-shop', 'error');
        }
    });
}
