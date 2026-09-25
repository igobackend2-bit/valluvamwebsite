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
                <button class="adm-btn adm-btn-primary" id="exportCsvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>
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
