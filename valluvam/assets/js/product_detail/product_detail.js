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
                <div class="row no-gutters">
                    <div class="col-md-5 pr-2">
                        <div class="card9">
                            <div class="demo">
                                <ul id="lightSlider9">
                                    <li data-thumb="${p.image}"> 
                                        <img src="assets/uploads/${p.image}" /> 
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card9">
                            <div class="d-flex flex-row align-items-center">
                                <div class="p-ratings">${'<i class="fa fa-star"></i>'.repeat(p.rating)}</div> 
                                <span class="ml-1">${p.rating}.0</span>
                            </div>
                            <div class="about">
                                <span class="font-weight-bold">${p.product_name}</span>
                                <h4 class="fw-semi-bold text-danger text-decoration-line-through">&#8377;${p.price}</h4>
                                <h4 class="fw-semi-bold text-success">&#8377;${p.dis_price}</h4>
                            </div>
            <div class="mb-2">
    <label>Quantity:</label>
    <p class="fw-bold">${p.quantity}</p>
</div>
                            <div class="buttons"> 
                                <button class="btn btn-outline-warning btn-long cart" id="add-to-cart" data-id="${p.id}">Add to Cart</button> 
                                <a href="cart.php" class="btn btn-warning btn-long buy" data-id="${p.id}">Buy it Now</a> 
                                <button class="btn btn-light wishlist wishlist-btn"  data-product-id="${productId}"> <ion-icon name="heart"></ion-icon> </button> 
                            </div>
                            <hr>
                            <div class="product-description">
                                <div class="mt-2"> 
                                    <span class="font-weight-bold">Description</span>
                                    <p>${p.description}</p>
                                    <div class="bullets">
                                        ${p.benefits.split(',').map(b => `
                                            <div class="d-flex align-items-center">
                                                <span class="dot"></span> 
                                                <span class="bullet-text">${b.trim()}</span>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mt-2"> 
                            <span>Similar items:</span>
                            <div class="similar-products mt-2 d-flex flex-row flex-wrap">
                                ${similar.map(s => `
                                    <div class="card border p-1 m-1" style="width: 9rem;">
                                        <a href="productdetail.php?product=${s.id}">
                                            <img src="assets/uploads/${s.image}" class="card-img-top" alt="${s.product_name}">
                                        </a>
                                        <div class="card-body text-center">
                                            <h6 class="card-title">&#8377;${s.dis_price}</h6>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
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


