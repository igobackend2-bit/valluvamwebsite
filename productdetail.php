  <?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
    include "header.php";
    $product_id = $_GET['product']; ?>

  <body class="goto-here">
      <div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
          <div class="container">
              <div class="row no-gutters slider-text align-items-center justify-content-center">
                  <div class="col-md-9 ftco-animate text-center">
                      <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Products
                          </span></p>
                      <h1 class="mb-0 bread"><b>Our Products</b></h1>
                  </div>
              </div>
          </div>
      </div>
      <div id="product-details-container" class="container-fluid mt-2 mb-3"></div>
      <script>
          let productId = "<?php echo $product_id; ?>";
      </script>

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
      <!-- jQuery already loaded in header.php, removed duplicate -->
      <script src='https://sachinchoolur.github.io/lightslider/dist/js/lightslider.js'></script>
      <!-- <script src="js/product.js"></script> -->
      <script type='text/javascript' src='https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js'></script>
      <script type='text/javascript' src='assets/js/product_detail/product_detail.js'></script>
      <script type='text/javascript' src=''></script>
      <script type='text/Javascript'></script>


  </body>