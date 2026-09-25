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
        /* ===== Hero: split-screen redesign =====
           Replaces the old centered-text-over-dark-photo carousel. The
           hero-1/2/3.jpg photos carry their own baked-in headline text on
           the left ~48% of the frame (1672x941) and product photography on
           the right - which meant real, accessible, editable copy was
           impossible without touching the JPGs. This keeps the same three
           photos and the same Owl Carousel JS/init (js/main.js targets
           `.home-slider` unchanged), but only shows their right-hand
           (product) portion as a visual panel, and puts real HTML copy in
           a solid content panel beside it - so headline/CTAs are selectable,
           screen-reader-visible and editable without touching an image. */
        #home-section {
            /* Owl's fade transition changes layout at the very top of the
               page each autoplay tick; scroll anchoring "corrects" for that
               even far below the hero, which reads as random scroll jumps. */
            overflow-anchor: none;
        }

        .v-hero {
            display: flex;
            align-items: stretch;
            min-height: 560px;
            background: linear-gradient(155deg, #123626 0%, #1c5034 65%, #21603d 100%);
            overflow: hidden;
        }

        .v-hero-content {
            flex: 0 0 44%;
            max-width: 44%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px 56px 64px 5vw;
            color: #fff;
            position: relative;
            z-index: 2;
        }

        .v-hero-eyebrow {
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 2.4px;
            text-transform: uppercase;
            color: #bfe0a8;
            margin-bottom: 18px;
        }

        .v-hero-heading {
            font-size: clamp(30px, 3.4vw, 46px);
            line-height: 1.14;
            letter-spacing: -0.5px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 18px;
            max-width: 15ch;
        }

        .v-hero-sub {
            font-size: 16px;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.82);
            max-width: 42ch;
            margin-bottom: 30px;
        }

        .v-hero-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 34px;
        }

        .v-hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 26px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: .3px;
            transition: transform .18s var(--v-ease, ease), background-color .25s ease, color .25s ease, border-color .25s ease;
        }

        .v-hero-btn:hover {
            text-decoration: none;
            transform: translateY(-2px);
        }

        .v-hero-btn:active {
            transform: translateY(-2px) scale(.97);
        }

        .v-hero-btn-primary {
            background: #fff;
            color: #123626;
            border: 1px solid #fff;
        }

        .v-hero-btn-primary:hover {
            background: #f2f2f2;
            color: #123626;
        }

        .v-hero-btn-secondary {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.45);
        }

        .v-hero-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: #fff;
        }

        .v-hero-trust {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 22px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .v-hero-trust li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
        }

        .v-hero-trust ion-icon {
            font-size: 17px;
            color: #bfe0a8;
        }

        .v-hero-visual {
            flex: 1 1 56%;
            position: relative;
            min-height: 560px;
            /* A flex item's default min-width:auto lets it grow to Owl's
               stage width (slides x N). Owl then re-measures that inflated
               width on resize and each slide becomes thousands of px wide,
               pushing the photo off-screen - the "blank green hero". */
            min-width: 0;
            overflow: hidden;
        }

        .v-hero-visual::before {
            /* Blends the content panel's solid colour into the photo so the
               seam reads as intentional, not a hard cut. */
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(90deg, #123626 0%, rgba(18, 54, 38, 0.45) 14%, rgba(18, 54, 38, 0) 32%);
            pointer-events: none;
        }

        #home-section .v-hero-visual .home-slider,
        #home-section .v-hero-visual .owl-carousel,
        #home-section .v-hero-visual .owl-stage-outer,
        #home-section .v-hero-visual .owl-stage,
        #home-section .v-hero-visual .owl-item {
            height: 100%;
        }

        #home-section .v-hero-visual .slider-item {
            /* #home-section is an ID, which outranks css/style.css's 3-class
               `.owl-carousel.home-slider .slider-item { height: 650px }` -
               needed so this can actually override that fixed height. */
            height: 100%;
            /* Override `background-size: cover` (also from style.css): cover
               alone only crops a small sliver here since the box's aspect
               ratio is close to the photo's own 1672x941. Sizing the image
               to 190% of the box width (height auto, so it keeps the
               photo's own proportions) and anchoring the crop window to the
               right edge shows only the rightmost ~53% of the frame -
               enough to hide the baked-in headline text that sits in the
               left half of all three photos. The new HTML copy in
               .v-hero-content replaces it. */
            background-size: 190% auto;
            /* !important: css/style.css sets `background-position: center
               center !important` on this same element below 1199.98px, which
               would otherwise silently win on every tablet/mobile width and
               re-expose the baked-in text this crop exists to hide. */
            background-position: 100% 38% !important;
            background-color: #123626;
        }

        /* hero-2.jpg (the "Aroma of Celebration" / Basmati slide) is laid
           out the other way round from the other two photos - its baked-in
           text sits on the RIGHT and the product photography on the LEFT -
           so it needs the crop window mirrored rather than anchored right. */
        #home-section .v-hero-visual .slider-item.v-crop-left {
            background-position: 0% 40% !important;
        }

        #home-section .v-hero-visual .overlay {
            /* Higher specificity than home-redesign.css's #home-section
               .overlay (a dark 0.35-0.55 gradient meant for text-over-photo
               contrast) - not needed now that copy lives in the solid
               .v-hero-content panel, so the product photo can show through
               clearly. */
            background: #000;
            opacity: .08;
        }

        @media (max-width: 1199.98px) {
            .v-hero-content {
                padding: 52px 40px;
            }
        }

        @media (max-width: 991.98px) {
            .v-hero {
                flex-direction: column;
                min-height: 0;
            }

            .v-hero-content,
            .v-hero-visual {
                flex: 1 1 auto;
                max-width: 100%;
            }

            .v-hero-content {
                padding: 44px 24px 36px;
                text-align: left;
            }

            .v-hero-heading {
                max-width: none;
            }

            .v-hero-visual {
                height: 260px;
                min-height: 260px;
            }

            #home-section .v-hero-visual .home-slider,
            #home-section .v-hero-visual .owl-carousel,
            #home-section .v-hero-visual .owl-stage-outer,
            #home-section .v-hero-visual .owl-stage,
            #home-section .v-hero-visual .owl-item,
            #home-section .v-hero-visual .slider-item {
                height: 260px;
                min-height: 260px;
            }

            .v-hero-visual::before {
                background: linear-gradient(180deg, #123626 0%, rgba(18, 54, 38, 0.3) 8%, rgba(18, 54, 38, 0) 20%);
            }
        }

        @media (max-width: 575.98px) {
            .v-hero-content {
                padding: 36px 18px 30px;
            }

            .v-hero-sub {
                max-width: none;
            }
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
            background: #faf6ee;
            border: 1px solid #e3e9d8;
            border-radius: 30px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 500;
            color: #252525;
        }

        .b2b-teaser-audience li ion-icon {
            color: #1c5034;
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
            background-color: #faf6ee;
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
            color: #1c5034;
            font-weight: 500;
        }
    </style>
    <!-- ===== Homepage merchandising additions (index.php only) =====
         New, additive sections only: Shop by Goal tiles, the Top Rated
         carousel (real product_details.rating data), two ingredient
         benefit info-cards, and an "Also Available On" marketplace strip
         (Meesho, Zepto, Amazon - the 3 platforms confirmed as real).
         Nothing here changes any selector used elsewhere on this page or
         on any other page. -->
    <style>
        /* Shop by Goal */
        .v-shop-goal .v-goal-tile {
            border-radius: 14px;
            padding: 28px 24px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            min-height: 170px;
        }

        .v-shop-goal .v-goal-tile h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #1c1c1c;
        }

        .v-shop-goal .v-goal-tile p {
            font-size: 14px;
            color: #4a4a4a;
            margin-bottom: 0;
        }

        .v-shop-goal .v-goal-tile a.v-goal-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            color: #1c5034;
            text-decoration: none;
        }

        .v-shop-goal .v-goal-tile a.v-goal-link ion-icon {
            font-size: 18px;
            transition: transform .2s ease;
        }

        .v-shop-goal .v-goal-tile:hover a.v-goal-link ion-icon {
            transform: translateX(4px);
        }

        .v-goal-tile.v-goal-1 {
            background: linear-gradient(135deg, #eaf5e6, #f7fbf3);
        }

        .v-goal-tile.v-goal-2 {
            background: linear-gradient(135deg, #fdeef0, #fff7f5);
        }

        .v-goal-tile.v-goal-3 {
            background: linear-gradient(135deg, #fdf3e3, #fffaf0);
        }

        .v-goal-tile.v-goal-4 {
            background: linear-gradient(135deg, #e6f3f7, #f4fbfc);
        }

        /* Top Rated carousel star row */
        .v-top-rated-stars {
            font-size: 14px;
            color: #f5a623;
            margin-bottom: 6px;
        }

        .v-top-rated-stars ion-icon {
            font-size: 14px;
        }

        .v-top-rated-stars span {
            margin-left: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }

        #top-rated-container {
            display: flex;
            flex-wrap: wrap;
        }

        /* Ingredient benefit info-cards */
        .v-benefit-card {
            background: #faf6ee;
            border: 1px solid #e3e9d8;
            border-radius: 12px;
            padding: 26px 24px;
            height: 100%;
        }

        .v-benefit-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1c5034;
            margin-bottom: 14px;
        }

        .v-benefit-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .v-benefit-card ul li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 14px;
            color: #4a4a4a;
            margin-bottom: 10px;
        }

        .v-benefit-card ul li ion-icon {
            color: #1c5034;
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* "Also Available On" marketplace strip - text/icon badges only,
           deliberately not brand logo images, and lists only the 3
           marketplaces confirmed real (Meesho, Zepto, Amazon). */
        .v-marketplace-strip {
            background: #1c5034;
            padding: 34px 0;
        }

        .v-marketplace-strip p.v-marketplace-heading {
            text-align: center;
            color: #d8e8d2;
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .v-marketplace-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
        }

        .v-marketplace-badges a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            color: #1c1c1c;
            padding: 10px 22px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
        }

        .v-marketplace-badges a ion-icon {
            font-size: 18px;
            color: #1c5034;
        }

        @media (max-width: 575.98px) {
            .v-shop-goal .v-goal-tile {
                min-height: auto;
            }
        }
    </style>
    <!-- title tag -->
    <title>Buy Premium Dry Fruits, Nuts, Spices & Cold Pressed Oils Online | Valluvam</title>
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
    <!-- canonical tag -->
    <link rel="canonical" href="https://www.valluvamproducts.com/">

    <!-- Phase 1 homepage redesign: presentation only, additive stylesheet.
         Linked here only, so it affects this page alone — header.php and
         footer.php render unchanged on every other page. -->
    <link rel="stylesheet" href="css/home-redesign.css?v=20260924c">

    <!-- Valluvam Products Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Apple touch icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">

    <!-- Optional PNG icons -->
    <link rel="icon" type="image/png" sizes="96x96" href="images/favicon/favicon-96x96.png">

    <!-- Web manifest -->
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <!-- Organization + WebSite structured data: same real business details
         already marked up on contact.php (name, address, phone, social
         links), extended to the homepage so search engines pick it up
         from / too. -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
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
                    "text": "Valluvam offers a wide range of premium nuts, dry fruits, cold-pressed oils, spices, millets and rice, sourced for purity and freshness."
                }
            },
            {
                "@type": "Question",
                "name": "Do you provide wholesale or bulk orders?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, Valluvam supports wholesale and bulk ordering for businesses, retailers and institutions. Visit our Wholesale page to submit an enquiry."
                }
            },
            {
                "@type": "Question",
                "name": "Do you deliver across India?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, Valluvam delivers products across India, bringing farm-fresh nuts, dry fruits, oils, spices and millets directly to your doorstep."
                }
            },
            {
                "@type": "Question",
                "name": "How can I place a bulk order?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "You can place a bulk order by browsing our Shop page and adding the required quantities to your cart, or by contacting us directly for wholesale enquiries."
                }
            },
            {
                "@type": "Question",
                "name": "What payment options are available?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Valluvam supports secure online payment options at checkout for a smooth and convenient shopping experience."
                }
            },
            {
                "@type": "Question",
                "name": "How can I contact Valluvam?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "You can reach Valluvam through our Contact page, by phone, or by email. Our team typically responds within 24 hours."
                }
            },
            {
                "@type": "Question",
                "name": "What is your return/refund process?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Returns are accepted for products that are damaged at the time of delivery. Please refer to our Returns and Exchange Policy page for full details on eligibility and how to request a return."
                }
            }
        ]
    }
    </script>

