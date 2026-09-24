<?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
include 'header.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combo Packs — Nuts, Dry Fruits, Oils & Spices | Valluvam</title>
    <meta name="description" content="Shop Valluvam combo packs bringing together nuts, dry fruits, cold-pressed oils and spices in one convenient order.">
    <meta name="keywords" content="nuts, dry fruits, cold pressed oils, spices online, millets delivery, farm fresh groceries">
    <link rel="canonical" href="https://www.valluvamproducts.com/combo.php">
    <link rel="stylesheet" href="css/category-redesign.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Combo Packs — Nuts, Dry Fruits, Oils & Spices | Valluvam">
    <meta property="og:description" content="Shop Valluvam combo packs bringing together nuts, dry fruits, cold-pressed oils and spices in one convenient order.">
    <meta property="og:url" content="https://www.valluvamproducts.com/combo.php">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Combo Packs — Nuts, Dry Fruits, Oils & Spices | Valluvam">
    <meta name="twitter:description" content="Shop Valluvam combo packs bringing together nuts, dry fruits, cold-pressed oils and spices in one convenient order.">
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

</head>

<body class="goto-here">
	<div class="hero-wrap hero-bread v-cat-hero" style="background-image: url('images/bg-main.jpg');">
		<div class="container">
			<div class="row no-gutters slider-text align-items-center justify-content-center">
				<div class="col-md-9 ftco-animate text-center">
					<img src="assets/thumbnail/combo.jpg" class="v-cat-badge" alt="Combo Packs" loading="lazy">
					<p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Combo</span></p>
					<h1 class="mb-0 bread">Combo</h1>
					<p class="v-cat-desc">Shop Valluvam combo packs bringing together nuts, dry fruits, cold-pressed oils and spices in one convenient order.</p>
				</div>
			</div>
		</div>
	</div>
	<section class="v-shop-results"><!-- was a nested .ftco-section: doubled the padding and left a large empty gap under the category pills -->
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-md-10 mb-5 text-center">
					<?php include __DIR__ . '/category_pills.php'; ?>
				</div>
			</div>
			<p class="v-cat-count" id="combo-count"></p>
			<div class="row mb-5 v-cat-grid" id="products-combo">
				<div class="col-md-6 col-lg-3 mb-5">
					<!-- Products will be loaded here -->
				</div>
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
	<script src="assets/js/combo/combo.js"></script>

</body>

</html>