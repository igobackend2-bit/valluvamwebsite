<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Out — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .so-item-row { display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:8px; align-items:center; margin-bottom:8px; }
        @media (max-width: 700px) { .so-item-row { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Stock Out</h1>
                    <div class="adm-sub">Record goods leaving a warehouse (Load Out) — internal transfers, damage, or other manual dispatches</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="newStockOutBtn"><i class="fas fa-plus"></i> New stock out</button>
            </div>

            <section class="adm-card" id="stockOutForm" style="display:none;">
                <div class="adm-card-head"><h2>New stock out</h2></div>
                <div class="adm-card-body">
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:12px;">
                        <div><label>Date</label><br><input type="date" id="f_date" class="adm-input"></div>
                        <div><label>Reference type</label><br>
                            <select id="f_reftype" class="adm-select">
                                <option value="internal_transfer">Internal transfer</option>
                                <option value="damage">Damage</option>
                                <option value="waste">Waste</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div><label>Reference number</label><br><input type="text" id="f_refnum" class="adm-input" placeholder="Optional"></div>
                        <div><label>Warehouse</label><br><select id="f_warehouse" class="adm-select"></select></div>
                        <div><label>Vehicle number</label><br><input type="text" id="f_vehicle" class="adm-input"></div>
                        <div><label>Customer name</label><br><input type="text" id="f_customer" class="adm-input" placeholder="Optional"></div>
                        <div><label>Authorized by</label><br><input type="text" id="f_authby" class="adm-input"></div>
                    </div>
                    <div class="adm-field" style="margin-top:10px;">
                        <label>Reason</label><br>
                        <textarea id="f_reason" class="adm-input" style="width:100%;" rows="2"></textarea>
                    </div>

                    <h3 style="margin-top:18px;">Line items</h3>
                    <div id="itemRows"></div>
                    <button type="button" class="adm-btn adm-btn-ghost" id="addItemRowBtn"><i class="fas fa-plus"></i> Add item</button>

                    <div style="margin-top:16px;display:flex;gap:8px;">
                        <button class="adm-btn adm-btn-primary" id="saveStockOutBtn">Save &amp; deduct stock</button>
                        <button class="adm-btn adm-btn-ghost" id="cancelFormBtn">Cancel</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head" style="flex-wrap:wrap;gap:10px;">
                    <h2>All stock outs</h2>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select id="f_period" class="adm-select">
                            <option value="">All time</option>
                            <option value="day">Today</option>
                            <option value="week">This week (7 days)</option>
                            <option value="month">This month (30 days)</option>
                        </select>
                        <button class="adm-btn adm-btn-ghost" id="exportStockOutCsvBtn"><i class="fas fa-file-csv"></i> Download CSV</button>
                    </div>
                </div>
                <div class="adm-card-body" id="stockOutsTable">
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
            loadWarehouses();
            loadStockOuts();

            $('#newStockOutBtn').on('click', function() {
                resetForm();
                $('#stockOutForm').show();
            });
            $('#cancelFormBtn').on('click', function() { $('#stockOutForm').hide(); });
            $('#addItemRowBtn').on('click', function() { addItemRow(); });
            $('#saveStockOutBtn').on('click', function() { saveStockOut(); });
            $('#f_period').on('change', function() { loadStockOuts(); });
            $('#exportStockOutCsvBtn').on('click', exportStockOutsCsv);
        });

        function resetForm() {
            $('#f_refnum, #f_vehicle, #f_customer, #f_authby, #f_reason').val('');
            $('#f_reftype').val('internal_transfer');
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
            products.forEach(p => { options += `<option value="${p.id}" data-sku="${escapeHtml(p.sku || '')}" data-stock="${p.stock}">${escapeHtml(p.product_name)} (in stock: ${p.stock})</option>`; });
            const row = `<div class="so-item-row" data-idx="${idx}">
                <select class="adm-select item-product">${options}</select>
                <input type="number" min="1" class="adm-input item-qty" placeholder="Qty">
                <input type="text" class="adm-input item-unit" placeholder="Unit" value="pcs">
                <button type="button" class="adm-icon-btn is-danger remove-item-row" title="Remove"><i class="fas fa-trash"></i></button>
            </div>`;
            $('#itemRows').append(row);
            $(`.so-item-row[data-idx="${idx}"] .remove-item-row`).on('click', function() {
                $(this).closest('.so-item-row').remove();
            });
        }

        function collectItems() {
            const items = [];
            $('#itemRows .so-item-row').each(function() {
                const productId = $(this).find('.item-product').val();
                const qty = $(this).find('.item-qty').val();
                if (!productId || !qty) return;
                items.push({
                    product_id: productId,
                    sku: $(this).find('.item-product option:selected').data('sku') || '',
                    quantity: qty,
                    unit: $(this).find('.item-unit').val() || 'pcs'
                });
            });
            return items;
        }

        function saveStockOut() {
            const items = collectItems();
            if (items.length === 0) {
                Swal.fire({ title: 'Add at least one item', icon: 'warning', confirmButtonColor: '#1c5034' });
                return;
            }
            Swal.fire({
                title: 'Confirm stock out?',
                text: 'This will immediately deduct these quantities from product stock.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/save_stock_out.php',
                    type: 'POST',
                    data: {
                        stock_out_date: $('#f_date').val(),
                        reference_type: $('#f_reftype').val(),
                        reference_number: $('#f_refnum').val(),
                        warehouse_id: $('#f_warehouse').val(),
                        vehicle_number: $('#f_vehicle').val(),
                        customer_name: $('#f_customer').val(),
                        reason: $('#f_reason').val(),
                        authorized_by: $('#f_authby').val(),
                        items: JSON.stringify(items)
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Saved', text: response.stock_out_number || '', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                            $('#stockOutForm').hide();
                            loadStockOuts();
                        } else {
                            Swal.fire({ title: 'Could not save', text: response.message || 'The stock out was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                });
            });
        }

        let lastStockOuts = [];

        function loadStockOuts() {
            const period = $('#f_period').val();
            $.ajax({
                url: '../assets/db_query/admin/get_stock_outs.php' + (period ? '?period=' + encodeURIComponent(period) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        lastStockOuts = data.stock_outs;
                        displayStockOuts(data.stock_outs);
                    } else {
                        $('#stockOutsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load stock outs.') + '</div>');
                    }
                },
                error: function() {
                    $('#stockOutsTable').html('<div class="adm-error">Could not reach the server while loading stock outs.</div>');
                }
            });
        }

        function exportStockOutsCsv() {
            if (!lastStockOuts.length) {
                Swal.fire({ title: 'Nothing to export', text: 'No stock outs in this period.', icon: 'info', confirmButtonColor: '#1c5034' });
                return;
            }
            const header = ['Stock Out #', 'Date', 'Reference Type', 'Reference #', 'Warehouse', 'Items', 'Total Qty', 'Customer', 'Created By', 'Created At'];
            const rows = [header.join(',')];
            lastStockOuts.forEach(r => {
                rows.push([
                    csvEscape(r.stock_out_number), csvEscape(r.stock_out_date), csvEscape(r.reference_type),
                    csvEscape(r.reference_number || ''), csvEscape(r.warehouse_name || ''), r.item_count,
                    r.total_quantity, csvEscape(r.customer_name || ''), csvEscape(r.created_by || ''), csvEscape(r.created_at)
                ].join(','));
            });
            const period = $('#f_period').val() || 'all';
            const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'stock_out_' + period + '_' + new Date().toISOString().substring(0, 10) + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function csvEscape(val) {
            const str = String(val ?? '');
            if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                return '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
        }

        function refBadge(type) {
            const map = { sales_order: 'is-info', delivery_challan: 'is-info', manual_sales: 'is-info',
                          internal_transfer: 'is-amber', damage: 'is-danger', waste: 'is-danger', other: 'is-neutral' };
            return `<span class="adm-badge ${map[type] || 'is-neutral'}">${escapeHtml(type.replace('_', ' '))}</span>`;
        }

        function displayStockOuts(rows) {
            if (rows.length === 0) {
                $('#stockOutsTable').html('<div class="adm-empty"><i class="fas fa-truck-ramp-box"></i><p><strong>No stock outs yet</strong></p><p>Create one to record goods dispatched.</p></div>');
                return;
            }
            let html = '';
            rows.forEach(r => {
                html += `<tr>
                    <td class="adm-cell-title"><a href="print_stock_out.php?id=${r.id}" target="_blank">${escapeHtml(r.stock_out_number)}</a><div class="adm-cell-sub">${formatDate(r.created_at)}</div></td>
                    <td>${formatDateOnly(r.stock_out_date)}</td>
                    <td>${refBadge(r.reference_type)}${r.reference_number ? '<div class="adm-cell-sub">' + escapeHtml(r.reference_number) + '</div>' : ''}</td>
                    <td>${escapeHtml(r.warehouse_name || '—')}</td>
                    <td>${r.item_count} item(s) / ${r.total_quantity} qty</td>
                    <td>${escapeHtml(r.customer_name || '—')}</td>
                    <td>${escapeHtml(r.created_by || '—')}</td>
                </tr>`;
            });
            $('#stockOutsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Number</th><th>Date</th><th>Reference</th><th>Warehouse</th><th>Items</th><th>Customer</th><th>Created by</th></tr></thead>
                <tbody>${html}</tbody>
            </table></div>`);
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
