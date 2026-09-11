<?php
// Session is started in header.php, so we don't need to start it here
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$actionpage = basename($_SERVER['PHP_SELF'], ".php");
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Order Tracking - Valluvam</title>
	<meta name="description" content="Track your orders with Valluvam - See order status from ordered to delivered">
	<link rel="canonical" href="https://www.valluvamproducts.com/order_tracking.php">
	<meta name="robots" content="noindex, follow">
	<meta property="og:type" content="website">
	<meta property="og:title" content="Order Tracking - Valluvam">
	<meta property="og:description" content="Track your orders with Valluvam - See order status from ordered to delivered">
	<meta property="og:url" content="https://www.valluvamproducts.com/order_tracking.php">
	<meta property="og:image" content="/images/logo.png">
	<meta property="og:site_name" content="Valluvam">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="Order Tracking - Valluvam">
	<meta name="twitter:description" content="Track your orders with Valluvam - See order status from ordered to delivered">
	<meta name="twitter:image" content="/images/logo.png">
	<link rel="stylesheet" href="css/style.css">
</head>

<body class="goto-here">
	<div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
		<div class="container">
			<div class="row no-gutters slider-text align-items-center justify-content-center">
				<div class="col-md-9 ftco-animate text-center">
					<p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Order Tracking</span></p>
					<h1 class="mb-0 bread">My Orders</h1>
				</div>
			</div>
		</div>
	</div>

	<section class="ftco-section ftco-cart">
		<div class="container">
			<div class="row">
				<div class="col-md-12 ftco-animate">
					<div id="ordersContainer">
						<!-- Orders will be loaded here via JavaScript -->
						<div class="text-center py-5">
							<div class="spinner-border text-primary" role="status">
								<span class="sr-only">Loading...</span>
							</div>
							<p class="mt-3">Loading your orders...</p>
						</div>
					</div>
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
	<script src="js/main.js"></script>
	<script src="assets/js/utils/swal-helper.js"></script>
	<script src="assets/js/order_tracking/order_tracking.js"></script>
</body>

</html>

