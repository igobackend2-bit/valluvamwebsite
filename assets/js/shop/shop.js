$(document).ready(function () {
    window.allShopProducts = [];
    
    // Filter State
    window.shopFilterState = {
        category: '',
        priceRange: 'all',
        minPrice: null,
        maxPrice: null,
        discountOnly: false,
        minRating: 0,
        searchQuery: '',
        sortBy: 'featured'
    };

    // Check URL parameters (e.g. ?category=nuts or ?search=almond)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('category')) {
        window.shopFilterState.category = urlParams.get('category').trim().toLowerCase();
    }
    if (urlParams.has('search')) {
        window.shopFilterState.searchQuery = urlParams.get('search').trim();
    }

    // Top search bar typing
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();
        window.shopFilterState.searchQuery = query;
        applyFiltersAndSort();
    });

    // Sort select change
    $('#shop-sort-select').on('change', function () {
        window.shopFilterState.sortBy = $(this).val();
        applyFiltersAndSort();
    });

    // Category Radio change (desktop & mobile)
    $(document).on('change', '.filter-cat-input', function () {
        window.shopFilterState.category = $(this).val();
        $('.filter-cat-input[value="' + $(this).val() + '"]').prop('checked', true);
        applyFiltersAndSort();
    });

    // Price Radio change
    $(document).on('change', '.filter-price-input', function () {
        window.shopFilterState.priceRange = $(this).val();
        window.shopFilterState.minPrice = null;
        window.shopFilterState.maxPrice = null;
        $('#price-min-input, #price-max-input').val('');
        $('.filter-price-input[value="' + $(this).val() + '"]').prop('checked', true);
        applyFiltersAndSort();
    });

    // Custom Price Go button
    $('#price-go-btn, #mobile-price-go-btn').on('click', function () {
        const minVal = parseFloat($('#price-min-input').val());
        const maxVal = parseFloat($('#price-max-input').val());

        if (!isNaN(minVal) || !isNaN(maxVal)) {
            window.shopFilterState.priceRange = 'custom';
            window.shopFilterState.minPrice = !isNaN(minVal) ? minVal : 0;
            window.shopFilterState.maxPrice = !isNaN(maxVal) ? maxVal : 999999;
            $('.filter-price-input').prop('checked', false);
            applyFiltersAndSort();
        }
    });

    // Discount Checkbox change
    $(document).on('change', '#filter-discount-only, #mobile-filter-discount-only', function () {
        const checked = $(this).is(':checked');
        window.shopFilterState.discountOnly = checked;
        $('#filter-discount-only, #mobile-filter-discount-only').prop('checked', checked);
        applyFiltersAndSort();
    });

    // Rating Radio change
    $(document).on('change', '.filter-rating-input', function () {
        const val = parseFloat($(this).val()) || 0;
        window.shopFilterState.minRating = val;
        $('.filter-rating-input[value="' + $(this).val() + '"]').prop('checked', true);
        applyFiltersAndSort();
    });

    // Reset All Filters
    $('#filter-reset-all, #mobile-drawer-clear-btn').on('click', function () {
        resetFilters();
    });

    // Mobile Drawer Open / Close
    $('#mobile-filter-open-btn').on('click', function () {
        syncMobileDrawer();
        $('#mobile-drawer-overlay').addClass('active');
        $('#mobile-filter-drawer').addClass('active');
        $('body').css('overflow', 'hidden');
    });

    $('#mobile-drawer-close-btn, #mobile-drawer-overlay, #mobile-drawer-apply-btn').on('click', function () {
        $('#mobile-drawer-overlay').removeClass('active');
        $('#mobile-filter-drawer').removeClass('active');
        $('body').css('overflow', '');
    });

    // Fetch catalogue products
    fetchShopProducts();
});

