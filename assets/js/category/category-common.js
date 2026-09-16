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
        <a href="${productUrl(product.category, product.product_name)}" class="img-prod">
          <img class="img-fluid" src="${image}" alt="${product.product_name}" loading="lazy">
          ${discount}
          <div class="overlay"></div>
        </a>

        <a href="#" class="heart wishlist-btn" data-product-id="${product.id}" title="Add to wishlist" aria-label="Add ${product.product_name} to wishlist">
          <span><ion-icon name="heart"></ion-icon></span>
        </a>

        <div class="text py-3 pb-4 px-3 text-center">
          <h3>
            <a href="${productUrl(product.category, product.product_name)}" title="${product.product_name}">
              ${product.product_name}
            </a>
          </h3>
          <p class="v-cat-qty">${product.quantity || ""}</p>

          <div class="d-flex justify-content-center">
            <div class="pricing">
              <p class="price">${priceHtml}</p>
            </div>
          </div>

          <div class="bottom-area d-flex px-3">
            <div class="m-auto d-flex">
              <a href="${productUrl(product.category, product.product_name)}"
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
