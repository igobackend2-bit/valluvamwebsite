<?php 
$activePage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php";
?>

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Valluvam",
        "url": "https://www.valluvamproducts.com/",
        "logo": "https://www.valluvamproducts.com/images/logo.png",
        "image": "https://www.valluvamproducts.com/images/logo.png",
        "telephone": "+91-8925969888",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "No 17, Kovalan street, 2nd main road, Uthandi Kanathur",
            "addressLocality": "Chennai",
            "postalCode": "600119",
            "addressCountry": "IN"
        },
        "sameAs": [
            "https://www.facebook.com/valluvamproducts/",
            "https://www.instagram.com/valluvam_agro_products/"
        ]
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Valluvam",
        "url": "https://www.valluvamproducts.com/",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://www.valluvamproducts.com/shop.php?search={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What products does Valluvam offer?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Valluvam offers a wide range of premium nuts, dry fruits, cold-pressed oils, spices, millets, heritage rice, dal, seeds, ghee and raw honey, sourced for purity and freshness."
                }
            },
            {
                "@type": "Question",
                "name": "Do you provide wholesale or bulk orders?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, Valluvam supports wholesale and bulk ordering for businesses, retailers and institutions. Visit our B2B / Wholesale page to submit an enquiry."
                }
            },
            {
                "@type": "Question",
                "name": "Do you deliver across India?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, Valluvam delivers products pan-India with real-time tracking, bringing farm-fresh foods directly to your doorstep."
                }
            }
        ]
    }
    </script>

    <!-- ====================================================================
         1. HERO SECTION (Editorial Banner & Brand Statement)
         ==================================================================== -->
    <style>
        /* Homepage hero: supplied campaign banner contains its own copy and CTAs. */
        .v-hero-section.v-hero-banner {
            min-height: 0 !important;
            padding: 0 !important;
            aspect-ratio: 25 / 9;
            background: url('images/Banner/hero-banner.jpg.jpeg') center / cover no-repeat;
        }
        .v-hero-section.v-hero-banner::before,
        .v-hero-section.v-hero-banner .container {
            display: none;
        }
    </style>
    <section class="v-hero-section v-hero-banner" aria-label="Valluvam products">
        <div class="container">
            <div class="v-hero-grid">
                <div class="v-hero-content">
                    <div class="v-hero-badge">
                        <i class="fa-solid fa-leaf"></i> TRADITIONAL &amp; PURE
                    </div>
                    <h1 class="v-hero-heading">
                        GOOD FOOD. <br>
                        <span class="v-hero-accent">ROOTED IN TRADITION.</span>
                    </h1>
                    <p class="v-hero-subtext">
                        Carefully sourced Indian foods for everyday living. Cold-pressed oils, hand-picked nuts, sun-dried fruits, whole spices and heritage grains — unadulterated and delivered straight to your home.
                    </p>
                    <div class="v-hero-actions">
                        <a href="shop.php" class="v-btn-primary">
                            SHOP PRODUCTS <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="#shop-by-category" class="v-btn-secondary">
                            EXPLORE COLLECTIONS
                        </a>
                        <a href="b2b-wholesale.php" class="v-btn-tertiary">
                            EXPLORE WHOLESALE &rarr;
                        </a>
                    </div>
                    <ul class="v-hero-trust-list">
                        <li><i class="fa-solid fa-droplet"></i> 100% Cold Pressed</li>
                        <li><i class="fa-solid fa-seedling"></i> Direct from Farmers</li>
                        <li><i class="fa-solid fa-shield-heart"></i> Zero Preservatives</li>
                        <li><i class="fa-solid fa-truck-fast"></i> Pan-India Delivery</li>
                    </ul>
                </div>

                <div class="v-hero-media-wrap">
                    <div class="v-hero-frame">
                        <img src="images/hero-premium.jpg" alt="Valluvam Traditional Cold Pressed Natural Foods" class="v-hero-img" loading="eager">
                    </div>
                    <div class="v-hero-float-card">
                        <div class="v-hero-float-icon">
                            <i class="fa-solid fa-award"></i>
                        </div>
                        <div>
                            <p class="v-hero-float-title">Certified Purity</p>
                            <p class="v-hero-float-desc">FSSAI Certified &amp; Lab-Tested</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         2. SHOP BY CATEGORY (Visual Circle Category Hub)
         ==================================================================== -->
    <section class="v-cat-circle-section" id="shop-by-category">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:var(--v-forest);">SHOP BY CATEGORY</span>
                    <h2 class="v-section-title" style="color:var(--v-forest-deep); font-size:clamp(28px, 4vw, 42px); font-family:var(--v-font-serif);">Nature's Goodness, In Every Category</h2>
                    <p class="v-section-subtitle text-left">Explore our range of natural and wholesome products.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:var(--v-forest); font-weight:600;">View All Categories &rarr;</a>
                </div>
            </div>

            <!-- Dynamic Slider Container (Maintains backend AJAX compatibility) -->
            <div class="slid-er mb-4 d-none d-md-block" style="display:none !important;">
                <div class="slides" id="slides"></div>
            </div>

            <div class="v-cat-circle-grid">
                <a href="nuts.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Nuts.jpeg" alt="Nuts" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Nuts</div>
                    <div class="v-cat-circle-sub">Crunchy &amp; Nutritious</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>
                
                <a href="dryfruits.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Dry%20Fruits.jpeg" alt="Dry Fruits" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Dry Fruits</div>
                    <div class="v-cat-circle-sub">Nature's Energy</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="oils.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Cold%20pressed%20oil.jpeg" alt="Cold Pressed Oils" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Cold Pressed Oils</div>
                    <div class="v-cat-circle-sub">Pure &amp; Healthy</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="spices.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Spices.jpeg" alt="Spices" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Spices</div>
                    <div class="v-cat-circle-sub">Rich in Flavour</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="millets.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Millets.jpeg" alt="Millets" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Millets</div>
                    <div class="v-cat-circle-sub">Ancient Superfood</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="rice.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Rice.jpeg" alt="Rice" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Rice</div>
                    <div class="v-cat-circle-sub">Heritage Grains</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="pulses.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Dals.jpeg" alt="Dals &amp; Pulses" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Dals &amp; Pulses</div>
                    <div class="v-cat-circle-sub">Protein Rich</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="seeds.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Seeds.jpeg" alt="Seeds" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Seeds</div>
                    <div class="v-cat-circle-sub">Daily Nutrition</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="palm-jaggery.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Jaggery.jpeg" alt="Palm Jaggery" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Palm Jaggery</div>
                    <div class="v-cat-circle-sub">Natural Sweetener</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="ghee.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Ghee.jpeg" alt="Desi Ghee" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Desi Ghee</div>
                    <div class="v-cat-circle-sub">Traditional A2</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="honey.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/Honey.jpeg" alt="Raw Honey" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Raw Honey</div>
                    <div class="v-cat-circle-sub">Forest Harvest</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="combo.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="images/Categories/cobo%20packs.jpeg" alt="Combo Packs" loading="lazy">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-gift"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Combo Packs</div>
                    <div class="v-cat-circle-sub">Curated Bundles</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         3. BEST SELLERS (Products from Database)
         ==================================================================== -->
    <section class="v-products-section" id="best-products-section">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-star"></i> Customer Favorites</span>
                <h2 class="v-section-title" style="font-family:var(--v-font-serif); color:var(--v-forest-deep);">BEST SELLERS</h2>
                <p class="v-section-subtitle">Loved by many, chosen for uncompromised quality and everyday nourishment.</p>
            </div>

            <!-- Products loaded dynamically by assets/js/index/index.js -->
            <div class="row" id="product-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="sr-only">Loading products...</span>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="shop.php" class="v-btn-primary" style="background:var(--v-forest); border-color:var(--v-forest); color:#fff !important;">
                    View All Products &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         4. WHY VALLUVAM (Brand Story & 5 Quality Pillars)
         ==================================================================== -->
    <section class="v-why-section" id="why-valluvam">
        <div class="v-why-bg-leaf v-why-leaf-left"></div>
        <div class="v-why-bg-leaf v-why-leaf-right"></div>
        <div class="v-why-bg-wave"></div>

        <div class="container relative-z">
            <div class="v-section-header">
                <span class="v-eyebrow v-eyebrow-pale">&#9670; THE VALLUVAM PROMISE</span>
                <h2 class="v-section-title">WHY VALLUVAM?</h2>
                <p class="v-section-subtitle">We honor the purity of traditional Indian food wisdom with modern transparency.</p>
            </div>

            <div class="v-why-wrapper">
                <div class="v-why-grid">
                    <!-- Pillar 1 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/sourced.jpg" alt="Carefully Sourced" class="v-why-img" loading="lazy">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-seedling"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Carefully Sourced</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Sourced directly from verified farming regions known for authentic native soil and heritage harvesting.</p>
                            <a href="about.php" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Pillar 2 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/traditional.jpg" alt="Traditional Products" class="v-why-img" loading="lazy">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-bowl-rice"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Traditional Products</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Time-honored techniques including wood cold-pressing (Marachekku) and slow stone processing.</p>
                            <a href="about.php" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Pillar 3 (Center Highlight) -->
                    <div class="v-why-card v-why-highlight">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/quality.jpg" alt="Quality Focused" class="v-why-img" loading="lazy">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-award"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Quality Focused</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Zero artificial chemicals, synthetic food colorings, or industrial preservatives in any batch.</p>
                            <a href="about.php" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Pillar 4 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/packaging.jpg" alt="Thoughtful Packaging" class="v-why-img" loading="lazy">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-box-open"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Thoughtful Packaging</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Hygienic, aroma-preserving food-grade packaging that protects vital nutrients to your door.</p>
                            <a href="about.php" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Pillar 5 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/customer.jpg" alt="Customer First" class="v-why-img" loading="lazy">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-heart"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Customer First</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Pan-India delivery with real-time tracking, transparent communication, and dedicated support.</p>
                            <a href="about.php" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         5. HEALTHY CHOICES (Nutrition-First Products)
         ==================================================================== -->
    <section class="v-products-section" id="healthy-choices-section" style="background:#fcfaf5; padding:64px 0; border-top:1px solid #f0eadd;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:var(--v-forest);">NUTRITION FIRST</span>
                    <h2 class="v-section-title" style="color:var(--v-forest-deep); font-family:var(--v-font-serif);">HEALTHY CHOICES</h2>
                    <p class="v-section-subtitle text-left">Nourishing selections to support your everyday wellness journey.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:var(--v-forest); font-weight:600;">View All Healthy Choices &rarr;</a>
                </div>
            </div>
            <div class="row" id="healthy-choices-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         6. DID YOU KNOW? (Educational Food Wisdom)
         ==================================================================== -->
    <section class="v-know-section v-know-premium">
        <div class="v-dk-leaf v-dk-leaf-l"></div>
        <div class="v-dk-leaf v-dk-leaf-r"></div>
        <div class="v-dk-leaf v-dk-leaf-br"></div>

        <div class="container position-relative" style="z-index:2;">
            <div class="v-section-header">
                <span class="v-eyebrow v-dk-badge"><i class="fa-solid fa-seedling"></i> TRADITION &bull; NUTRITION &bull; HERITAGE</span>
                <h2 class="v-section-title v-dk-heading">DID YOU KNOW?</h2>
                <p class="v-section-subtitle">Factual insights on the health and nutritional heritage of Indian staples.</p>
                <div class="v-dk-divider"><span></span><i class="fa-solid fa-leaf"></i><span></span></div>
            </div>

            <div class="v-dk-grid">
                <!-- Card 1: Millets -->
                <div class="v-dk-card">
                    <div class="v-dk-img-wrap">
                        <img src="assets/thumbnail/millets.jpg" alt="Millets" class="v-dk-img" loading="lazy">
                        <div class="v-dk-icon-float"><i class="fa-solid fa-wheat-awn"></i></div>
                    </div>
                    <div class="v-dk-body">
                        <span class="v-dk-tag">ANCIENT GRAINS</span>
                        <h4 class="v-dk-title">Millets</h4>
                        <p class="v-dk-desc">Climate-resilient grains cultivated in India for millennia. Naturally gluten-free with low glycemic index and high dietary fibre.</p>
                        <a href="millets.php" class="v-dk-cta">Explore Millets <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Card 2: Cold-Pressed Oils -->
                <div class="v-dk-card">
                    <div class="v-dk-img-wrap">
                        <img src="assets/thumbnail/oil.jpg" alt="Cold-Pressed Oils" class="v-dk-img" loading="lazy">
                        <div class="v-dk-icon-float"><i class="fa-solid fa-droplet"></i></div>
                    </div>
                    <div class="v-dk-body">
                        <span class="v-dk-tag">UNREFINED OILS</span>
                        <h4 class="v-dk-title">Cold-Pressed Oils</h4>
                        <p class="v-dk-desc">Extracted without artificial heat or chemical hexanes, preserving natural polyphenols, Vitamin E, and original aroma.</p>
                        <a href="oils.php" class="v-dk-cta">Explore Oils <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Card 3: Whole Spices (center highlight) -->
                <div class="v-dk-card v-dk-card-center">
                    <div class="v-dk-img-wrap">
                        <img src="assets/thumbnail/spice.jpg" alt="Whole Spices" class="v-dk-img" loading="lazy">
                        <div class="v-dk-icon-float"><i class="fa-solid fa-fire-flame-curved"></i></div>
                    </div>
                    <div class="v-dk-body">
                        <span class="v-dk-tag">AROMATIC PURITY</span>
                        <h4 class="v-dk-title">Whole Spices</h4>
                        <p class="v-dk-desc">Unadulterated whole spices retain their natural volatile essential oils that factory-pulverized spice blends lose.</p>
                        <a href="spices.php" class="v-dk-cta">Explore Spices <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Card 4: Nuts & Dry Fruits -->
                <div class="v-dk-card">
                    <div class="v-dk-img-wrap">
                        <img src="assets/thumbnail/nuts.jpg" alt="Nuts and Dry Fruits" class="v-dk-img" loading="lazy">
                        <div class="v-dk-icon-float"><i class="fa-solid fa-apple-whole"></i></div>
                    </div>
                    <div class="v-dk-body">
                        <span class="v-dk-tag">CLEAN ENERGY</span>
                        <h4 class="v-dk-title">Nuts &amp; Dry Fruits</h4>
                        <p class="v-dk-desc">Hand-sorted whole almonds, cashews, and sun-dried figs provide clean plant protein, heart-healthy fats, and essential minerals.</p>
                        <a href="nuts.php" class="v-dk-cta">Explore Nuts <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Card 5: Palm Jaggery -->
                <div class="v-dk-card">
                    <div class="v-dk-img-wrap">
                        <img src="images/palm-jaggery.jpg" alt="Palm Jaggery" class="v-dk-img" loading="lazy">
                        <div class="v-dk-icon-float"><i class="fa-solid fa-tree"></i></div>
                    </div>
                    <div class="v-dk-body">
                        <span class="v-dk-tag">HERITAGE SWEETENER</span>
                        <h4 class="v-dk-title">Palm Jaggery</h4>
                        <p class="v-dk-desc">Made from traditional palm sap. An unrefined alternative to white refined sugar, rich in iron, magnesium, and potassium.</p>
                        <a href="palm-jaggery.php" class="v-dk-cta">Explore Jaggery <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         7. TRADITIONAL FOODS (Products)
         ==================================================================== -->
    <section class="v-products-section" id="traditional-foods-section" style="background:#f9f5ed; padding:64px 0; border-top:1px solid #e8e2d2;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:#8b5a2b;">HERITAGE TASTE</span>
                    <h2 class="v-section-title" style="color:#5c3a21; font-family:var(--v-font-serif);">TRADITIONAL FOODS</h2>
                    <p class="v-section-subtitle text-left">Authentic flavors rooted in generations of culinary wisdom.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:#8b5a2b; font-weight:600;">View All Traditional Foods &rarr;</a>
                </div>
            </div>
            <div class="row" id="traditional-foods-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         8. FROM FARM TO PACK & ARTISANAL OIL STORY (Process & Craft)
         ==================================================================== -->
    <div class="container my-5">
        <section class="v-farm-banner">
            <div class="v-farm-banner-content">
                <div>
                    <h2 style="font-family:var(--v-font-serif); margin-bottom:10px; font-size:28px;">From Farm to Pack</h2>
                    <p style="opacity:0.85; font-size:14px; margin-bottom:0;">Pure. Natural. Sourced With Care.</p>
                    <div style="margin-top:20px;">
                        <a href="about.php" class="v-btn-secondary" style="border-color:#fff; color:#fff; background:transparent;">Discover Our Process &rarr;</a>
                    </div>
                </div>
                <div class="v-farm-steps">
                    <div class="v-farm-step">
                        <div class="v-farm-icon"><i class="fa-solid fa-seedling"></i></div>
                        <span>Source</span>
                    </div>
                    <div class="v-farm-step">
                        <div class="v-farm-icon"><i class="fa-solid fa-microscope"></i></div>
                        <span>Quality Check</span>
                    </div>
                    <div class="v-farm-step">
                        <div class="v-farm-icon"><i class="fa-solid fa-gears"></i></div>
                        <span>Processing</span>
                    </div>
                    <div class="v-farm-step">
                        <div class="v-farm-icon"><i class="fa-solid fa-box-archive"></i></div>
                        <span>Packaging</span>
                    </div>
                </div>
            </div>
            <img src="images/hero-premium.jpg" alt="Farm to Pack Process" class="v-farm-banner-img" loading="lazy">
        </section>
    </div>

    <!-- Editorial Product Craft Section -->
    <section class="v-story-section">
        <div class="container">
            <div class="v-story-grid">
                <div class="v-story-media">
                    <img src="images/story-cold-pressed.jpg" alt="Valluvam Traditional Cold Pressed Sesame and Groundnut Oil" loading="lazy">
                </div>

                <div class="v-story-content">
                    <span class="v-eyebrow"><i class="fa-solid fa-droplet"></i> Artisanal Heritage</span>
                    <h2 class="v-story-heading">FROM TRADITION TO YOUR TABLE</h2>
                    <p class="v-story-prose">
                        In traditional Indian kitchens, oil was never an industrial chemical solvent — it was pure, cold-extracted liquid sunshine. At Valluvam, our wood cold-pressed (Marachekku) sesame, groundnut, and coconut oils are extracted slowly at room temperature using wooden pestles.
                    </p>

                    <div class="v-story-highlights">
                        <div class="v-story-point">
                            <i class="fa-solid fa-check-circle"></i>
                            <div class="v-story-point-text">
                                <strong>Why Customers Choose It</strong>
                                <span>Zero chemical bleaching, zero artificial refining, natural aroma intact.</span>
                            </div>
                        </div>
                        <div class="v-story-point">
                            <i class="fa-solid fa-check-circle"></i>
                            <div class="v-story-point-text">
                                <strong>Everyday Culinary Use</strong>
                                <span>Perfect for authentic tadka, daily curries, dosas, and healthy salad dressings.</span>
                            </div>
                        </div>
                    </div>

                    <a href="oils.php" class="v-btn-primary">
                        EXPLORE COLD-PRESSED OILS &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         9. GIFTING & FESTIVE COMBOS (Products)
         ==================================================================== -->
    <section class="v-products-section" id="gifting-section" style="background:#fffaf0; padding:64px 0; border-top:1px solid #f2ead3;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:#c0392b;">SHARE THE GOODNESS</span>
                    <h2 class="v-section-title" style="color:#7a1f16; font-family:var(--v-font-serif);">GIFTING &amp; COMBOS</h2>
                    <p class="v-section-subtitle text-left">Curated boxes and festive bundles for your loved ones.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="combo.php" class="v-btn-tertiary" style="color:#c0392b; font-weight:600;">View All Gifting &rarr;</a>
                </div>
            </div>
            <div class="row" id="gifting-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Curated Combos Visual Cards -->
    <section class="v-category-section" style="background:var(--v-cream); padding:50px 0;">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-gift"></i> Balanced Packs</span>
                <h2 class="v-section-title">CURATED COMBOS</h2>
                <p class="v-section-subtitle">Carefully matched combinations designed for complete kitchen health and thoughtful gifting.</p>
            </div>

            <div class="row">
                <div class="col-6 col-lg-3 mb-4">
                    <a href="combo.php" class="v-category-card">
                        <div class="v-category-img-wrap">
                            <img src="images/combo1.jpg" alt="Wellness Combo" class="v-category-img" loading="lazy">
                        </div>
                        <div class="v-category-info">
                            <h3 class="v-category-name">Wellness Combo</h3>
                            <p class="v-category-desc">Daily energy nuts, natural seeds, and nutrient-dense dry fruits.</p>
                            <span class="v-category-cta">View Details &rarr;</span>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg-3 mb-4">
                    <a href="combo.php" class="v-category-card">
                        <div class="v-category-img-wrap">
                            <img src="images/combo2.jpg" alt="Family Essentials" class="v-category-img" loading="lazy">
                        </div>
                        <div class="v-category-info">
                            <h3 class="v-category-name">Family Essentials</h3>
                            <p class="v-category-desc">Everyday cold-pressed oils, native dal, and heritage grains.</p>
                            <span class="v-category-cta">View Details &rarr;</span>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg-3 mb-4">
                    <a href="combo.php" class="v-category-card">
                        <div class="v-category-img-wrap">
                            <img src="images/combo3.jpg" alt="Traditional Foods Combo" class="v-category-img" loading="lazy">
                        </div>
                        <div class="v-category-info">
                            <h3 class="v-category-name">Traditional Foods</h3>
                            <p class="v-category-desc">Time-honored millets, palm jaggery, and unrefined wood-pressed oils.</p>
                            <span class="v-category-cta">View Details &rarr;</span>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg-3 mb-4">
                    <a href="combo.php" class="v-category-card">
                        <div class="v-category-img-wrap">
                            <img src="images/combo4.jpg" alt="Festive Gifting" class="v-category-img" loading="lazy">
                        </div>
                        <div class="v-category-info">
                            <h3 class="v-category-name">Festive Gifting</h3>
                            <p class="v-category-desc">Artisanal dry fruit boxes and wellness sets packaged for celebrations.</p>
                            <span class="v-category-cta">View Details &rarr;</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         10. EVERYDAY ESSENTIALS (Daily Pantry Products)
         ==================================================================== -->
    <section class="v-products-section" id="everyday-essentials-section" style="background:#ffffff; padding:64px 0; border-top:1px solid #eef0ec;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:#4a6050;">DAILY PANTRY</span>
                    <h2 class="v-section-title" style="color:#1a3d2b; font-family:var(--v-font-serif);">EVERYDAY ESSENTIALS</h2>
                    <p class="v-section-subtitle text-left">Pure and unadulterated staples for your daily cooking needs.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:#1a3d2b; font-weight:600;">View All Essentials &rarr;</a>
                </div>
            </div>
            <div class="row" id="everyday-essentials-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Strip -->
    <section class="v-trust-strip-section">
        <div class="container">
            <div class="v-trust-grid">
                <div class="v-trust-card">
                    <div class="v-trust-icon-box">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <div>
                        <h4 class="v-trust-card-title">Naturally Sourced</h4>
                        <p class="v-trust-card-desc">Direct from verified farming regions</p>
                    </div>
                </div>
                <div class="v-trust-card">
                    <div class="v-trust-icon-box">
                        <i class="fa-solid fa-certificate"></i>
                    </div>
                    <div>
                        <h4 class="v-trust-card-title">Quality Checked</h4>
                        <p class="v-trust-card-desc">Every batch rigorously verified</p>
                    </div>
                </div>
                <div class="v-trust-card">
                    <div class="v-trust-icon-box">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                    <div>
                        <h4 class="v-trust-card-title">Carefully Packed</h4>
                        <p class="v-trust-card-desc">Aroma-lock food-grade protection</p>
                    </div>
                </div>
                <div class="v-trust-card">
                    <div class="v-trust-icon-box">
                        <i class="fa-solid fa-handshake-angle"></i>
                    </div>
                    <div>
                        <h4 class="v-trust-card-title">Trusted Service</h4>
                        <p class="v-trust-card-desc">Doorstep delivery with real tracking</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Top Rated Section (Dynamic if database has rated products) -->
    <section class="v-products-section" id="top-rated-section" style="background:var(--v-cream); display:none; padding:64px 0;">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-fire"></i> Most Popular</span>
                <h2 class="v-section-title">TRENDING NOW</h2>
                <p class="v-section-subtitle">Real top-rated selections from our kitchen staples and wholesome dry fruits.</p>
            </div>
            <div class="row" id="top-rated-container"></div>
        </div>
    </section>

    <!-- ====================================================================
         11. CUSTOMER REVIEWS (Real Testimonials)
         ==================================================================== -->
    <section class="v-reviews-section">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-comments"></i> Real Stories</span>
                <h2 class="v-section-title">LOVED BY CUSTOMERS</h2>
                <p class="v-section-subtitle">Authentic feedback from families who trust Valluvam for their everyday groceries.</p>
            </div>

            <div class="v-reviews-grid">
                <div class="v-review-card">
                    <div>
                        <div class="v-review-stars">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        </div>
                        <p class="v-review-quote">"Best place for pure cold-pressed coconut oil and fresh nuts! Quick delivery and friendly service."</p>
                    </div>
                    <div class="v-review-author">
                        <img src="images/customer-1.jpeg" alt="Nirmal" class="v-review-avatar" loading="lazy">
                        <div>
                            <h5 class="v-review-name">Nirmal</h5>
                            <span class="v-review-role">Verified Customer</span>
                        </div>
                    </div>
                </div>

                <div class="v-review-card">
                    <div>
                        <div class="v-review-stars">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        </div>
                        <p class="v-review-quote">"Pure sesame and groundnut oil with amazing aroma! Wholesale rates are the best. Highly satisfied."</p>
                    </div>
                    <div class="v-review-author">
                        <img src="images/customer-2.jpeg" alt="Pari" class="v-review-avatar" loading="lazy">
                        <div>
                            <h5 class="v-review-name">Pari</h5>
                            <span class="v-review-role">Verified Customer</span>
                        </div>
                    </div>
                </div>

                <div class="v-review-card">
                    <div>
                        <div class="v-review-stars">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        </div>
                        <p class="v-review-quote">"Best quality sesame oil and dry fruits! Reliable delivery and good wholesale pricing."</p>
                    </div>
                    <div class="v-review-author">
                        <img src="images/customer-3.jpeg" alt="Santhosh" class="v-review-avatar" loading="lazy">
                        <div>
                            <h5 class="v-review-name">Santhosh</h5>
                            <span class="v-review-role">Verified Customer</span>
                        </div>
                    </div>
                </div>

                <div class="v-review-card">
                    <div>
                        <div class="v-review-stars">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        </div>
                        <p class="v-review-quote">"A one-stop shop for millets, nuts, and cold-pressed oils. Great customer service with quick delivery."</p>
                    </div>
                    <div class="v-review-author">
                        <img src="images/customer-4.jpeg" alt="Sanjay" class="v-review-avatar" loading="lazy">
                        <div>
                            <h5 class="v-review-name">Sanjay</h5>
                            <span class="v-review-role">Verified Customer</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- ====================================================================
         11b. SHOP VALLUVAM YOUR WAY — Marketplace Availability Strip
              Logos shown without links for now. Links will be added later.
         ==================================================================== -->
    <section class="v-mp-home-section">
        <div class="container">
            <div class="v-mp-home-header">
                <span class="v-eyebrow" style="justify-content:center;"><i class="fa-solid fa-store"></i> FIND US ON</span>
                <h2 class="v-mp-home-title">SHOP VALLUVAM YOUR WAY</h2>
                <p class="v-mp-home-sub">Also available on your favourite marketplace apps. Links coming soon.</p>
            </div>

            <div class="v-mp-home-grid">

                <!-- Zepto -->
                <div class="v-mp-home-card v-mp-home-coming" role="img" aria-label="Zepto — Coming Soon" data-mp-tooltip="Coming Soon on Zepto">
                    <div class="v-mp-home-logo-wrap">
                        <svg viewBox="0 0 130 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <!-- Zepto "Z" icon mark -->
                            <rect x="2" y="4" width="36" height="36" rx="10" fill="#5B2D8E"/>
                            <text x="9" y="30" font-family="Arial Black,Arial,sans-serif" font-size="22" font-weight="900" fill="#ffffff">Z</text>
                            <!-- Wordmark -->
                            <text x="46" y="30" font-family="Arial Black,Arial,sans-serif" font-size="22" font-weight="900" fill="#5B2D8E">zepto</text>
                        </svg>
                    </div>
                    <div class="v-mp-home-name" style="color:#5B2D8E;">Zepto</div>
                    <div class="v-mp-home-badge">Coming Soon</div>
                </div>

                <!-- Blinkit -->
                <div class="v-mp-home-card v-mp-home-coming" role="img" aria-label="Blinkit — Coming Soon" data-mp-tooltip="Coming Soon on Blinkit">
                    <div class="v-mp-home-logo-wrap">
                        <svg viewBox="0 0 150 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <!-- Blinkit yellow icon mark -->
                            <rect x="2" y="4" width="36" height="36" rx="10" fill="#F8C200"/>
                            <text x="9.5" y="30" font-family="Arial Black,Arial,sans-serif" font-size="18" font-weight="900" fill="#1c1c1c">b!</text>
                            <!-- Wordmark -->
                            <text x="46" y="30" font-family="Arial Black,Arial,sans-serif" font-size="20" font-weight="900" fill="#1c1c1c">blinkit</text>
                        </svg>
                    </div>
                    <div class="v-mp-home-name" style="color:#1c1c1c;">Blinkit</div>
                    <div class="v-mp-home-badge">Coming Soon</div>
                </div>

                <!-- Amazon -->
                <div class="v-mp-home-card v-mp-home-coming" role="img" aria-label="Amazon — Coming Soon" data-mp-tooltip="Coming Soon on Amazon">
                    <div class="v-mp-home-logo-wrap">
                        <svg viewBox="0 0 150 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <!-- Amazon wordmark + smile arrow -->
                            <text x="0" y="28" font-family="Arial,Helvetica,sans-serif" font-size="24" font-weight="700" fill="#0F1111">amazon</text>
                            <!-- Smile / arrow underline — Amazon's brand arc -->
                            <path d="M4 33 Q54 44 104 33" stroke="#FF9900" stroke-width="3.5" fill="none" stroke-linecap="round"/>
                            <!-- Arrow head -->
                            <polygon points="100,29 108,33 100,37" fill="#FF9900"/>
                        </svg>
                    </div>
                    <div class="v-mp-home-name" style="color:#0F1111;">Amazon</div>
                    <div class="v-mp-home-badge">Coming Soon</div>
                </div>

                <!-- Meesho -->
                <div class="v-mp-home-card v-mp-home-coming" role="img" aria-label="Meesho — Coming Soon" data-mp-tooltip="Coming Soon on Meesho">
                    <div class="v-mp-home-logo-wrap">
                        <svg viewBox="0 0 150 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <!-- Meesho "M" icon dot -->
                            <circle cx="18" cy="22" r="14" fill="#F43397"/>
                            <text x="10" y="28" font-family="Arial Black,Arial,sans-serif" font-size="18" font-weight="900" fill="#ffffff">M</text>
                            <!-- Wordmark -->
                            <text x="40" y="29" font-family="Arial,Helvetica,sans-serif" font-size="21" font-weight="800" fill="#F43397">meesho</text>
                        </svg>
                    </div>
                    <div class="v-mp-home-name" style="color:#F43397;">Meesho</div>
                    <div class="v-mp-home-badge">Coming Soon</div>
                </div>

            </div>
        </div>
    </section>

    <!-- ====================================================================
         12. B2B / WHOLESALE & PRIVATE LABEL
         ==================================================================== -->
    <section class="v-b2b-section">
        <div class="container">
            <div class="v-b2b-grid">
                <div>
                    <span class="v-b2b-badge">
                        <i class="fa-solid fa-handshake"></i> Business Partnerships
                    </span>
                    <h2 class="v-b2b-heading">LOOKING FOR BULK SUPPLY?</h2>
                    <p class="v-b2b-desc">
                        Solutions for retailers, hotels, restaurants, distributors, institutions and business buyers. Direct sourcing, commercial batch pricing, and dependable logistics.
                    </p>

                    <div class="v-b2b-features">
                        <div class="v-b2b-feat-item"><i class="fa-solid fa-circle-check"></i> Direct Farm Sourcing</div>
                        <div class="v-b2b-feat-item"><i class="fa-solid fa-circle-check"></i> Tiered Wholesale Pricing</div>
                        <div class="v-b2b-feat-item"><i class="fa-solid fa-circle-check"></i> Consistent Batch Quality</div>
                        <div class="v-b2b-feat-item"><i class="fa-solid fa-circle-check"></i> Pan-India Logistics Support</div>
                    </div>

                    <a href="b2b-wholesale.php" class="v-btn-primary">
                        REQUEST BULK QUOTE &rarr;
                    </a>
                </div>

                <div>
                    <div class="v-b2b-cta-card">
                        <h4>Partner with Valluvam</h4>
                        <p>Join hundreds of hotels, organic stores, and food enterprises relying on our authentic supply.</p>
                        <a href="tel:+918925969888" class="btn btn-outline-success btn-block py-3 font-weight-bold mb-2" style="border-radius:30px; border-color:var(--v-forest); color:var(--v-forest);">
                            <i class="fa-solid fa-phone mr-2"></i> +91 89259 69888
                        </a>
                        <a href="b2b-wholesale.php" class="v-btn-primary btn-block justify-content-center">
                            Submit B2B Enquiry
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Private Label White-Label Manufacturing -->
    <section class="v-privatelabel-section">
        <div class="container">
            <div class="v-privatelabel-grid">
                <div class="v-privatelabel-media">
                    <img src="images/private-label-oils.jpeg" alt="Valluvam Private Label Oils &amp; Contract Manufacturing" loading="lazy">
                </div>

                <div>
                    <span class="v-eyebrow"><i class="fa-solid fa-industry"></i> White Label Solutions</span>
                    <h2 class="v-section-title">BUILD YOUR OWN FOOD BRAND</h2>
                    <p class="v-section-subtitle" style="text-align:left; margin-left:0;">
                        Want your own brand on natural foods? Valluvam provides end-to-end white-label manufacturing, custom pouch and bottle packaging, and commercial volume supply.
                    </p>

                    <div class="v-privatelabel-features">
                        <div class="v-privatelabel-feat-box">
                            <h5>Bulk Supply</h5>
                            <p>Commercial drums, tins, and sacks ready for industrial distribution.</p>
                        </div>
                        <div class="v-privatelabel-feat-box">
                            <h5>Private Label</h5>
                            <p>Your brand name, custom logos, and barcodes on our verified foods.</p>
                        </div>
                        <div class="v-privatelabel-feat-box">
                            <h5>Custom Packaging</h5>
                            <p>Flexible bottle, tin, and stand-up pouch sizes to fit your target market.</p>
                        </div>
                        <div class="v-privatelabel-feat-box">
                            <h5>Business Support</h5>
                            <p>Regulatory guidance, testing certificates, and flexible minimum order sizes.</p>
                        </div>
                    </div>

                    <a href="https://api.whatsapp.com/send?phone=918925878327&amp;text=Hi%2C%20I%20am%20interested%20in%20Valluvam%27s%20Private%20Label%20program." target="_blank" rel="noopener" class="v-btn-primary">
                        <i class="fa-brands fa-whatsapp"></i> INQUIRE ABOUT PRIVATE LABEL &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         13. FROM OUR KITCHEN (Knowledge / Recipes)
         ==================================================================== -->
    <section class="v-blog-section">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-book-open"></i> Heritage Knowledge</span>
                <h2 class="v-section-title">FROM OUR KITCHEN</h2>
                <p class="v-section-subtitle">Traditional recipes, cooking secrets, and nutrient guides for mindful living.</p>
            </div>

            <div class="v-blog-grid">
                <div class="v-blog-card">
                    <div class="v-blog-img-wrap">
                        <img src="images/blog-oil.jpg" alt="Cold-Pressed Oil Guide" loading="lazy">
                    </div>
                    <div class="v-blog-body">
                        <span class="v-blog-cat">Oil Wisdom</span>
                        <h4 class="v-blog-title">Why Wood Cold-Pressed (Marachekku) Oils Are Worth It</h4>
                        <p class="v-blog-snippet">Understanding the crucial difference between unheated extraction and refined factory cooking oils.</p>
                        <a href="blog.php" class="v-blog-read-link">Read Guide &rarr;</a>
                    </div>
                </div>

                <div class="v-blog-card">
                    <div class="v-blog-img-wrap">
                        <img src="images/blog-millet.jpg" alt="Cooking Millets" loading="lazy">
                    </div>
                    <div class="v-blog-body">
                        <span class="v-blog-cat">Ancient Grains</span>
                        <h4 class="v-blog-title">How to Cook Foxtail, Kodo &amp; Little Millets Fluffily</h4>
                        <p class="v-blog-snippet">Simple soaking ratios and traditional timing to make millets a delicious everyday replacement for white rice.</p>
                        <a href="blog.php" class="v-blog-read-link">Read Guide &rarr;</a>
                    </div>
                </div>

                <div class="v-blog-card">
                    <div class="v-blog-img-wrap">
                        <img src="images/blog-spices.jpg" alt="Whole Spices Storage" loading="lazy">
                    </div>
                    <div class="v-blog-body">
                        <span class="v-blog-cat">Spice Care</span>
                        <h4 class="v-blog-title">Keeping Whole Spices Aromatic for Months</h4>
                        <p class="v-blog-snippet">Why storing unground cardamom, cloves, and pepper in airtight glass containers preserves essential oils.</p>
                        <a href="blog.php" class="v-blog-read-link">Read Guide &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         14. NEWSLETTER
         ==================================================================== -->
    <section class="v-newsletter-section">
        <div class="container">
            <div class="v-newsletter-box">
                <div>
                    <h3 class="v-newsletter-title">STAY CONNECTED WITH VALLUVAM</h3>
                    <p class="v-newsletter-desc">Discover new harvest arrivals, traditional recipes, and wholesome food stories delivered straight to your inbox.</p>
                </div>
                <div>
                    <form action="#" class="v-newsletter-form">
                        <input type="email" class="v-newsletter-input" placeholder="Enter your email address" required>
                        <button type="submit" class="v-newsletter-btn">SUBSCRIBE &rarr;</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         15. FINAL BANNER CTA
         ==================================================================== -->
    <section class="v-final-cta-section">
        <img src="images/cta-pantry.jpg" alt="Valluvam Natural Food Pantry" class="v-final-cta-bg" loading="lazy">
        <div class="container v-final-cta-content">
            <span class="v-eyebrow" style="background: rgba(255,255,255,0.2); color:#ffffff;">Everyday Purity</span>
            <h2 class="v-final-cta-title">BRING BETTER FOOD TO YOUR EVERYDAY TABLE</h2>
            <p class="v-final-cta-desc">Taste the uncompromised difference of unrefined, traditional Indian staples in your home.</p>
            <div class="v-final-cta-btns">
                <a href="shop.php" class="v-btn-primary">
                    SHOP PRODUCTS <i class="fa-solid fa-arrow-right"></i>
                </a>
                <a href="about.php" class="v-btn-secondary">
                    EXPLORE VALLUVAM
                </a>
            </div>
        </div>
    </section>

    <!-- Loader & Scripts -->
    <div id="ftco-loader" class="show fullscreen" style="display:none !important;">
        <svg class="circular" width="48px" height="48px">
            <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee" />
            <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#133826" />
        </svg>
    </div>

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
    <script src="assets/js/index/index.js?v=<?php echo @filemtime(__DIR__ . "/assets/js/index/index.js"); ?>"></script>
    <script src="assets/js/index/homepage-cms.js?v=<?php echo @filemtime(__DIR__ . "/assets/js/index/homepage-cms.js"); ?>"></script>

    <!-- ====================================================================
         16. FOOTER INCLUSION
         ==================================================================== -->
    <?php include "footer.php"; ?>
