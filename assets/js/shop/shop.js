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
// Shared card template (used by both the search/loadshop renderer and
// the initial fetchShopProducts load below) so both stay in sync.
// Functional hooks preserved exactly: .add-to-cart / #add-to-cart with
// data-id (cart), .product-detail-btn with data-id (view details),
// .wishlist-btn with data-product-id (wishlist) — all handled globally
// in assets/js/header/header.js.
// -------------------------------------------------------------------
function buildProductCard(product) {
    let slug = slugify(product.product_name);
    let image = product.image ? "assets/uploads/" + product.image : "images/logo.png";

    let discount = "";
    // Defensive parse: some catalogue prices come through as formatted
    // strings (e.g. "1,099") rather than numbers, which previously made
    // the badge render "NaN% OFF". Parsing here, and only rendering the
    // badge for a valid, positive percentage, prevents "NaN% OFF" and
    // nonsensical negative-percentage badges without touching any price
    // or discount data itself.
    let priceNum = parseFloat(String(product.price).replace(/,/g, ""));
    let disPriceNum = parseFloat(String(product.dis_price).replace(/,/g, ""));
    if (disPriceNum && priceNum) {
        let percent = Math.round((1 - disPriceNum / priceNum) * 100);
        if (Number.isFinite(percent) && percent > 0) {
            discount = `<span class="status">${percent}% OFF</span>`;
        }
    }

    let priceHtml = (product.dis_price && product.price)
        ? `<span class="mr-2 price-dc">&#8377;${product.price}</span><span class="price-sale">&#8377;${product.dis_price}</span>`
        : `<span>&#8377;${product.price}</span>`;

    return `
    <div class="col-md-6 col-lg-3 mb-3">
      <div class="product">
        <a href="productdetail.php?product=${slug}" class="img-prod">
          <img class="img-fluid" src="${image}" alt="${product.product_name}" loading="lazy">
          ${discount}
          <div class="overlay"></div>
        </a>

        <a href="#" class="heart wishlist-btn" data-product-id="${product.id}" title="Add to wishlist" aria-label="Add ${product.product_name} to wishlist">
          <span><ion-icon name="heart"></ion-icon></span>
        </a>

        <div class="text py-3 pb-4 px-3 text-center">
          <h3>
            <a href="productdetail.php?product=${slug}" title="${product.product_name}">
              ${product.product_name}
            </a>
          </h3>
          <p class="v-shop-qty">${product.quantity || ""}</p>

          <div class="d-flex justify-content-center">
            <div class="pricing">
              <p class="price">${priceHtml}</p>
            </div>
          </div>

          <div class="bottom-area d-flex px-3">
            <div class="m-auto d-flex">
              <a href="productdetail.php?product=${slug}"
                 class="add-to-cart d-flex justify-content-center align-items-center text-center product-detail-btn"
                 data-id="${product.id}" title="View Product" aria-label="View ${product.product_name}">
                <span><ion-icon name="menu"></ion-icon></span>
              </a>
              <a href="#" class="buy now d-flex justify-content-center align-items-center btn btn-primary add-to-cart"
                 id="add-to-cart" data-id="${product.id}" title="Add to Cart" aria-label="Add ${product.product_name} to cart">
                <span><ion-icon name="cart"></ion-icon></span>
                <span class="v-btn-label">Add to Cart</span>
              </a>
            </div>
          </div>
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
