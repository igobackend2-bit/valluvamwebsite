$(document).ready(function () {
    fetchComboProducts();
});

function fetchComboProducts() {
    $.ajax({
        url: 'assets/db_query/combo/combo_query.php?action=combo_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(function (product) {
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
                                <h3>
                                    <a href="productdetail.php?product=${slugify(product.product_name)}">
                                        ${product.product_name} (${product.quantity})
                                    </a>
                                </h3>
                                <div class="d-flex">
                                    <div class="pricing">
                                        <p class="price">
                                            ${
                                                product.dis_price && product.price
                                                ? `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                                                   <span class="price-sale">&#8377;${product.dis_price}</span>`
                                                : `<span>&#8377;${product.price}</span>`
                                            }
                                        </p>
                                    </div>
                                </div>
                                <div class="bottom-area d-flex px-3">
                                    <div class="m-auto d-flex">
                                        <a href="productdetail.php?product=${slugify(product.product_name)}" 
                                           class="product-detail-btn d-flex justify-content-center align-items-center text-center" 
                                           data-id="${product.id}">
                                            <span><ion-icon name="menu"></ion-icon></span>
                                        </a>

                                        <a href="#" class="add-to-cart d-flex justify-content-center align-items-center btn btn-primary" 
                                           data-id="${product.id}">
                                            <span><ion-icon name="cart"></ion-icon></span>
                                        </a>

                                        <a href="#" class="wishlist-btn d-flex justify-content-center align-items-center" 
                                           data-product-id="${product.id}">
                                           <span><ion-icon name="heart"></ion-icon></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });

                $('#products-combo').html(html);
            } else {
                $('#products-combo').html('<p>No combo products found.</p>');
            }
        },
        error: function () {
            $('#products-combo').html('<p>Error loading products.</p>');
        }
    });
}

