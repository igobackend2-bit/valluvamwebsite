<?php 
$activePage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valluvam — Premium Natural Foods | Cold-Pressed Oils, Dry Fruits, Nuts &amp; Spices</title>
    <meta name="description" content="Discover pure, authentic Indian foods from Valluvam. Premium wood cold-pressed oils, hand-picked nuts, dry fruits, unadulterated spices, native millets and heritage rice. Naturally sourced, quality checked, delivered pan-India.">
    <meta name="keywords" content="Valluvam, cold pressed oils, organic dry fruits, premium nuts online, whole spices, millets delivery, traditional Indian food, farm fresh groceries">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.valluvamproducts.com/">

    <!-- Social & Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Valluvam — Good Food, Rooted in Tradition">
    <meta property="og:description" content="Discover pure, authentic Indian foods from Valluvam. Cold-pressed oils, nuts, dry fruits, spices and millets delivered with care.">
    <meta property="og:url" content="https://www.valluvamproducts.com/">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Valluvam — Good Food, Rooted in Tradition">
    <meta name="twitter:description" content="Discover pure, authentic Indian foods from Valluvam. Cold-pressed oils, nuts, dry fruits, spices and millets delivered with care.">
    <meta name="twitter:image" content="/images/logo.png">

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">

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
</head>

