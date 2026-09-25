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

		// Admin can enter whatever quantity/unit fits the product — grams,
		// kilograms, millilitres or litres, any positive amount (a decimal
		// like "1.5kg" or "0.5L" is fine too). No fixed min/max range is
		// enforced anymore; only the shape (a positive number + a real unit)
		// is checked so the field can't be left garbled or empty.
		if (!/^(\d+(?:\.\d+)?)\s*(g|kg|ml|l)$/i.test(quantity)) {
			Swal.fire('Validation Error', 'Enter a quantity with its unit — e.g. "500g", "1kg", "750ml" or "1L".', 'warning');
			return;
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
		openEditModal($(this).data('id'));
	});

	// Opened from admin/products.php "Edit" link: new_product.php?id=123
	const idFromUrl = new URLSearchParams(window.location.search).get('id');
	if (idFromUrl) {
		openEditModal(idFromUrl);
	}
}

function openEditModal(id) {
	$.getJSON(`assets/db_query/new_product/new_product_query.php?action=fetch_products&id=${encodeURIComponent(id)}`, function (data) {
		if (!data || !data.id) {
			Swal.fire('Product not found');
			return;
		}
		$('#product_id').val(data.id);
		$('[name="product_name"]').val(data.product_name);
		$('[name="price"]').val(data.price);
		$('[name="dis_price"]').val(data.dis_price);
		// Category options load asynchronously; add the saved value if it isn't there yet
		const $cat = $('[name="category"]');
		if (data.category && $cat.find('option').filter(function () { return this.value === data.category; }).length === 0) {
			$cat.append($('<option>').val(data.category).text(data.category));
		}
		$cat.val(data.category);
		$('[name="quantity"]').val(data.quantity);
		$('[name="rating"]').val(data.rating);
		$('[name="description"]').val(data.description);
		$('[name="benefits"]').val(data.benefits);
		// Image won't be previewed in file input; you can preview separately if needed
		$('#productModal').modal('show');
	});
}

function deleteProduct() {

	// Delete
	$('#productTable').on('click', '.delete-btn', function () {
		const id = $(this).data('id');
		Swal.fire({
			title: 'Delete this product?',
			text: "This can't be undone.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#a8442f',
			confirmButtonText: 'Delete'
		}).then(function (result) {
			if (!result.isConfirmed) return;
			$.post('assets/db_query/new_product/new_product_query.php?action=delete_products', {
				id
			}, function (res) {
				Swal.fire(res.message);
				table.ajax.reload();
			}, 'json');
		});
	});
}

// Category -> default unit auto-fill. Liquid categories (oils, ghee, honey)
// default to litres, everything else (nuts, dry fruits, spices, millets,
// rice, pulses, etc.) defaults to grams. Only fills in a starting value —
// the admin can still type any quantity/unit they want over it.
const LIQUID_CATEGORY_HINT = /(oil|ghee|honey|milk|syrup|juice)/i;
function suggestQuantityForCategory(category) {
	if (!category) return;
	const $qty = $('#quantity');
	if ($qty.val().trim() !== '') return; // don't overwrite something the admin already typed
	$qty.val(LIQUID_CATEGORY_HINT.test(category) ? '1L' : '500g');
}

$(document).on('change', '#category', function () {
	suggestQuantityForCategory($(this).val());
});


