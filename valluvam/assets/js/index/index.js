const defaultProductImage = 'images/default.jpg';

const escapeHtml = (value = '') => $('<div>').text(String(value)).html();

const normalisePrice = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numericValue = Number(String(value).replace(/[^0-9.]/g, '').trim());
    return Number.isFinite(numericValue) ? numericValue : null;
};

const formatPrice = (value) => {
    const numericValue = normalisePrice(value);
    if (numericValue === null) {
        return escapeHtml(value ?? '');
    }

    return numericValue.toLocaleString('en-IN');
};

const getProductImagePath = (imageName) => {
    if (!imageName) {
        return defaultProductImage;
    }

    return `assets/uploads/${encodeURIComponent(String(imageName))}`;
};

const buildDiscountBadge = (product) => {
    const price = normalisePrice(product.price);
    const salePrice = normalisePrice(product.dis_price);

    if (!price || !salePrice || salePrice >= price) {
        return '';
    }

    const percent = Math.round(((price - salePrice) / price) * 100);
    return `<span class="status">${percent}%</span>`;
};

const buildPriceMarkup = (product) => {
    const price = normalisePrice(product.price);
    const salePrice = normalisePrice(product.dis_price);

    if (price && salePrice && salePrice < price) {
        return `
            <span class="mr-2 price-dc">&#8377;${formatPrice(product.price)}</span>
            <span class="price-sale">&#8377;${formatPrice(product.dis_price)}</span>
        `;
    }

    if (price) {
        return `<span>&#8377;${formatPrice(product.price)}</span>`;
    }

    return '<span>Price unavailable</span>';
};

const buildProductCard = (product) => {
    const productId = encodeURIComponent(product.id ?? '');
    const productName = escapeHtml(product.product_name ?? 'Product');
    const quantity = product.quantity ? ` (${escapeHtml(product.quantity)})` : '';
    const imagePath = getProductImagePath(product.image);
    const discountBadge = buildDiscountBadge(product);

    return `
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="product">
                <a href="productdetail.php?product=${productId}" class="img-prod">
                    <img class="img-fluid" src="${imagePath}" alt="${productName}" loading="lazy" onerror="this.onerror=null;this.src='${defaultProductImage}';">
                    ${discountBadge}
                    <div class="overlay"></div>
                </a>
                <div class="text py-3 pb-4 px-3 text-center">
                    <h3>
                        <a href="productdetail.php?product=${productId}">
                            ${productName}${quantity}
                        </a>
                    </h3>
                    <div class="d-flex justify-content-center">
                        <div class="pricing">
                            <p class="price">${buildPriceMarkup(product)}</p>
                        </div>
                    </div>
                    <div class="bottom-area d-flex px-3">
                        <div class="m-auto d-flex">
                            <a href="productdetail.php?product=${productId}" class="add-to-cart d-flex justify-content-center align-items-center text-center" title="View Details">
                                <span><ion-icon name="menu"></ion-icon></span>
                            </a>
                            <a href="#" class="buy-now btn btn-primary add-to-cart d-flex justify-content-center align-items-center" data-id="${productId}" title="Add to cart">
                                <span><ion-icon name="cart"></ion-icon></span>
                            </a>
                            <a href="#" class="heart d-flex justify-content-center align-items-center wishlist-btn" data-product-id="${productId}" title="Add to wishlist">
                                <span><ion-icon name="heart"></ion-icon></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};

const renderProducts = (products) => {
    if (!Array.isArray(products) || products.length === 0) {
        $('#product-container').html('<div class="col-12"><p>No products found.</p></div>');
        return;
    }

    const html = products.map(buildProductCard).join('');
    $('#product-container').html(html);
};

const renderProductMessage = (message) => {
    $('#product-container').html(`<div class="col-12"><p>${escapeHtml(message)}</p></div>`);
};

const product_catelog = () => {
    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=product_catelog',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status !== 'success') {
                renderProductMessage('Error loading products.');
                return;
            }

            renderProducts(res.data);
        },
        error: function () {
            renderProductMessage('Error loading products.');
        }
    });
};

const category_slider = () => {
    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=category_slider',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status !== 'success') {
                console.error('Error:', res.message);
                return;
            }

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
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', error);
        }
    });
};

$(document).ready(function () {
    $("#search").on("keyup", function () {
        const query = $(this).val().trim();

        if (query.length < 1) {
            product_catelog();
            return;
        }

        $.ajax({
            url: 'assets/db_query/index/index_product_query.php?action=product_search',
            method: 'GET',
            data: { query: query },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    renderProducts(res.data);
                    return;
                }

                if (res.status === 'not_found') {
                    renderProductMessage('No products found.');
                    return;
                }

                if (res.status === 'empty') {
                    renderProductMessage('Type something to search...');
                    return;
                }

                renderProductMessage('Error loading products.');
            },
            error: function () {
                renderProductMessage('Error loading products.');
            }
        });
    });

    category_slider();
});
