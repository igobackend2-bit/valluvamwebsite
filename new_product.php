<?php
// SECURITY FIX: product editor was open to anyone. Admin login required.
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
	header('Location: admin/login.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Products — Valluvam Admin</title>
	<link rel="icon" href="images/logo.png">
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="assets/css/new-product-admin.css">
</head>

<body>

	<header class="npa-topbar">
		<div class="npa-brand">
			<img src="images/logo.png" alt="">
			<div>
				<strong>Valluvam</strong>
				<span>Admin</span>
			</div>
		</div>
		<a class="npa-back" href="admin/products.php"><i class="bi bi-arrow-left"></i> Back to admin</a>
	</header>

	<main class="npa-main">
		<div class="npa-head">
			<div>
				<h1>Products</h1>
				<p>Add and edit catalog items</p>
			</div>
			<button type="button" class="btn adm-btn-primary" onclick="$('#product_form')[0].reset(); $('#productModal').modal('show'); $('#product_id').val('')">
				<i class="bi bi-plus-lg"></i> New product
			</button>
		</div>

		<div class="npa-card">
			<table id="productTable" class="display table">
				<thead>
					<tr>
						<th>S.No</th>
						<th>Name</th>
						<th>Price</th>
						<th>Discount Price</th>
						<th>Category</th>
						<th>Quantity</th>
						<th>Rating</th>
						<th>Image</th>
						<th>Description</th>
						<th>Benefits</th>
						<th>Actions</th>
					</tr>
				</thead>
			</table>
		</div>
	</main>

	<!-- Modal Form -->
	<div class="modal fade" id="productModal" tabindex="-1">
		<div class="modal-dialog">
			<form id="product_form" enctype="multipart/form-data">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Product details</h5>
					</div>
					<div class="modal-body">
						<input type="hidden" id="product_id" name="id">

						<label for="product_name" class="npa-field-label">Name</label>
						<input type="text" name="product_name" id="product_name" class="form-control mb-3" placeholder="e.g. Premium Almonds" required>

						<label for="price" class="npa-field-label">Price (₹)</label>
						<input type="text" name="price" id="price" class="form-control mb-3" placeholder="650.00" required>

						<label for="dis_price" class="npa-field-label">Discount price (₹, optional)</label>
						<input type="text" name="dis_price" id="dis_price" class="form-control mb-3" placeholder="549.00">

						<label for="category" class="npa-field-label">Category</label>
						<select name="category" id="category" class="form-control mb-3" required>
							<option value="">Select a category</option>
						</select>

						<label for="quantity" class="npa-field-label">Quantity</label>
						<input type="text" name="quantity" id="quantity" class="form-control mb-3" placeholder="500g or 750ml">

						<label for="rating" class="npa-field-label">Rating (1–5, optional)</label>
						<input type="text" name="rating" id="rating" class="form-control mb-3" placeholder="4.5">

						<label for="image" class="npa-field-label">Product image</label>
						<input type="file" name="image" id="image" class="form-control mb-3">

						<label for="description" class="npa-field-label">Description</label>
						<textarea name="description" id="description" class="form-control mb-3" placeholder="What makes this product worth buying"></textarea>

						<label for="benefits" class="npa-field-label">Benefits</label>
						<textarea name="benefits" id="benefits" class="form-control mb-0" placeholder="Key nutritional or usage benefits"></textarea>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-success">Save product</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
	<script src="assets/js/new_product/new_product.js"></script>
	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>
