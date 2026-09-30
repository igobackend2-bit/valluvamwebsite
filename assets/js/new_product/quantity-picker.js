// ============================================================
// Quantity picker for the admin product form (new_product.php)
// Added 30 Sep 2026.
//
// Replaces typing "500g" by hand with:  Unit [g | kg | ml | L]  +  Size [dropdown]
// plus Add / Edit / Delete for the size list of the chosen unit.
//
// - The existing #quantity input is kept (just hidden). This script only writes the
//   final value into it (e.g. "200g", "5kg", "750ml", "10L"), so the existing
//   validation, save endpoint and the main website keep working exactly as before.
// - The size lists are stored in the existing admin_settings table (key
//   "quantity_presets_v1") through the existing admin endpoints
//   get_settings.php / update_setting.php. No database change needed.
// - If this script fails to load, the plain Quantity text box still works.
// ============================================================
(function ($) {
	var SETTING_KEY = 'quantity_presets_v1';
	var UNITS = [
		{ key: 'g', label: 'Grams (g)' },
		{ key: 'kg', label: 'Kilograms (kg)' },
		{ key: 'ml', label: 'Millilitres (ml)' },
		{ key: 'L', label: 'Litres (L)' }
	];
	var DEFAULT_PRESETS = {
		g: [50, 100, 200, 250, 500],
		kg: [1, 2, 5, 10, 25],
		ml: [100, 200, 250, 500],
		L: [1, 2, 5, 10]
	};
	var presets = JSON.parse(JSON.stringify(DEFAULT_PRESETS));

	function normUnit(u) {
		u = String(u || '').toLowerCase();
		if (u === 'l') return 'L';
		return (u === 'g' || u === 'kg' || u === 'ml') ? u : '';
	}

	function parseQty(q) {
		var m = String(q || '').trim().match(/^(\d+(?:\.\d+)?)\s*(g|kg|ml|l)$/i);
		return m ? { num: parseFloat(m[1]), unit: normUnit(m[2]) } : null;
	}

	function cleanList(list) {
		var seen = {}, out = [];
		(list || []).forEach(function (n) {
			n = parseFloat(n);
			if (isFinite(n) && n > 0 && !seen[n]) { seen[n] = true; out.push(n); }
		});
		return out.sort(function (a, b) { return a - b; });
	}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
		});
	}

	// ---------- UI ----------
	function buildUi() {
		var $qty = $('#quantity');
		if (!$qty.length || $('#qp-wrap').length) return false;
		$qty.hide();
		var unitOpts = '<option value="">Select unit</option>' + UNITS.map(function (u) {
			return '<option value="' + u.key + '">' + u.label + '</option>';
		}).join('');
		$qty.after(
			'<div id="qp-wrap" class="mb-3">' +
				'<div class="d-flex gap-2 mb-2">' +
					'<select id="qp-unit" class="form-control" aria-label="Unit">' + unitOpts + '</select>' +
					'<select id="qp-size" class="form-control" aria-label="Size" disabled><option value="">Select size</option></select>' +
				'</div>' +
				'<div class="d-flex gap-2 flex-wrap">' +
					'<button type="button" class="btn btn-sm btn-outline-success" id="qp-add"><i class="bi bi-plus-lg"></i> Add size</button>' +
					'<button type="button" class="btn btn-sm btn-outline-secondary" id="qp-edit"><i class="bi bi-pencil"></i> Edit size</button>' +
					'<button type="button" class="btn btn-sm btn-outline-danger" id="qp-delete"><i class="bi bi-trash3"></i> Delete size</button>' +
				'</div>' +
				'<small class="text-muted d-block mt-1">Saved as: <strong id="qp-preview">—</strong></small>' +
			'</div>'
		);
		return true;
	}

	function renderSizes(unit, selectedNum) {
		var $size = $('#qp-size');
		if (!unit) {
			$size.html('<option value="">Select size</option>').prop('disabled', true);
			return;
		}
		var list = (presets[unit] || []).slice();
		// Keep a product's saved size visible even if it isn't in the list
		if (selectedNum != null && list.indexOf(selectedNum) === -1) list = cleanList(list.concat([selectedNum]));
		var html = '<option value="">Select size</option>' + list.map(function (n) {
			return '<option value="' + n + '"' + (n === selectedNum ? ' selected' : '') + '>' + n + ' ' + unit + '</option>';
		}).join('');
		$size.html(html).prop('disabled', false);
	}

	function writeQuantity() {
		var unit = $('#qp-unit').val();
		var num = $('#qp-size').val();
		var value = (unit && num) ? (num + unit) : '';
		$('#quantity').val(value);
		$('#qp-preview').text(value || '—');
	}

	// Read the hidden #quantity (set by edit / reset / category auto-fill) into the dropdowns
	function syncFromQuantity() {
		var parsed = parseQty($('#quantity').val());
		if (parsed) {
			$('#qp-unit').val(parsed.unit);
			renderSizes(parsed.unit, parsed.num);
		} else {
			$('#qp-unit').val('');
			renderSizes('', null);
		}
		$('#qp-preview').text($('#quantity').val() || '—');
	}

	// ---------- storage (existing admin_settings endpoints) ----------
	function loadPresets(done) {
		$.getJSON('assets/db_query/admin/get_settings.php').done(function (res) {
			if (res && res.status === 'success' && Array.isArray(res.settings)) {
				res.settings.forEach(function (s) {
					if (s.setting_key === SETTING_KEY) {
						try {
							var saved = JSON.parse(s.setting_value);
							UNITS.forEach(function (u) {
								if (saved && Array.isArray(saved[u.key])) presets[u.key] = cleanList(saved[u.key]);
							});
						} catch (e) { /* bad JSON - keep defaults */ }
					}
				});
			}
		}).always(function () { if (done) done(); });
	}

	function savePresets(onOk) {
		$.post('assets/db_query/admin/update_setting.php', { key: SETTING_KEY, value: JSON.stringify(presets) }, null, 'json')
			.done(function (res) {
				if (res && res.status === 'success') {
					if (onOk) onOk();
				} else {
					qpSwal('Could not save sizes', (res && res.message) || 'Please try again.', 'error');
				}
			})
			.fail(function () { qpSwal('Could not save sizes', 'The server did not respond.', 'error'); });
	}

	function askNumber(title, unit, current) {
		return qpSwal({
			title: title,
			input: 'number',
			inputValue: current != null ? current : '',
			inputLabel: 'Size in ' + unit,
			inputAttributes: { min: '0', step: 'any' },
			showCancelButton: true,
			confirmButtonColor: '#1c5034',
			inputValidator: function (v) {
				var n = parseFloat(v);
				if (!isFinite(n) || n <= 0) return 'Enter a number greater than 0';
			}
		});
	}

	// Open SweetAlert inside the Bootstrap product modal. Otherwise the modal's
	// focus trap pulls the cursor back into the form and typing goes to the
	// wrong field.
	function qpSwal(a, b, c) {
		var opts = (typeof a === 'object') ? a : { title: a, text: b || '', icon: c };
		opts.target = document.getElementById('productModal') || 'body';
		return Swal.fire(opts);
	}

	function needUnit() {
		var unit = $('#qp-unit').val();
		if (!unit) qpSwal('Select a unit first', 'Choose g, kg, ml or L.', 'info');
		return unit;
	}

	// ---------- events ----------
	$(document).on('change', '#qp-unit', function () {
		renderSizes($(this).val(), null);
		writeQuantity();
	});
	$(document).on('change', '#qp-size', writeQuantity);

	$(document).on('click', '#qp-add', function () {
		var unit = needUnit(); if (!unit) return;
		askNumber('Add a size', unit).then(function (r) {
			if (!r.isConfirmed) return;
			var n = parseFloat(r.value);
			presets[unit] = cleanList((presets[unit] || []).concat([n]));
			savePresets(function () {
				renderSizes(unit, n); writeQuantity();
				qpSwal({ toast: true, position: 'top-end', icon: 'success', title: n + ' ' + unit + ' added', showConfirmButton: false, timer: 1600 });
			});
		});
	});

	$(document).on('click', '#qp-edit', function () {
		var unit = needUnit(); if (!unit) return;
		var cur = parseFloat($('#qp-size').val());
		if (!isFinite(cur)) { qpSwal('Select a size to edit', '', 'info'); return; }
		askNumber('Edit size', unit, cur).then(function (r) {
			if (!r.isConfirmed) return;
			var n = parseFloat(r.value);
			presets[unit] = cleanList((presets[unit] || []).filter(function (x) { return x !== cur; }).concat([n]));
			savePresets(function () {
				renderSizes(unit, n); writeQuantity();
				qpSwal({ toast: true, position: 'top-end', icon: 'success', title: 'Size updated', showConfirmButton: false, timer: 1600 });
			});
		});
	});

	$(document).on('click', '#qp-delete', function () {
		var unit = needUnit(); if (!unit) return;
		var cur = parseFloat($('#qp-size').val());
		if (!isFinite(cur)) { qpSwal('Select a size to delete', '', 'info'); return; }
		qpSwal({
			title: 'Delete ' + esc(cur) + ' ' + unit + '?',
			text: 'It is removed from the size list only. Products already saved with this size are not changed.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#a8442f',
			confirmButtonText: 'Delete'
		}).then(function (r) {
			if (!r.isConfirmed) return;
			presets[unit] = (presets[unit] || []).filter(function (x) { return x !== cur; });
			savePresets(function () { renderSizes(unit, null); writeQuantity(); });
		});
	});

	// Existing code fills #quantity when the modal opens (edit / new) or when the
	// category changes (auto-suggest) - mirror that into the dropdowns.
	$(document).on('shown.bs.modal', '#productModal', syncFromQuantity);
	$(document).on('change', '#category', function () { setTimeout(syncFromQuantity, 0); });

	$(function () {
		if (!buildUi()) return;
		loadPresets(syncFromQuantity);
	});
})(jQuery);
