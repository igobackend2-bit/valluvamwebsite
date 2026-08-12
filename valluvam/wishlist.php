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


<head>
	<style>
		/* wishlist thumbnails */
		.image-prod .img {
			width: 120px;
			height: 80px;
			background-size: cover;
			background-position: center;
			border-radius: 4px;
		}
	</style>
</head>

<body class="goto-here">
	<div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
		<div class="container">
			<div class="row no-gutters slider-text align-items-center justify-content-center">
				<div class="col-md-9 ftco-animate text-center">
					<p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Wishlist</span></p>
					<h1 class="mb-0 bread">Wishlist</h1>
				</div>
			</div>
		</div>
	</div>
	<!-- wishlist.php -->
	<div class="container mt-4">
		<h2>My Wishlist</h2>
		<div id="loginNotice" style="display:none;" class="alert alert-warning">Please login to view your wishlist.</div>

		<div class="cart-list">
			<table class="table">
				<thead class="thead-primary" style="background:#28a745;color:#fff;"> <!-- green header -->
					<tr class="text-center">
						<th>&nbsp;</th>
						<th>Product</th>
						<th>&nbsp;</th>
						<th>Price</th>
						<th>Quantity</th>
						<th>Total</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody id="wishlistTable"></tbody>
				<tfoot>
					<tr>
						<td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
						<td id="grandTotal">₹0.00</td>
						<td></td>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>


	<script>
		$(document).ready(function() {
			function loadWishlist() {
				$.ajax({
					url: 'assets/db_query/wishlist/wishlist_query.php?action=get',
					type: 'GET',
					dataType: 'json',
					success: function(res) {
						if (res.status === 'not_logged_in') {
							$('#loginNotice').show();
							$('#wishlistTable').html('');
							return;
						}
						if (res.status !== 'success') {
							alert(res.message || 'Error loading wishlist');
							return;
						}

						const rows = res.wishlist.map(item => {
							const price = parseFloat(item.dis_price || 0);
							const qty = parseInt(item.quantity || 1);
							const total = (price * qty).toFixed(2);

							return `
                        <tr class="text-center" data-wishlist-id="${item.id}">
                            <td class="product-remove">
                                <a href="#" class="remove-wishlist" data-id="${item.id}">
                                    <span class="ion-ios-close"></span>
                                </a>
                            </td>
                            <td class="image-prod">
                                <div class="img" style="background-image:url(${escapeHtml(item.product_image)});"></div>
                            </td>
                            <td class="product-name">
                                <h3>${escapeHtml(item.product_name)}</h3>
                                <p>${escapeHtml(item.category || '')}</p>
                            </td>
                            <td class="price">${price.toFixed(2)}</td>
                            <td class="quantity">
                                <div class="input-group mb-3">
                                    <input type="text" class="quantity form-control input-number" value="${qty}" min="1" max="100">
                                </div>
                            </td>
                            <td class="total">${total}</td>
                            <td>
                                <button class="btn btn-sm  add-to-cart" data-id="${item.product_id}" style="background-color:82ae46">
                                    Add to Cart
                                </button>
                            </td>
                        </tr>
                    `;
						}).join('');

						$('#wishlistTable').html(rows);
					},
					error: function() {
						alert('AJAX error while loading wishlist');
					}
				});
			}

			// Remove item from wishlist
			$(document).on('click', '.remove-wishlist', function(e) {
				e.preventDefault();
				const wishId = $(this).data('id');
				if (!confirm('Remove this item from your wishlist?')) return;

				$.ajax({
					url: 'assets/db_query/wishlist/wishlist_query.php?action=delete',
					type: 'POST',
					data: {
						wishlist_id: wishId
					},
					dataType: 'json',
					success: function(res) {
						if (res.status === 'success') {
							loadWishlist();
						} else if (res.status === 'not_logged_in') {
							alert('Please login first');
						} else {
							alert(res.message || 'Delete failed');
						}
					}
				});
			});

			// Add to cart handler removed - now handled globally in header.js to prevent duplicate execution

			function escapeHtml(text) {
				if (text === null || text === undefined) return '';
				return String(text)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;');
			}

			loadWishlist();
		});
	</script>



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

	<!-- jQuery already loaded in header.php, removed duplicate -->
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
	<script src="assets/js/wishlist/wishlist.js"></script>




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