<?php 
$actionpage = basename($_SERVER['PHP_SELF'], ".php");
include 'header.php';
?>

    <style>
        .v-wishlist-container {
            max-width: 1100px;
            margin: 40px auto 60px;
            padding: 0 15px;
        }

        .v-wishlist-card {
            background: #ffffff;
            border-radius: var(--v-radius-lg, 16px);
            border: 1px solid #eae5d9;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .v-wishlist-header {
            padding: 24px 30px;
            border-bottom: 1px solid #ede7dc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #faf7f2;
        }

        .v-wishlist-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #133826;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--v-font-serif, serif);
        }

        .v-wishlist-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .v-wishlist-table th {
            background: #f4efe6;
            color: #133826;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 20px;
            border-bottom: 1px solid #e5dec9;
        }

        .v-wishlist-table td {
            padding: 18px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f2ece0;
            font-size: 14px;
        }

        .v-wishlist-table tr:last-child td {
            border-bottom: none;
        }

        .v-wishlist-img-frame {
            width: 72px;
            height: 72px;
            background: #fbf9f4;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #eae5d9;
        }

        .v-wishlist-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            padding: 4px;
        }

        .v-wishlist-name {
            font-weight: 700;
            color: #1a2420;
            text-decoration: none;
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
            transition: color 0.2s;
        }

        .v-wishlist-name:hover {
            color: #133826;
            text-decoration: none;
        }

        .v-wishlist-cat {
            font-size: 12px;
            color: #8c8273;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .v-wishlist-price {
            font-size: 16px;
            font-weight: 800;
            color: #133826;
        }

        .v-wishlist-remove {
            color: #c0392b;
            background: rgba(192, 57, 43, 0.08);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .v-wishlist-remove:hover {
            background: #c0392b;
            color: #ffffff;
        }

        .v-wishlist-empty {
            text-align: center;
            padding: 60px 20px;
        }

        .v-wishlist-empty i {
            font-size: 48px;
            color: #d8d0c2;
            margin-bottom: 16px;
        }

        .v-wishlist-empty h4 {
            font-size: 18px;
            font-weight: 700;
            color: #133826;
            margin-bottom: 8px;
        }

        .v-wishlist-empty p {
            font-size: 14px;
            color: #726859;
            margin-bottom: 20px;
        }

        @media (max-width: 767.98px) {
            .v-wishlist-table thead {
                display: none;
            }
            .v-wishlist-table, .v-wishlist-table tbody, .v-wishlist-table tr, .v-wishlist-table td {
                display: block;
                width: 100%;
            }
            .v-wishlist-table tr {
                padding: 16px;
                border-bottom: 1px solid #f2ece0;
                position: relative;
            }
            .v-wishlist-table td {
                padding: 6px 0;
                border-bottom: none;
                text-align: left !important;
            }
            .v-wishlist-remove-td {
                position: absolute;
                top: 14px;
                right: 14px;
                width: auto !important;
            }
        }
    </style>

    <!-- Page Breadcrumbs Banner -->
    <div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
        <div class="container">
            <div class="row no-gutters slider-text align-items-center justify-content-center">
                <div class="col-md-9 ftco-animate text-center">
                    <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Wishlist</span></p>
                    <h1 class="mb-0 bread">My Wishlist</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Wishlist Container -->
    <div class="container v-wishlist-container">
        <div id="loginNotice" style="display:none;" class="alert alert-warning p-4 rounded-lg mb-4 text-center">
            <i class="fa-solid fa-lock mr-2"></i>
            Please <a href="javascript:void(0)" onclick="openForm()" class="font-weight-bold text-success" style="text-decoration:underline;">login to your account</a> to view and sync your saved wishlist items.
        </div>

        <div class="v-wishlist-card">
            <div class="v-wishlist-header">
                <h2><i class="fa-solid fa-heart" style="color:#c59a45;"></i> Saved Items</h2>
                <a href="shop.php" class="v-btn-tertiary" style="color:#133826; font-size:13px; font-weight:600;">+ Explore More Products</a>
            </div>

            <div id="wishlistContent">
                <table class="v-wishlist-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">&nbsp;</th>
                            <th style="width: 90px;">Product</th>
                            <th>Details</th>
                            <th>Unit Price</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="wishlistTable">
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-success" role="status"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-migrate-3.0.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>

    <script>
        $(document).ready(function() {
            function loadWishlist() {
                $.ajax({
                    url: 'assets/db_query/wishlist/wishlist_query.php?action=get',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'not_logged_in') {
                            $('#loginNotice').show();
                            $('#wishlistContent').html(`
                                <div class="v-wishlist-empty">
                                    <i class="fa-solid fa-user-lock"></i>
                                    <h4>Sign In Required</h4>
                                    <p>Login to see your saved favourites across all your devices.</p>
                                    <button type="button" class="v-btn-primary" onclick="openForm()">Sign In Now</button>
                                </div>
                            `);
                            return;
                        }

                        if (res.status !== 'success' || !res.wishlist || !res.wishlist.length) {
                            $('#wishlistContent').html(`
                                <div class="v-wishlist-empty">
                                    <i class="fa-regular fa-heart"></i>
                                    <h4>Your wishlist is empty</h4>
                                    <p>Explore our natural foods and click the heart icon to save favourites.</p>
                                    <a href="shop.php" class="v-btn-primary">Browse Catalogue &rarr;</a>
                                </div>
                            `);
                            return;
                        }

                        const rows = res.wishlist.map(item => {
                            const price = parseFloat(item.dis_price || item.price || 0);
                            const imgUrl = item.product_image ? (item.product_image.startsWith('http') || item.product_image.startsWith('assets/') || item.product_image.startsWith('images/') ? item.product_image : 'assets/uploads/' + item.product_image) : 'images/logo.png';
                            const detailUrl = productUrl(item.category, item.product_name);

                            return `
                                <tr data-wishlist-id="${item.id}">
                                    <td class="v-wishlist-remove-td">
                                        <button type="button" class="v-wishlist-remove remove-wishlist" data-id="${item.id}" title="Remove from Wishlist" aria-label="Remove item">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <a href="${detailUrl}">
                                            <div class="v-wishlist-img-frame">
                                                <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(item.product_name)}" class="v-wishlist-img" loading="lazy">
                                            </div>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="${detailUrl}" class="v-wishlist-name">${escapeHtml(item.product_name)}</a>
                                        <p class="v-wishlist-cat">${escapeHtml(item.category || '')} ${item.quantity ? ' &bull; ' + escapeHtml(item.quantity) : ''}</p>
                                    </td>
                                    <td>
                                        <span class="v-wishlist-price">&#8377;${price.toFixed(2)}</span>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="v-btn-primary add-to-cart" data-id="${item.product_id}" style="padding: 9px 18px; font-size: 13px;">
                                            <i class="fa-solid fa-bag-shopping"></i> Add to Cart
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join('');

                        $('#wishlistTable').html(rows);
                    },
                    error: function() {
                        $('#wishlistContent').html(`
                            <div class="v-wishlist-empty">
                                <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                                <h4>Unable to load wishlist</h4>
                                <p>Please refresh the page and try again.</p>
                            </div>
                        `);
                    }
                });
            }

            // Remove item from wishlist
            $(document).on('click', '.remove-wishlist', function(e) {
                e.preventDefault();
                const wishId = $(this).data('id');
                const $row = $(this).closest('tr');

                $.ajax({
                    url: 'assets/db_query/wishlist/wishlist_query.php?action=delete',
                    type: 'POST',
                    data: { wishlist_id: wishId },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            $row.fadeOut(200, function() {
                                $(this).remove();
                                if ($('#wishlistTable tr').length === 0) {
                                    loadWishlist();
                                }
                            });
                        } else {
                            alert(res.message || 'Could not remove item');
                        }
                    }
                });
            });

            function escapeHtml(text) {
                if (text === null || text === undefined) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            loadWishlist();
        });
    </script>

    <?php include 'footer.php'; ?>