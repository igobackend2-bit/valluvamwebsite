// ============================================================
// "Sizes for this product" section in the admin product form (new_product.php)
// Added 30 Sep 2026.
//
// Admin adds / edits / deletes the sizes this product is sold in, each with its
// own price and discount price (e.g. 100g Rs.120, 250g Rs.280, 1kg Rs.990).
// Only these sizes appear in the "Size" row on the website product page, and
// the cart charges the chosen size's own price - no cart/checkout change.
// Backend: assets/db_query/admin/product_sizes.php
// ============================================================
(function ($) {
	var API = 'assets/db_query/admin/product_sizes.php';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
		});
	}
	function money(v) {
		var n = parseFloat(v);
		return isFinite(n) && n > 0 ? '₹' + n.toFixed(2) : '—';
	}
	// Open SweetAlert inside the Bootstrap modal so typing isn't stolen by its focus trap
	function psSwal(opts) {
		if (typeof opts !== 'object') opts = { title: opts };
		opts.target = document.getElementById('productModal') || 'body';
		return Swal.fire(opts);
	}
	function toast(msg) {
		psSwal({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 1600 });
	}
	function currentId() { return ($('#product_id').val() || '').trim(); }

	function buildUi() {
		if ($('#ps-wrap').length) return;
		var $anchor = $('#qp-wrap').length ? $('#qp-wrap') : $('#quantity');
		if (!$anchor.length) return;
		$anchor.after(
			'<div id="ps-wrap" class="mb-3 p-2" style="border:1px solid #e5e1d8;border-radius:8px;">' +
				'<div class="d-flex justify-content-between align-items-center mb-2">' +
					'<strong style="font-size:14px;">Sizes for this product</strong>' +
					'<button type="button" class="btn btn-sm btn-success" id="ps-add"><i class="bi bi-plus-lg"></i> Add size</button>' +
				'</div>' +
				'<div id="ps-body" class="small text-muted">Save the product first, then add its sizes.</div>' +
				'<small class="text-muted d-block mt-1">Only these sizes show on the website product page. Each size has its own price and discount price.</small>' +
			'</div>'
		);
	}

	function render(sizes) {
		if (!sizes || !sizes.length) { $('#ps-body').html('No sizes yet.'); return; }
		var rows = sizes.map(function (s) {
			var actions = s.is_current
				? '<span class="text-muted">This form</span>'
				: '<button type="button" class="btn btn-sm btn-outline-secondary ps-edit" data-id="' + s.id + '" data-q="' + esc(s.quantity) + '" data-p="' + esc(s.price) + '" data-d="' + esc(s.dis_price || '') + '" title="Edit"><i class="bi bi-pencil"></i></button> ' +
				  '<button type="button" class="btn btn-sm btn-outline-danger ps-del" data-id="' + s.id + '" data-q="' + esc(s.quantity) + '" title="Delete"><i class="bi bi-trash3"></i></button>';
			return '<tr><td><strong>' + esc(s.quantity) + '</strong></td><td>' + money(s.price) + '</td><td>' + money(s.dis_price) +
				'</td><td>' + (s.stock === null || s.stock === undefined ? '—' : esc(s.stock)) + '</td><td class="text-end" style="white-space:nowrap;">' + actions + '</td></tr>';
		}).join('');
		$('#ps-body').html('<table class="table table-sm mb-0" style="font-size:13px;"><thead><tr><th>Size</th><th>Price</th><th>Discount</th><th>Stock</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>');
	}

	function load() {
		var id = currentId();
		$('#ps-add').prop('disabled', !id);
		if (!id) { $('#ps-body').html('Save the product first, then add its sizes.'); return; }
		$('#ps-body').html('Loading sizes…');
		$.getJSON(API, { action: 'list', id: id })
			.done(function (r) { r && r.status === 'success' ? render(r.sizes) : $('#ps-body').html(esc((r && r.message) || 'Could not load sizes.')); })
			.fail(function () { $('#ps-body').html('Could not load sizes.'); });
	}

	function sizeForm(title, q, p, d) {
		var m = String(q || '').match(/^(\d+(?:\.\d+)?)\s*(g|kg|ml|l)$/i);
		var num = m ? m[1] : '', unit = m ? (m[2].toLowerCase() === 'l' ? 'L' : m[2].toLowerCase()) : 'g';
		var opt = function (u) { return '<option value="' + u + '"' + (u === unit ? ' selected' : '') + '>' + u + '</option>'; };
		return psSwal({
			title: title,
			html:
				'<div style="text-align:left;font-size:14px;">' +
				'<label class="form-label mb-1">Size</label>' +
				'<div class="d-flex gap-2 mb-2"><input id="ps-num" type="number" min="0" step="any" class="form-control" placeholder="e.g. 250" value="' + esc(num) + '">' +
				'<select id="ps-unit" class="form-control" style="max-width:110px;">' + ['g', 'kg', 'ml', 'L'].map(opt).join('') + '</select></div>' +
				'<label class="form-label mb-1">Price (₹)</label><input id="ps-price" type="number" min="0" step="any" class="form-control mb-2" value="' + esc(p || '') + '">' +
				'<label class="form-label mb-1">Discount price (₹, optional)</label><input id="ps-dis" type="number" min="0" step="any" class="form-control" value="' + esc(d || '') + '">' +
				'</div>',
			showCancelButton: true,
			confirmButtonText: 'Save size',
			confirmButtonColor: '#1c5034',
			focusConfirm: false,
			preConfirm: function () {
				var n = parseFloat($('#ps-num').val()), pr = parseFloat($('#ps-price').val()), di = $('#ps-dis').val().trim();
				if (!isFinite(n) || n <= 0) { Swal.showValidationMessage('Enter the size, e.g. 250'); return false; }
				if (!isFinite(pr) || pr <= 0) { Swal.showValidationMessage('Enter a valid price'); return false; }
				if (di !== '' && (!isFinite(parseFloat(di)) || parseFloat(di) > pr)) { Swal.showValidationMessage('Discount price must be a number not more than the price'); return false; }
				return { quantity: n + $('#ps-unit').val(), price: pr, dis_price: di };
			}
		});
	}

	function post(data, okMsg) {
		data.id = currentId();
		$.post(API, data, null, 'json')
			.done(function (r) {
				if (r && r.status === 'success') { render(r.sizes); toast(okMsg || r.message); refreshTable(); }
				else psSwal({ title: 'Could not save', text: (r && r.message) || 'Please try again.', icon: 'error' });
			})
			.fail(function () { psSwal({ title: 'Could not save', text: 'The server did not respond.', icon: 'error' }); });
	}
	function refreshTable() {
		try { $('#productTable').DataTable().ajax.reload(null, false); } catch (e) { /* table not ready */ }
	}

	$(document).on('click', '#ps-add', function () {
		sizeForm('Add a size').then(function (r) {
			if (r.isConfirmed) post({ action: 'save', size_id: '', quantity: r.value.quantity, price: r.value.price, dis_price: r.value.dis_price });
		});
	});
	$(document).on('click', '.ps-edit', function () {
		var $b = $(this);
		sizeForm('Edit size', $b.attr('data-q'), $b.attr('data-p'), $b.attr('data-d')).then(function (r) {
			if (r.isConfirmed) post({ action: 'save', size_id: $b.attr('data-id'), quantity: r.value.quantity, price: r.value.price, dis_price: r.value.dis_price });
		});
	});
	$(document).on('click', '.ps-del', function () {
		var $b = $(this);
		psSwal({
			title: 'Delete the ' + esc($b.attr('data-q')) + ' size?',
			text: 'It will no longer be sold on the website.',
			icon: 'warning', showCancelButton: true, confirmButtonColor: '#a8442f', confirmButtonText: 'Delete'
		}).then(function (r) {
			if (r.isConfirmed) post({ action: 'delete', size_id: $b.attr('data-id') }, 'Size deleted');
		});
	});

	$(document).on('shown.bs.modal', '#productModal', load);
	$(function () { buildUi(); });
})(jQuery);
