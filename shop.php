<?php 
$actionpage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php";
?>

    <link rel="stylesheet" href="css/shop-redesign.css?v=20260926">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Store",
            "name": "Valluvam",
            "description": "Nuts, dry fruits, cold-pressed oils, spices & millets delivered fresh to your door.",
            "url": "https://www.valluvamproducts.com/",
            "logo": "https://www.valluvamproducts.com/assets/images/logo.png",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "No 17, Kovalan street, 2nd main road, Uthandi Kanathur",
                "addressLocality": "Chennai",
                "postalCode": "600119",
                "addressCountry": "IN"
            },
            "contactPoint": {
                "@type": "ContactPoint",
                "telephone": "+91-8925969888",
                "contactType": "Customer Support"
            },
            "sameAs": [
                "https://www.facebook.com/valluvamproducts/",
                "https://www.instagram.com/valluvam_agro_products/"
            ],
            "openingHours": "Mo-Su 10:00-07:30"
        }
    </script>

    <!-- Page Breadcrumb Banner -->
    <div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
        <div class="container">
            <div class="row no-gutters slider-text align-items-center justify-content-center">
                <div class="col-md-9 ftco-animate text-center">
                    <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Shop</span></p>
                    <h1 class="mb-0 bread">Shop All Products</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Pills Bar -->
    <section class="py-4" style="background: #fbf9f5; border-bottom: 1px solid #eee8db;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 text-center">
                    <?php include __DIR__ . '/category_pills.php'; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Catalogue Section with Sticky Sidebar & Grid -->
    <section class="py-5" style="background: #faf7f2;">
        <div class="container">
            <div class="v-shop-layout">

                <!-- DESKTOP STICKY FILTER SIDEBAR -->
                <aside class="v-filter-sidebar" id="desktop-filter-sidebar">
                    <div class="v-filter-header">
                        <h3><i class="fa-solid fa-sliders"></i> Filters</h3>
                        <button type="button" class="v-filter-reset-btn" id="filter-reset-all">Reset All</button>
                    </div>

                    <!-- Category Filter -->
                    <div class="v-filter-group">
                        <div class="v-filter-group-title">
                            <span>Category</span>
                        </div>
                        <ul class="v-filter-list" id="category-filter-list">
                            <li class="v-filter-item">
                                <label class="v-filter-label">
                                    <span>
                                        <input type="radio" name="shop-category" value="" class="v-filter-radio filter-cat-input" checked> All Categories
                                    </span>
                                    <span class="v-filter-count" id="count-cat-all">0</span>
                                </label>
                            </li>
                            <!-- Populated dynamically with real counts -->
                        </ul>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="v-filter-group">
                        <div class="v-filter-group-title">
                            <span>Price Range</span>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-price" value="all" class="v-filter-radio filter-price-input" checked> All Prices</span>
                            </label>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-price" value="0-250" class="v-filter-radio filter-price-input"> Under &#8377;250</span>
                            </label>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-price" value="250-500" class="v-filter-radio filter-price-input"> &#8377;250 - &#8377;500</span>
                            </label>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-price" value="500-1000" class="v-filter-radio filter-price-input"> &#8377;500 - &#8377;1,000</span>
                            </label>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-price" value="1000-99999" class="v-filter-radio filter-price-input"> Above &#8377;1,000</span>
                            </label>
                        </div>

                        <!-- Custom Price Inputs -->
                        <div class="v-price-inputs">
                            <div class="v-price-input-wrap">
                                <span>&#8377;</span>
                                <input type="number" id="price-min-input" class="v-price-input" placeholder="Min">
                            </div>
                            <span style="color:#a89f91;">–</span>
                            <div class="v-price-input-wrap">
                                <span>&#8377;</span>
                                <input type="number" id="price-max-input" class="v-price-input" placeholder="Max">
                            </div>
                            <button type="button" id="price-go-btn" class="v-price-go-btn">Go</button>
                        </div>
                    </div>

                    <!-- Offers & Discounts -->
                    <div class="v-filter-group">
                        <div class="v-filter-group-title">
                            <span>Offers</span>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="checkbox" id="filter-discount-only" class="v-filter-checkbox"> Discounted Items</span>
                                <span class="v-filter-count" id="count-discount">0</span>
                            </label>
                        </div>
                    </div>

                    <!-- Customer Rating Filter -->
                    <div class="v-filter-group">
                        <div class="v-filter-group-title">
                            <span>Rating</span>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-rating" value="0" class="v-filter-radio filter-rating-input" checked> All Ratings</span>
                            </label>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-rating" value="4" class="v-filter-radio filter-rating-input"> 4&#9733; &amp; Above</span>
                            </label>
                        </div>
                        <div class="v-filter-item">
                            <label class="v-filter-label">
                                <span><input type="radio" name="shop-rating" value="3" class="v-filter-radio filter-rating-input"> 3&#9733; &amp; Above</span>
                            </label>
                        </div>
                    </div>
                </aside>

                <!-- PRODUCTS CATALOGUE AREA -->
                <main class="v-shop-main">
                    <!-- Shop Toolbar -->
                    <div class="v-shop-toolbar">
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="v-mobile-filter-btn d-lg-none" id="mobile-filter-open-btn">
                                <i class="fa-solid fa-sliders"></i> Filters
                                <span class="v-filter-badge-count d-none" id="mobile-filter-badge">0</span>
                            </button>
                            <div class="v-shop-count">
                                Showing <strong id="shop-showing-count">0</strong> of <strong id="shop-total-count">0</strong> products
                            </div>
                        </div>

                        <div class="v-shop-sort-wrap">
                            <label for="shop-sort-select" class="v-shop-sort-label d-none d-sm-inline">Sort By:</label>
                            <select id="shop-sort-select" class="v-shop-sort-select" aria-label="Sort products">
                                <option value="featured">Featured / Default</option>
                                <option value="price-asc">Price: Low to High</option>
                                <option value="price-desc">Price: High to Low</option>
                                <option value="name-asc">Name: A to Z</option>
                                <option value="name-desc">Name: Z to A</option>
                                <option value="rating-desc">Customer Rating</option>
                            </select>
                        </div>
                    </div>

                    <!-- Active Filter Chips Bar -->
                    <div class="v-active-chips-wrap d-none" id="active-chips-container">
                        <!-- Chips inserted dynamically -->
                    </div>

                    <!-- Products Grid -->
                    <div class="row" id="product-shop">
                        <div class="col-12 text-center py-5">
                            <div class="spinner-border text-success" role="status">
                                <span class="sr-only">Loading products...</span>
                            </div>
                        </div>
                    </div>
                </main>

            </div>
        </div>
    </section>

    <!-- MOBILE BOTTOM-SHEET FILTER DRAWER & OVERLAY -->
    <div class="v-drawer-overlay" id="mobile-drawer-overlay"></div>
    <div class="v-drawer-sheet" id="mobile-filter-drawer">
        <div class="v-drawer-header">
            <h3><i class="fa-solid fa-sliders"></i> Filter Products</h3>
            <button type="button" class="v-drawer-close-btn" id="mobile-drawer-close-btn" aria-label="Close filters">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="v-drawer-body" id="mobile-drawer-body">
            <!-- Filter groups cloned/rendered for mobile -->
        </div>
        <div class="v-drawer-footer">
            <button type="button" class="v-drawer-clear-btn" id="mobile-drawer-clear-btn">Clear All</button>
            <button type="button" class="v-drawer-apply-btn" id="mobile-drawer-apply-btn">Apply Filters</button>
        </div>
    </div>

    <!-- Scripts & Footer -->
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-migrate-3.0.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.easing.1.3.js"></script>
    <script src="js/jquery.waypoints.min.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/jquery.magnific-popup.min.js"></script>
    <script src="js/aos.js"></script>
    <script src="js/main.js"></script>
    <script src="assets/js/shop/shop.js?v=<?php echo @filemtime(__DIR__ . "/assets/js/shop/shop.js"); ?>"></script>

    <?php include 'footer.php'; ?>