<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Valluvam</title>
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>

<body class="p-4">

	<h3>Product List</h3>
	<button class="btn btn-primary mb-3" onclick="$('#product_form')[0].reset(); $('#productModal').modal('show'); $('#product_id').val('')">+ New Product</button>

	<table id="productTable" class="display table table-bordered">
		<thead>
			<tr>
				<th>S.No</th>
				<th>Name</th>
				<th>Price</th>
				<th>Discount Price</th>
				<th>Category</th>
				<th>Quantity</th>
				<th>Rating</th>
				<th>Images</th>
				<th>Description</th>
				<th>Benefits</th>
				<th>Actions</th>

			</tr>
		</thead>
	</table>

	<!-- Modal Form -->
	<div class="modal fade" id="productModal" tabindex="-1">
		<div class="modal-dialog">
			<form id="product_form" enctype="multipart/form-data">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Product Form</h5>
					</div>
					<div class="modal-body">
						<input type="hidden" id="product_id" name="id">
						<label for="name" class="form-label"></label>
						<input type="text" name="product_name" id="product_name" class="form-control mb-2" placeholder="Name" required>
						<label for="price" class="form-label"></label>
						<input type="text" name="price" id="price" class="form-control mb-2" placeholder="Price" required>
						<label for="discount" class="form-label"></label>
						<input type="text" name="dis_price" id="dis_price" class="form-control mb-2" placeholder="Discount Price">
						<label for="category" class="form-label"></label>
						<!-- <input type="text" name="category" id="category"> -->
						<select name="category" id="category" class="form-control mb-2" required>
							<option value="">--Category--</option>
						</select>

						<label for="quantity" class="form-label"></label>
						<input type="text" name="quantity" id="quantity" class="form-control mb-2" placeholder="Quantity" required>
						<label for="rating" class="form-label"></label>
						<input type="text" name="rating" id="rating" class="form-control mb-2" placeholder="Rating">
						<label for="image" class="form-label"></label>
						<input type="file" name="image" id="image" class="form-control mb-2">
						<label for="description" class="description"></label>
						<textarea name="description" id="description" class="form-control mb-2" placeholder="Description"></textarea>
						<label for="benefits" class="form-label"></label>
						<textarea name="benefits" id="benefits" class="form-control mb-2" placeholder="Benefits"></textarea>
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn btn-success">Save</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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