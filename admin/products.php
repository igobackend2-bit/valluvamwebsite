<?php
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/includes/sidebar.php';
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
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Products</h1>
                    <div class="adm-sub">Add, edit and retire catalog items</div>
                </div>
                <a href="../new_product.php" class="adm-btn adm-btn-primary">
                    <i class="fas fa-plus"></i> Add product
                </a>
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
        });

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
