<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$status = isset($_SESSION['status']) ? $_SESSION['status'] : 0;
$user_name = isset($_SESSION['username']) ? $_SESSION['username'] : '';

?>
<script>
  const userStatus = <?php echo $status; ?>;
  const currentPage = "<?php echo basename($_SERVER['PHP_SELF']); ?>";
</script>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">
  <!-- Include Swiper and Animate.css -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">

  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">

  <link rel="stylesheet" href="css/aos.css">

  <link rel="stylesheet" href="css/ionicons.min.css">

  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">


  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">
  <!-- <link rel="stylesheet" href="css/products.css"> -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <!-- Font Awesome Free 6 (latest stable via jsDelivr CDN) -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-CutkzkCZnQpjkGH5W8cztD8Lq1SxH0g3ssZzkYO4CDYQhQwH4iLRyKfUENtYyX6UGN7vNzQk5xFojy6LXz9lBA==" crossorigin="anonymous" referrerpolicy="no-referrer" /> -->
  <meta property="og:image" content="images/logo.png" />
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" integrity="sha512-5A8nwdMOWrSz20fDsjczgUidUBR8liPYU+WymTZP1lmY9G6Oc7HlZv156XqnsgNUzTyMefFTcsFH/tnJE/+xBg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/fontawesome.min.css">
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">

  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">

  <link rel="stylesheet" href="css/aos.css">

  <link rel="stylesheet" href="css/ionicons.min.css">

  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">


  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

  <link href="https://fonts.googleapis.com/css?family=Poppins:400,500,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome (for icons) -->
  <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet"> -->
  <!-- <link href="https://fonts. leapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet"> -->
  <!-- <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet"> -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=account_circle" />
  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">

  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">

  <link rel="stylesheet" href="css/aos.css">

  <link rel="stylesheet" href="css/ionicons.min.css">

  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">


  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">

  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">

  <link rel="stylesheet" href="css/aos.css">

  <link rel="stylesheet" href="css/ionicons.min.css">

  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">


  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">

  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">

  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">

  <link rel="stylesheet" href="css/aos.css">

  <link rel="stylesheet" href="css/ionicons.min.css">

  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">


  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/productdet.css">
  <link rel="stylesheet" href="css/products.css?v=3">
  <link rel="stylesheet" href="css/login.css">
  <!-- bootstrap-4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

  <!-- <link rel="stylesheet" href="css/catelog.css"> -->
  <!-- sweetheart -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.css">
</head>