</head>

<body class="goto-here">


    <!-- Swiper Section -->
    <section id="home-section" class="hero v-hero">
        <div class="v-hero-content">
            <span class="v-hero-eyebrow">As Pure As Nature</span>
            <h1 class="v-hero-heading">Rice, spices &amp; oils, checked before they ever reach your kitchen.</h1>
            <p class="v-hero-sub">From everyday rice and millets to dry fruits, nuts, cold-pressed oils and spices — every batch is quality-checked, then shipped pan-India with tracking.</p>
            <div class="v-hero-cta">
                <a href="shop.php" class="v-hero-btn v-hero-btn-primary">Shop Now <ion-icon name="arrow-forward-outline"></ion-icon></a>
                <a href="#shop-by-category" class="v-hero-btn v-hero-btn-secondary">Browse Categories</a>
            </div>
            <ul class="v-hero-trust">
                <li><ion-icon name="checkmark-circle-outline"></ion-icon> Quality checked</li>
                <li><ion-icon name="shield-checkmark-outline"></ion-icon> Secure payments</li>
                <li><ion-icon name="navigate-outline"></ion-icon> Tracked delivery</li>
            </ul>
        </div>
        <div class="v-hero-visual">
            <div class="home-slider owl-carousel">
                <div class="slider-item" style="background-image: url(images/hero-1.jpg);">
                    <div class="overlay"></div>
                </div>
                <div class="slider-item v-crop-left" style="background-image: url(images/hero-2.jpg);">
                    <div class="overlay"></div>
                </div>
                <div class="slider-item" style="background-image: url(images/hero-3.jpg);">
                    <div class="overlay"></div>
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

    <!-- Trust strip: compact visual summary of existing, already-published
         claims (see Why Choose Valluvam / footer / checkout for the same
         copy) — nothing new is asserted here. -->
    <section class="v-trust-strip">
        <div class="container">
            <ul>
                <li><span class="v-trust-icon"><ion-icon name="ribbon-outline"></ion-icon></span> Quality Products</li>
                <li><span class="v-trust-icon"><ion-icon name="cube-outline"></ion-icon></span> Fresh Packaging</li>
                <li><span class="v-trust-icon"><ion-icon name="lock-closed-outline"></ion-icon></span> Secure Checkout</li>
                <li><span class="v-trust-icon"><ion-icon name="rocket-outline"></ion-icon></span> Pan-India Delivery</li>
            </ul>
        </div>
    </section>

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

    <!-- Shop by Goal - new section. Each tile links to an existing category
         page (dryfruits.php/nuts.php, oils.php/spices.php, rice.php/millets.php,
         combo.php) - no new pages, no schema change, purely a curated set of
         shortcuts to pages that already exist. -->
    <section class="ftco-section v-shop-goal">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Find what you need</span>
                        <h2>Shop by Goal</h2>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-6 col-lg-3 mb-4">
                    <div class="v-goal-tile v-goal-1">
                        <div>
                            <h3>Everyday Wellness</h3>
                            <p>Nuts &amp; dry fruits for daily nourishment.</p>
                        </div>
                        <a href="nuts.php" class="v-goal-link">Explore <ion-icon name="arrow-forward-outline"></ion-icon></a>
                    </div>
                </div>
                <div class="col-6 col-lg-3 mb-4">
                    <div class="v-goal-tile v-goal-2">
                        <div>
                            <h3>Kitchen Essentials</h3>
                            <p>Cold-pressed oils &amp; spices for everyday cooking.</p>
                        </div>
                        <a href="oils.php" class="v-goal-link">Explore <ion-icon name="arrow-forward-outline"></ion-icon></a>
                    </div>
                </div>
                <div class="col-6 col-lg-3 mb-4">
                    <div class="v-goal-tile v-goal-3">
                        <div>
                            <h3>Traditional Grains</h3>
                            <p>Rice &amp; millets sourced the traditional way.</p>
                        </div>
                        <a href="rice.php" class="v-goal-link">Explore <ion-icon name="arrow-forward-outline"></ion-icon></a>
                    </div>
                </div>
                <div class="col-6 col-lg-3 mb-4">
                    <div class="v-goal-tile v-goal-4">
                        <div>
                            <h3>Gifting &amp; Combos</h3>
                            <p>Curated combo packs, ready to gift or stock up.</p>
                        </div>
                        <a href="combo.php" class="v-goal-link">Explore <ion-icon name="arrow-forward-outline"></ion-icon></a>
                    </div>
                </div>
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

    <!-- Top Rated - new section, hidden by loadTopRated() in index.js if no
         product currently has a rating set. Uses the same real
         product_details.rating column already shown on the product detail
         page - not a fabricated "bestseller" list. -->
    <section class="ftco-section ftco-no-pb" id="top-rated-section" style="display:none;">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Loved by customers</span>
                        <h2>Top Rated</h2>
                    </div>
                </div>
            </div>
            <div class="row" id="top-rated-container" style="align-items:flex-start;">
                <!-- Products will be loaded here -->
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
                        <h2 class="title">Why Choose <span style="color: #1c5034;">Valluvam</span></h2>
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

    <!-- Did You Know? - two general, uncontroversial educational cards (not
         medical claims tied to a specific product). New section, static
         content only, no database or schema change. -->
    <section class="ftco-section" style="background:#fff;">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Good to know</span>
                        <h2>Did You Know?</h2>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="v-benefit-card">
                        <h3>Why Cold-Pressed Oils</h3>
                        <ul>
                            <li><ion-icon name="checkmark-circle-outline"></ion-icon> Extracted without heat, which helps retain more of the oil's natural nutrients.</li>
                            <li><ion-icon name="checkmark-circle-outline"></ion-icon> No chemical solvents used in the extraction process.</li>
                            <li><ion-icon name="checkmark-circle-outline"></ion-icon> Closer to its natural, unrefined form than commercially refined oils.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="v-benefit-card">
                        <h3>Why Millets</h3>
                        <ul>
                            <li><ion-icon name="checkmark-circle-outline"></ion-icon> A traditional Indian grain grown for generations before rice became widespread.</li>
                            <li><ion-icon name="checkmark-circle-outline"></ion-icon> Naturally gluten-free.</li>
                            <li><ion-icon name="checkmark-circle-outline"></ion-icon> A good source of dietary fibre as part of a balanced diet.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How Valluvam Works — new Phase 2 section. Each step names an existing,
         real part of the site (shop.php browsing, the product detail page,
         the existing cart/checkout flow, and pan-India delivery already
         stated elsewhere) — no new functionality or claim is introduced. -->
    <section class="v-how-it-works">
        <div class="container">
            <div class="row justify-content-center text-center mb-3">
                <div class="col-md-8">
                    <div class="heading-section">
                        <span class="subheading">Getting started</span>
                        <h2>How Valluvam Works</h2>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-6 col-lg-3">
                    <div class="v-step">
                        <span class="v-step-num">1</span>
                        <span class="icon"><ion-icon name="search-outline"></ion-icon></span>
                        <h3 class="title">Explore</h3>
                        <p class="description">Browse nuts, dry fruits, oils, spices, millets, rice and combos in our <a href="shop.php">Shop</a>.</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="v-step">
                        <span class="v-step-num">2</span>
                        <span class="icon"><ion-icon name="checkmark-circle-outline"></ion-icon></span>
                        <h3 class="title">Choose</h3>
                        <p class="description">Pick the pack size you need and add it to your cart or wishlist.</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="v-step">
                        <span class="v-step-num">3</span>
                        <span class="icon"><ion-icon name="card-outline"></ion-icon></span>
                        <h3 class="title">Order</h3>
                        <p class="description">Check out securely with Razorpay — cards, UPI, net banking and wallets.</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="v-step">
                        <span class="v-step-num">4</span>
                        <span class="icon"><ion-icon name="cube-outline"></ion-icon></span>
                        <h3 class="title">Receive</h3>
                        <p class="description">Your order is packed and shipped pan-India, with tracking to your door.</p>
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
                <a href="b2b-wholesale.php" class="btn btn-primary">Explore Wholesale</a>
            </div>
        </div>
    </section>

    <!-- Valluvam Brand Story / Quality & Sourcing - moved here (was above
         Wholesale & Bulk) so the journey reads discovery → trust → wholesale
         → sourcing story → reviews, per the Phase 2 flow. Copy unchanged. -->
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
                                <span class="subheading d-block mb-2"><a href="about.php" class="text-white text-decoration-none" style="justify-content: center;">OUR STORY</a></span>
                                <h2 class="mb-3" style="color: #1c5034;">Valluvam</h2>
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

    <!-- Also Available On - lists only the marketplaces confirmed real
         (Meesho, Zepto, Amazon). Text/icon badges, not brand logo images,
         to avoid any trademark or "is this really their logo" concern.
         Update this list if the set of marketplaces changes. -->
    <section class="v-marketplace-strip">
        <div class="container">
            <p class="v-marketplace-heading">Also Available On</p>
            <div class="v-marketplace-badges">
                <a href="https://www.amazon.in" target="_blank" rel="noopener"><ion-icon name="bag-handle-outline"></ion-icon> Amazon</a>
                <a href="https://www.meesho.com" target="_blank" rel="noopener"><ion-icon name="bag-handle-outline"></ion-icon> Meesho</a>
                <a href="https://www.zeptonow.com" target="_blank" rel="noopener"><ion-icon name="bag-handle-outline"></ion-icon> Zepto</a>
            </div>
        </div>
    </section>

    <section class="ftco-section ftco-no-pt ftco-no-pb py-5 bg-light">
        <div class="container py-4">
            <div class="row d-flex justify-content-center py-5">
                <div class="col-md-6">
                    <h2 style="font-size: 22px;" class="mb-0">Subscribe to our Newsletter</h2>
                    <span>Get email updates about our latest products and special offers</span>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <form action="#" class="subscribe-form">
                        <div class="form-group d-flex">
                            <input type="text" class="form-control" placeholder="Enter email address">
                            <input type="submit" value="Subscribe" class="submit px-3">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

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
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/jquery.magnific-popup.min.js"></script>
    <script src="js/aos.js"></script>
    <script src="js/jquery.animateNumber.min.js"></script>
    <script src="js/bootstrap-datepicker.js"></script>
    <script src="js/scrollax.min.js"></script>
    <!-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBVWaKrjvy3MaE7SQ74_uJiULgl1JY0H2s&sensor=false"></script>
    <script src="js/google-map.js"></script> -->
    <script src="js/main.js"></script>
    <script src="assets/js/index/index.js?v=<?php echo @filemtime(__DIR__ . "/assets/js/index/index.js"); ?>"></script>



</body>

</html>
