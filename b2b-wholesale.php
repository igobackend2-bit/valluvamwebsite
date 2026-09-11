<?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php" ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B / Wholesale Supply – Valluvam Organic Nuts, Dry Fruits, Oils, Spices & Millets</title>
    <meta name="description" content="Partner with Valluvam for wholesale & institutional supply of premium organic nuts, dry fruits, cold-pressed oils, spices and millets. Bulk pricing, private label & fast dispatch.">
    <meta name="keywords" content="valluvam wholesale, b2b nuts supplier, bulk dry fruits supplier, cold pressed oil wholesale, spices bulk supply, millets wholesale, private label nuts">
    <link rel="canonical" href="https://valluvamproducts.com/b2b-wholesale.php">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="B2B / Wholesale Supply – Valluvam">
    <meta property="og:description" content="Wholesale & institutional supply of premium organic nuts, dry fruits, cold-pressed oils, spices and millets from Valluvam.">
    <meta property="og:url" content="https://valluvamproducts.com/b2b-wholesale.php">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="B2B / Wholesale Supply – Valluvam">
    <meta name="twitter:description" content="Wholesale & institutional supply of premium organic nuts, dry fruits, cold-pressed oils, spices and millets from Valluvam.">
    <meta name="twitter:image" content="/images/logo.png">
    <!-- Valluvam Products Favicon -->
    <link rel="icon" type="image/png" href="images/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="images/favicon/favicon.svg" />
    <link rel="shortcut icon" href="images/favicon/favicon.ico" type="image/ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png" />
    <link rel="manifest" href="images/favicon/site.webmanifest" />

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Store",
            "name": "Valluvam",
            "description": "Wholesale & institutional supply of organic nuts, dry fruits, cold-pressed oils, spices & millets.",
            "url": "https://valluvamproducts.com/b2b-wholesale.php",
            "logo": "https://valluvamproducts.com/assets/images/logo.png",
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
                "contactType": "Sales / Wholesale"
            },
            "sameAs": [
                "https://www.facebook.com/valluvamproducts/",
                "https://www.instagram.com/valluvam_agro_products/"
            ]
        }
    </script>

    <style>
        .b2b-section {
            padding: 70px 0;
        }

        .b2b-section h2.head {
            margin-bottom: 8px;
        }

        .b2b-section .head-sub {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 40px;
            color: #6b6b6b;
        }

        .wholesale-hero-title {
            color: #fff;
            font-size: 34px;
            font-weight: 700;
            margin-top: 18px;
            margin-bottom: 16px;
            line-height: 1.3;
            text-shadow: 0 2px 10px rgba(0, 0, 0, .35);
        }

        .wholesale-hero-sub {
            max-width: 760px;
            margin: 0 auto 26px;
            color: #eef1e6;
            font-size: 17px;
        }

        .btn-b2b {
            display: inline-block;
            padding: 13px 28px;
            margin: 6px;
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: .5px;
            transition: .3s;
        }

        .btn-b2b-primary {
            background-color: #82ae46;
            color: #fff !important;
        }

        .btn-b2b-primary:hover {
            background-color: #6c9438;
            color: #fff !important;
        }

        .btn-b2b-outline {
            border: 2px solid #82ae46;
            color: #82ae46 !important;
            background: transparent;
        }

        .btn-b2b-outline:hover {
            background-color: #82ae46;
            color: #fff !important;
        }

        .supply-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 28px 22px;
            height: 100%;
            text-align: center;
            transition: .3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }

        .supply-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, .08);
            transform: translateY(-4px);
        }

        .supply-card ion-icon {
            font-size: 40px;
            color: #82ae46;
            margin-bottom: 14px;
        }

        .supply-card h4 {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #252525;
        }

        .supply-card p {
            font-size: 14px;
            color: #6b6b6b;
        }

        .oil-card {
            background-color: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .06);
            height: 100%;
            transition: .3s;
        }

        .oil-card:hover {
            box-shadow: 0 12px 28px rgba(0, 0, 0, .12);
            transform: translateY(-6px);
        }

        .oil-card .oil-img-wrap {
            background-color: #f6f8f1;
            padding: 20px;
            text-align: center;
        }

        .oil-card .oil-img-wrap img {
            max-height: 260px;
            width: auto;
            margin: 0 auto;
            transition: .4s;
        }

        .oil-card:hover .oil-img-wrap img {
            transform: scale(1.06);
        }

        .oil-card .oil-card-body {
            padding: 20px 22px 26px;
            text-align: center;
        }

        .oil-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: #252525;
            margin-bottom: 6px;
        }

        .oil-card p {
            font-size: 14px;
            color: #6b6b6b;
            margin-bottom: 0;
        }

        .private-label-img {
            width: 100%;
            border-radius: 10px;
        }

        .b2b-table th {
            background-color: #82ae46;
            color: #fff;
            vertical-align: middle;
        }

        .b2b-table td,
        .b2b-table th {
            vertical-align: middle;
            padding: 14px 16px;
        }

        .private-label-box {
            background-color: #252525;
            color: #fff;
            border-radius: 10px;
            padding: 45px 40px;
        }

        .private-label-box h2 {
            color: #fff;
        }

        .private-label-box ul li {
            margin-bottom: 12px;
            padding-left: 4px;
        }

        .private-label-box ion-icon {
            color: #82ae46;
            margin-right: 10px;
            vertical-align: middle;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #82ae46;
            color: #fff;
            font-weight: 700;
        }

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

        .bg-cta {
            background-color: #82ae46;
            padding: 60px 0;
            text-align: center;
        }

        .bg-cta h2 {
            color: #fff;
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .bg-cta p {
            color: #eef4e4;
            max-width: 680px;
            margin: 0 auto 26px;
        }

        .bg-cta .btn-b2b-primary {
            background-color: #252525;
        }

        .bg-cta .btn-b2b-outline {
            border-color: #fff;
            color: #fff !important;
        }

        .bg-cta .btn-b2b-outline:hover {
            background-color: #fff;
            color: #82ae46 !important;
        }

        .enquiry-box {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .07);
            padding: 40px;
            max-width: 820px;
            margin: 0 auto;
        }

        .enquiry-box .form-group {
            margin-bottom: 20px;
        }

        .enquiry-box label.field-label {
            display: block;
            font-weight: 600;
            color: #252525;
            margin-bottom: 8px;
            font-size: 14.5px;
        }

        .enquiry-box input[type="text"],
        .enquiry-box input[type="tel"],
        .enquiry-box input[type="email"],
        .enquiry-box textarea {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px 14px;
            font-size: 14.5px;
        }

        .enquiry-box input:focus,
        .enquiry-box textarea:focus {
            outline: none;
            border-color: #82ae46;
        }

        .enquiry-box textarea {
            min-height: 100px;
            resize: vertical;
        }

        .enquiry-box .choice-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 26px;
        }

        .enquiry-box .choice-row label {
            font-weight: 400;
            color: #444;
            font-size: 14.5px;
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 0;
        }

        .enquiry-box .btn-submit-enquiry {
            width: 100%;
            background-color: #252525;
            color: #fff;
            border: none;
            border-radius: 30px;
            padding: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-size: 14px;
            transition: .3s;
        }

        .enquiry-box .btn-submit-enquiry:hover {
            background-color: #82ae46;
        }

        .enquiry-box .consent-text {
            font-size: 12.5px;
            color: #888;
            margin-top: 14px;
            text-align: center;
        }
    </style>