<body class="goto-here">

    <!-- ====================================================================
         1. HERO SECTION (Full-Width Cinematic Natural Food Presentation)
         ==================================================================== -->
    <section class="v-hero-section" id="home-section">
        <div class="container">
            <div class="v-hero-grid">
                <!-- Left: Editorial Content -->
                <div class="v-hero-content">
                    <span class="v-hero-badge">
                        <i class="fa-solid fa-seedling"></i> GOOD FOOD.
                    </span>
                    <h1 class="v-hero-heading">
                        ROOTED IN <span class="v-hero-accent">TRADITION.</span>
                    </h1>
                    <p class="v-hero-subtext">
                        Carefully sourced Indian foods for everyday living. From wood cold-pressed oils and sun-dried spices to hand-sorted nuts and ancient grains.
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
                        <li><i class="fa-solid fa-shield-halved"></i> Quality Checked</li>
                        <li><i class="fa-solid fa-truck-fast"></i> Tracked Pan-India Delivery</li>
                        <li><i class="fa-solid fa-lock"></i> Secure Checkout</li>
                    </ul>
                </div>

                <!-- Right: Visual Food Photography -->
                <div class="v-hero-media-wrap">
                    <div class="v-hero-frame">
                        <img src="images/hero-premium.jpg" alt="Valluvam natural cold-pressed oils, nuts, spices, and heritage grains" class="v-hero-img" width="800" height="550" loading="eager">
                    </div>
                    <div class="v-hero-float-card">
                        <div class="v-hero-float-icon">
                            <i class="fa-solid fa-award"></i>
                        </div>
                        <div>
                            <p class="v-hero-float-title">100% Pure &amp; Unadulterated</p>
                            <p class="v-hero-float-desc">Hand-selected at the harvest source</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

        

    <!-- ====================================================================
         2. OUR COLLECTIONS (Redesigned)
         ==================================================================== -->
    <section class="v-collections-section">
        <div class="v-collections-bg-leaf"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-3 mb-4 mb-lg-0">
                    <div class="v-col-title-wrap">
                        <span class="v-eyebrow" style="color:var(--v-forest);">CURATED FOR YOU</span>
                        <h2 class="v-section-title" style="color:var(--v-forest-deep); font-family:var(--v-font-serif);">Our Collections</h2>
                        <p class="v-section-subtitle" style="text-align:left; max-width:100%; margin-bottom:20px;">Thoughtfully curated for every occasion, season and lifestyle.</p>
                        <div>
                            <a href="shop.php" class="v-btn-secondary" style="border:1px solid var(--v-forest); color:var(--v-forest);">Explore All Collections &rarr;</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="v-col-grid">
                        <div class="v-col-card">
                            <img src="images/combo1.jpg" alt="Best Sellers" class="v-col-img" loading="lazy">
                            <div class="v-col-overlay">
                                <h3 class="v-col-name">Best Sellers</h3>
                                <p class="v-col-desc">Our most loved products</p>
                                <a href="shop.php" class="v-col-link">Explore &rarr;</a>
                            </div>
                        </div>
                        <div class="v-col-card">
                            <img src="images/combo2.jpg" alt="Healthy Choices" class="v-col-img" loading="lazy">
                            <div class="v-col-overlay">
                                <h3 class="v-col-name">Healthy Choices</h3>
                                <p class="v-col-desc">Good food, better living</p>
                                <a href="shop.php" class="v-col-link">Explore &rarr;</a>
                            </div>
                        </div>
                        <div class="v-col-card">
                            <img src="images/combo3.jpg" alt="Traditional Foods" class="v-col-img" loading="lazy">
                            <div class="v-col-overlay">
                                <h3 class="v-col-name">Traditional Foods</h3>
                                <p class="v-col-desc">Timeless taste</p>
                                <a href="shop.php" class="v-col-link">Explore &rarr;</a>
                            </div>
                        </div>
                        <div class="v-col-card">
                            <img src="images/combo4.jpg" alt="Gifting" class="v-col-img" loading="lazy">
                            <div class="v-col-overlay">
                                <h3 class="v-col-name">Gifting</h3>
                                <p class="v-col-desc">Share the goodness</p>
                                <a href="combo.php" class="v-col-link">Explore &rarr;</a>
                            </div>
                        </div>
                        <div class="v-col-card">
                            <img src="assets/thumbnail/rice.jpg" alt="Everyday Essentials" class="v-col-img" loading="lazy">
                            <div class="v-col-overlay">
                                <h3 class="v-col-name">Everyday Essentials</h3>
                                <p class="v-col-desc">For your daily needs</p>
                                <a href="shop.php" class="v-col-link">Explore &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- ====================================================================
         3. SHOP BY CATEGORY (Redesigned)
         ==================================================================== -->
    <section class="v-cat-circle-section" id="shop-by-category">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:var(--v-forest);">SHOP BY CATEGORY</span>
                    <h2 class="v-section-title" style="color:var(--v-forest-deep); font-size:clamp(28px, 4vw, 42px); font-family:var(--v-font-serif);">Natture's Goodness, In Every Category</h2>
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
                        <img src="assets/thumbnail/nuts.jpg" alt="Nuts">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Nuts</div>
                    <div class="v-cat-circle-sub">Crunchy &amp; Nutritious</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>
                
                <a href="dryfruits.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="assets/thumbnail/dryfruits.jpg" alt="Dry Fruits">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Dry Fruits</div>
                    <div class="v-cat-circle-sub">Nature's Energy</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="oils.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="assets/thumbnail/oil.jpg" alt="Cold Pressed Oils">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Cold Pressed Oils</div>
                    <div class="v-cat-circle-sub">Pure &amp; Healthy</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="spices.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="assets/thumbnail/spice.jpg" alt="Spices">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Spices</div>
                    <div class="v-cat-circle-sub">Rich in Flavour</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="millets.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="assets/thumbnail/millets.jpg" alt="Millets">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Millets</div>
                    <div class="v-cat-circle-sub">Ancient Superfood</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="rice.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="assets/thumbnail/rice.jpg" alt="Rice">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Rice</div>
                    <div class="v-cat-circle-sub">Wholesome &amp; Natural</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>

                <a href="combo.php" class="v-cat-circle-card">
                    <div class="v-cat-image-wrap">
                        <img src="assets/thumbnail/combo.jpg" alt="Combos">
                        <div class="v-cat-icon-sm"><i class="fa-solid fa-leaf"></i></div>
                    </div>
                    <div class="v-cat-circle-title">Combos</div>
                    <div class="v-cat-circle-sub">Better Together</div>
                    <div class="v-cat-circle-link">Explore &rarr;</div>
                </a>
            </div>
        </div>
    </section>

