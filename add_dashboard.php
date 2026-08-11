<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

</head>

<body class="goto-here">
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
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>

</html>


</html>	