</head>

<body class="goto-here">

    <div class="hero-wrap hero-bread" style="background-image: linear-gradient(rgba(20,24,12,.72), rgba(20,24,12,.8)), url('images/bg-main.jpg');">
        <div class="container">
            <div class="row no-gutters slider-text align-items-center justify-content-center">
                <div class="col-md-10 col-lg-9 ftco-animate text-center">
                    <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>B2B / Wholesale</span></p>
                    <h1 class="wholesale-hero-title">Wholesale &amp; Institutional Supply of Premium Nuts, Dry Fruits, Oils, Spices &amp; Millets</h1>
                    <p class="wholesale-hero-sub">Stock a product customers trust – clean, consistent, and crafted for taste, health, and repeat purchase.</p>
                    <a href="https://api.whatsapp.com/send?phone=918925969888&text=Hi%2C%20I%20would%20like%20to%20request%20the%20Valluvam%20wholesale%20price%20list." target="_blank" class="btn-b2b btn-b2b-primary">Request Price List</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Who We Supply -->
    <section class="b2b-section">
        <div class="container">
            <h2 class="head">Who We Supply</h2>
            <p class="head-sub">Built for every business that needs quality, farm-fresh products:</p>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="supply-card">
                        <ion-icon name="storefront-outline"></ion-icon>
                        <h4>Retailers &amp; Supermarkets</h4>
                        <p>High repeat purchase products</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="supply-card">
                        <ion-icon name="restaurant-outline"></ion-icon>
                        <h4>Hotels &amp; Restaurants</h4>
                        <p>Stable taste, reliable performance in cooking</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="supply-card">
                        <ion-icon name="fast-food-outline"></ion-icon>
                        <h4>Caterers &amp; Cloud Kitchens</h4>
                        <p>Bulk supply, predictable quality</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="supply-card">
                        <ion-icon name="cube-outline"></ion-icon>
                        <h4>Distributors &amp; Stockists</h4>
                        <p>Margin-friendly pricing and fast movement</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="supply-card">
                        <ion-icon name="business-outline"></ion-icon>
                        <h4>Corporate / Institutions</h4>
                        <p>Large volume supply with documentation</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="supply-card">
                        <ion-icon name="cart-outline"></ion-icon>
                        <h4>Resellers / Online Sellers</h4>
                        <p>Branded packs + dropship support (optional)</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Partner -->
    <section class="section-services" style="width: 99.94%;">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-md-10 col-lg-8">
                    <div class="header-section">
                        <h2 class="title">Why Partner With <span style="color: #82ae46;">Valluvam</span></h2>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="checkmark-circle-outline"></ion-icon></span>
                            <h3 class="title">Consistent Batch Quality</h3>
                            <p class="description">Standardized sourcing and processing for taste, purity and quality consistency in every batch we dispatch.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="leaf-outline"></ion-icon></span>
                            <h3 class="title">100% Natural &amp; Clean</h3>
                            <p class="description">No unnecessary additives, no shortcuts – just honest, natural nuts, dry fruits, oils, spices and millets.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="flask-outline"></ion-icon></span>
                            <h3 class="title">Lab Testing (On Request)</h3>
                            <p class="description">Quality checks available for every production cycle, so you can supply with confidence.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="cube-outline"></ion-icon></span>
                            <h3 class="title">Packaging Options</h3>
                            <p class="description">Pouches, bottles, jars, tins and canisters – pick the format that fits your customer base.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="rocket-outline"></ion-icon></span>
                            <h3 class="title">Fast Dispatch</h3>
                            <p class="description">Typical dispatch in 24-72 hrs based on stock availability.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="single-service">
                        <div class="content">
                            <span class="icon"><ion-icon name="headset-outline"></ion-icon></span>
                            <h3 class="title">Reliable Support</h3>
                            <p class="description">A dedicated B2B contact for ordering, tracking and after-sales support.</p>
                        </div>
                        <span class="circle-before"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Oils Showcase -->
    <section class="b2b-section">
        <div class="container">
            <h2 class="head">Our <span style="color:#82ae46;">Cold-Pressed Oils</span></h2>
            <p class="head-sub">Vaagu wood cold-pressed, unrefined and packed fresh – the range that drives repeat purchase for our wholesale partners.</p>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="oil-card">
                        <div class="oil-img-wrap">
                            <img src="images/ground-1L.jpg" alt="Valluvam Cold-Pressed Groundnut Oil" class="img-fluid">
                        </div>
                        <div class="oil-card-body">
                            <h4>Groundnut Oil</h4>
                            <p>Rich aroma, ideal for daily cooking; strong repeat purchase.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="oil-card">
                        <div class="oil-img-wrap">
                            <img src="images/sesame-1L.jpg" alt="Valluvam Cold-Pressed Sesame Oil" class="img-fluid">
                        </div>
                        <div class="oil-card-body">
                            <h4>Sesame (Gingelly) Oil</h4>
                            <p>Traditional taste, premium category for health-conscious buyers.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="oil-card">
                        <div class="oil-img-wrap">
                            <img src="images/coconut-1L.jpg" alt="Valluvam Cold-Pressed Coconut Oil" class="img-fluid">
                        </div>
                        <div class="oil-card-body">
                            <h4>Coconut Oil</h4>
                            <p>Multipurpose – cooking + wellness, popular across segments.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Range -->
    <section class="b2b-section bg-light">
        <div class="container">
            <h2 class="head">Product Range (B2B)</h2>
            <p class="head-sub">Choose your SKU mix based on your customer demand – we will help you plan the right inventory.</p>
            <div class="table-responsive">
                <table class="table table-bordered b2b-table bg-white">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Positioning / Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Organic Nuts <br><small class="text-muted">(Almonds, Cashews, Pistachios, Walnuts)</small></td>
                            <td>Premium quality, strong repeat purchase across retail &amp; gifting segments.</td>
                        </tr>
                        <tr>
                            <td>Dry Fruits <br><small class="text-muted">(Raisins, Dates, Figs, Apricots)</small></td>
                            <td>Naturally sourced, popular across health-conscious buyers.</td>
                        </tr>
                        <tr>
                            <td>Cold-Pressed Oils <br><small class="text-muted">(Groundnut, Coconut, Sesame)</small></td>
                            <td>Traditional wood-pressed taste, ideal for daily cooking; strong repeat purchase.</td>
                        </tr>
                        <tr>
                            <td>Spices</td>
                            <td>Farm-sourced, authentic flavor, consistent aroma &amp; colour.</td>
                        </tr>
                        <tr>
                            <td>Millets <br><small class="text-muted">(Ragi, Bajra, Jowar &amp; more)</small></td>
                            <td>Fast-growing health food category, ideal for health-focused retailers.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Pack Sizes -->
    <section class="b2b-section">
        <div class="container">
            <h2 class="head">Pack Sizes &amp; Formats</h2>
            <div class="table-responsive">
                <table class="table table-bordered b2b-table">
                    <thead>
                        <tr>
                            <th>Pack Type</th>
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Retail Packs</td>
                            <td>100 g | 250 g | 500 g | 1 kg pouches / jars</td>
                        </tr>
                        <tr>
                            <td>Bulk / Institutional Packs</td>
                            <td>5 kg | 10 kg | 15 kg | 25 kg bags / tins</td>
                        </tr>
                        <tr>
                            <td>Custom Packing</td>
                            <td>Available for bulk orders</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Private Label -->
    <section class="b2b-section bg-light">
        <div class="container">
            <div class="private-label-box">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <h2>Private Label / White Label</h2>
                        <p class="mt-3 mb-3">Want your own brand on our products? We offer private label manufacturing for businesses that want to sell nuts, dry fruits, oils, spices &amp; millets under their own brand name.</p>
                        <ul class="list-unstyled">
                            <li><ion-icon name="checkmark-circle"></ion-icon> Custom label with your brand name</li>
                            <li><ion-icon name="checkmark-circle"></ion-icon> Packaging selection guidance</li>
                            <li><ion-icon name="checkmark-circle"></ion-icon> Barcode support (if you provide)</li>
                            <li><ion-icon name="checkmark-circle"></ion-icon> Batch &amp; manufacturing details on label</li>
                            <li><ion-icon name="checkmark-circle"></ion-icon> Shipping cartons branding (optional)</li>
                        </ul>
                        <a href="https://api.whatsapp.com/send?phone=918925969888&text=Hi%2C%20I%20am%20interested%20in%20Valluvam%27s%20Private%20Label%20program." target="_blank" class="btn-b2b btn-b2b-primary mt-2">Start Private Label Inquiry</a>
                    </div>
                    <div class="col-lg-5 d-none d-lg-block text-center">
                        <img src="images/private-label-oils.jpeg" alt="Valluvam Private Label Oils" class="private-label-img">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How Ordering Works -->
    <section class="b2b-section">
        <div class="container">
            <h2 class="head">How Ordering Works</h2>
            <div class="table-responsive">
                <table class="table table-bordered b2b-table">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Step</th>
                            <th>What Happens</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="step-number">1</span></td>
                            <td>Send your requirement (products, quantities, pack size, delivery city).</td>
                        </tr>
                        <tr>
                            <td><span class="step-number">2</span></td>
                            <td>Get price list + margin sheet (within 24 hrs).</td>
                        </tr>
                        <tr>
                            <td><span class="step-number">3</span></td>
                            <td>Confirm order + payment (invoice shared).</td>
                        </tr>
                        <tr>
                            <td><span class="step-number">4</span></td>
                            <td>Dispatch + tracking (door delivery).</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- B2B Enquiry Form -->
    <section class="b2b-section bg-light">
        <div class="container">
            <h2 class="head">B2B Enquiry Form</h2>
            <p class="head-sub">Fill this form and our team will contact you within 24 hrs.</p>
            <div class="enquiry-box">
                <div id="b2bEnquiryMsg"></div>
                <form id="b2bEnquiryForm">
                    <div class="form-group">
                        <label class="field-label">Full Name</label>
                        <input type="text" name="full_name" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Business Name</label>
                        <input type="text" name="business_name" placeholder="Business Name">
                    </div>
                    <div class="form-group">
                        <label class="field-label">Business Type</label>
                        <div class="choice-row">
                            <label><input type="radio" name="business_type" value="Retailer" checked> Retailer</label>
                            <label><input type="radio" name="business_type" value="Restaurant"> Restaurant</label>
                            <label><input type="radio" name="business_type" value="Distributor"> Distributor</label>
                            <label><input type="radio" name="business_type" value="Other"> Other</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">City</label>
                            <input type="text" name="city" placeholder="City">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">State</label>
                            <input type="text" name="state" placeholder="State">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Phone / WhatsApp</label>
                            <input type="tel" name="phone" placeholder="+91 98765 43210" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Email</label>
                            <input type="email" name="email" placeholder="you@business.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="field-label">GST Number (optional)</label>
                        <input type="text" name="gst" placeholder="GST Number">
                    </div>
                    <div class="form-group">
                        <label class="field-label">Products Interested In</label>
                        <div class="choice-row">
                            <label><input type="checkbox" name="products[]" value="Nuts"> Nuts</label>
                            <label><input type="checkbox" name="products[]" value="Dry Fruits"> Dry Fruits</label>
                            <label><input type="checkbox" name="products[]" value="Cold-Pressed Oils"> Cold-Pressed Oils</label>
                            <label><input type="checkbox" name="products[]" value="Spices"> Spices</label>
                            <label><input type="checkbox" name="products[]" value="Millets"> Millets</label>
                            <label><input type="checkbox" name="products[]" value="Others"> Others</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Approx Monthly Requirement</label>
                            <input type="text" name="monthly_requirement" placeholder="e.g. 50 kg / 100 L">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Pack Size Needed</label>
                            <div class="choice-row" style="margin-top: 10px;">
                                <label><input type="radio" name="pack_size" value="Retail" checked> Retail</label>
                                <label><input type="radio" name="pack_size" value="Bulk"> Bulk</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Message / Notes</label>
                        <textarea name="message" placeholder="Anything else we should know?"></textarea>
                    </div>
                    <button type="submit" class="btn-submit-enquiry">Submit</button>
                    <p class="consent-text">By submitting this form, you agree to be contacted by our team regarding your enquiry.</p>
                </form>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="b2b-section bg-light">
        <div class="container">
            <h2 class="head">Wholesale FAQ's</h2>
            <p class="head-sub">Here we answer the most common questions about the process. Feel free to contact us.</p>
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div id="b2bFaqAccordion">
                        <div class="faq-card">
                            <div class="card-header" id="faqHeading1">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#faqCollapse1" aria-expanded="true" aria-controls="faqCollapse1">
                                    What is the minimum order quantity (MOQ)?
                                </button>
                            </div>
                            <div id="faqCollapse1" class="collapse" aria-labelledby="faqHeading1" data-parent="#b2bFaqAccordion">
                                <div class="card-body">MOQ depends on the product and pack size. Typical starting MOQ is 1 carton or 25-50 kg.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="faqHeading2">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                    Do you provide credit?
                                </button>
                            </div>
                            <div id="faqCollapse2" class="collapse" aria-labelledby="faqHeading2" data-parent="#b2bFaqAccordion">
                                <div class="card-body">Credit terms are considered case-by-case for regular bulk partners after the first few orders.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="faqHeading3">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                    Do you ship to my city?
                                </button>
                            </div>
                            <div id="faqCollapse3" class="collapse" aria-labelledby="faqHeading3" data-parent="#b2bFaqAccordion">
                                <div class="card-body">Yes, we ship pan-India through our logistics partners with door delivery and tracking.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="faqHeading4">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                                    Shelf life?
                                </button>
                            </div>
                            <div id="faqCollapse4" class="collapse" aria-labelledby="faqHeading4" data-parent="#b2bFaqAccordion">
                                <div class="card-body">Shelf life is mentioned on each pack; nuts and dry fruits typically carry 6-12 months when stored properly.</div>
                            </div>
                        </div>
                        <div class="faq-card">
                            <div class="card-header" id="faqHeading5">
                                <button class="btn btn-link" data-toggle="collapse" data-target="#faqCollapse5" aria-expanded="false" aria-controls="faqCollapse5">
                                    Can I visit your unit / see the process?
                                </button>
                            </div>
                            <div id="faqCollapse5" class="collapse" aria-labelledby="faqHeading5" data-parent="#b2bFaqAccordion">
                                <div class="card-body">Yes, facility visits can be arranged for serious bulk/institutional partners on request.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="bg-cta">
        <div class="container">
            <h2>Let's Grow Together</h2>
            <p>Whether you are a retailer, restaurant, or distributor – we will help you build a strong nuts, dry fruits, oils, spices &amp; millets category with consistent quality and attractive margins.</p>
            <a href="https://api.whatsapp.com/send?phone=918925969888&text=Hi%2C%20I%20would%20like%20to%20request%20the%20Valluvam%20wholesale%20price%20list." target="_blank" class="btn-b2b btn-b2b-primary">Request Price List</a>
            <a href="https://api.whatsapp.com/send?phone=918925969888&text=Hi%2C%20I%20would%20like%20to%20talk%20about%20Valluvam%20wholesale." target="_blank" class="btn-b2b btn-b2b-outline">Talk On WhatsApp</a>
        </div>
    </section>

    <?php include 'footer.php' ?>

    <!-- loader -->
    <div id="ftco-loader" class="show fullscreen"><svg class="circular" width="48px" height="48px">
            <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee" />
            <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#F96D00" />
        </svg></div>

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
    <script src="js/main.js"></script>
    <script src="assets/js/b2b/b2b_enquiry.js"></script>

</body>

</html>
