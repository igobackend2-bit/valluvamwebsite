$(document).ready(function () {
    // Run search while typing
    $("#search").on("keyup", function () {
        var query = $(this).val().trim();
        // Show instruction if less than 2 characters
        if (query.length < 1) {
            $('#product-container').html('<p>Type at least 2 characters to search...</p>');
            return;
        }

        $.ajax({
            url: 'assets/db_query/index/index_product_query.php?action=product_search',
            method: 'GET',
            data: { query: query }, // ✅ no need to repeat action here
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    let html = '<div class="row">';

                    res.data.forEach(function (product) {
                        let slug = product.product_name.toLowerCase().replace(/\s+/g, '-');
                        let image = product.image ? 'assets/uploads/' + product.image : 'images/default.jpg';

                        let discount = '';
                        if (product.dis_price && product.price) {
                            let percent = Math.round((1 - product.dis_price / product.price) * 100);
                            discount = `<span class="status">${percent}%</span>`;
                        }

                        html += `
                        <div class="col-md-6 col-lg-3 mb-3">
                          <div class="product">
                        
                            <!-- IMAGE -->
                            <a href="productdetail.php?product=${slugify(product.product_name)}" class="img-prod">
                              <img class="img-fluid" src="${image}" alt="${product.product_name}">
                              ${discount}
                              <div class="overlay"></div>
                            </a>
                        
                            <div class="text py-3 pb-4 px-3 text-center">
                        
                              <!-- PRODUCT NAME -->
                              <h3>
                                <a href="productdetail.php?product=${slugify(product.product_name)}">
                                  ${product.product_name} (${product.quantity})
                                </a>
                              </h3>
                        
                              <!-- PRICE -->
                              <div class="d-flex justify-content-center">
                                <div class="pricing">
                                  <p class="price">
                                    ${product.dis_price && product.price
                                ? `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                                           <span class="price-sale">&#8377;${product.dis_price}</span>`
                                : `<span>&#8377;${product.price}</span>`
                            }
                                  </p>
                                </div>
                              </div>
                        
                              <!-- ACTION BUTTONS -->
                              <div class="bottom-area d-flex px-3">
                                <div class="m-auto d-flex">
                        
                                  <a href="productdetail.php?product=${slugify(product.product_name)}"
                                     class="add-to-cart d-flex justify-content-center align-items-center text-center"
                                     title="View Details">
                                    <span><ion-icon name="menu"></ion-icon></span>
                                  </a>
                        
                                  <a href="#" class="buy-now btn btn-primary add-to-cart" data-id="${product.id}">
                                    <span><ion-icon name="cart"></ion-icon></span>
                                  </a>
                        
                                  <a href="#" class="heart wishlist-btn" data-product-id="${product.id}">
                                    <span><ion-icon name="heart"></ion-icon></span>
                                  </a>
                        
                                </div>
                              </div>
                        
                            </div>
                          </div>
                        </div>
                        `;

                    });

                    html += '</div>';
                    $('#product-container').html(html);
                }
                else if (res.status === 'not_found') {
                    $('#product-container').html('<p>No products found</p>');
                }
                else if (res.status === 'empty') {
                    $('#product-container').html('<p>Type something to search...</p>');
                }
            },
            error: function () {
                $('#product-container').html('<p>Error loading products.</p>');
            }
        });
    });
    // Add-to-cart handler removed - now handled globally in header.js to prevent duplicate execution

    // Wishlist handler removed - now handled globally in header.js to prevent duplicate execution


    product_catelog();
    category_slider();
});

function category_slider() {
    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=category_slider',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(function (cat) {
                    html += `
                    <div class="slide">
                        <div class="slide-content">
                            <a href="${cat.link}" target="_blank">
                                <img src="assets/thumbnail/${cat.thumbnali}" loading="lazy" />
                                <div class="button-container">
                                    <span class="button">View More</span>
                                </div>
                            </a>
                        </div>
                    </div>
                    `;
                });
                $('#slides').html(html + html);
            } else {
                console.error('Error:', res.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', error);
        }
    });
}
function product_catelog() {

    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=product_catelog',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            console.log(res)
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

                    // ✅ Quantity added next to product name
                    html += `
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="product">
                            <a href="productdetail.php?product=${slugify(product.product_name)}" class="img-prod" onclick="showproduct('product-${slug}')">
                                <img class="img-fluid" src="${image}" alt="${product.product_name}">
                                ${discount}
                                <div class="overlay"></div>
                            </a>
                            <div class="text py-3 pb-4 px-3 text-center">
                                <h3>
                                    <a href="productdetail.php?product=${slug}" onclick="showproduct('product-${slug}')">
                                        ${product.product_name} (${product.quantity})
                                    </a>
                                </h3>
                                <div class="d-flex">
                                    <div class="pricing">
                                        <p class="price">`;

                    if (product.dis_price && product.price) {
                        html += `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                                     <span class="price-sale">&#8377;${product.dis_price}</span>`;
                    } else {
                        html += `<span>&#8377;${product.price}</span>`;
                    }

                    html += `       </p>
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

                $('#product-container').html(html);
            } else {
                $('#product-container').html('<p>Error loading products.</p>');
            }
        },
        error: function () {
            $('#product-container').html('<p>Error loading products.</p>');
        }
    });
}

