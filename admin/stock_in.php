<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock In — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .si-item-row { display:grid; grid-template-columns: 2fr 1fr 0.8fr 1fr 1fr 1fr auto; gap:8px; align-items:center; margin-bottom:8px; }
        @media (max-width: 900px) { .si-item-row { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Stock In</h1>
                    <div class="adm-sub">Record goods received into a warehouse (Load In)</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="newStockInBtn"><i class="fas fa-plus"></i> New stock in</button>
            </div>

            <section class="adm-card" id="stockInForm" style="display:none;">
                <div class="adm-card-head"><h2>New stock in</h2></div>
                <div class="adm-card-body">
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:12px;">
                        <div><label>Date</label><br><input type="date" id="f_date" class="adm-input"></div>
                        <div><label>Supplier</label><br><select id="f_supplier" class="adm-select" style="min-width:200px;"><option value="">No supplier / cash purchase</option></select></div>
                        <div><label>Purchase reference</label><br><input type="text" id="f_purchase_ref" class="adm-input" placeholder="PO / invoice #"></div>
                        <div><label>Warehouse</label><br><select id="f_warehouse" class="adm-select"></select></div>
                        <div><label>Received by</label><br><input type="text" id="f_received_by" class="adm-input"></div>
                        <div><label>Vehicle number</label><br><input type="text" id="f_vehicle" class="adm-input"></div>
                    </div>
                    <div class="adm-field" style="margin-top:10px;">
                        <label>Remarks</label><br>
                        <textarea id="f_remarks" class="adm-input" style="width:100%;" rows="2"></textarea>
                    </div>
                    <div class="adm-field">
                        <label>Attachment note <span class="adm-cell-sub">(file upload not available yet — describe attached documents here)</span></label><br>
                        <input type="text" id="f_attachment_note" class="adm-input" style="width:100%;" placeholder="e.g. Invoice #1234 filed with accounts">
                    </div>

                    <h3 style="margin-top:18px;">Line items</h3>
                    <div id="itemRows"></div>
                    <button type="button" class="adm-btn adm-btn-ghost" id="addItemRowBtn"><i class="fas fa-plus"></i> Add item</button>

                    <div style="margin-top:16px;display:flex;gap:8px;">
                        <button class="adm-btn adm-btn-primary" id="saveDraftBtn">Save as draft</button>
                        <button class="adm-btn adm-btn-ghost" id="cancelFormBtn">Cancel</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>All stock ins</h2></div>
                <div class="adm-card-body" id="stockInsTable">
                    <div class="adm-table-wrap"><table class="adm-table"><tbody>
                        <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                    </tbody></table></div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let products = [];
        let itemRowIndex = 0;

        $(document).ready(function() {
            $('#f_date').val(new Date().toISOString().substring(0, 10));
            loadProducts();
            loadSuppliers();
            loadWarehouses();
            loadStockIns();

            $('#newStockInBtn').on('click', function() {
                resetForm();
                $('#stockInForm').show();
            });
            $('#cancelFormBtn').on('click', function() { $('#stockInForm').hide(); });
            $('#addItemRowBtn').on('click', function() { addItemRow(); });
            $('#saveDraftBtn').on('click', function() { saveStockIn(); });
        });

        function resetForm() {
            $('#f_purchase_ref, #f_received_by, #f_vehicle, #f_remarks, #f_attachment_note').val('');
            $('#f_supplier').val('');
            $('#itemRows').empty();
            itemRowIndex = 0;
            addItemRow();
        }

        function loadProducts() {
            $.ajax({
                url: '../assets/db_query/admin/get_products_lite.php',
                type: 'GET', dataType: 'json',
                success: function(data) { if (data.status === 'success') products = data.products; }
            });
        }

        function loadSuppliers() {
            $.ajax({
                url: '../assets/db_query/admin/get_suppliers_lite.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        if (data.suppliers.length === 0) {
                            $('#f_supplier').append('<option value="" disabled>No suppliers set up yet</option>');
                        }
                        data.suppliers.forEach(s => {
                            $('#f_supplier').append(`<option value="${s.id}">${escapeHtml(s.supplier_name)}${s.company_name ? ' (' + escapeHtml(s.company_name) + ')' : ''}</option>`);
                        });
                    }
                }
            });
        }

        function loadWarehouses() {
            $.ajax({
                url: '../assets/db_query/admin/get_warehouses.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        data.warehouses.forEach(w => {
                            $('#f_warehouse').append(`<option value="${w.id}" ${w.id == 1 ? 'selected' : ''}>${escapeHtml(w.name)}</option>`);
                        });
                    }
                }
            });
        }

        function addItemRow() {
            const idx = itemRowIndex++;
            let options = '<option value="">Select product…</option>';
            products.forEach(p => { options += `<option value="${p.id}" data-sku="${escapeHtml(p.sku || '')}">${escapeHtml(p.product_name)}</option>`; });
            const row = `<div class="si-item-row" data-idx="${idx}">
                <select class="adm-select item-product">${options}</select>
                <input type="number" min="1" class="adm-input item-qty" placeholder="Qty">
                <input type="text" class="adm-input item-unit" placeholder="Unit" value="pcs">
                <input type="text" class="adm-input item-batch" placeholder="Batch # (optional)">
                <input type="date" class="adm-input item-expiry" title="Expiry date (optional)">
                <input type="number" step="0.01" class="adm-input item-rate" placeholder="Rate ₹">
                <button type="button" class="adm-icon-btn is-danger remove-item-row" title="Remove"><i class="fas fa-trash"></i></button>
            </div>`;
            $('#itemRows').append(row);
            $(`.si-item-row[data-idx="${idx}"] .remove-item-row`).on('click', function() {
                $(this).closest('.si-item-row').remove();
            });
        }

        function collectItems() {
            const items = [];
            $('#itemRows .si-item-row').each(function() {
                const productId = $(this).find('.item-product').val();
                const qty = $(this).find('.item-qty').val();
                if (!productId || !qty) return;
                items.push({
                    product_id: productId,
                    sku: $(this).find('.item-product option:selected').data('sku') || '',
                    quantity: qty,
                    unit: $(this).find('.item-unit').val() || 'pcs',
                    batch_number: $(this).find('.item-batch').val(),
                    manufacturing_date: '',
                    expiry_date: $(this).find('.item-expiry').val(),
                    purchase_rate: $(this).find('.item-rate').val()
                });
            });
            return items;
        }

        function saveStockIn() {
            const items = collectItems();
            if (items.length === 0) {
                Swal.fire({ title: 'Add at least one item', icon: 'warning', confirmButtonColor: '#1c5034' });
                return;
            }
            $.ajax({
                url: '../assets/db_query/admin/save_stock_in.php',
                type: 'POST',
                data: {
                    stock_in_date: $('#f_date').val(),
                    supplier_id: $('#f_supplier').val(),
                    purchase_reference: $('#f_purchase_ref').val(),
                    warehouse_id: $('#f_warehouse').val(),
                    received_by: $('#f_received_by').val(),
                    vehicle_number: $('#f_vehicle').val(),
                    remarks: $('#f_remarks').val(),
                    attachment_note: $('#f_attachment_note').val(),
                    status: 'draft',
                    items: JSON.stringify(items)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        $('#stockInForm').hide();
                        loadStockIns();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The stock in was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function loadStockIns() {
            $.ajax({
                url: '../assets/db_query/admin/get_stock_ins.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayStockIns(data.stock_ins);
                    } else {
                        $('#stockInsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load stock ins.') + '</div>');
                    }
                },
                error: function() {
                    $('#stockInsTable').html('<div class="adm-error">Could not reach the server while loading stock ins.</div>');
                }
            });
        }

        function statusBadge(status) {
            const map = { draft: 'is-neutral', received: 'is-amber', verified: 'is-info', completed: 'is-green', cancelled: 'is-danger' };
            return `<span class="adm-badge ${map[status] || 'is-neutral'}">${escapeHtml(status)}</span>`;
        }

        function displayStockIns(rows) {
            if (rows.length === 0) {
                $('#stockInsTable').html('<div class="adm-empty"><i class="fas fa-dolly"></i><p><strong>No stock ins yet</strong></p><p>Create one to record goods received.</p></div>');
                return;
            }
            let html = '';
            rows.forEach(r => {
                html += `<tr>
                    <td class="adm-cell-title"><a href="print_stock_in.php?id=${r.id}" target="_blank">${escapeHtml(r.stock_in_number)}</a><div class="adm-cell-sub">${formatDate(r.created_at)}</div></td>
                    <td>${formatDateOnly(r.stock_in_date)}</td>
                    <td>${escapeHtml(r.supplier_name || 'No supplier')}</td>
                    <td>${escapeHtml(r.warehouse_name || '—')}</td>
                    <td>${r.item_count} item(s) / ${r.total_quantity} qty</td>
                    <td>${statusBadge(r.status)}</td>
                    <td>${escapeHtml(r.created_by || '—')}</td>
                    <td>
                        ${(r.status !== 'completed' && r.status !== 'cancelled') ? `<button class="adm-btn adm-btn-primary complete-btn" data-id="${r.id}" data-number="${escapeHtml(r.stock_in_number)}" style="padding:4px 10px;font-size:12px;">Complete</button>` : ''}
                    </td>
                </tr>`;
            });
            $('#stockInsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Number</th><th>Date</th><th>Supplier</th><th>Warehouse</th><th>Items</th><th>Status</th><th>Created by</th><th>Actions</th></tr></thead>
                <tbody>${html}</tbody>
            </table></div>`);

            $('.complete-btn').on('click', function() {
                const id = $(this).data('id');
                const number = $(this).data('number');
                Swal.fire({
                    title: `Complete ${number}?`,
                    text: 'This will add every line item to product stock and cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#1c5034',
                    cancelButtonColor: '#6b6459',
                    confirmButtonText: 'Complete'
                }).then((result) => {
                    if (result.isConfirmed) completeStockIn(id);
                });
            });
        }

        function completeStockIn(id) {
            $.ajax({
                url: '../assets/db_query/admin/complete_stock_in.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Completed', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadStockIns();
                    } else {
                        Swal.fire({ title: 'Could not complete', text: response.message || 'The stock in was not completed.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not complete', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }
        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
        }
        function formatDateOnly(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN');
        }
    </script>
</body>
</html>
