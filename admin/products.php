<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — Valluvam Admin</title>
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
                    <h1>Products</h1>
                    <div class="adm-sub">Add, edit and retire catalog items</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="adm-btn adm-btn-ghost" id="importProductsBtn"><i class="fas fa-file-import"></i> Import Products</button>
                    <a href="../new_product.php" class="adm-btn adm-btn-primary">
                        <i class="fas fa-plus"></i> Add product
                    </a>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All products</h2>
                    <input type="text" id="searchProduct" class="adm-input" placeholder="Search products…" style="width:220px;">
                </div>
                <div class="adm-card-body" id="productsTable">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
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
            loadProducts();
            $('#searchProduct').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadProducts, 250);
            });
            $('#importProductsBtn').on('click', openImportProducts);
        });

        // ---- Bulk product import (Excel/CSV) ----
        // Creates NEW products only — a row whose name already exists, or
        // whose category doesn't match an existing category, is skipped and
        // reported, never silently overwritten or invented.
        function openImportProducts() {
            Swal.fire({
                title: 'Import Products from File',
                html: `
                    <p style="text-align:left;margin:0 0 10px;color:#5a5650;font-size:14px;">
                        Upload a <strong>CSV</strong> or <strong>Excel (.xlsx)</strong> file with a header row
                        containing <strong>Product Name, Category, Quantity, Price</strong> (required) and
                        optionally <strong>Discount Price, Stock, Rating, Description, Benefits</strong>.
                        Quantity needs a unit, e.g. "500g", "1kg", "750ml" or "1L". Category must match an
                        existing category exactly — rows with an unrecognized category are skipped and listed
                        afterwards so nothing gets miscategorized.
                    </p>
                    <p style="text-align:left;margin:0 0 10px;color:#8a8478;font-size:13px;">
                        A product that already exists (by name) is skipped, not changed. No photo can come from
                        a spreadsheet — imported products start with no image; add one later by editing the
                        product. PDF isn't supported — please save/export the list as CSV or Excel instead.
                    </p>
                    <input type="file" id="swal-import-products-file" class="swal2-file" accept=".csv,.xlsx">
                `,
                confirmButtonText: 'Import',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const fileInput = document.getElementById('swal-import-products-file');
                    if (!fileInput.files || fileInput.files.length === 0) {
                        Swal.showValidationMessage('Choose a CSV or Excel file first');
                        return false;
                    }
                    return fileInput.files[0];
                }
            }).then((result) => {
                if (result.isConfirmed) submitImportProducts(result.value);
            });
        }

        function submitImportProducts(file) {
            Swal.fire({ title: 'Importing…', text: 'Reading and creating products, please wait.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            const formData = new FormData();
            formData.append('import_file', file);

            $.ajax({
                url: '../assets/db_query/admin/import_products.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showImportProductsSummary(response.summary, response.created);
                        loadProducts();
                    } else {
                        Swal.fire({ title: 'Could not import', text: response.message || 'The file could not be processed.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not import', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function showImportProductsSummary(summary, created) {
            let html = `<div style="text-align:left;font-size:14px;">
                <p><strong>${summary.created_count}</strong> new product(s) created out of <strong>${summary.total_rows}</strong> row(s) read.</p>`;

            if (created && created.length > 0) {
                html += '<div style="max-height:150px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;margin-bottom:10px;">';
                created.forEach(p => {
                    html += `<div>${escapeHtml(p.name)} — ${escapeHtml(p.category)}, ${escapeHtml(p.quantity)}, ₹${parseFloat(p.price).toFixed(2)}</div>`;
                });
                html += '</div>';
            }

            if (summary.skipped_existing && summary.skipped_existing.length > 0) {
                html += `<p style="color:#8a8478;margin-bottom:4px;"><strong>${summary.skipped_existing.length} row(s) skipped</strong> — a product with that name already exists:</p>`;
                html += '<div style="max-height:100px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;margin-bottom:10px;">' + summary.skipped_existing.map(escapeHtml).join('<br>') + '</div>';
            }

            if (summary.skipped_unknown_category && summary.skipped_unknown_category.length > 0) {
                html += `<p style="color:#c0392b;margin-bottom:4px;"><strong>${summary.skipped_unknown_category.length} row(s) skipped</strong> — category didn't match an existing one:</p>`;
                html += '<div style="max-height:100px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;margin-bottom:10px;">';
                summary.skipped_unknown_category.forEach(u => { html += `<div>${escapeHtml(u.name)}: "${escapeHtml(u.category)}"</div>`; });
                html += '</div>';
            }

            if (summary.invalid && summary.invalid.length > 0) {
                html += `<p style="color:#c0392b;margin-bottom:4px;"><strong>${summary.invalid.length} row(s) had missing/invalid data</strong> and were skipped:</p>`;
                html += '<div style="max-height:120px;overflow-y:auto;border:1px solid #e5e1d8;border-radius:6px;padding:8px;">';
                summary.invalid.forEach(u => { html += `<div>${escapeHtml(u.name)}: ${escapeHtml(u.reason)}</div>`; });
                html += '</div>';
            }

            html += '</div>';

            const hasIssues = (summary.skipped_existing && summary.skipped_existing.length > 0) ||
                (summary.skipped_unknown_category && summary.skipped_unknown_category.length > 0) ||
                (summary.invalid && summary.invalid.length > 0);

            Swal.fire({ title: 'Import complete', html: html, icon: hasIssues ? 'warning' : 'success', confirmButtonColor: '#1c5034', width: 560 });
        }

        function loadProducts() {
            const search = $('#searchProduct').val();
            $.ajax({
                url: '../assets/db_query/admin/get_products.php' + (search ? '?search=' + encodeURIComponent(search) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayProducts(data.products);
                    } else {
                        $('#productsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load products.') + '</div>');
                    }
                },
                error: function() {
                    $('#productsTable').html('<div class="adm-error">Could not reach the server while loading products.</div>');
                }
            });
        }

        function displayProducts(products) {
            if (products.length === 0) {
                $('#productsTable').html('<div class="adm-empty"><i class="fas fa-box-open"></i><p><strong>No products found</strong></p><p>Try a different search, or add a new product.</p></div>');
                return;
            }

            let rows = '';
            products.forEach(product => {
                const price = parseFloat(product.price || 0);
                const disPrice = parseFloat(product.dis_price || 0);
                rows += `<tr>
                    <td><img src="../assets/uploads/${encodeURI(product.image || 'no-image.jpg')}" class="adm-thumb" alt="${escapeHtml(product.product_name)}"></td>
                    <td class="adm-cell-title">${escapeHtml(product.product_name)}</td>
                    <td><span class="adm-badge is-info">${escapeHtml(product.category || 'Uncategorized')}</span></td>
                    <td class="adm-money">₹${price.toFixed(2)}</td>
                    <td class="adm-money">${disPrice ? '₹' + disPrice.toFixed(2) : '<span class="adm-cell-sub">—</span>'}</td>
                    <td>${product.stock ? escapeHtml(String(product.stock)) : '<span class="adm-cell-sub">—</span>'}</td>
                    <td>
                        <a href="../new_product.php?id=${product.id}" class="adm-icon-btn" title="Edit ${escapeHtml(product.product_name)}">
                            <i class="fas fa-pen"></i>
                        </a>
                        <button class="adm-icon-btn is-danger delete-product" data-product-id="${product.id}" data-product-name="${escapeHtml(product.product_name)}" title="Delete ${escapeHtml(product.product_name)}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });

            $('#productsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Discount price</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.delete-product').on('click', function() {
                deleteProduct($(this).data('product-id'), $(this).data('product-name'));
            });
        }

        function deleteProduct(productId, productName) {
            Swal.fire({
                title: 'Delete this product?',
                text: `"${productName}" will be permanently removed. This can't be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#a8442f',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/delete_product.php',
                        type: 'POST',
                        data: { product_id: productId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Deleted', text: 'Product removed.', icon: 'success', confirmButtonColor: '#1c5034' });
                                loadProducts();
                            } else {
                                Swal.fire({ title: 'Could not delete', text: response.message || 'The product was not removed.', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not delete', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    });
                }
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }
    </script>
</body>
</html>
