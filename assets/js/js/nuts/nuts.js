$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // ✅ If empty, show all dryfruits again
        if (query.length === 0) {
            loadnuts();
            return;
        }

        // ✅ Optional: require 2 chars
        if (query.length < 2) {
            $("#products-nuts").html("<p>Type at least 2 characters to search...</p>");
            return;
        }

        $.ajax({
            url: "assets/db_query/nuts/nuts_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_nuts", query: query },
            success: function (res) {
                if (res.status !== "success") {
                    $("#products-nuts").html("<p>No products found</p>");
                    return;
                }
                renderProducts(res.data, "#products-nuts");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                $("#products-nuts").html("<p>Error loading products</p>");
            }
        });
    });

    // ✅ Load all dryfruits products
    function loadnuts() {
        $.ajax({
            url: "assets/db_query/nuts/nuts_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "nuts_products" },
            success: function (res) {
                if (res.status !== "success") {
                    $("#products-nuts").html("<p>No products found</p>");
                    return;
                }
                renderProducts(res.data, "#products-nuts");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                $("#products-nuts").html("<p>Error loading products</p>");
            }
        });
    }

    // ✅ Single renderer used for both search + all products
    function renderProducts(products, containerId) {
        let html = "";

        products.forEach(function (product) {
            let image = product.image ? "assets/uploads/" + product.image : "images/default.jpg";

            let discount = "";
            if (product.dis_price && product.price) {
                let percent = Math.round((1 - product.dis_price / product.price) * 100);
                discount = `<span class="status">${percent}%</span>`;
            }

            html += `
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="product">
                <a href="productdetail.php?product=${slugify(product.product_name)}" class="img-prod">
                  <img class="img-fluid" src="${image}" alt="${product.product_name}">
                  ${discount}
                  <div class="overlay"></div>
                </a>
      
                <div class="text py-3 pb-4 px-3 text-center">
                  <h3>
                    <a href="productdetail.php?product=${slugify(product.product_name)}">
                      ${product.product_name} (${product.quantity})
                    </a>
                  </h3>
      
                  <div class="d-flex justify-content-center">
                    <div class="pricing">
                      <p class="price">
                        ${product.dis_price && product.price
                    ? `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                             <span class="price-sale">&#8377;${product.dis_price}</span>`
                    : `<span>&#8377;${product.price}</span>`}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
        });

        $(containerId).html(html);
    }
    fetchnutsProducts();
});
function fetchnutsProducts() {
    $.ajax({
        url: 'assets/db_query/nuts/nuts_query.php?action=nuts_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(function (product) {
                    let slug = product.product_name.toLowerCase().replace(/\s+/g, '-');
                    let image = product.image ? 'assets/uploads/' + product.image : 'images/default.jpg';

                    let discount = '';
                    if (product.dis_price && product.price) {
                        let percent = Math.round((1 - product.dis_price / product.price) * 100);
                        discount = `<span class="status">${percent}%</span>`;
                    }

                    html += `
                        <div class="col-md-6 col-lg-3 mb-5">
                            <div class="product">
                                <a href="productdetail.php?product=${slugify(product.product_name)}" class="img-prod">
                                    <img class="img-fluid" src="${image}" alt="${product.product_name}">
                                    ${discount}
                                    <div class="overlay"></div>
                                </a>
                                <div class="text py-3 pb-4 px-3 text-center">
                                    <h3><a href="productdetail.php?product=${slugify(product.product_name)}">${product.product_name} (${product.quantity})</a></h3>
                                    <div class="d-flex">
                                        <div class="pricing">
                                            <p class="price">`;

                    if (product.dis_price && product.price) {
                        html += `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                                 <span class="price-sale">&#8377;${product.dis_price}</span>`;
                    } else {
                        html += `<span>&#8377;${product.price}</span>`;
                    }

                    html += `</p>
                                        </div>
                                    </div>
                                    <div class="bottom-area d-flex px-3">
                                        <div class="m-auto d-flex">
                                        <a href="productdetail.php?product=${slugify(product.product_name)}" 
                                        class="add-to-cart d-flex justify-content-center align-items-center text-center product-detail-btn" 
                                        data-id="${product.id}">
                                            <span><ion-icon name="menu"></ion-icon></span>
                                        </a>
                                        <a href="#" class="buy now d-flex justify-content-center align-items-center btn btn-primary add-to-cart" id="add-to-cart" data-id="${product.id}"
                                            <span><ion-icon name="cart"></ion-icon></a></span>
                                        </a>
                                        <a href="#" class="heart d-flex justify-content-center align-items-center wishlist-btn" 
                                          data-product-id="${product.id}">
                                           <span><ion-icon name="heart"></ion-icon></span>
                                        </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });

                $('#products-nuts').html(html);
            } else {
                $('#products-nuts').html('<p>No dryfruits products found.</p>');
            }
        },
        error: function () {
            $('#products-nuts').html('<p>Error loading products.</p>');
        }
    });
}
// Add-to-cart handler removed - now handled globally in header.js to prevent duplicate execution
// Wishlist handler removed - now handled globally in header.js to prevent duplicate execution