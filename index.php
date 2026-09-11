<?php $activePage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php"
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- <style>
        /* Hero Video */
        .outter.hero-video {
            width: 100%;
            /* height: 100%; */
            display: flex;
            flex-direction: column;
            justify-content: center;

            @media (max-width: 767px) {
                height: 325px;
            }
        }

        .hero-video {

            .video-container {
                height: 550px;
                width: 100%;
                position: relative;
                overflow: hidden;

                @media (max-width: 767px) {
                    height: 325px;
                }
            }

            video {
                object-fit: cover;
                position: absolute;
                height: 550px;
                width: 100%;
                top: 0;
                left: 0;

                @media (max-width: 767px) {
                    height: 325px;
                }
            }

            .video-container:after {
                content: '';
                display: block;
                height: 100%;
                width: 100%;
                position: absolute;
                top: 0;
                left: 0;
                background: rgba(black, .2);
                z-index: 1;
            }

            h1 {
                text-transform: uppercase;
                margin: 0 0 1rem;
                padding: 0;
                line-height: 1;
                color: white;

                @media (max-width: 767px) {
                    font-size: 32px;
                }

                @media (min-width: 768px) {
                    font-size: 52px;
                }
            }

            .desc {
                color: white;
                font-weight: 400;
                font-size: 18px;
            }

            .callout {
                position: relative;
                display: flex;
                justify-content: center;
                align-items: center;
                flex-direction: column;
                height: 100%;
                text-align: center;
                position: relative;
                z-index: 10;
                width: 70%;
                margin: auto;

                @media (max-width: 767px) {
                    width: 90%;
                }
            }

            .button {
                text-transform: uppercase;
                background-color: transparent;
                border-radius: 0px;
                margin-top: 20px;
                background-color: #82bb00;
                padding: 15px 30px;
                border-radius: 0px;
                color: #fff;
                text-decoration: none;
                font-weight: bold;

                @media (max-width: 767px) {
                    padding: 10px 20px;
                }
            }

            .button:hover {
                cursor: pointer;
                background-color: #6e9e01;
            }
        }
    </style> -->
    <style>
        /* ===== MOBILE HERO VIEW CHANGE ONLY =====
           Re-scoped for the new hero copy/CTAs added below. The banner used
           to carry no text on mobile (everything was commented out), so this
           block dropped the overlay and shrank the slide to a short,
           image-only strip. Now that the hero has a heading, sub-copy and
           two buttons again, that same treatment would sit white-on-bright-
           photo with no contrast and clip the content. Height/overlay are
           restored close to the desktop treatment so the text stays legible
           and doesn't overlap the artwork; nothing outside #home-section is
           touched. */
        @media (max-width: 576px) {

            #home-section {
                height: auto;
            }

            #home-section .slider-item {
                height: 420px;
                min-height: auto;
                background-size: cover;
                background-position: center center;
                background-repeat: no-repeat;
                background-color: #000;
            }

            /* Keep a soft overlay so white text stays readable on any photo */
            #home-section .overlay {
                background: rgba(0, 0, 0, 0.35);
            }

            .home-slider .slider-text {
                min-height: 0;
                padding: 0 10px;
            }

            .home-slider .owl-stage,
            .home-slider .owl-item {
                height: auto !important;
            }
        }
    </style>
    <!-- ===== Homepage refinement (index.php only) =====
         Additive styles for the new/expanded homepage sections below:
         hero CTAs, the "Shop by Category" and "Featured Products" headings,
         the "Why Choose Valluvam" trust grid, the brand-story panel, the
         B2B teaser and the FAQ accordion. Nothing here touches selectors
         used on other pages. -->
    <style>
        /* Hero CTAs - .btn/.btn-primary are already site buttons; this just
           gives the pair breathing room under the heading. */
        .home-slider .hero-cta {
            margin-top: 10px;
        }

        .home-slider .hero-cta .btn {
            margin: 6px 8px;
        }

        .home-slider .slider-text .subheading {
            letter-spacing: 3px;
        }

        /* .btn-outline-primary is green-on-transparent by default (see
           css/style.css), which is too low-contrast sitting directly on a
           photo. In the hero only, swap it to a white outline so it's
           readable against any of the three banner images. */
        .home-slider .btn-primary.btn-outline-primary {
            border-color: #fff;
            color: #fff;
        }

        .home-slider .btn-primary.btn-outline-primary:hover {
            background: #fff;
            color: #82ae46;
            border-color: #fff;
        }

        /* Anchor targets so the fixed-ish navbar spacing doesn't crowd the
           section heading when a hero CTA jumps to it. */
        #shop-by-category,
        #our-products {
            scroll-margin-top: 90px;
        }

        /* "View All Products" CTA under the featured grid */
        .products-view-all {
            text-align: center;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* Brand-story panel (existing markup, re-enabled below): the image
           used bare `object-fit: contain` with no defined box, so on some
           viewports it could render very short/tall. Giving it a fixed,
           responsive aspect ratio keeps it consistent with the rest of the
           card. */
        .brand-story-img {
            aspect-ratio: 4 / 3;
            width: 100%;
            object-fit: cover;
        }

        @media (max-width: 991.98px) {
            .brand-story-img {
                aspect-ratio: 16 / 9;
            }
        }

        /* B2B teaser "who we supply" chip row */
        .b2b-teaser-audience {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin: 28px 0 34px;
            padding: 0;
            list-style: none;
        }

        .b2b-teaser-audience li {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f6f8f1;
            border: 1px solid #e3e9d8;
            border-radius: 30px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 500;
            color: #252525;
        }

        .b2b-teaser-audience li ion-icon {
            color: #82ae46;
            font-size: 18px;
        }

        /* FAQ accordion - same visual treatment as the B2B page's FAQ so the
           two pages read as one system; copied here (not linked) because
           this block lives in b2b-wholesale.php's own inline <style> and
           isn't available on index.php. */
        .faq-card {
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 14px;
            overflow: hidden;
        }

        .faq-card .card-header {
            background-color: #f6f8f1;
            padding: 0;
            border: none;
        }

        .faq-card .card-header button {
            width: 100%;
            text-align: left;
            padding: 16px 20px;
            font-weight: 600;
            color: #252525;
            text-decoration: none;
        }

        .faq-card .card-body {
            font-size: 14.5px;
            color: #6b6b6b;
        }

        .faq-card .card-body a {
            color: #82ae46;
            font-weight: 500;
        }
    </style>
    <!-- title tag -->
    <title>Buy Premium Dry Fruits, Nuts, Spices & Cold Pressed Oils Online | Valluvam
</title>
    <!-- meta tag -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Valluvam brings you nuts, dry fruits, cold-pressed oils, spices, and millets delivered fresh to your doorstep. Farm-fresh, pure, and nutritious.">
    <meta name="keywords" content="nuts, dry fruits, cold pressed oils, spices online, millets delivery, farm fresh groceries">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <!-- meta property -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Valluvam – Nuts & More Delivered Fresh">
    <meta property="og:description" content="Discover nuts, dry fruits, cold-pressed oils, spices & millets from Valluvam. Fresh to your home, pure by nature.">
    <meta property="og:url" content="https://www.valluvamproducts.com/">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Valluvam – Nuts & More Delivered Fresh">
    <meta name="twitter:description" content="Discover nuts, dry fruits, cold-pressed oils, spices & millets from Valluvam. Fresh to your home, pure by nature.">
    <meta name="twitter:image" content="/images/logo.png">
    <link rel="icon" href="/img" type="image/png">
    <!-- canonical tag -->
    <link rel="canonical" href="https://www.valluvamproducts.com/">

    <!-- Valluvam Products Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Apple touch icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/valluvam/images/favicon/apple-touch-icon.png">

    <!-- Optional PNG icons -->
    <link rel="icon" type="image/png" sizes="96x96" href="/valluvam/images/favicon/favicon-96x96.png">

    <!-- Web manifest -->
    <link rel="manifest" href="/valluvam/images/favicon/site.webmanifest">

    <!-- Organization structured data: same real business details already
         marked up on contact.php (name, address, phone, social links),
         extended to the homepage so search engines pick it up from / too. -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Store",
        "name": "Valluvam",
        "url": "https://www.valluvamproducts.com/",
        "logo": "https://www.valluvamproducts.com/images/logo.png",
        "image": "https://valluvamproducts.com/images/logo.png",
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

</head>

<body class="goto-here">


    <!-- Swiper Section -->
    <section id="home-section" class="hero">
        <div class="home-slider owl-carousel">
            <div class="slider-item" style="background-image: url(images/hero-1.jpg);">
                <div class="overlay"></div>
                <div class="container">
                    <div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">

                        <div class="col-md-12 ftco-animate text-center">
                            <span class="subheading mb-2">Welcome to Valluvam</span>
                            <h1 class="mb-3">Premium Dry Fruits, Nuts, Spices,<br class="d-none d-md-block"> Cold-Pressed Oils &amp; Millets</h1>
                            <p class="mb-4">Naturally sourced, carefully packed and delivered fresh to your doorstep — for your home and your business.</p>
                            <p class="hero-cta">
                                <a href="shop.php" class="btn btn-primary">Shop Now</a>
                                <a href="#our-products" class="btn btn-primary btn-outline-primary">Explore Products</a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            <div class="slider-item" style="background-image: url(images/hero-2.jpg);">
                <div class="overlay"></div>
                <div class="container">
                    <div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">

                        <div class="col-md-12 ftco-animate text-center">
                            <span class="subheading mb-2">Valluvam</span>
                            <p class="mb-4" style="font-size: 22px; color: #fff;">Wholesome millets and cold-pressed oils, packed with natural goodness.</p>
                            <p class="hero-cta">
                                <a href="shop.php" class="btn btn-primary">Shop Now</a>
                                <a href="#our-products" class="btn btn-primary btn-outline-primary">Explore Products</a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>
            <div class="slider-item" style="background-image: url(images/hero-3.jpg);">
                <div class="overlay"></div>
                <div class="container">
                    <div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">

                        <div class="col-md-12 ftco-animate text-center">
                            <span class="subheading mb-2">Valluvam</span>
                            <p class="mb-4" style="font-size: 22px; color: #fff;">A perfect blend of crunchy nuts and sweet dry fruits, packed with nutrition and flavor.</p>
                            <p class="hero-cta">
                                <a href="shop.php" class="btn btn-primary">Shop Now</a>
                                <a href="#our-products" class="btn btn-primary btn-outline-primary">Explore Products</a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>
    <!-- <section id="home-section" class="hero">
  <div class="home-slider owl-carousel">


    <div class="slider-item" style="background-image: url('images/hero-1.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
        </div>
      </div>
    </div>


    <div class="slider-item" style="background-image: url('images/hero-2.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
        </div>
      </div>
    </div>


    <div class="slider-item" style="background-image: url('images/hero-3.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
        </div>
      </div>
    </div>

  </div>
</section> -->

    <!-- Shop by Category (categories are loaded dynamically into #slides by
         assets/js/index/index.js -> category_slider(); only the heading
         around it is new) -->
    <section class="ftco-section-category" id="shop-by-category">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Shop by</span>
                        <h2>Category</h2>
                        <p>From premium nuts to cold-pressed oils — explore everything Valluvam has to offer.</p>
                    </div>
                </div>
            </div>
            <div class="slid-er">
                <div class="slides" id="slides">
                    <!-- Products will be loaded here -->
                </div>
            </div>
    </section>

    <!-- Featured Products (data loaded dynamically into #product-container
         by assets/js/index/index.js -> product_catelog(); heading, anchor
         and the "View All Products" link are the only additions) -->
    <section class="ftco-section ftco-no-pb" id="our-products">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Fresh in stock</span>
                        <h2>Featured Products</h2>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="ftco-section">
        <div class="container">
            <div class="row" id="product-container" style="align-items:flex-start;">
                <!-- Products will be loaded here -->
            </div>
            <div class="products-view-all">
                <a href="shop.php" class="btn btn-primary btn-outline-primary">View All Products</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Valluvam - reuses the .section-services / .single-service
         component already styled in css/main.css for the Services section
         below, so this introduces no new card CSS. -->
    <section class="section-services" style="width: 99.94%;">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-md-10 col-lg-8">
                    <div class="header-section">
                        <h2 class="title">Why Choose <span style="color: #82ae46;">Valluvam</span></h2>
                        <p class="description">A few reasons customers and business partners keep coming back.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="ribbon-outline"></ion-icon></span>
                            <h3 class="title">Quality Products</h3>
                            <p class="description">Every batch is checked so what reaches you matches what we promise.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="leaf-outline"></ion-icon></span>
                            <h3 class="title">Carefully Sourced</h3>
                            <p class="description">Nuts, dry fruits, oils, spices and millets selected with care at the source.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="cube-outline"></ion-icon></span>
                            <h3 class="title">Fresh Packaging</h3>
                            <p class="description">Packed to protect freshness and flavor from us to your doorstep.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="shield-checkmark-outline"></ion-icon></span>
                            <h3 class="title">Trusted Suppliers</h3>
                            <p class="description">Long-standing supplier relationships behind every product we sell.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="lock-closed-outline"></ion-icon></span>
                            <h3 class="title">Secure Payments</h3>
                            <p class="description">Checkout safely with Razorpay-powered card, UPI and net banking options.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="rocket-outline"></ion-icon></span>
                            <h3 class="title">Reliable Delivery</h3>
                            <p class="description">Pan-India shipping with tracking, from dispatch to your door.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Valluvam Brand Story - previously written and ready in this file but
         commented out; re-enabled here with its "WHY CHOOSE" link pointed at
         the real about.php route (it was "#") and its image given a
         descriptive alt and a fixed aspect ratio. Copy is unchanged. -->
    <section class="ftco-section img" style="background-image: url('images/why.jfif'); background-size: cover; background-position: center; width: 99.94%">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row no-gutters d-flex align-items-stretch shadow rounded overflow-hidden">

                        <!-- Left Image (hidden on mobile) -->
                        <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-white">
                            <img src="images/why.jfif" alt="Valluvam nuts, dry fruits and cold-pressed oils being sourced and packed" class="img-fluid brand-story-img" loading="lazy">
                        </div>

                        <!-- Right Text Content -->
                        <div class="col-12 col-lg-6 text-white p-4 d-flex flex-column justify-content-center" style="background: rgba(0, 0, 0, 0.5);">
                            <div>
                                <h3><a href="about.php" class="text-white text-decoration-none" style="justify-content: center;">OUR STORY</a></h3>
                                <h2 class="mb-3" style="color: green;">Valluvam</h2>
                                <p><strong>Purity You Can Trust:</strong> Every product is carefully selected, processed, and packaged to maintain the highest standards of quality and freshness.</p>
                                <p><strong>Sustainable Practices:</strong> We work closely with local farmers and follow eco-friendly processes to support sustainability and ensure minimal impact on the environment.</p>
                                <p><strong>Convenience at Your Fingertips:</strong> With round-the-clock delivery, we bring premium products straight to your doorstep, making healthy living easier than ever.</p>
                                <p>At Valluvam, we blend the essence of tradition with modern convenience to create a brand you can rely on. Our goal is simple: to help you embrace a healthier lifestyle with pure and natural products delivered with care.</p>
                                <p><a href="about.php" class="btn btn-primary btn-outline-primary" style="border-color:#fff;color:#fff;">About Valluvam</a></p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Wholesale & Bulk Orders - short homepage teaser for b2b-wholesale.php;
         audience list and CTA reuse the same real copy already published on
         that page (no new claims). -->
    <section class="ftco-section" style="background:#f7f6f2;">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-md-9">
                    <div class="heading-section">
                        <span class="subheading">For businesses</span>
                        <h2>Wholesale &amp; Bulk Orders</h2>
                        <p>Valluvam supplies premium nuts, dry fruits, cold-pressed oils, spices and millets in bulk to businesses across India.</p>
                    </div>
                </div>
            </div>
            <ul class="b2b-teaser-audience">
                <li><ion-icon name="storefront-outline"></ion-icon> Retailers &amp; Supermarkets</li>
                <li><ion-icon name="restaurant-outline"></ion-icon> Hotels &amp; Restaurants</li>
                <li><ion-icon name="fast-food-outline"></ion-icon> Caterers &amp; Cloud Kitchens</li>
                <li><ion-icon name="cube-outline"></ion-icon> Distributors &amp; Stockists</li>
                <li><ion-icon name="business-outline"></ion-icon> Corporate / Institutions</li>
                <li><ion-icon name="cart-outline"></ion-icon> Resellers / Online Sellers</li>
            </ul>
            <div class="text-center">
                <a href="b2b-wholesale.php" class="btn btn-primary">Bulk Enquiry</a>
            </div>
        </div>
    </section>

    <?php include "service.php"; ?>
    <?php include "review.php"; ?>

    <!-- Homepage FAQ - same accordion pattern and .faq-card styling used on
         b2b-wholesale.php, styled locally above since that CSS lives in that
         page's own inline <style>. Answers only restate what's already
         published elsewhere on the site (pan-India shipping and returns
         copy from b2b-wholesale.php / return.php, Razorpay from the
         checkout), nothing new is claimed. -->
    <section class="ftco-section ftco-no-pt" id="faq">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Questions</span>
                        <h2>Frequently Asked Questions</h2>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div id="homeFaqAccordion">
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading1">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#homeFaqCollapse1" aria-expanded="true" aria-controls="homeFaqCollapse1">
                                    What products does Valluvam offer?
                                </button>
                            </div>
                            <div id="homeFaqCollapse1" class="collapse show" aria-labelledby="homeFaqHeading1" data-parent="#homeFaqAccordion">
                                <div class="card-body">Nuts, dry fruits, cold-pressed oils, spices, millets, rice and combo packs — browse the full range on our <a href="shop.php">Shop</a> page.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading2">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#homeFaqCollapse2" aria-expanded="false" aria-controls="homeFaqCollapse2">
                                    Do you provide wholesale or bulk orders?
                                </button>
                            </div>
                            <div id="homeFaqCollapse2" class="collapse" aria-labelledby="homeFaqHeading2" data-parent="#homeFaqAccordion">
                                <div class="card-body">Yes. We supply retailers, restaurants, hotels, distributors and other businesses — see <a href="b2b-wholesale.php">B2B / Wholesale</a> for details.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading3">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#homeFaqCollapse3" aria-expanded="false" aria-controls="homeFaqCollapse3">
                                    Do you deliver across India?
                                </button>
                            </div>
                            <div id="homeFaqCollapse3" class="collapse" aria-labelledby="homeFaqHeading3" data-parent="#homeFaqAccordion">
                                <div class="card-body">Yes, we ship pan-India through our logistics partners with doorstep delivery and tracking.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading4">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#homeFaqCollapse4" aria-expanded="false" aria-controls="homeFaqCollapse4">
                                    How can I place a bulk order?
                                </button>
                            </div>
                            <div id="homeFaqCollapse4" class="collapse" aria-labelledby="homeFaqHeading4" data-parent="#homeFaqAccordion">
                                <div class="card-body">Submit the enquiry form on the <a href="b2b-wholesale.php">B2B / Wholesale</a> page with your requirement and our team will get back to you.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading5">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#homeFaqCollapse5" aria-expanded="false" aria-controls="homeFaqCollapse5">
                                    What payment options are available?
                                </button>
                            </div>
                            <div id="homeFaqCollapse5" class="collapse" aria-labelledby="homeFaqHeading5" data-parent="#homeFaqAccordion">
                                <div class="card-body">Secure online payments via Razorpay, including cards, UPI, net banking and wallets.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading6">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#homeFaqCollapse6" aria-expanded="false" aria-controls="homeFaqCollapse6">
                                    How can I contact Valluvam?
                                </button>
                            </div>
                            <div id="homeFaqCollapse6" class="collapse" aria-labelledby="homeFaqHeading6" data-parent="#homeFaqAccordion">
                                <div class="card-body">Call or WhatsApp +91 89259 69888, or use our <a href="contact.php">Contact</a> page.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="homeFaqHeading7">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#homeFaqCollapse7" aria-expanded="false" aria-controls="homeFaqCollapse7">
                                    What is your return/refund process?
                                </button>
                            </div>
                            <div id="homeFaqCollapse7" class="collapse" aria-labelledby="homeFaqHeading7" data-parent="#homeFaqAccordion">
                                <div class="card-body">Returns are accepted if a product arrives damaged — share photos/video within 24 hours of delivery. See our full <a href="return.php">Returns and Exchange Policy</a>.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-4">
        <!-- <div class="row d-flex justify-content-center py-5">
                <div class="col-md-6">
                    <h2 style="font-size: 22px;" class="mb-0">Have you any questions?</h2>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <form action="#" class="subscribe-form">
                        <div class="form-group d-flex">
                            <input type="text" class="form-control" placeholder="Enter email address">
                            <input type="submit" value="Subscribe" class="submit px-3">
                        </div>
                    </form>
                </div>
             </div> -->
    </div>

    <?php include "footer.php"; ?>



    <!-- loader -->
    <div id="ftco-loader" class="show fullscreen"><svg class="circular" width="48px" height="48px">
            <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee" />
            <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#F96D00" />
        </svg></div>


    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-migrate-3.0.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.easing.1.3.js"></script>
    <script src="js/jquery.waypoints.min.js"></script>
    <script src="js/jquery.stellar.min.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/jquery.magnific-popup.min.js"></script>
    <script src="js/aos.js"></script>
    <script src="js/jquery.animateNumber.min.js"></script>
    <script src="js/bootstrap-datepicker.js"></script>
    <script src="js/scrollax.min.js"></script>
    <!-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBVWaKrjvy3MaE7SQ74_uJiULgl1JY0H2s&sensor=false"></script>
    <script src="js/google-map.js"></script> -->
    <script src="js/main.js"></script>
    <script src="assets/js/index/index.js"></script>



</body>

</html>
