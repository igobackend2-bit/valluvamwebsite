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
					<p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Cart</span></p>
					<h1 class="mb-0 bread">Cart</h1>
				</div>
			</div>
		</div>
	</div>
	<section class="ftco-section ftco-cart">
		<div class="container">
			<div class="row">
				<div class="col-md-12 ftco-animate">
					<div class="cart-list">
						<table class="table">
							<thead class="thead-primary">
								<tr class="text-center">
									<th>Remove</th>
									<th>Image</th>
									<th>Product Name</th>
									<!-- <th>Category</th> -->
									<th>Price</th>
									<th>Quantity</th>
									<th>Total</th>
								</tr>
							</thead>
							<tbody id="cartTable"></tbody>
						</table>
					</div>
				</div>
			</div>

		</div>
		<div class="row justify-content-end">
			<div class="col-lg-4 mt-5 cart-wrap ftco-animate">
				<div class="cart-total mb-3">
					<h3>Coupon Code</h3>
					<p>Enter your coupon code if you have one</p>
					<form action="#" class="info">
						<div class="form-group">
							<label for="">Coupon code</label>
							<input type="text" class="form-control text-left px-3" placeholder="">
						</div>
					</form>
				</div>
				<p><a href="checkout.php" class="btn btn-primary py-3 px-4">Apply Coupon</a></p>
			</div>

			<div class="col-lg-4 mt-5 cart-wrap ftco-animate">
				<div class="cart-total mb-3">
					<h3>Cart Totals</h3>
					<p class="d-flex">
						<span>Subtotal</span>
						<span id="subtotal">00.00</span>
					</p>
					<p class="d-flex">
						<span>Delivery</span>
						<span>&#8377;0.00</span>
					</p>
					<p class="d-flex">
						<span>Discount</span>
						<span>&#8377;3.00</span>
					</p>
					<hr>
					<p class="d-flex total-price">
						<span>Total</span>
						<span id="total">00.00</span>
					</p>
				</div>
				<button type="button" id="checkoutBtn" class="btn btn-primary py-3 px-4">
					Proceed to Checkout
				</button>



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
	<script src="assets/js/cart/cart.js"></script>

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
	<script>
		document.addEventListener("click", async function(e) {
			const btn = e.target.closest("#checkoutBtn");
			if (!btn) return;

			// ✅ STOP everything that causes redirect
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();

			// ✅ Read values from UI
			const subtotal = (document.getElementById("subtotal")?.innerText || "0").replace(/[^0-9.]/g, "").trim();
			const total = (document.getElementById("total")?.innerText || "0").replace(/[^0-9.]/g, "").trim();

			// ✅ Build correct API url relative to current page (no wrong paths)
			const apiUrl = new URL("assets/db_query/cart/cart_query.php", window.location.href);

			try {
				const res = await fetch(apiUrl.toString(), {
					method: "POST",
					headers: {
						"Content-Type": "application/x-www-form-urlencoded"
					},
					credentials: "same-origin",
					body: "action=proceedtocheckout&subtotal=" + encodeURIComponent(subtotal) +
						"&total=" + encodeURIComponent(total)
				});

				const raw = await res.text();
				console.log("API status:", res.status);
				console.log("API raw:", raw);

				if (!res.ok) {
					alert("API HTTP Error: " + res.status);
					return;
				}

				const data = JSON.parse(raw);

				if (data.status === "success") {
					// ✅ Redirect only after session saved
					window.location.href = "checkout.php";
				} else {
					alert("Failed: " + (data.message || data.status));
				}
			} catch (err) {
				console.error(err);
				alert("AJAX failed: " + err.message);
			}
		}, true); // ✅ capture mode (runs before other handlers)
	</script>


</body>

</html>