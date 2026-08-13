$(document).ready(function () {
	addProduct();
	fetchProduct();
	editProduct();
	deleteProduct();
	getCategroy();
});

function getCategroy(){
	$.ajax({
        url: 'assets/db_query/new_product/new_product_query.php?action=get_category',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let options = '<option value="">--Category--</option>';
                res.categories.forEach(function (cat) {
                    options += `<option value="${cat.category_name}">${cat.category_name}</option>`;
                });
                $('#category').html(options);
            } else {
                SwalHelper.error('Load Failed', 'Failed to load categories: ' + (res.message || 'Unknown error'));
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', error);
            SwalHelper.ecommerce.serverError();
        }
    });
}

function addProduct() {
	$('#product_form').on('submit', function (e) {
		e.preventDefault();

		const quantity = $('#quantity').val().trim();
		const rating = $('#rating').val().trim();
		const category = $('#category').val().trim();

		// Basic validation
		if (category === '') {
			Swal.fire('Validation Error', 'Please select a product category.', 'warning');
			return;
		}

		if (!/^(\d+)(g|ml)$/i.test(quantity)) {
			Swal.fire('Validation Error', 'Quantity must end with g or ml (e.g., "100g", "750ml").', 'warning');
			return;
		}
		
		let qtyValue = parseInt(quantity);
		let unit = quantity.toLowerCase().replace(/[0-9]/g, ''); // Extract g or ml
		
		if (unit === 'g') {
			if (qtyValue < 50 || qtyValue > 10000) { // 50g to 10000g
				Swal.fire('Validation Error', 'Quantity in grams must be between 50g and 10000g (10kg).', 'warning');
				return;
			}
		} else if (unit === 'ml') {
			if (qtyValue < 500 || qtyValue > 1000) { // 500ml to 1000ml
				Swal.fire('Validation Error', 'Quantity in ml must be between 500ml and 1000ml.', 'warning');
				return;
			}
		}		
		
		const ratingVal = parseFloat(rating);
		if (isNaN(ratingVal) || ratingVal < 1 || ratingVal > 5) {
			Swal.fire('Validation Error', 'Rating must be a number between 1 and 5.', 'warning');
			return;
		}

		// Proceed with AJAX if validation passes
		let formData = new FormData(this);

		$('#product_form').on('submit', function (e) {
			e.preventDefault();
			let formData = new FormData(this);

			$.ajax({
				url: 'assets/db_query/new_product/new_product_query.php?action=add_product', // Make sure this matches your PHP script
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function (res) {
					if (res.status === 'success') {
						Swal.fire('Success!', res.message, 'success');
						$('#productModal').modal('hide');
						$('#product_form')[0].reset();
						$('#productTable').DataTable().ajax.reload();
					} else {
						Swal.fire('Error!', res.message || 'Unknown error', 'error');
					}
				},
				error: function (xhr, status, err) {
					console.error(xhr.responseText);
					Swal.fire('Error!', 'Something went wrong', 'error');
				}
							});
		});

	});
}

function fetchProduct() {
	const table = $('#productTable').DataTable({
		ajax: 'assets/db_query/new_product/new_product_query.php?action=fetch_products',
		columns: [{
			data: 'id'
		},
		{
			data: 'product_name'
		},
		{
			data: 'price'
		},
		{
			data: 'dis_price'
		},
		{
			data: 'category'
		},
		{
			data: 'quantity'
		},
		{
			data: 'rating'
		},
		{
			data: 'image',
			render: function(data) {
				if (data) {
					// Display image thumbnail
					return `<img src="assets/uploads/${data}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;" alt="Product Image">`;
				}
				return 'No Image';
			}
		},
		{
			data: 'description'
		},
		{
			data: 'benefits'
		},
		{
			data: null,
			render: function (data) {
				return `
             <button class="btn btn-sm btn-dark edit-btn" data-id="${data.id}"><i class="bi bi-pencil-square"></i></button>
             <button class="btn btn-sm btn-danger delete-btn" data-id="${data.id}"><i class="bi bi-trash3-fill"></i></button>
          `;
			}
		}
		]
	});
}

function editProduct() {
	$('#productTable').on('click', '.edit-btn', function () {
		const id = $(this).data('id');
		$.getJSON(`assets/db_query/new_product/new_product_query.php?action=fetch_products&id=${id}`, function (data) {
			$('#product_id').val(data.id);
			$('[name="product_name"]').val(data.product_name);
			$('[name="price"]').val(data.price);
			$('[name="dis_price"]').val(data.dis_price);
			$('[name="category"]').val(data.category);
			$('[name="quantity"]').val(data.quantity);
			$('[name="rating"]').val(data.rating);
			$('[name="description"]').val(data.description);
			$('[name="benefits"]').val(data.benefits);
			// Image won't be previewed in file input; you can preview separately if needed
			$('#productModal').modal('show');
		});
	});

}
function deleteProduct() {

	// Delete
	$('#productTable').on('click', '.delete-btn', function () {
		if (Swal.fire('Are you sure you want to delete this product?')) {
			const id = $(this).data('id');
			$.post('assets/db_query/new_product/new_product_query.php?action=delete_products', {
				id
			}, function (res) {
				Swal.fire(res.message);
				table.ajax.reload();
			}, 'json');
		}
	});
}
$(document).ready(function () {
	// Create a modal instance with options to disable outside close
	const modal = new bootstrap.Modal(document.getElementById('productModal'), {
		backdrop: 'static',
		keyboard: false
	});
});

// New Product button opens modal
$('.btn-primary').on('click', function () {
	$('#product_form')[0].reset();
	$('#product_id').val('');
	modal.show();
});

// Handle form submission (Save button)
$('#product_form').on('submit', function (e) {
	e.preventDefault();

	let quantity = $('#quantity').val().trim();
	let rating = $('#rating').val().trim();
	let category = $('#category').val().trim();

	if (category === '') {
		Swal.fire('Validation Error', 'Please enter a product category.', 'warning');
		return;
	}

	if (!/^\d+g$/i.test(quantity) || parseInt(quantity) < 50) {
		Swal.fire('Validation Error', 'Quantity must be at least 50g (e.g., "100g").', 'warning');
		return;
	}

	const ratingVal = parseFloat(rating);
	if (isNaN(ratingVal) || ratingVal < 1 || ratingVal > 5) {
		Swal.fire('Validation Error', 'Rating must be a number between 1 and 5.', 'warning');
		return;
	}
});


