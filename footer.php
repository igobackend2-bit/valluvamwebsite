<h3>OUR BRANDS</h3>
<section id="brand-slider">
	<div class="slider6">
		<div class="slider6-items">
			<img src="images/brand-1.jpg" alt="brand">
			<img src="images/brand-2.jpg" alt="brand">
			<img src="images/brand-3.jpg" alt="brand">
			<img src="images/brand-4.jpg" alt="brand">
			<img src="images/brand-5.jpg" alt="brand">
			<img src="images/brand-6.jpg" alt="brand">
			<!-- Duplicate images for infinite loop -->
			<img src="images/brand-1.jpg" alt="brand">
			<img src="images/brand-2.jpg" alt="brand">
			<img src="images/brand-3.jpg" alt="brand">
		</div>
	</div>
</section>

<style>
	#brand-slider {
		overflow: hidden;
		background: #fff;
		padding: 20px 0;
	}

	.slider6 {
		width: 100%;
		position: relative;
		overflow: hidden;
	}

	.slider6-items {
		display: flex;
		width: max-content;
		animation: slide 20s linear infinite;
	}

	.slider6-items img {
		width: 150px;
		height: auto;
		margin: 0 15px;
		object-fit: contain;
	}

	/* Animation */
	@keyframes slide {
		from {
			transform: translateX(0);
		}

		to {
			transform: translateX(-50%);
		}
	}

	/* Mobile Responsive */
	@media (max-width: 768px) {
		.slider6-items img {
			width: 100px;
			margin: 0 10px;
		}
	}

	@media (max-width: 480px) {
		.slider6-items img {
			width: 80px;
			margin: 0 5px;
		}
	}
</style>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="css/style.css">
	<!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"> -->
	<style>
		.footer-col .social-links {
			display: flex;
			/* Arrange icons in one row */
			flex-wrap: wrap;
			/* Allow wrapping if needed */
			gap: 10px;
			/* Space between icons */
		}

		.footer-col .social-links a {
			display: inline-flex;
			/* Keeps them inline, flex inside for centering */
			justify-content: center;
			align-items: center;
			height: 40px;
			width: 40px;
			background-color: white;
			border-radius: 50%;
			color: #252525;
			transition: all 0.5s ease;
			font-size: 18px;
		}

		.footer-col .social-links a:hover {
			color: white;
			background-color: green;
		}

		#footer {
			background-color: #252525;
			color: white;
		}

		.whatsapp-float {
			position: fixed;
			bottom: 20px;
			right: 20px;
			z-index: 9999;
			background-color: #25ae25;
			height: 60px;
			width: 60px;
			color: white;
			border-radius: 50%;
			text-align: center;
			font-size: 32px;
			display: flex;
			justify-content: center;
			align-items: center;
			box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.5);
			transition: transform .3s;
		}

		.whatsapp-float:hover {
			transform: scale(1.1);
			color: #252525;
		}
	</style>
</head>

<body>
	<div class="icon-bar">
		<a href="https://api.whatsapp.com/send?phone=918925969888" class="whatsapp-float" target="_blank">
			<ion-icon name="logo-whatsapp"></ion-icon>
		</a>
	</div>


	<footer class="ftco-footer ftco-section" id="footer">
		<div class="container">
			<div class="row">
				<div class="mouse">
					<a href="#" class="mouse-icon">
						<div class="mouse-wheel"><span class="ion-ios-arrow-up"></span></div>
					</a>
				</div>
			</div>
			<div class="row mb-5">
				<div class="col-md">
					<div class="ftco-footer-widget mb-4">
						<h2 class="ftco-heading-2" style="color: #fff;">Valluvam</h2>
						<p>"Discover purity and tradition with Valuvam – your trusted source for cold-pressed oils, premium spices, dry fruits, nuts, and wholesome millets.
							Naturally sourced, carefully packed, and delivered fresh to your doorstep."</p>
						<div class="footer-col">
							<h4 style="color:#fff">follow us</h4>
							<div class="social-links">
								<a href="https://www.facebook.com/valluvamproducts/"><ion-icon name="logo-facebook"></ion-icon></a>
								<a href="#"><ion-icon name="logo-twitter"></ion-icon></a>
								<a href="https://www.instagram.com/valluvam_agro_products/"><ion-icon name="logo-instagram"></ion-icon></a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md" style="color:#fff">
					<div class="ftco-footer-widget mb-4 ml-md-5" style="color:#fff">
						<h2 class="ftco-heading-2" style="color: #fff;">Menu</h2>
						<ul class="list-unstyled" style="color:#fff">
							<li><a href="index.php">Home</a></li>
							<li><a href="shop.php">shop</a></li>
							<li><a href="about.php">About</a></li>
							<li><a href="blog.php">Blog</a></li>
							<li><a href="contact.php">Contact</a></li>
						</ul>
					</div>
				</div>
				<div class="col-md-4" style="color:#fff">
					<div class="ftco-footer-widget mb-4" style="color:#fff">
						<h2 class="ftco-heading-2" style="color:#fff">Help</h2>
						<div class="d-flex" style="color:#fff">
							<ul class="list-unstyled mr-l-5 pr-l-3 mr-4" style="color:#fff">
								<li><a href="#">Shipping Information</a></li>
								<li><a href="return.php">Returns and Exchange</a></li>
								<li><a href="checkout.php">order status</a></li>
								<li><a href="#">payment options</a></li>
								<li><a href="term.php">Term & Conditions</a></li>
								<li><a href="privacy.php">Privacy Policy</a></li>
							</ul>
						</div>
					</div>
				</div>
				<!-- <div class="col-md-4" style="color:#fff">
					<div class="ftco-footer-widget mb-4" style="color:#fff">
						<h2 class="ftco-heading-2" style="color:greem">Our Brands</h2>
						<div class="d-flex" style="color:#fff">
							<ul class="list-unstyled mr-l-5 pr-l-3 mr-4" style="color:#fff">
								<li><img src="" alt="brand1" width="60px"  height="60px"></li>
								<li><img src="" alt="brand2" width="60px"  height="60px"></li>
								<li><img src="" alt="brand3" width="60px"  height="60px"></li>
								<li><img src="" alt="brand4" width="60px"  height="60px"></li>
								<li><img src="" alt="brand5"width="60px"  height="60px"></li>
							</ul>
						</div>
					</div>
				</div> -->
				<div class="col-md">
					<div class="ftco-footer-widget mb-4" style="color:#fff">
						<h2 class="ftco-heading-2" style="color:#fff">Have a Questions?</h2>
						<div class="block-23 mb-3">
							<ul style="color:#fff">
								<li><span class="icon icon-map-marker"></span><span class="text">No 17 , Kovalan street, 2nd main road, Uthandi kanathur, Chennai 600119.

									</span></li>
								<li><a href="#"><span class="icon icon-phone"></span><span class="text">+918925969888</span></a></li>
								<li><a href="#"><span class="icon icon-envelope"></span><span class="text"> info.thefarmersfactory@<br>gmail.com
										</span></a></li>
							</ul>
						</div>
					</div>
				</div>

			</div>
		</div>
	</footer>
	<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
	<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>

</html>