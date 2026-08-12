<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body { background-color: #f5f5f5; }
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1000;
        }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu a { display: block; padding: 15px 20px; color: white; text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); padding-left: 25px; }
        .sidebar-menu a.active { background: rgba(255,255,255,0.2); border-left: 4px solid white; }
        .sidebar-menu i { width: 20px; margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 30px; }
        .header-bar { background: white; padding: 15px 30px; margin: -30px -30px 30px -30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .product-image { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user-shield"></i> Admin Panel</h4>
            <small>Valluvam Products</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="products.php" class="active"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header-bar d-flex justify-content-between align-items-center">
            <h2>Products Management</h2>
            <a href="../new_product.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Products</h5>
                <div>
                    <input type="text" id="searchProduct" class="form-control form-control-sm" placeholder="Search products..." style="display: inline-block; width: 200px;">
                </div>
            </div>
            <div class="card-body">
                <div id="productsTable">Loading products...</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            loadProducts();
            $('#searchProduct').on('keyup', function() {
                loadProducts();
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
                        $('#productsTable').html('<p class="text-danger">' + (data.message || 'Failed to load products') + '</p>');
                    }
                },
                error: function() {
                    $('#productsTable').html('<p class="text-danger">Failed to load products</p>');
                }
            });
        }

        function displayProducts(products) {
            if (products.length === 0) {
                $('#productsTable').html('<p class="text-muted">No products found</p>');
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>' +
                '<th>Image</th><th>Product Name</th><th>Category</th><th>Price</th><th>Discount Price</th><th>Stock</th><th>Actions</th>' +
                '</tr></thead><tbody>';

            products.forEach(product => {
                html += `<tr>
                    <td><img src="../assets/uploads/${product.image || 'no-image.jpg'}" class="product-image" alt="${product.product_name}"></td>
                    <td><strong>${product.product_name}</strong></td>
                    <td><span class="badge badge-info">${product.category || 'N/A'}</span></td>
                    <td>₹${parseFloat(product.price || 0).toFixed(2)}</td>
                    <td>₹${parseFloat(product.dis_price || 0).toFixed(2)}</td>
                    <td>${product.stock || 'N/A'}</td>
                    <td>
                        <a href="../new_product.php?id=${product.id}" class="btn btn-sm btn-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button class="btn btn-sm btn-danger delete-product" data-product-id="${product.id}" data-product-name="${product.product_name}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            $('#productsTable').html(html);

            // Handle delete
            $('.delete-product').on('click', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                deleteProduct(productId, productName);
            });
        }

        function deleteProduct(productId, productName) {
            Swal.fire({
                title: 'Delete Product?',
                text: `Are you sure you want to delete "${productName}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, Delete',
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
                                Swal.fire('Deleted!', 'Product has been deleted.', 'success');
                                loadProducts();
                            } else {
                                Swal.fire('Error', response.message || 'Failed to delete product', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Failed to delete product', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>

