<?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buy Seeds Online | Valluvam</title>
  <meta name="description" content="Shop a variety of nutritious seeds from Valluvam, carefully sourced and freshly packed for your daily wellness.">
  <meta name="keywords" content="buy seeds online, chia seeds, pumpkin seeds, sunflower seeds, Valluvam seeds">
  <link rel="canonical" href="https://www.valluvamproducts.com/seeds.php">
  <link rel="stylesheet" href="css/category-redesign.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="website">
  <meta property="og:title" content="Buy Seeds Online | Valluvam">
  <meta property="og:description" content="Shop a variety of nutritious seeds from Valluvam, carefully sourced and freshly packed for your daily wellness.">
  <meta property="og:url" content="https://www.valluvamproducts.com/seeds.php">
  <meta property="og:image" content="/images/logo.png">
  <meta property="og:site_name" content="Valluvam">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Buy Seeds Online | Valluvam">
  <meta name="twitter:description" content="Shop a variety of nutritious seeds from Valluvam, carefully sourced and freshly packed for your daily wellness.">
  <meta name="twitter:image" content="/images/logo.png">
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

  <!-- Matches the visible breadcrumb (Home > Rice) already rendered below - additive only. -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://www.valluvamproducts.com/index.php" },
        { "@type": "ListItem", "position": 2, "name": "Seeds", "item": "https://www.valluvamproducts.com/seeds.php" }
      ]
    }
  </script>

  <!-- Sort toolbar: scoped to this new element only, additive. -->
  <style>
    .v-cat-toolbar {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 18px;
    }

    .v-cat-sort {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .v-cat-sort label {
      font-size: 14px;
      font-weight: 500;
      color: #666;
      margin: 0;
    }

    .v-cat-sort select {
      height: 40px;
      padding: 0 12px;
      border-radius: 6px;
      border: 1px solid #ddd;
      font-size: 14px;
      background: #fff;
    }
  </style>

</head>

<body class="goto-here">
  <div class="hero-wrap hero-bread v-cat-hero" style="background-image: url('images/bg-main.jpg');">
    <div class="container">
      <div class="row no-gutters slider-text align-items-center justify-content-center">
        <div class="col-md-9 ftco-animate text-center">
          <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Seeds</span></p>
          <h1 class="mb-0 bread">Seeds</h1>
          <p class="v-cat-desc">Shop a variety of nutritious seeds from Valluvam, carefully sourced and freshly packed for your daily wellness.</p>
        </div>
      </div>
    </div>
  </div>

  <section class="ftco-section">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-10 mb-5 text-center">
          <?php include __DIR__ . '/category_pills.php'; ?>
        </div>
      </div>
      <div class="v-cat-toolbar">
        <p class="v-cat-count" id="seeds-count"></p>
        <!-- Client-side sort over the products already fetched for this page - no new
             endpoint or database change. Price and Newest use real columns already
             returned by seeds_query.php (price / timestamp). There is no "featured" flag
             or sales/popularity data in product_details, so those two sort options are
             intentionally left out here rather than faked. -->
        <div class="v-cat-sort">
          <label for="seeds-sort">Sort by</label>
          <select id="seeds-sort">
            <option value="default">Default</option>
            <option value="price-asc">Price: Low to High</option>
            <option value="price-desc">Price: High to Low</option>
            <option value="newest">Newest</option>
          </select>
        </div>
      </div>
      <div class="row v-cat-grid" id="products-seeds">
        <!-- All seeds products here -->
      </div>

      <div class="v-cat-related">
        <h3>Explore Other Categories</h3>
        <div class="v-cat-related-links">
            <a href="shop.php">All Products</a>
            <a href="dryfruits.php">Dry Fruits</a>
            <a href="nuts.php">Nuts</a>
            <a href="spices.php">Spices</a>
            <a href="oils.php">Oils</a>
            <a href="millets.php">Millets</a>
            <a href="rice.php">Rice</a>
            <a href="combo.php">Combo</a>
            <a href="palm-jaggery.php">Palm Jaggery</a>
            <a href="dal.php">Dal</a>
            <a href="honey.php">Honey</a>
            <a href="ghee.php">Ghee</a>
            <a href="pulses.php">Pulses</a>
        </div>
      </div>
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
  <script src="js/owl.carousel.min.js"></script>
  <script src="js/jquery.magnific-popup.min.js"></script>
  <script src="js/aos.js"></script>
  <script src="js/jquery.animateNumber.min.js"></script>
  <script src="js/bootstrap-datepicker.js"></script>
  <script src="js/scrollax.min.js"></script>
  <!-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBVWaKrjvy3MaE7SQ74_uJiULgl1JY0H2s&sensor=false"></script>
  <script src="js/google-map.js"></script> -->
  <script src="js/main.js"></script>
  <script src="assets/js/category/category-common.js"></script>
  <script src="assets/js/seeds/seeds.js"></script>

</body>

</html>