<!-- ====================================================================
         4. BEST PRODUCTS (Dynamic from Database)
         ==================================================================== -->
    <section class="v-products-section" id="best-products-section">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-star"></i> Customer Favorites</span>
                <h2 class="v-section-title">BEST PRODUCTS</h2>
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
         2. TRUST STRIP
         ==================================================================== -->
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

    
<!-- ====================================================================
         5. PREMIUM PROMOTIONAL AREA (3-Column Layout)
         ==================================================================== -->
    <section class="v-promo-section">
        <div class="container">
            <div class="v-promo-grid">
                <!-- CARD 1: PREMIUM COMBOS -->
                <div class="v-promo-card v-promo-card-1">
                    <div>
                        <span class="v-promo-tag">Family Wellness</span>
                        <h3 class="v-promo-title">PREMIUM COMBOS</h3>
                        <p class="v-promo-desc">Curated products for complete family wellness and everyday nourishment.</p>
                    </div>
                    <a href="combo.php" class="v-promo-btn">
                        EXPLORE COMBOS &rarr;
                    </a>
                </div>

                <!-- CARD 2: SPECIAL OFFERS -->
                <div class="v-promo-card v-promo-card-2">
                    <div>
                        <span class="v-promo-tag">Value Packs</span>
                        <h3 class="v-promo-title">SPECIAL OFFERS</h3>
                        <p class="v-promo-desc">Hand-picked seasonal discounts on essential kitchen staples.</p>
                    </div>
                    <a href="shop.php" class="v-promo-btn">
                        SHOP NOW &rarr;
                    </a>
                </div>

                <!-- CARD 3: SHOP BY GOAL -->
                <div class="v-promo-card v-promo-card-3">
                    <div>
                        <span class="v-promo-tag">Personalized Nutrition</span>
                        <h3 class="v-promo-title">SHOP BY GOAL</h3>
                        <p class="v-promo-desc">Wellness, Kitchen Essentials, Traditional Foods, and Healthy Gifting.</p>
                    </div>
                    <a href="shop.php" class="v-promo-btn">
                        FIND YOUR FIT &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         6. TRENDING NOW / TOP RATED SECTION
         ==================================================================== -->
    <section class="v-products-section" id="top-rated-section" style="background:var(--v-cream); display:none;">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-fire"></i> Most Popular</span>
                <h2 class="v-section-title">TRENDING NOW</h2>
                <p class="v-section-subtitle">Real top-rated selections from our kitchen staples and wholesome dry fruits.</p>
            </div>

            <!-- Populated via loadTopRated() in index.js -->
            <div class="row" id="top-rated-container"></div>
        </div>
    </section>

    <!-- ====================================================================
         7. SPECIAL OFFERS BANNER (OFFERS YOU'LL LOVE)
         ==================================================================== -->
    <section class="v-promo-section" style="background:#ffffff;">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-tag"></i> Pure Savings</span>
                <h2 class="v-section-title">OFFERS YOU'LL LOVE</h2>
                <p class="v-section-subtitle">Verified value on everyday essentials, cold-pressed oils, and farm staples.</p>
            </div>

            <div class="row align-items-center" style="background: linear-gradient(135deg, #133826 0%, #1e5237 100%); border-radius: var(--v-radius-xl); overflow: hidden; padding: 40px; color:#ffffff;">
                <div class="col-lg-7">
                    <span class="v-eyebrow" style="background: rgba(255,255,255,0.15); color:#ffffff;">Seasonal Pantry Savings</span>
                    <h3 style="font-size: clamp(26px, 3vw, 38px); color:#ffffff; margin: 14px 0 16px;">Stock Up on Pure Indian Staples</h3>
                    <p style="font-size: 16px; color: rgba(255,255,255,0.9); line-height: 1.6; max-width: 500px; margin-bottom: 26px;">
                        Save more on wholesome family bundles. Multi-pack cold-pressed oils, nutritious millets, and daily cooking essentials without compromising on unrefined purity.
                    </p>
                    <a href="shop.php" class="v-btn-primary" style="background:#ffffff; color:#133826 !important; border-color:#ffffff;">
                        DISCOVER ALL OFFERS &rarr;
                    </a>
                </div>
                <div class="col-lg-5 text-center mt-4 mt-lg-0">
                    <img src="images/story-cold-pressed.jpg" alt="Special Offers" class="img-fluid rounded-lg shadow-lg" style="max-height: 280px; object-fit: cover; border-radius: var(--v-radius-lg);" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         8. CURATED COMBOS
         ==================================================================== -->
    <section class="v-category-section" style="background:var(--v-cream);">
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
                            <img src="images/combo2.jpg" alt="Family Combo" class="v-category-img" loading="lazy">
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
                            <img src="images/combo4.jpg" alt="Gifting Combo" class="v-category-img" loading="lazy">
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
         9. WHY VALLUVAM (Brand Story & Pillars)
         ==================================================================== -->
    <section class="v-why-section">
        <!-- Subtle background shapes -->
        <div class="v-why-bg-leaf v-why-leaf-left"></div>
        <div class="v-why-bg-leaf v-why-leaf-right"></div>
        <div class="v-why-bg-wave"></div>

        <div class="container relative-z">
            <div class="v-section-header">
                <span class="v-eyebrow v-eyebrow-pale">◆ THE VALLUVAM PROMISE</span>
                <h2 class="v-section-title">WHY VALLUVAM?</h2>
                <p class="v-section-subtitle">We honor the purity of traditional Indian food wisdom with modern transparency.</p>
            </div>

            <!-- Swipe carousel on mobile, grid on desktop -->
            <div class="v-why-wrapper">
                <div class="v-why-grid">
                    
                    <!-- Card 1 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/sourced.jpg" alt="Carefully Sourced" class="v-why-img">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-seedling"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Carefully Sourced</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Sourced directly from verified farming regions known for authentic native soil and heritage harvesting.</p>
                            <a href="#" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/traditional.jpg" alt="Traditional Products" class="v-why-img">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-bowl-rice"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Traditional Products</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Time-honored techniques including wood cold-pressing (Marachekku) and slow stone processing.</p>
                            <a href="#" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Card 3 (Center Highlighted) -->
                    <div class="v-why-card v-why-highlight">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/quality.jpg" alt="Quality Focused" class="v-why-img">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-award"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Quality Focused</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Zero artificial chemicals, synthetic food colorings, or industrial preservatives in any batch.</p>
                            <a href="#" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/packaging.jpg" alt="Thoughtful Packaging" class="v-why-img">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-box-open"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Thoughtful Packaging</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Hygienic, aroma-preserving food-grade packaging that protects vital nutrients to your door.</p>
                            <a href="#" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                    <!-- Card 5 -->
                    <div class="v-why-card">
                        <div class="v-why-image-wrap">
                            <img src="images/why-valluvam/customer.jpg" alt="Customer First" class="v-why-img">
                            <div class="v-why-icon-badge"><i class="fa-solid fa-heart"></i></div>
                        </div>
                        <div class="v-why-content">
                            <h3 class="v-why-title">Customer First</h3>
                            <div class="v-why-divider"></div>
                            <p class="v-why-desc">Pan-India delivery with real-time tracking, transparent communication, and dedicated support.</p>
                            <a href="#" class="v-why-cta">Learn More <i class="fa-solid fa-arrow-right"></i></a>
                            <div class="v-why-decor"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

        <!-- ====================================================================
         10. FROM FARM TO PACK (Redesigned Banner)
         ==================================================================== -->
    <div class="container">
        <section class="v-farm-banner">
            <div class="v-farm-banner-content">
                <div>
                    <h2 style="font-family:var(--v-font-serif); margin-bottom:10px; font-size:28px;">From Farm to Pack</h2>
                    <p style="opacity:0.8; font-size:14px; margin-bottom:0;">Pure. Natural. With Care.</p>
                    <div style="margin-top:20px;">
                        <a href="#" class="v-btn-secondary" style="border-color:#fff; color:#fff; background:transparent;">Discover Our Process &rarr;</a>
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
            <img src="images/hero-premium.jpg" alt="Farm to Pack" class="v-farm-banner-img">
        </section>
    </div>

<!-- ====================================================================
         11. FEATURED PRODUCT STORY (Editorial Split Section)
         ==================================================================== -->
    <section class="v-story-section">
        <div class="container">
            <div class="v-story-grid">
                <!-- Left: Editorial Product Photography -->
                <div class="v-story-media">
                    <img src="images/story-cold-pressed.jpg" alt="Valluvam Traditional Cold Pressed Sesame and Groundnut Oil" loading="lazy">
                </div>

                <!-- Right: Story Narrative -->
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
         12. DID YOU KNOW? (Educational Cards)
         ==================================================================== -->
    <section class="v-know-section">
        <div class="container">
            <div class="v-section-header">
                <span class="v-eyebrow"><i class="fa-solid fa-lightbulb"></i> Food Wisdom</span>
                <h2 class="v-section-title">DID YOU KNOW?</h2>
                <p class="v-section-subtitle">Factual insights on the health and nutritional heritage of Indian staples.</p>
            </div>

            <div class="v-know-grid">
                <div class="v-know-card">
                    <div>
                        <span class="v-know-tag">Ancient Grains</span>
                        <h4 class="v-know-title">Millets</h4>
                        <p class="v-know-desc">Climate-resilient grains cultivated in India for millennia. Naturally gluten-free with low glycemic index and high dietary fibre.</p>
                    </div>
                    <a href="millets.php" class="v-know-link">Explore Millets &rarr;</a>
                </div>

                <div class="v-know-card">
                    <div>
                        <span class="v-know-tag">Unrefined Oils</span>
                        <h4 class="v-know-title">Cold-Pressed Oils</h4>
                        <p class="v-know-desc">Extracted without artificial heat or chemical hexanes, preserving natural polyphenols, Vitamin E, and original aroma.</p>
                    </div>
                    <a href="oils.php" class="v-know-link">Explore Oils &rarr;</a>
                </div>

                <div class="v-know-card">
                    <div>
                        <span class="v-know-tag">Aromatic Purity</span>
                        <h4 class="v-know-title">Whole Spices</h4>
                        <p class="v-know-desc">Unadulterated whole spices retain their natural volatile essential oils that factory-pulverized spice blends lose.</p>
                    </div>
                    <a href="spices.php" class="v-know-link">Explore Spices &rarr;</a>
                </div>

                <div class="v-know-card">
                    <div>
                        <span class="v-know-tag">Clean Energy</span>
                        <h4 class="v-know-title">Nuts &amp; Dry Fruits</h4>
                        <p class="v-know-desc">Hand-sorted whole almonds, cashews, and sun-dried figs provide clean plant protein, heart-healthy fats, and essential minerals.</p>
                    </div>
                    <a href="nuts.php" class="v-know-link">Explore Nuts &rarr;</a>
                </div>

                <div class="v-know-card">
                    <div>
                        <span class="v-know-tag">Heritage Sweetener</span>
                        <h4 class="v-know-title">Palm Jaggery</h4>
                        <p class="v-know-desc">Made from traditional palm sap. An unrefined alternative to white refined sugar, rich in iron, magnesium, and potassium.</p>
                    </div>
                    <a href="palm-jaggery.php" class="v-know-link">Explore Jaggery &rarr;</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         13. SHOP BY LIFESTYLE (Premium Redesign)
         ==================================================================== -->
    <section class="v-lifestyle-section v-lifestyle-premium">
        <!-- Botanical leaf decorations -->
        <div class="v-ls-leaf v-ls-leaf-tl"></div>
        <div class="v-ls-leaf v-ls-leaf-br"></div>
        <div class="v-ls-leaf v-ls-leaf-tr"></div>

        <div class="container position-relative">
            <div class="v-section-header">
                <span class="v-eyebrow v-ls-badge"><i class="fa-solid fa-heart"></i> DAILY LIVING</span>
                <h2 class="v-section-title v-ls-heading">SHOP FOR <span class="v-ls-accent">EVERYDAY LIFE</span> <span class="v-ls-leaf-icon">🌿</span></h2>
                <p class="v-section-subtitle">Tailored categories designed around your household rhythms and culinary habits.</p>
                <!-- Decorative divider -->
                <div class="v-ls-divider"><span></span><i class="fa-solid fa-leaf"></i><span></span></div>
            </div>

            <div class="v-ls-grid">

                <!-- Card 1: Everyday Pantry -->
                <a href="shop.php" class="v-ls-card" style="--ls-bg: #fef9f0;">
                    <div class="v-ls-img-wrap">
                        <img src="images/lifestyle/pantry.jpg" alt="Everyday Pantry" class="v-ls-img" loading="lazy">
                        <div class="v-ls-icon-float">
                            <i class="fa-solid fa-kitchen-set"></i>
                        </div>
                    </div>
                    <div class="v-ls-body">
                        <h4 class="v-ls-name">Everyday Pantry</h4>
                        <p class="v-ls-sub">Rice, dal, salt, cold-pressed oils</p>
                        <span class="v-ls-cta">Shop Now <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>

                <!-- Card 2: Traditional Kitchen -->
                <a href="millets.php" class="v-ls-card" style="--ls-bg: #f5f5ef;">
                    <div class="v-ls-img-wrap">
                        <img src="assets/thumbnail/spice.jpg" alt="Traditional Kitchen" class="v-ls-img" loading="lazy">
                        <div class="v-ls-icon-float">
                            <i class="fa-solid fa-mortar-pestle"></i>
                        </div>
                    </div>
                    <div class="v-ls-body">
                        <h4 class="v-ls-name">Traditional Kitchen</h4>
                        <p class="v-ls-sub">Native spices, millets &amp; jaggery</p>
                        <span class="v-ls-cta">Shop Now <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>

                <!-- Card 3: Healthy Choices (center, highlighted) -->
                <a href="nuts.php" class="v-ls-card v-ls-card-center" style="--ls-bg: #f2f7f2;">
                    <div class="v-ls-img-wrap">
                        <img src="assets/thumbnail/nuts.jpg" alt="Healthy Choices" class="v-ls-img" loading="lazy">
                        <div class="v-ls-icon-float">
                            <i class="fa-solid fa-person-running"></i>
                        </div>
                    </div>
                    <div class="v-ls-body">
                        <h4 class="v-ls-name">Healthy Choices</h4>
                        <p class="v-ls-sub">Almonds, chia seeds &amp; walnuts</p>
                        <span class="v-ls-cta">Shop Now <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>

                <!-- Card 4: Family Essentials -->
                <a href="honey.php" class="v-ls-card" style="--ls-bg: #fef6f0;">
                    <div class="v-ls-img-wrap">
                        <img src="assets/thumbnail/dryfruits.jpg" alt="Family Essentials" class="v-ls-img" loading="lazy">
                        <div class="v-ls-icon-float">
                            <i class="fa-solid fa-people-roof"></i>
                        </div>
                    </div>
                    <div class="v-ls-body">
                        <h4 class="v-ls-name">Family Essentials</h4>
                        <p class="v-ls-sub">Pure ghee, raw honey &amp; dry fruits</p>
                        <span class="v-ls-cta">Shop Now <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>

                <!-- Card 5: Gifting -->
                <a href="combo.php" class="v-ls-card" style="--ls-bg: #f8f2f8;">
                    <div class="v-ls-img-wrap">
                        <img src="images/combo4.jpg" alt="Gifting" class="v-ls-img" loading="lazy">
                        <div class="v-ls-icon-float">
                            <i class="fa-solid fa-gift"></i>
                        </div>
                    </div>
                    <div class="v-ls-body">
                        <h4 class="v-ls-name">Gifting</h4>
                        <p class="v-ls-sub">Festive boxes &amp; wellness packs</p>
                        <span class="v-ls-cta">Shop Now <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </a>

            </div>
        </div>
    </section>

    <!-- ====================================================================
         14. CUSTOMER REVIEWS (Real Testimonials)
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
         15. B2B / WHOLESALE SUPPLY
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

    <!-- ====================================================================
         16. PRIVATE LABEL (BUILD YOUR OWN BRAND)
         ==================================================================== -->
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
         17. FROM OUR KITCHEN (Knowledge / Recipes)
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
         18. NEWSLETTER
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
         19. FINAL BANNER CTA
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

    <!-- ====================================================================
         20. FOOTER INCLUSION
         ==================================================================== -->
    <?php include "footer.php"; ?>

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

</body>

</html>
