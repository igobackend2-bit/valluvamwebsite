$(document).ready(function () {


    $.ajax({
        url: 'assets/db_query/product_detail/product_detail_query.php',
        type: 'GET',
        data: {
            id: productId
        },
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let p = res.data.product;
                let similar = res.data.similar;

                // Build main product HTML
                let html = `
                <div class="pd-wrap">
                    <div class="pd-gallery">
                        <div class="pd-image-frame">
                            <img src="assets/uploads/${p.image}" class="pd-image" alt="${p.product_name}" />
                        </div>
                    </div>
                    <div class="pd-info">
                        <span class="pd-category">${p.category ? p.category : 'Valluvam'}</span>
                        <h1 class="pd-title">${p.product_name}</h1>
                        <div class="pd-rating">${'<ion-icon name="star"></ion-icon>'.repeat(p.rating)}<span>${p.rating}.0</span></div>
                        <div class="pd-price-row">
                            <span class="pd-price-old">&#8377;${p.price}</span>
                            <span class="pd-price-new">&#8377;${p.dis_price}</span>
                            ${p.quantity ? `<span class="pd-price-unit">/ ${p.quantity}</span>` : ''}
                        </div>
                        <div class="pd-stock"><ion-icon name="checkmark-circle"></ion-icon> In Stock</div>
                        <p class="pd-desc">${p.description}</p>
                        <div class="pd-bullets">
                            ${p.benefits.split(',').map(b => `
                                <div class="d-flex align-items-center">
                                    <span class="dot"></span>
                                    <span class="bullet-text">${b.trim()}</span>
                                </div>
                            `).join('')}
                        </div>
                        <div class="pd-qty-row">
                            <label>Quantity</label>
                            <div class="pd-stepper">
                                <button type="button" class="pd-qty-minus">&minus;</button>
                                <span class="pd-qty-value" data-unit-price="${p.dis_price}">1</span>
                                <button type="button" class="pd-qty-plus">+</button>
                            </div>
                        </div>
                        <div class="pd-total-row">
                            <span>Total:</span>
                            <span class="pd-total-value">&#8377;${p.dis_price}</span>
                        </div>
                        <div class="pd-actions">
                            <button class="pd-btn pd-btn-cart cart" id="add-to-cart" data-id="${p.id}"><ion-icon name="cart-outline"></ion-icon> Add to Cart</button>
                            <a href="cart.php" class="pd-btn pd-btn-buy buy" data-id="${p.id}"><ion-icon name="arrow-forward-outline"></ion-icon> Buy it Now</a>
                            <button class="pd-btn-wishlist wishlist wishlist-btn" data-product-id="${productId}"><ion-icon name="heart-outline"></ion-icon></button>
                        </div>
                        <div class="pd-badges">
                            <span class="pd-badge">&#127807; 100% Natural</span>
                            <span class="pd-badge">&#128666; Farm Direct</span>
                            <span class="pd-badge">&#128230; Trusted Sourcing</span>
                            <span class="pd-badge">&#128666; Pan India Delivery</span>
                        </div>
                    </div>
                </div>
                <div class="pd-similar">
                    <h3>Similar items</h3>
                    <div class="pd-similar-grid">
                        ${similar.map(s => `
                            <a href="productdetail.php?product=${slugify(s.product_name)}" class="pd-similar-card">
                                <img src="assets/uploads/${s.image}" alt="${s.product_name}">
                                <div class="pd-similar-price">&#8377;${s.dis_price}</div>
                            </a>
                        `).join('')}
                    </div>
                </div>
                `;

                $('#product-details-container').html(html);
            } else {
                $('#product-details-container').html('<p>Product not found.</p>');
            }
        }
    });
});
$(document).on("click", ".buy", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $btn = $(this);
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

// Quantity stepper on the product detail page (display-only: updates the
// "Total" line shown to the shopper; Add to Cart / Buy it Now still add a
// single unit via the existing global handlers in header.js, unchanged).
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


