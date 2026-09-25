<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Overview — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Inventory Overview</h1>
                    <div class="adm-sub">Warehouse-aware stock levels, reservations and reorder status. For the original manual "Adjust stock" tool, see the Inventory page.</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="adm-btn adm-btn-ghost" id="importStockBtn"><i class="fas fa-file-import"></i> Import Stock</button>
                    <button class="adm-btn adm-btn-primary" id="exportCsvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>

            <!--
                NOTE: product_details.stock remains a single global total in this
                pass. The warehouse filter below is cosmetic today — every product
                currently has one stock figure regardless of warehouse. True
                per-warehouse stock splitting is a larger future change; this pass
                only tracks the warehouse a movement/stock-in/stock-out happened at.
            -->

            <section class="adm-card">
                <div class="adm-card-head"><h2>Filters</h2></div>
                <div class="adm-card-body">
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:12px;">
                        <input type="text" id="searchBox" class="adm-input" style="min-width:220px;" placeholder="Search by product name or SKU…">
                        <select id="statusFilter" class="adm-select">
                            <option value="">All statuses</option>
                            <option value="out">Out of Stock</option>
                            <option value="low">Low Stock</option>
                            <option value="over">Overstocked</option>
                            <option value="ok">In Stock</option>
                        </select>
                        <select id="warehouseFilter" class="adm-select"><option value="">All warehouses (cosmetic)</option></select>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Products</h2></div>
                <div class="adm-card-body" id="inventoryTable">
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
        let allProducts = [];

        $(document).ready(function() {
            loadWarehouses();
            loadInventory();
            $('#searchBox').on('input', render);
            $('#statusFilter').on('change', render);
            $('#warehouseFilter').on('change', render);
            $('#exportCsvBtn').on('click', exportCsv);
            $('#importStockBtn').on('click', openImportStock);
        });

        function loadWarehouses() {
            $.ajax({
                url: '../assets/db_query/admin/get_warehouses.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        data.warehouses.forEach(w => {
                            $('#warehouseFilter').append(`<option value="${w.id}">${escapeHtml(w.name)}</option>`);
                        });
                    }
                }
            });
        }

        function loadInventory() {
            $.ajax({
                url: '../assets/db_query/admin/get_inventory_overview.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        allProducts = data.products;
                        render();
                    } else {
                        $('#inventoryTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load inventory.') + '</div>');
                    }
                },
                error: function() {
                    $('#inventoryTable').html('<div class="adm-error">Could not reach the server while loading inventory.</div>');
                }
            });
        }

        // Default low-stock cutoff when a product has no explicit reorder level
        // set: below 20 (units/kg/litre) counts as Low Stock, 0 as Out of Stock.
        const DEFAULT_LOW_STOCK_THRESHOLD = 20;

        function computeStatus(p) {
            const stock = Number(p.stock) || 0;
            const reorder = p.reorder_level !== null && p.reorder_level !== undefined && p.reorder_level !== '' ? Number(p.reorder_level) : DEFAULT_LOW_STOCK_THRESHOLD;
            const max = p.max_stock_level !== null && p.max_stock_level !== undefined ? Number(p.max_stock_level) : null;
            if (stock === 0) return 'out';
            if (stock < reorder) return 'low';
            if (max !== null && max > 0 && stock > max) return 'over';
            return 'ok';
        }

        function statusBadge(status) {
            const map = {
                out: '<span class="adm-badge is-danger">Out of Stock</span>',
                low: '<span class="adm-badge is-amber">Low Stock</span>',
                over: '<span class="adm-badge is-info">Overstocked</span>',
                ok: '<span class="adm-badge is-green">In Stock</span>'
            };
            return map[status] || map.ok;
        }

        function filteredProducts() {
            const q = ($('#searchBox').val() || '').toLowerCase().trim();
            const statusFilter = $('#statusFilter').val();
            return allProducts.filter(p => {
                if (q && !((p.product_name || '').toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q))) return false;
                if (statusFilter && computeStatus(p) !== statusFilter) return false;
                return true;
            });
        }

        function render() {
            const rows = filteredProducts();
            if (rows.length === 0) {
                $('#inventoryTable').html('<div class="adm-empty"><i class="fas fa-boxes-stacked"></i><p><strong>No products found</strong></p><p>Try a different search or filter.</p></div>');
                return;
            }
            let html = '';
            rows.forEach(p => {
                const status = computeStatus(p);
                html += `<tr>
                    <td class="adm-cell-title">${escapeHtml(p.product_name)}<div class="adm-cell-sub">${escapeHtml(p.sku || '—')}</div></td>
                    <td>${p.stock}</td>
                    <td>${p.reserved_stock}</td>
                    <td><strong>${p.available_to_sell}</strong></td>
                    <td>${p.min_stock_level ?? '—'}</td>
                    <td>${p.reorder_level ?? '—'}</td>
                    <td>${p.max_stock_level ?? '—'}</td>
                    <td>${statusBadge(status)}</td>
                    <td>${status === 'out'
                        ? '<span class="adm-cell-sub">Already out</span>'
                        : `<button class="adm-btn adm-btn-ghost mark-oos-btn" data-id="${p.id}" data-name="${escapeHtml(p.product_name)}"><i class="fas fa-triangle-exclamation"></i> Mark Out of Stock</button>`}</td>
                </tr>`;
            });
            $('#inventoryTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Product</th><th>Available Stock</th><th>Reserved</th><th>Available to Sell</th><th>Min</th><th>Reorder</th><th>Max</th><th>Status</th><th>Emergency</th></tr></thead>
                <tbody>${html}</tbody>
            </table></div>`);

            $('.mark-oos-btn').on('click', function() {
                markOutOfStock($(this).data('id'), $(this).data('name'));
            });
        }

        function markOutOfStock(productId, productName) {
            Swal.fire({
                title: 'Mark as Out of Stock?',
                html: `This immediately sets <strong>${escapeHtml(productName)}</strong>'s stock to <strong>0</strong>, for emergencies (recall, damage, supply issue, etc). It will show as Out of Stock everywhere right away.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#c0392b',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Yes, mark Out of Stock',
                input: 'text',
                inputPlaceholder: 'Reason (optional)'
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/mark_out_of_stock.php',
                    type: 'POST',
                    data: { product_id: productId, reason: result.value || '' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Marked Out of Stock', icon: 'success', confirmButtonColor: '#1c5034', timer: 1400, showConfirmButton: false });
                            loadInventory();
                        } else {
                            Swal.fire({ title: 'Could not update', text: response.message || 'Stock was not changed.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                });
            });
        }

        // ---- Bulk stock import (Excel/CSV) ----
        // Matches rows to EXISTING products by exact name (case-insensitive)
        // only — never creates or renames a product. Unmatched names and bad
        // quantity values are reported back, never guessed.
        function openImportStock() {
            Swal.fire({
                title: 'Import Stock from File',
                html: `
                    <p style="text-align:left;margin:0 0 10px;color:#5a5650;font-size:14px;">
                        Upload a <strong>CSV</strong> or <strong>Excel (.xlsx)</strong> file with a
                        <strong>Product Name</strong> column and a <strong>Quantity</strong> column.
                        Each row updates that product's stock to the quantity in the file — matching
                        is by exact product name only, so nothing is renamed and no new product is
                        created. Rows that don't match an existing product are listed afterwards so
                        nothing silently happens to the wrong product.
                    </p>
                    <p style="text-align:left;margin:0 0 10px;color:#8a8478;font-size:13px;">
                        PDF isn't supported — it can't be read reliably enough to trust with stock
                        numbers. Please save/export the list as CSV or Excel instead.
                    </p>
                    <input type="file" id="swal-import-file" class="swal2-file" accept=".csv,.xlsx">
                `,
                confirmButtonText: 'Import',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const fileInput = document.getElementById('swal-import-file');
                    if (!fileInput.files || fileInput.files.length === 0) {
                        Swal.showValidationMessage('Choose a CSV or Excel file first');
                        return false;
                    }
                    return fileInput.files[0];
                }
            }).then((result) => {
                if (result.isConfirmed) submitImportStock(result.value);
            });
        }

        function submitImportStock(file) {
            Swal.fire({ title: 'Importing…', text: 'Reading and matching the file, please wait.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            const formData = new FormData();
            formData.append('import_file', file);

            $.ajax({
                url: '../assets/db_query/admin/import_stock.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showImportSummary(response.summary, response.updated);
                        loadInventory();
                    } else {
                        Swal.fire({ title: 'Could not import', text: response.message || 'The file could not be processed.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not import', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function showImportSummary(summary, updated) {
            let html = `<div style="text-align:left;font-size:14px;">
                <p><strong>${summary.updated_count}</strong> product(s) updated, <strong>${summary.unchanged_count}</strong> already matched the file, out of <strong>${summary.total_rows}</strong> row(s) read.</p>`;

            if (updated && updated.length > 0) {
                html += '<div style="max-height:150px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;margin-bottom:10px;">';
                updated.forEach(u => {
                    html += `<div>${escapeHtml(u.name)}: ${u.previous_stock} → <strong>${u.new_stock}</strong></div>`;
                });
                html += '</div>';
            }

            if (summary.unmatched && summary.unmatched.length > 0) {
                html += `<p style="color:#c0392b;margin-bottom:4px;"><strong>${summary.unmatched.length} row(s) not matched to any product</strong> (nothing was changed for these — check spelling against the product list):</p>`;
                html += '<div style="max-height:120px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;margin-bottom:10px;">';
                summary.unmatched.forEach(u => {
                    html += `<div>${escapeHtml(u.name)} <span style="color:#8a8478;">— ${escapeHtml(u.reason)}</span></div>`;
                });
                html += '</div>';
            }

            if (summary.invalid_quantity && summary.invalid_quantity.length > 0) {
                html += `<p style="color:#c0392b;margin-bottom:4px;"><strong>${summary.invalid_quantity.length} row(s) had an unreadable quantity</strong> and were skipped:</p>`;
                html += '<div style="max-height:120px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;">';
                summary.invalid_quantity.forEach(u => {
                    html += `<div>${escapeHtml(u.name)}: "${escapeHtml(u.value)}"</div>`;
                });
                html += '</div>';
            }

            html += '</div>';

            Swal.fire({
                title: 'Import complete',
                html: html,
                icon: summary.unmatched.length > 0 || summary.invalid_quantity.length > 0 ? 'warning' : 'success',
                confirmButtonColor: '#1c5034',
                width: 560
            });
        }

        function exportCsv() {
            const rows = filteredProducts();
            if (rows.length === 0) {
                Swal.fire({ title: 'Nothing to export', icon: 'info', confirmButtonColor: '#1c5034' });
                return;
            }
            const header = ['Product', 'SKU', 'Available Stock', 'Reserved', 'Available to Sell', 'Min Level', 'Reorder Level', 'Max Level', 'Status'];
            const csvRows = [header.join(',')];
            rows.forEach(p => {
                const status = computeStatus(p);
                const line = [
                    csvEscape(p.product_name), csvEscape(p.sku || ''), p.stock, p.reserved_stock, p.available_to_sell,
                    p.min_stock_level ?? '', p.reorder_level ?? '', p.max_stock_level ?? '', status
                ];
                csvRows.push(line.join(','));
            });
            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'inventory_overview_' + new Date().toISOString().substring(0, 10) + '.csv');
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

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }
    </script>
</body>
</html>