// Fetch all products via existing endpoint (DB is untouched)
function fetchShopProducts() {
    renderSkeleton("#product-shop", 8);

    $.ajax({
        url: 'assets/db_query/shop/shop_query.php?action=all_products',
        method: 'GET',
        dataType: 'json',
        cache: false,
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                window.allShopProducts = res.data;
                initFilterSidebarCounts(res.data);
                applyFiltersAndSort();
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

// Populate Category Filter with real categories and counts
function initFilterSidebarCounts(products) {
    const catCounts = {};
    let discountCount = 0;

    products.forEach(p => {
        const cat = (p.category || 'other').trim();
        const catKey = cat.toLowerCase();
        catCounts[catKey] = (catCounts[catKey] || 0) + 1;

        let priceNum = parseFloat(String(p.price || 0).replace(/,/g, ""));
        let disPriceNum = parseFloat(String(p.dis_price || 0).replace(/,/g, ""));
        if (disPriceNum > 0 && priceNum > disPriceNum) {
            discountCount++;
        }
    });

    $('#count-cat-all').text(products.length);
    $('#count-discount').text(discountCount);

    // List of standard Valluvam categories
    const categoryCatalog = [
        { key: 'nuts', label: 'Nuts' },
        { key: 'dryfruits', label: 'Dry Fruits' },
        { key: 'oils', label: 'Cold Pressed Oils' },
        { key: 'spices', label: 'Spices' },
        { key: 'millets', label: 'Millets' },
        { key: 'rice', label: 'Rice' },
        { key: 'pulses', label: 'Dals & Pulses' },
        { key: 'seeds', label: 'Seeds' },
        { key: 'palm-jaggery', label: 'Palm Jaggery' },
        { key: 'ghee', label: 'Desi Ghee' },
        { key: 'honey', label: 'Raw Honey' },
        { key: 'combo', label: 'Combos' }
    ];

    let html = `
        <li class="v-filter-item">
            <label class="v-filter-label">
                <span>
                    <input type="radio" name="shop-category" value="" class="v-filter-radio filter-cat-input" ${!window.shopFilterState.category ? 'checked' : ''}> All Categories
                </span>
                <span class="v-filter-count">${products.length}</span>
            </label>
        </li>
    `;

    categoryCatalog.forEach(c => {
        const count = catCounts[c.key] || 0;
        if (count > 0 || window.shopFilterState.category === c.key) {
            const isChecked = window.shopFilterState.category === c.key ? 'checked' : '';
            html += `
                <li class="v-filter-item">
                    <label class="v-filter-label">
                        <span>
                            <input type="radio" name="shop-category" value="${c.key}" class="v-filter-radio filter-cat-input" ${isChecked}> ${c.label}
                        </span>
                        <span class="v-filter-count">${count}</span>
                    </label>
                </li>
            `;
        }
    });

    $('#category-filter-list').html(html);
}

// In-Memory Filter & Sort Engine
function applyFiltersAndSort() {
    if (!window.allShopProducts || !window.allShopProducts.length) return;

    const state = window.shopFilterState;
    let filtered = window.allShopProducts.filter(p => {
        // 1. Category Filter
        if (state.category) {
            const pCat = (p.category || '').toLowerCase();
            if (pCat !== state.category) {
                return false;
            }
        }

        // Price calculations
        let priceNum = parseFloat(String(p.price || 0).replace(/,/g, ""));
        let disPriceNum = parseFloat(String(p.dis_price || 0).replace(/,/g, ""));
        let effectivePrice = (disPriceNum > 0 && priceNum > disPriceNum) ? disPriceNum : priceNum;

        // 2. Price Range Filter
        if (state.priceRange === '0-250' && effectivePrice > 250) return false;
        if (state.priceRange === '250-500' && (effectivePrice < 250 || effectivePrice > 500)) return false;
        if (state.priceRange === '500-1000' && (effectivePrice < 500 || effectivePrice > 1000)) return false;
        if (state.priceRange === '1000-99999' && effectivePrice < 1000) return false;

        // Custom min/max
        if (state.minPrice !== null && effectivePrice < state.minPrice) return false;
        if (state.maxPrice !== null && effectivePrice > state.maxPrice) return false;

        // 3. Discount Only Filter
        if (state.discountOnly && !(disPriceNum > 0 && priceNum > disPriceNum)) {
            return false;
        }

        // 4. Rating Filter
        if (state.minRating > 0) {
            let r = parseFloat(p.rating || 0);
            if (r < state.minRating) return false;
        }

        // 5. Search Query Filter
        if (state.searchQuery && state.searchQuery.length > 0) {
            const q = state.searchQuery.toLowerCase();
            const name = (p.product_name || '').toLowerCase();
            const desc = (p.description || '').toLowerCase();
            const cat = (p.category || '').toLowerCase();
            if (!name.includes(q) && !desc.includes(q) && !cat.includes(q)) {
                return false;
            }
        }

        return true;
    });

    // Sorting
    filtered.sort((a, b) => {
        let priceA = parseFloat(String(a.price || 0).replace(/,/g, ""));
        let disA = parseFloat(String(a.dis_price || 0).replace(/,/g, ""));
        let effA = (disA > 0 && priceA > disA) ? disA : priceA;

        let priceB = parseFloat(String(b.price || 0).replace(/,/g, ""));
        let disB = parseFloat(String(b.dis_price || 0).replace(/,/g, ""));
        let effB = (disB > 0 && priceB > disB) ? disB : priceB;

        switch (state.sortBy) {
            case 'price-asc':
                return effA - effB;
            case 'price-desc':
                return effB - effA;
            case 'name-asc':
                return (a.product_name || '').localeCompare(b.product_name || '');
            case 'name-desc':
                return (b.product_name || '').localeCompare(a.product_name || '');
            case 'rating-desc':
                return parseFloat(b.rating || 0) - parseFloat(a.rating || 0);
            default:
                return 0; // Natural id DESC order preserved
        }
    });

    // Update Counts
    $('#shop-showing-count').text(filtered.length);
    $('#shop-total-count').text(window.allShopProducts.length);

    // Update Active Filter Chips & Badges
    renderActiveFilterChips();

    // Render Product Cards
    if (filtered.length > 0) {
        let html = '';
        filtered.forEach(p => {
            html += buildProductCard(p);
        });
        $('#product-shop').html(html);
    } else {
        renderEmptyState('#product-shop', state.searchQuery ? 'search' : 'empty');
    }
}

// Render active filter chips bar
function renderActiveFilterChips() {
    const state = window.shopFilterState;
    const chips = [];

    if (state.category) {
        chips.push({
            type: 'category',
            label: 'Category: ' + state.category.toUpperCase(),
            reset: () => {
                state.category = '';
                $('.filter-cat-input[value=""]').prop('checked', true);
            }
        });
    }

    if (state.priceRange !== 'all') {
        let label = 'Price: ';
        if (state.priceRange === '0-250') label += 'Under ₹250';
        else if (state.priceRange === '250-500') label += '₹250 - ₹500';
        else if (state.priceRange === '500-1000') label += '₹500 - ₹1,000';
        else if (state.priceRange === '1000-99999') label += 'Above ₹1,000';
        else if (state.priceRange === 'custom') label += `₹${state.minPrice} - ₹${state.maxPrice}`;

        chips.push({
            type: 'price',
            label: label,
            reset: () => {
                state.priceRange = 'all';
                state.minPrice = null;
                state.maxPrice = null;
                $('#price-min-input, #price-max-input').val('');
                $('.filter-price-input[value="all"]').prop('checked', true);
            }
        });
    }

    if (state.discountOnly) {
        chips.push({
            type: 'discount',
            label: 'On Sale / Discounts',
            reset: () => {
                state.discountOnly = false;
                $('#filter-discount-only, #mobile-filter-discount-only').prop('checked', false);
            }
        });
    }

    if (state.minRating > 0) {
        chips.push({
            type: 'rating',
            label: `${state.minRating}★ & Above`,
            reset: () => {
                state.minRating = 0;
                $('.filter-rating-input[value="0"]').prop('checked', true);
            }
        });
    }

    if (state.searchQuery) {
        chips.push({
            type: 'search',
            label: `Search: "${state.searchQuery}"`,
            reset: () => {
                state.searchQuery = '';
                $('#search').val('');
            }
        });
    }

    // Update Mobile Badge Count
    if (chips.length > 0) {
        $('#mobile-filter-badge').removeClass('d-none').text(chips.length);
        let html = '';
        chips.forEach((c, idx) => {
            html += `
                <span class="v-active-chip">
                    ${c.label}
                    <button type="button" class="v-active-chip-remove" data-chip-idx="${idx}" aria-label="Remove filter">&times;</button>
                </span>
            `;
        });
        html += `<button type="button" class="v-chips-clear-all" id="clear-all-chips">Clear All</button>`;
        $('#active-chips-container').html(html).removeClass('d-none');

        // Bind chip click handlers
        $('.v-active-chip-remove').off('click').on('click', function () {
            const idx = $(this).data('chip-idx');
            if (chips[idx]) {
                chips[idx].reset();
                applyFiltersAndSort();
            }
        });

        $('#clear-all-chips').off('click').on('click', function () {
            resetFilters();
        });
    } else {
        $('#mobile-filter-badge').addClass('d-none').text('0');
        $('#active-chips-container').html('').addClass('d-none');
    }
}

// Reset all filters back to default
function resetFilters() {
    window.shopFilterState = {
        category: '',
        priceRange: 'all',
        minPrice: null,
        maxPrice: null,
        discountOnly: false,
        minRating: 0,
        searchQuery: '',
        sortBy: 'featured'
    };

    $('.filter-cat-input[value=""]').prop('checked', true);
    $('.filter-price-input[value="all"]').prop('checked', true);
    $('.filter-rating-input[value="0"]').prop('checked', true);
    $('#filter-discount-only, #mobile-filter-discount-only').prop('checked', false);
    $('#price-min-input, #price-max-input').val('');
    $('#shop-sort-select').val('featured');
    $('#search').val('');

    applyFiltersAndSort();
}

// Sync contents to mobile drawer
function syncMobileDrawer() {
    const desktopHtml = $('#desktop-filter-sidebar').children().not('.v-filter-header').clone();
    // Rename inputs inside mobile drawer to avoid radio group clashes
    desktopHtml.find('#filter-discount-only').attr('id', 'mobile-filter-discount-only');
    desktopHtml.find('#price-go-btn').attr('id', 'mobile-price-go-btn');
    $('#mobile-drawer-body').html(desktopHtml);
}

// Card Builder — Standardized with Homepage Card & Functional Hooks
function buildProductCard(product) {
    let image = product.image ? "assets/uploads/" + product.image : "images/logo.png";
    let url   = productUrl(product.category, product.product_name);

    let priceNum    = parseFloat(String(product.price || 0).replace(/,/g, ""));
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

    let ratingHtml = "";
    if (product.rating && parseFloat(product.rating) > 0) {
        let rVal = parseFloat(product.rating).toFixed(1);
        ratingHtml = `<div class="v-product-rating"><i class="fa-solid fa-star"></i> <span>${rVal}</span></div>`;
    }

    let packSize = product.quantity ? `<span class="v-product-pack-size">${product.quantity}</span>` : "";

    return `
    <div class="col-6 col-md-4 col-lg-4 mb-4 d-flex align-items-stretch">
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

// Skeleton loader
function renderSkeleton(containerId, count) {
    count = count || 6;
    let html = "";
    for (let i = 0; i < count; i++) {
        html += `
        <div class="col-6 col-md-4 col-lg-4 mb-4">
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

// Empty state
function renderEmptyState(containerId, type) {
    let icon = "fa-solid fa-box-open";
    let title = "No products found";
    let text = "We couldn't find any products matching your current filters.";

    if (type === "search") {
        icon = "fa-solid fa-magnifying-glass";
        title = "No search results";
        text = "Try adjusting your search terms or clearing active filters.";
    } else if (type === "error") {
        icon = "fa-solid fa-circle-exclamation";
        title = "Unable to load catalogue";
        text = "Please check your network connection and refresh the page.";
    }

    let html = `
    <div class="col-12 py-5 text-center v-shop-empty">
      <i class="${icon} fa-3x text-muted mb-3"></i>
      <h4>${title}</h4>
      <p class="text-muted mb-3">${text}</p>
      <button type="button" class="v-btn-primary" onclick="resetFilters()">Reset All Filters</button>
    </div>`;

    $(containerId).html(html);
}
