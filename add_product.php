<?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
include 'header.php' ?>

<body class="goto-here">
	<link rel="stylesheet" href="css/login.css">

	<div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
		<div class="container">
			<div class="row no-gutters slider-text align-items-center justify-content-center">
				<div class="col-md-9 ftco-animate text-center">
					<p class="breadcrumbs"><span class="mr-2"><a href="index.html">Home</a></span> <span>Add Products</span></p>
					<h1 class="mb-0 bread">Add Products</h1>
				</div>
			</div>
		</div>
	</div>
	<!-- <div class="form-modal">
		<div id="login-form">
			<form>
				<input type="text" placeholder="Enter email or username" />
				<input type="password" placeholder="Enter password" />
				<input type="email" placeholder="Enter your email" />
				<input type="text" placeholder="Enter your contact number" />
				<input type="text" placeholder="Choose username" />
				<input type="password" placeholder="Create password" />
				<button type="button" class="btn signup">create account</button>
				<p>Clicking <strong>create account</strong> means that you are agree to our <a href="javascript:void(0)">terms of services</a>.</p>
				<hr />
			</form>
		</div>

	</div> -->
	<div class="container mt-5">
		<div class="row">
			<div class="d-flex justify-content-between">
				<h5>Product Details</h5>
				<a href="new_product.php" class="btn btn-primary"> <i class="bi bi-plus-circle"></i>New Product</a>

			</div>
			<div class="col-md-12 table-responsive mt-3">
				<table class="table table-bordered">
					<thead>
						<th>S.No</th>
						<th>Product name</th>
						<th>Price</th>
						<th>Description</th>
						<th>Category</th>
						<th>Quantity</th>
						<th>Images</th>
						<th>Benefits</th>
						<th>Rating</th>
						<th>action</th>
					</thead>
					<tbody>
						<tr>
							<td>1.</td>
							<td>almond</td>
							<td>price</td>
							<td>Lorem ipsum dolor sit amet consectetur adipisicing elit. Molestias mollitia earum cupiditate. Non ducimus veniam nostrum fugit
								placeat minima porro dolorum minus nesciunt? Rerum obcaecati sequi cupiditate minima quas assumenda?</td>
							<td>nuts</td>
							<td>50g</td>
							<td><img src="images/products-18.jpg" alt="product" style="width: 50px; height:50px; object-fit:contain" /></td>
							<td>Lorem ipsum dolor sit amet consectetur adipisicing elit. Eos atque ut ipsa labore provident ea! Deleniti animi voluptatem, ut
								perspiciatis consequuntur voluptas corrupti ex eveniet praesentium temporibus, ipsum veniam aspernatur!</td>
							<td>5</td>
							<td class="d-flex" style="gap: 2px;">
								<a href="edit.php" class="btn btn-dark btn-sm"><i class="bi bi-pencil-square"></i></a>
								<a href="#" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a>
							</td>

						</tr>
					</tbody>

				</table>
			</div>
		</div>
	</div>
	<?php include 'footer.php' ?>
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
	<script src="js/main.js"></script> -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

	<!-- <script>
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
	</script> -->

</body>

</html>