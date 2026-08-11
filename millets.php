<?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Valluvam – Organic Nuts, Dry Fruits, Oils, Spices & Millets Delivered Fresh</title>
  <meta name="description" content="Valluvam brings you organic nuts, dry fruits, cold-pressed oils, spices, and millets delivered fresh to your doorstep. Farm-fresh, pure, and nutritious.">
  <meta name="keywords" content="organic nuts, dry fruits, cold pressed oils, spices online, millets delivery, farm fresh groceries">
  <link rel="canonical" href="https://valluvamproducts.com/">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="website">
  <meta property="og:title" content="Valluvam – Organic Nuts & More Delivered Fresh">
  <meta property="og:description" content="Discover organic nuts, dry fruits, cold-pressed oils, spices & millets from Valluvam. Fresh to your home, pure by nature.">
  <meta property="og:url" content="https://valluvamproducts.com/">
  <meta property="og:image" content="/images/logo.png">
  <meta property="og:site_name" content="Valluvam">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Valluvam – Organic Nuts & More Delivered Fresh">
  <meta name="twitter:description" content="Discover organic nuts, dry fruits, cold-pressed oils, spices & millets from Valluvam. Fresh to your home, pure by nature.">
  <meta name="twitter:image" content="/images/logo.png">
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Store",
      "name": "Valluvam",
      "description": "Organic nuts, dry fruits, cold-pressed oils, spices & millets delivered fresh to your door.",
      "url": "https://valluvamproducts.com/",
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
        "contactType": "Customer Support"
      },
      "sameAs": [
        "https://www.facebook.com/valluvamproducts/",
        "https://www.instagram.com/valluvam_agro_products/"
      ],
      "openingHours": "Mo-Su 10:00-07:30"
    }
  </script>

</head>

<body class="goto-here">
  <div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
    <div class="container">
      <div class="row no-gutters slider-text align-items-center justify-content-center">
        <div class="col-md-9 ftco-animate text-center">
          <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Products</span></p>
          <h1 class="mb-0 bread">Products</h1>
        </div>
      </div>
    </div>
  </div>

  <section class="ftco-section">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-10 mb-5 text-center">
          <ul class="product-category">
            <li><a href="shop.php">All</a></li>
            <li><a href="dryfruits.php">Dryfruits</a></li>
            <li><a href="nuts.php">Nuts</a></li>
            <li><a href="spices.php">Spices</a></li>
            <li><a href="oils.php">Oils</a></li>
            <li><a href="millets.php" class="active">Millets</a></li>


          </ul>
        </div>
      </div>
      <div class="row" id="products-millets">
        <!-- All millets products here -->
      </div>
      <div class="row mt-5">
        <div class="col text-center">
          <div class="block-27">
            <ul>
              <li><a href="oils.php">&lt;</a></li>
              <li><a href="shop.php"><span>1</span></a></li>
              <li><a href="dryfruits.php"><span>2</span></a></li>
              <li><a href="nuts.php"><span>3</span></a></li>
              <li><a href="spices.php"><span>4</span></a></li>
              <li><a href="oils.php">5</a></li>
              <li class="active"><a href="millets.php">6</a></li>
              <li><a href="shop.php">&gt;</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="ftco-section ftco-no-pt ftco-no-pb py-5 bg-light">
    <div class="container py-4">
      <div class="row d-flex justify-content-center py-5">
        <div class="col-md-6">
          <h2 style="font-size: 22px;" class="mb-0">Subcribe to our Newsletter</h2>
          <span>Get e-mail updates about our latest shops and special offers</span>
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
  <!-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBVWaKrjvy3MaE7SQ74_uJiULgl1JY0H2s&sensor=false"></script>
  <script src="js/google-map.js"></script> -->
  <script src="js/main.js"></script>
  <script src="assets/js/millets/millets.js"></script>

</body>

</html>