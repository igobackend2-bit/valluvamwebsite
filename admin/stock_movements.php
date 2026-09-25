<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movement History — Valluvam Admin</title>
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
                    <h1>Stock Movement History</h1>
                    <div class="adm-sub">Every stock change, across every module — read-only, permanent record</div>
                </div>
            </div>

            <!--
                READ-ONLY BY DESIGN: this page has no edit/delete affordance for a
                movement row, on purpose — movement history is immutable. The only
                future exception is a "Super Admin correction" feature (out of scope
                for this build); even that must INSERT a new correction row rather
                than amend an existing one. Do not add edit/delete controls here.
            -->

            <section class="adm-card">
                <div class="adm-card-head"><h2>Filters</h2></div>
                <div class="adm-card-body">
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:12px;">
                        <select id="filterProduct" class="adm-select" style="min-width:200px;"><option value="">All products</option></select>
                        <select id="filterWarehouse" class="adm-select" style="min-width:160px;"><option value="">All warehouses</option></select>
                        <select id="filterType" class="adm-select" style="min-width:160px;">
                            <option value="">All movement types</option>
                            <option value="stock_in">Stock In</option>
                            <option value="stock_out">Stock Out</option>
                            <option value="sale">Sale</option>
                            <option value="purchase">Purchase</option>
                            <option value="transfer">Transfer</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="damage">Damage</option>
                            <option value="waste">Waste</option>
                            <option value="return">Return</option>
                            <option value="correction">Correction</option>
                        </select>
                        <input type="date" id="filterFrom" class="adm-input" style="max-width:170px;">
                        <input type="date" id="filterTo" class="adm-input" style="max-width:170px;">
                        <button class="adm-btn adm-btn-primary" id="applyFiltersBtn"><i class="fas fa-filter"></i> Apply</button>
                        <button class="adm-btn adm-btn-ghost" id="clearFiltersBtn">Clear</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Movements</h2></div>
                <div class="adm-card-body" id="movementsTable">
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
        $(document).ready(function() {
            loadProductFilter();
            loadWarehouseFilter();
            loadMovements();
            $('#applyFiltersBtn').on('click', loadMovements);
            $('#clearFiltersBtn').on('click', function() {
                $('#filterProduct, #filterWarehouse, #filterType, #filterFrom, #filterTo').val('');
                loadMovements();
            });
        });

        function loadProductFilter() {
            $.ajax({
                url: '../assets/db_query/admin/get_products_lite.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        data.products.forEach(p => {
                            $('#filterProduct').append(`<option value="${p.id}">${escapeHtml(p.product_name)}</option>`);
                        });
                    }
                }
            });
        }

        function loadWarehouseFilter() {
            $.ajax({
                url: '../assets/db_query/admin/get_warehouses.php',
                type: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        data.warehouses.forEach(w => {
                            $('#filterWarehouse').append(`<option value="${w.id}">${escapeHtml(w.name)}</option>`);
                        });
                    }
                }
            });
        }

        function loadMovements() {
            $('#movementsTable').html('<div class="adm-table-wrap"><table class="adm-table"><tbody><tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr></tbody></table></div>');
            $.ajax({
                url: '../assets/db_query/admin/get_stock_movements.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    product_id: $('#filterProduct').val(),
                    warehouse_id: $('#filterWarehouse').val(),
                    movement_type: $('#filterType').val(),
                    date_from: $('#filterFrom').val(),
                    date_to: $('#filterTo').val()
                },
                success: function(data) {
                    if (data.status === 'success') {
                        displayMovements(data.movements);
                    } else {
                        $('#movementsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load movements.') + '</div>');
                    }
                },
                error: function() {
                    $('#movementsTable').html('<div class="adm-error">Could not reach the server while loading movements.</div>');
                }
            });
        }

        function typeBadge(type) {
            const positive = ['stock_in', 'purchase', 'return'];
            const negative = ['stock_out', 'sale', 'damage', 'waste'];
            let cls = 'is-neutral';
            if (positive.includes(type)) cls = 'is-green';
            else if (negative.includes(type)) cls = 'is-danger';
            else if (type === 'adjustment' || type === 'correction') cls = 'is-info';
            else if (type === 'transfer') cls = 'is-amber';
            return `<span class="adm-badge ${cls}">${escapeHtml(type.replace('_', ' '))}</span>`;
        }

        function displayMovements(movements) {
            if (movements.length === 0) {
                $('#movementsTable').html('<div class="adm-empty"><i class="fas fa-clock-rotate-left"></i><p><strong>No movements found</strong></p><p>Try adjusting the filters above.</p></div>');
                return;
            }

            let rows = '';
            movements.forEach(m => {
                const qty = parseInt(m.quantity, 10);
                const qtyDisplay = (qty > 0 ? '+' : '') + qty;
                rows += `<tr>
                    <td>${formatDate(m.created_at)}</td>
                    <td>${typeBadge(m.movement_type)}</td>
                    <td class="adm-cell-title">${escapeHtml(m.product_name || ('#' + m.product_id))}<div class="adm-cell-sub">${escapeHtml(m.sku || '—')}</div></td>
                    <td>${escapeHtml(m.warehouse_name || '—')}</td>
                    <td class="${qty >= 0 ? '' : ''}">${qtyDisplay}</td>
                    <td>${m.previous_stock} → ${m.new_stock}</td>
                    <td>${escapeHtml(m.reference_number || '—')}<div class="adm-cell-sub">${escapeHtml(m.reference_type || '')}</div></td>
                    <td>${escapeHtml(m.reason || '—')}</td>
                    <td>${escapeHtml(m.created_by || '—')}</td>
                </tr>`;
            });

            $('#movementsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Date</th><th>Type</th><th>Product</th><th>Warehouse</th><th>Qty</th><th>Stock</th><th>Reference</th><th>Reason</th><th>By</th></tr></thead>
                <tbody>${rows}</tbody>
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
    </script>
</body>
</html>
