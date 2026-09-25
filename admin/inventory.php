<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory — Valluvam Admin</title>
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
                    <h1>Inventory</h1>
                    <div class="adm-sub">Stock levels and stock movement history</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>Stock levels</h2>
                    <input type="text" id="searchStock" class="adm-input" placeholder="Search products…" style="width:220px;">
                </div>
                <div class="adm-card-body" id="stockTable">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                        </tbody></table>
                    </div>
                </div>
            </section>

            <section class="adm-card" style="margin-top:20px;">
                <div class="adm-card-head"><h2>Recent stock movements</h2></div>
                <div class="adm-card-body" id="historyTable">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                        </tbody></table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let searchTimer = null;

        $(document).ready(function() {
            loadStock();
            loadHistory();
            $('#searchStock').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadStock, 250);
            });
        });

        function loadStock() {
            const search = $('#searchStock').val();
            $.ajax({
                url: '../assets/db_query/admin/get_stock.php' + (search ? '?search=' + encodeURIComponent(search) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayStock(data.products);
                    } else {
                        $('#stockTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load stock.') + '</div>');
                    }
                },
                error: function() {
                    $('#stockTable').html('<div class="adm-error">Could not reach the server while loading stock.</div>');
                }
            });
        }

        function displayStock(products) {
            if (products.length === 0) {
                $('#stockTable').html('<div class="adm-empty"><i class="fas fa-box-open"></i><p><strong>No products found</strong></p></div>');
                return;
            }

            let rows = '';
            products.forEach(p => {
                const low = Number(p.stock) <= 10;
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(p.product_name)}</td>
                    <td><span class="adm-badge is-info">${escapeHtml(p.category || 'Uncategorized')}</span></td>
                    <td>${low ? '<span class="adm-badge is-amber">' + p.stock + ' — low</span>' : escapeHtml(String(p.stock))}</td>
                    <td>
                        <input type="number" class="adm-input stock-adjust-input" data-id="${p.id}" placeholder="±qty" style="width:90px;">
                        <button class="adm-btn adm-btn-ghost stock-adjust-btn" data-id="${p.id}" data-name="${escapeHtml(p.product_name)}">Apply</button>
                    </td>
                </tr>`;
            });

            $('#stockTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Product</th><th>Category</th><th>Current stock</th><th>Adjust</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.stock-adjust-btn').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const input = $(`.stock-adjust-input[data-id="${id}"]`);
                const qty = parseInt(input.val(), 10);
                if (!qty) {
                    Swal.fire({ title: 'Enter a quantity', text: 'Use a positive number to add stock, negative to remove.', icon: 'info', confirmButtonColor: '#1c5034' });
                    return;
                }
                adjustStock(id, name, qty);
            });
        }

        function adjustStock(productId, productName, qty) {
            Swal.fire({
                title: `${qty > 0 ? 'Add' : 'Remove'} ${Math.abs(qty)} units`,
                text: `for "${productName}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/adjust_stock.php',
                        type: 'POST',
                        data: { product_id: productId, quantity_change: qty },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Stock updated', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadStock();
                                loadHistory();
                            } else {
                                Swal.fire({ title: 'Could not update', text: response.message || 'Stock was not changed.', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    });
                }
            });
        }

        function loadHistory() {
            $.ajax({
                url: '../assets/db_query/admin/get_stock_history.php?limit=20',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success' && data.history.length > 0) {
                        displayHistory(data.history);
                    } else if (data.status === 'success') {
                        $('#historyTable').html('<div class="adm-empty"><i class="fas fa-clock-rotate-left"></i><p><strong>No stock movements yet</strong></p></div>');
                    } else {
                        $('#historyTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load history.') + '</div>');
                    }
                },
                error: function() {
                    $('#historyTable').html('<div class="adm-error">Could not reach the server while loading history.</div>');
                }
            });
        }

        function displayHistory(history) {
            let rows = '';
            history.forEach(h => {
                const sign = h.quantity_change > 0 ? '+' : '';
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(h.product_name || ('#' + h.product_id))}</td>
                    <td><span class="adm-badge is-neutral">${escapeHtml(h.change_type)}</span></td>
                    <td class="adm-money">${sign}${h.quantity_change}</td>
                    <td>${h.resulting_stock}</td>
                    <td>${escapeHtml(h.reason || '—')}</td>
                    <td class="adm-cell-sub">${formatDate(h.created_at)}</td>
                </tr>`;
            });
            $('#historyTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Product</th><th>Type</th><th>Change</th><th>Resulting stock</th><th>Reason</th><th>When</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>
