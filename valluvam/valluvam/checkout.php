<?php
// Session is started in header.php, so we don't need to start it here
// But we need to check session before including header
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

// ✅ Use session totals saved from cart page, or calculate from cart
$subtotal = (float)($_SESSION['checkout_subtotal'] ?? 0);
$delivery = (float)($_SESSION['checkout_delivery'] ?? 0.00);
$discount = (float)($_SESSION['checkout_discount'] ?? 3.00);

// If no session data, try to calculate from cart
if ($subtotal == 0) {
    require_once 'assets/db_query/config.php';
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT SUM(c.quantity * p.dis_price) as subtotal
        FROM cart c
        JOIN product_details p ON c.product_id = p.id
        WHERE c.user_id = ? AND c.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $subtotal = (float)($result['subtotal'] ?? 0);
}

// ✅ Calculate final total here (don't trust stored total)
$total = max(0, $subtotal + $delivery - $discount);

// If cart is empty, redirect to cart page
if ($subtotal == 0) {
    header('Location: cart.php');
    exit;
}
?>


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
					<p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Checklist</span></p>
					<h1 class="mb-0 bread">Checklist</h1>
				</div>
			</div>
		</div>
	</div>


	<section class="ftco-section">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-xl-7 ftco-animate">
					<form action="#" class="billing-form" id="checkoutForm">
						<h3 class="mb-4 billing-heading">Billing Details</h3>
						<div class="row align-items-end">
							<div class="col-md-6">
								<div class="form-group">
									<label for="firstname">First Name</label>
									<input type="text" class="form-control" name="first_name" required>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="lastname">Last Name</label>
									<input type="text" class="form-control" name="last_name" required>
								</div>
							</div>

							<div class="col-md-12">
								<div class="form-group">
									<label for="country">State / Country</label>
									<select class="form-control" name="state" required>
										<option value="">-- Select --</option>
										<option value="Tamilnadu">Tamilnadu</option>
										<option value="Kerala">Kerala</option>
										<option value="Karnataka">Karnataka</option>
										<option value="Andhra Pradesh">Andhra Pradesh</option>
										<option value="Telangana">Telangana</option>
										<option value="Uttar Pradesh">Uttar Pradesh</option>
									</select>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="streetaddress">Street Address</label>
									<input type="text" class="form-control" name="street_address" required>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<input type="text" class="form-control" name="apartment" placeholder="Apartment, suite (optional)">
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="towncity">Town / City</label>
									<input type="text" class="form-control" name="city" required>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="postcodezip">Postcode / ZIP *</label>
									<input type="text" class="form-control" name="postcode" required>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="phone">Phone</label>
									<input type="text" class="form-control" name="phone" required>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="emailaddress">Email Address</label>
									<input type="email" class="form-control" name="email" required>
								</div>
							</div>
						</div>
				</div>
				<div class="col-xl-5">
					<div class="row mt-5 pt-3">
						<div class="col-md-12 d-flex mb-5">
							<div class="cart-detail cart-total p-3 p-md-4">
								<h3 class="billing-heading mb-4">Cart Total</h3>

								<p class="d-flex">
									<span>Subtotal</span>
									<span>&#8377;<?= number_format($subtotal, 2) ?></span>
								</p>

								<p class="d-flex">
									<span>Delivery</span>
									<span>&#8377;<?= number_format($delivery, 2) ?></span>
								</p>

								<p class="d-flex">
									<span>Discount</span>
									<span style="color: green;">-&#8377;<?= number_format($discount, 2) ?></span>
								</p>

								<hr>

								<p class="d-flex total-price">
									<span><strong>Total</strong></span>
									<span><strong>&#8377;<?= number_format($total, 2) ?></strong></span>
								</p>


							</div>
						</div>
						<div class="col-md-12">
							<div class="cart-detail p-3 p-md-4">
								<h3 class="billing-heading mb-4">Payment Method</h3>
								<div class="form-group">
									<div class="col-md-12">
										<div class="form-group">
											<label style="display: block; margin-bottom: 10px; cursor: pointer;">
												<input type="radio" name="payment_method" value="COD" required checked style="margin-right: 8px;">
												<strong>Cash on Delivery</strong>
											</label>
											<label style="display: block; margin-bottom: 10px; cursor: pointer;">
												<input type="radio" name="payment_method" value="RZP" required style="margin-right: 8px;">
												<strong>Razorpay (Online Payment)</strong>
											</label>
											<br>
											<label style="cursor: pointer;">
												<input type="checkbox" name="terms" value="1" required style="margin-right: 8px;">
												I accept terms and conditions
											</label>
										</div>
										<button type="submit" id="placeOrderBtn" class="btn btn-primary mt-3" style="width: 100%;">Place Order</button>
										</form>
									</div>
								</div>
							</div>
						</div>
					</div> <!-- .col-md-8 -->
				</div>
			</div>
	</section>
	<!-- .section -->
	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<!-- SwalHelper utility -->
	<script src="assets/js/utils/swal-helper.js"></script>
	<!-- jQuery already loaded in header.php, removed duplicate -->

	<script>
		$(document).ready(function() {
			// Load cart from sessionStorage if available (fallback)
			loadCartFromStorage();
			
			// Load previous address if user has ordered before
			loadPreviousAddress();
			
			// Handle payment method change
			$('input[name="payment_method"]').on('change', function() {
				const method = $(this).val();
				if (method === 'RZP') {
					$('#placeOrderBtn').text('Pay with Razorpay');
				} else {
					$('#placeOrderBtn').text('Place Order');
				}
			});
		});

		function loadCartFromStorage() {
			// This is a fallback - totals are already calculated in PHP from session
			const cartData = sessionStorage.getItem('checkout_cart');
			if (cartData) {
				try {
					const cart = JSON.parse(cartData);
					console.log('Cart loaded from sessionStorage:', cart.length, 'items');
				} catch(e) {
					console.error('Error parsing cart data:', e);
				}
			}
		}

		function loadPreviousAddress() {
			$.ajax({
				url: 'assets/db_query/checkout/get_previous_address.php',
				type: 'GET',
				dataType: 'json',
				success: function(res) {
					if (res.status === 'success' && res.address) {
						const addr = res.address;
						
						// Auto-fill form fields
						if (addr.first_name) $('input[name="first_name"]').val(addr.first_name);
						if (addr.last_name) $('input[name="last_name"]').val(addr.last_name);
						if (addr.email) $('input[name="email"]').val(addr.email);
						if (addr.phone) $('input[name="phone"]').val(addr.phone);
						if (addr.state) $('select[name="state"]').val(addr.state);
						if (addr.city) $('input[name="city"]').val(addr.city);
						if (addr.street_address) $('input[name="street_address"]').val(addr.street_address);
						if (addr.apartment) $('input[name="apartment"]').val(addr.apartment);
						if (addr.postcode) $('input[name="postcode"]').val(addr.postcode);
					}
				},
				error: function() {
					// Silently fail - user can fill manually
					console.log('Could not load previous address');
				}
			});
		}

		$("#checkoutForm").on("submit", function(e) {
			e.preventDefault();

			// Validate required fields manually
			let valid = true;
			$("#checkoutForm [required]").each(function() {
				if ($(this).val().trim() === "") {
					valid = false;
					SwalHelper.ecommerce.validationError('Required Field', 'Please fill all required fields to complete your order.');
					return false; // break loop
				}
			});

			if (!valid) return;

			// Check terms checkbox
			if (!$('input[name="terms"]').is(':checked')) {
				SwalHelper.warning('Terms Required', 'Please accept the terms and conditions to proceed.');
				return;
			}

			const paymentMethod = $('input[name="payment_method"]:checked').val();
			const formData = $(this).serialize();

			if (paymentMethod === 'RZP') {
				// Handle Razorpay payment
				handleRazorpayPayment(formData);
			} else {
				// Handle COD
				handleCODPayment(formData);
			}
		});

		function handleCODPayment(formData) {
			SwalHelper.loading('Processing Your Order...');
			
			$.ajax({
				url: "assets/db_query/order/create.php",
				type: "POST",
				data: formData,
				dataType: "json",
				success: function(res) {
					SwalHelper.close();
					if (res.status === "success") {
						// Clear sessionStorage
						sessionStorage.removeItem('checkout_cart');
						sessionStorage.removeItem('checkout_subtotal');
						sessionStorage.removeItem('checkout_delivery');
						sessionStorage.removeItem('checkout_discount');
						sessionStorage.removeItem('checkout_total');
						
						SwalHelper.ecommerce.orderSuccess(res.order_id || res.receipt || 'N/A').then(() => {
							window.location.href = "index.php";
						});
					} else {
						SwalHelper.ecommerce.orderError(res.message);
					}
				},
				error: function(xhr) {
					SwalHelper.close();
					console.error('Order error:', xhr);
					SwalHelper.ecommerce.networkError();
				}
			});
		}

		function handleRazorpayPayment(formData) {
			// Create Razorpay order only (NOT database order yet)
			SwalHelper.loading('Initiating Payment...');
			
			$.ajax({
				url: "assets/db_query/order/create_razorpay_order.php",
				type: "POST",
				data: formData,
				dataType: "json",
				success: function(res) {
					SwalHelper.close();
					if (res.status === "success" && res.razorpay_order_id) {
						// Initialize Razorpay payment
						initiateRazorpayPayment(res);
					} else {
						SwalHelper.ecommerce.orderError(res.message || 'Failed to initiate payment. Please try again.');
					}
				},
				error: function(xhr) {
					SwalHelper.close();
					console.error('Payment initiation error:', xhr);
					SwalHelper.ecommerce.networkError();
				}
			});
		}

		function initiateRazorpayPayment(orderData) {
			const options = {
				key: orderData.key_id,
				amount: orderData.amount_paise,
				currency: orderData.currency || 'INR',
				name: 'Valluvam Products',
				description: 'Order Payment - ' + (orderData.receipt || ''),
				order_id: orderData.razorpay_order_id,
				handler: function(response) {
					// Verify payment
					verifyRazorpayPayment(response, orderData);
				},
				prefill: {
					name: $('input[name="first_name"]').val() + ' ' + $('input[name="last_name"]').val(),
					email: $('input[name="email"]').val(),
					contact: $('input[name="phone"]').val()
				},
				// Notes for order tracking
				notes: {
					order_id: orderData.order_id || '',
					receipt: orderData.receipt || ''
				},
				// Enable UPI intent flow (shows UPI payment option)
				upi: {
					flow: 'intent' // Shows UPI payment option
				},
				theme: {
					color: '#57B846'
				},
				modal: {
					ondismiss: function() {
						// Clear pending order from session since payment was cancelled
						$.ajax({
							url: "assets/db_query/order/clear_pending_order.php",
							type: "POST",
							success: function() {
								SwalHelper.warning('Payment Cancelled', 'Payment was cancelled. Your cart items are still available. You can try again later.');
							}
						});
					}
				},
				// Ensure all payment methods are visible
				readonly: {
					email: false,
					contact: false
				}
			};

			try {
				const rzp = new Razorpay(options);
				rzp.open();
			} catch(error) {
				console.error('Razorpay initialization error:', error);
				SwalHelper.ecommerce.orderError('Failed to initialize payment gateway. Please try again or use Cash on Delivery.');
			}
		}

		function verifyRazorpayPayment(response, orderData) {
			SwalHelper.loading('Verifying Payment...');
			
			$.ajax({
				url: "assets/db_query/order/payment/verify.php",
				type: "POST",
				data: {
					razorpay_order_id: response.razorpay_order_id,
					razorpay_payment_id: response.razorpay_payment_id,
					razorpay_signature: response.razorpay_signature
				},
				dataType: "json",
				success: function(res) {
					SwalHelper.close();
					if (res.status === "success") {
						// Clear sessionStorage
						sessionStorage.removeItem('checkout_cart');
						sessionStorage.removeItem('checkout_subtotal');
						sessionStorage.removeItem('checkout_delivery');
						sessionStorage.removeItem('checkout_discount');
						sessionStorage.removeItem('checkout_total');
						
						SwalHelper.ecommerce.orderSuccess(res.receipt || res.order_id || 'N/A').then(() => {
							window.location.href = "index.php";
						});
					} else {
						SwalHelper.ecommerce.orderError(res.message || 'Payment verification failed. Please contact support.');
					}
				},
				error: function(xhr) {
					SwalHelper.close();
					console.error('Payment verification error:', xhr);
					SwalHelper.ecommerce.networkError();
				}
			});
		}
	</script>
	
	<!-- Razorpay Checkout Script -->
	<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

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

	<script>
		$(document).ready(function() {

			var quantitiy = 0;
			$('.quantity-right-plus').click(function(e) {

				// Stop acting like a button
				e.preventDefault();
				// Get the field name
				var quantity = parseInt($('#quantity').val());

				// If is not undefined

				$('#quantity').val(quantity + 1);


				// Increment

			});

			$('.quantity-left-minus').click(function(e) {
				// Stop acting like a button
				e.preventDefault();
				// Get the field name
				var quantity = parseInt($('#quantity').val());

				// If is not undefined

				// Increment
				if (quantity > 0) {
					$('#quantity').val(quantity - 1);
				}
			});

		});
	</script>

</body>

</html>