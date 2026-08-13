$(document).ready(function () {
    loadCartCount();
});

function loadCartCount() {
    $.ajax({
        url: "assets/db_query/header/header_query.php?action=CartCount",
        type: "GET",
        dataType: "json",
        success: function (res) {
            if (res.status === "success") {
                $("#cartCount").text("[" + res.count + "]");
            } else {
                $("#cartCount").text("[0]");
            }
        }
    });
}
// Global add-to-cart handler with double-click prevention
// This handles both #add-to-cart (ID) and .add-to-cart (class) selectors
let addToCartProcessing = false;

$(document).off('click', '#add-to-cart, .add-to-cart').on('click', '#add-to-cart, .add-to-cart', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Prevent double-click/double execution
    if (addToCartProcessing) {
        return false;
    }
    
    let product_id = $(this).data('id');
    
    // Validate product_id
    if (!product_id) {
        // If no data-id, try to get from href or other attributes
        let href = $(this).attr('href');
        if (href && href.includes('product=')) {
            product_id = href.split('product=')[1].split('&')[0];
        }
        if (!product_id) {
            console.warn('No product ID found');
            return false;
        }
    }
    
    // Set processing flag
    addToCartProcessing = true;
    
    // Disable button temporarily
    let $btn = $(this);
    let originalText = $btn.html();
    $btn.prop('disabled', true).html('Adding...');

    $.ajax({
        url: 'assets/db_query/cart/cart_query.php?action=add',
        type: 'POST',
        data: { product_id: product_id },
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                SwalHelper.ecommerce.addedToCart();
                loadCartCount();
                
                // Update quantity instantly in frontend if cart table exists
                let row = $(`#cartTable tr[data-id="${product_id}"]`);
                if (row.length) {
                    let qtyCell = row.find('td').eq(5);
                    let qty = parseInt(qtyCell.text()) + 1;
                    qtyCell.text(qty);

                    // Update total cell
                    let price = parseFloat(row.find('td').eq(4).text().replace('₹', ''));
                    row.find('td').eq(6).text(`₹${(price * qty).toFixed(2)}`);
                }
            } else if (res.status === 'not_logged_in') {
                SwalHelper.ecommerce.notLoggedIn().then(() => {
                    $('#popupForm').fadeIn();
                });
            } else {
                SwalHelper.ecommerce.cartError(res.message);
            }
        },
        error: function() {
            SwalHelper.ecommerce.networkError();
        },
        complete: function() {
            // Reset processing flag and button state
            addToCartProcessing = false;
            $btn.prop('disabled', false).html(originalText);
        }
    });
    
    return false;
});
// Global wishlist handler with double-click prevention
let wishlistProcessing = false;

$(document).off('click', '.wishlist-btn').on('click', '.wishlist-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Prevent double-click/double execution
    if (wishlistProcessing) {
        return false;
    }
    
    const productId = $(this).data('product-id');
    
    // Validate product ID
    if (!productId) {
        console.warn('No product ID found for wishlist');
        SwalHelper.ecommerce.wishlistError('Invalid product. Please try again.');
        return false;
    }
    
    // Set processing flag
    wishlistProcessing = true;
    
    // Disable button temporarily and add visual feedback
    let $btn = $(this);
    let originalHtml = $btn.html();
    $btn.css('opacity', '0.6').css('pointer-events', 'none');

    $.ajax({
        url: 'assets/db_query/wishlist/wishlist_query.php?action=add',
        type: 'POST',
        data: { product_id: productId },
        dataType: 'json',
        success: function (res) {
            if (res.status === 'not_logged_in') {
                SwalHelper.ecommerce.notLoggedIn().then(() => {
                    $('#popupForm').fadeIn();
                });
            }
            else if (res.status === 'exists') {
                SwalHelper.ecommerce.wishlistExists();
            }
            else if (res.status === 'added') {
                SwalHelper.ecommerce.addedToWishlist();
                // Optionally update wishlist icon to show it's added
                $btn.css('color', '#ff0000'); // Red color to indicate added
            }
            else {
                SwalHelper.ecommerce.wishlistError(res.message);
            }
        },
        error: function () {
            SwalHelper.ecommerce.networkError();
        },
        complete: function() {
            // Reset processing flag and button state
            wishlistProcessing = false;
            $btn.css('opacity', '1').css('pointer-events', 'auto');
        }
    });
    
    return false;
});
