$(document).ready(function () {
    // Run search while typing
    $("#search").on("keyup", function () {
        var query = $(this).val().trim();
        // Show instruction if less than 2 characters
        if (query.length < 1) {
            $('#product-container').html('<p class="w-100 text-center py-4 text-muted">Type at least 2 characters to search...</p>');
            return;
        }

        $.ajax({
            url: 'assets/db_query/index/index_product_query.php?action=product_search',
            method: 'GET',
            data: { query: query },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success' && res.data && res.data.length) {
                    let html = '<div class="row w-100">';
                    res.data.forEach(function (product, idx) {
                        html += renderValluvamProductCard(product, idx);
                    });
                    html += '</div>';
                    $('#product-container').html(html);
                } else if (res.status === 'not_found' || (res.data && res.data.length === 0)) {
                    $('#product-container').html('<div class="w-100 text-center py-5"><i class="fa-solid fa-box-open fa-2x text-muted mb-2"></i><p class="text-muted">No products found matching "' + query + '"</p></div>');
                } else if (res.status === 'empty') {
                    $('#product-container').html('<p class="w-100 text-center py-4 text-muted">Type something to search...</p>');
                }
            },
            error: function () {
                $('#product-container').html('<p class="w-100 text-center py-4 text-muted">Error loading search results.</p>');
            }
        });
    });

    product_catelog();
    category_slider();
    loadTopRated();
    loadCollection('healthy', 'healthy-choices-container', 'healthy-choices-section');
    loadCollection('traditional', 'traditional-foods-container', 'traditional-foods-section');
    loadCollection('gifting', 'gifting-container', 'gifting-section');
    loadCollection('everyday', 'everyday-essentials-container', 'everyday-essentials-section');
});

// Shared premium product card builder for Valluvam
// Strictly adheres to data integrity: no fabricated ratings, discounts, or prices.
function renderValluvamProductCard(product, index) {
    let image = product.image ? 'assets/uploads/' + product.image : 'images/logo.png';
    let url = productUrl(product.category, product.product_name);

    let priceNum = parseFloat(String(product.price || 0).replace(/,/g, ''));
    let disPriceNum = parseFloat(String(product.dis_price || 0).replace(/,/g, ''));
    let hasDiscount = disPriceNum > 0 && priceNum > disPriceNum;

    let discountBadge = '';
    let displayPrice = priceNum;
    let oldPriceHtml = '';

    if (hasDiscount) {
        let percent = Math.round((1 - disPriceNum / priceNum) * 100);
        if (Number.isFinite(percent) && percent > 0) {
            discountBadge = `<span class="v-product-badge-discount">${percent}% OFF</span>`;
        }
        displayPrice = disPriceNum;
        oldPriceHtml = `<span class="v-product-price-old">&#8377;${priceNum}</span>`;
    }

    // New badge for first 2 items if ordered by id DESC
    let newBadge = (typeof index === 'number' && index < 2 && !hasDiscount) ? '<span class="v-product-badge-new">New Harvest</span>' : '';

    // Only render rating if present in the database (never fabricated)
    let ratingHtml = '';
    if (product.rating && parseFloat(product.rating) > 0) {
        let rVal = parseFloat(product.rating).toFixed(1);
        ratingHtml = `<div class="v-product-rating"><i class="fa-solid fa-star"></i> <span>${rVal}</span></div>`;
    }

    let packSize = product.quantity ? `<span class="v-product-pack-size">${product.quantity}</span>` : '';

    return `
    <div class="col-6 col-md-4 col-lg-3 mb-4">
        <div class="v-product-card">
            <div class="v-product-media">
                <a href="${url}" class="d-block w-100 h-100 text-center">
                    <img src="${image}" alt="${product.product_name}" loading="lazy">
                </a>
                ${discountBadge}
                ${newBadge}
                <button type="button" class="v-product-wishlist-btn wishlist-btn" data-product-id="${product.id}" title="Add to Wishlist" aria-label="Add to Wishlist">
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

// Top Rated section (uses real product_details.rating)
function loadTopRated() {
    let $container = $('#top-rated-container');
    if (!$container.length) return;

    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=top_rated',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product, idx) {
                    html += renderValluvamProductCard(product, idx);
                });
                $container.html(html);
                $('#top-rated-section').show();
            } else {
                $('#top-rated-section').hide();
            }
        },
        error: function () {
            $('#top-rated-section').hide();
        }
    });
}

// Collection Loaders
function loadCollection(type, containerId, sectionId) {
    let $container = $('#' + containerId);
    if (!$container.length) return;

    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=collection&type=' + type,
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product, idx) {
                    html += renderValluvamProductCard(product, idx);
                });
                $container.html(html);
                if(sectionId) $('#' + sectionId).show();
            } else {
                $container.html('<p class="w-100 text-center py-4 text-muted">No products found in this collection.</p>');
                if(sectionId) $('#' + sectionId).hide();
            }
        },
        error: function () {
            if(sectionId) $('#' + sectionId).hide();
        }
    });
}

// Category Slider (Shop by Category)
function category_slider() {
    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=category_slider',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data) {
                let html = '';
                res.data.forEach(function (cat) {
                    let catName = cat.category_name ? cat.category_name : '';
                    html += `
                    <div class="slide">
                        <div class="slide-content">
                            <a href="${cat.link}">
                                <img src="assets/thumbnail/${cat.thumbnali}" loading="lazy" alt="${catName ? catName + ' - Valluvam' : 'Valluvam Category'}" />
                                <div class="button-container">
                                    ${catName ? `<span class="slide-cat-name">${catName}</span>` : ''}
                                    <span class="button">Explore Category &rarr;</span>
                                </div>
                            </a>
                        </div>
                    </div>
                    `;
                });
                $('#slides').html(html + html);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', error);
        }
    });
}

// Best Products Catalog
function product_catelog() {
    $.ajax({
        url: 'assets/db_query/index/index_product_query.php?action=product_catelog',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data) {
                let html = '';
                res.data.forEach(function (product, index) {
                    html += renderValluvamProductCard(product, index);
                });
                $('#product-container').html(html);
            } else {
                $('#product-container').html('<p class="w-100 text-center py-4 text-muted">Error loading products.</p>');
            }
        },
        error: function () {
            $('#product-container').html('<p class="w-100 text-center py-4 text-muted">Error loading products.</p>');
        }
    });
}