<body>
  <div class="py-1 bg-primary" style="background-color: green;">
    <div class="container">
      <div class="row no-gutters d-flex align-items-start align-items-center px-md-0">
        <div class="col-lg-12 d-block">
          <div class="row d-flex">
            <div class="col-md pr-4 d-flex topper align-items-center">
              <div class="icon mr-2 d-flex justify-content-center align-items-center"><span class="icon-phone2"></span></div>
              <span class="text">+918925969888</span>
            </div>
            <div class="col-md pr-4 d-flex topper align-item  s-center">
              <div class="icon mr-2 d-flex justify-content-center align-items-center"><span class="icon-paper-plane"></span></div>
              <span class="text">info.thefarmersfactory@gmail.com</span>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
  <nav class="navbar navbar-expand-lg navbar-dark ftco_navbar bg-dark ftco-navbar-light" id="ftco-navbar">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="index.php">
        <img src="images/logo.jpeg" alt="Valluva" class="img-fluid" style="max-height:100px;">
      </a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="oi oi-menu"></span> Menu
      </button>
      <button id="navbarClose" class="navbar-close" aria-label="Close menu">&times;</button>


      <?php
      // get the current page name
      $currentPage = basename($_SERVER['PHP_SELF']);

      // allowed pages where search should be shown
      $allowedPages = ['index.php', 'shop.php', 'dryfruits.php', 'nuts.php', 'combo.php', 'spices.php', 'oils.php', 'millets.php', 'productdetail.php'];
      ?>

      <?php if (in_array($currentPage, $allowedPages)) : ?>
        <form id="searchForm">
          <div class="search">
            <ion-icon name="search"></ion-icon>
            <input class="search-input" type="search" id="search" placeholder="search products.....">
          </div>
        </form>
      <?php endif; ?>

      <!-- Products will display here -->
      <div id="product-results"></div>


      <div class="collapse navbar-collapse" id="ftco-nav">
        <ul class="navbar-nav ml-auto" style="gap: 2px;">
          <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown04" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Shop</a>
            <div class="dropdown-menu" aria-labelledby="dropdown04">
              <a class="dropdown-item" href="shop.php">Shop</a>
              <a class="dropdown-item" href="nuts.php">Nuts</a>
              <a class="dropdown-item" href="dryfruits.php">Dryfruits</a>
              <a class="dropdown-item" href="oils.php">Oils</a>
              <a class="dropdown-item" href="spices.php">Spices</a>
              <a class="dropdown-item" href="millets.php">Millets</a>
              <a class="dropdown-item" href="combo.php">Combo</a>
              <a class="dropdown-item" href="wishlist.php">Wishlist</a>
              <!-- <a class="dropdown-item" href="cart.php">Cart</a> -->
              <a class="dropdown-item" href="checkout.php">Checkout</a>
            </div>
          </li>

          <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
          <li class="nav-item"><a href="blog.php" class="nav-link">Blog</a></li>
          <li class="nav-item"><a href="b2b-wholesale.php" class="nav-link">B2B / Wholesale</a></li>
          <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
          <li class="nav-item cta cta-colored">
            <a href="cart.php" class="nav-link">
              <span class="icon-shopping_cart"></span>
              <span id="cartCount">[0]</span>
            </a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link <?php echo ($status == 1) ? 'dropdown-toggle' : ''; ?>"
              href="#"
              id="userIcon"
              <?php if ($status == 1): ?>
              data-toggle="dropdown"
              aria-haspopup="true"
              aria-expanded="false"
              <?php endif; ?>>
              <span class="login-nav">
                <i class="ri-account-circle-fill"></i>
                <?php if ($status == 1): ?>
                  <span style="font-weight:bold;margin-left:5px;">
                    <?php echo htmlspecialchars($user_name); ?>
                  </span>
                <?php endif; ?>

              </span>
            </a>

            <?php if ($status == 1): ?>
              <div class="dropdown-menu" aria-labelledby="userIcon">
                <a class="dropdown-item" href="order_tracking.php"><i class="fa fa-shopping-bag mr-2"></i>My Orders</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out mr-2"></i>Logout</a>
              </div>
            <?php endif; ?>
          </li>

        </ul>
      </div>
    </div>
  </nav>
  <!-- Login/Signup Modal -->
  <div class="form-modal" id="popupForm" style="display:none;">
    <div class="form-close" onclick="closeForm()">×</div>

    <div class="form-toggle">
      <button id="login-toggle" onclick="toggleLogin()">log in</button>
      <button id="signup-toggle" onclick="toggleSignup()">sign up</button>
    </div>

    <div id="login-form">
      <form id="loginForm">
        <input type="text" name="identifier" placeholder="Enter email or username" required />
        <input type="password" name="password" placeholder="Enter password" required />
        <button type="submit" class="btn login">login</button>
        <!-- <p><a href="#" onclick="retryLogin()">Forgotten account?</a></p> -->
        <hr />
      </form>
    </div>

    <div id="signup-form" style="display:none;">
      <form id="signupForm">
        <input type="email" name="email" placeholder="Enter your email" required />
        <input type="text" name="phone" placeholder="Enter your contact number" required />
        <input type="text" name="username" placeholder="Choose username" required />
        <input type="password" name="password" placeholder="Create password" required />
        <button type="submit" class="btn signup">create account</button>
        <p>Clicking <strong>create account</strong> means you agree to our <a href="#">terms of services</a>.</p>
        <hr />
      </form>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="assets/js/utils/swal-helper.js"></script>
  <script src="assets/js/login/login.js"></script>
  <script src="assets/js/header/header.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {

      // REMOVE all FTCO auto navbar events
      $('.ftco-navbar-light').off();
      $('#ftco-nav').off();
      $('.navbar-toggler').off();
      $('.navbar-collapse').off();

      // Make sure navbar starts closed
      $('#ftco-nav').removeClass('show');

      // Bootstrap default toggle ONLY
      $('.navbar-toggler').on('click', function(e) {
        e.preventDefault();
        $('#ftco-nav').collapse('toggle');
      });

    });
  </script>

  <!-- Removed duplicate jQuery 3.2.1.slim - using jQuery 3.6.0 from above -->
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>