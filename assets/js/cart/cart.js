$(document).ready(function () {
    loadCart();
    proceedtoCheckout();
    removeitems();
    // Remove from frontend only
    // $(document).on('click', '.remove-item', function () {
    //     $(this).closest('tr').remove();
    //     updateTotals();
    // });

});
function loadCart() {
    $.getJSON('assets/db_query/cart/cart_query.php?action=fetch', function (data) {

        if (data.status === 'success') {

            let cartHTML = '';
            let subtotal = 0;

            data.cart.forEach(item => {

                let total = item.dis_price * item.quantity;
                subtotal += total;

                cartHTML += `
                <tr data-id="${item.cart_id}">
                  <td><button type="button" class="btn btn-sm remove-item">X</button></td>
                  <td><img src="assets/uploads/${item.image}" style="width:60px;height:60px;"></td>
                  <td>${item.product_name}</td>
                  <td>₹${item.dis_price}</td>
                  <td>${item.quantity}</td>
                  <td class="line-total">₹${(item.dis_price * item.quantity).toFixed(2)}</td>
                </tr>`;


            });

            $("#cartTable").html(cartHTML);

            $("#subtotal").text("₹" + subtotal.toFixed(2));
            $("#total").text("₹" + subtotal.toFixed(2));
        }
    });
}


// Remove item from Cart
function removeitems() {
    const CART_API = "assets/db_query/cart/cart_query.php";

    $(document).on("click", ".remove-item", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const $row = $btn.closest("tr");
        const cart_id = parseInt($row.attr("data-id"), 10);

        if (!cart_id) {
            SwalHelper.error('Cart Error', 'Unable to identify the item. Please refresh the page and try again.');
            return;
        }

        // Get current quantity to show appropriate message
        const currentQty = parseInt($row.find('td').eq(4).text()) || 1;
        const confirmMessage = currentQty > 1 
            ? `This will decrease the quantity from ${currentQty} to ${currentQty - 1}. Click again to remove completely.`
            : 'Are you sure you want to remove this item from your cart?';

        // Show confirmation dialog
        SwalHelper.confirm(
            currentQty > 1 ? 'Decrease Quantity?' : 'Remove Item?',
            confirmMessage,
            currentQty > 1 ? 'Yes, Decrease' : 'Yes, Remove',
            'Cancel'
        ).then((result) => {
            if (!result.isConfirmed) {
                return; // User cancelled
            }

            // Proceed with removal
            $btn.prop("disabled", true);

            $.ajax({
                url: CART_API,
                type: "POST",
                dataType: "json",
                data: { action: "remove", cart_id: cart_id },
                success: function (res) {
                    console.log("REMOVE RES:", res);

                    if (res.status === "success") {
                        // Check if item was completely removed or just quantity decreased
                        if (res.removed === true) {
                            // Item completely removed - remove row
                            $row.remove();
                            
                            // Show success message
                            SwalHelper.success('Item Removed', 'The item has been completely removed from your cart.', 1500);

                            // If cart is now empty, show message
                            if ($("#cartTable tr").length === 0) {
                                setTimeout(() => {
                                    SwalHelper.info('Cart Empty', 'Your cart is now empty. Continue shopping to add items.');
                                }, 1600);
                            }
                        } else {
                            // Quantity decreased - update the row
                            const newQuantity = res.new_quantity || 1;
                            const qtyCell = $row.find('td').eq(4); // Quantity column (5th column, 0-indexed)
                            const priceCell = $row.find('td').eq(3); // Price column
                            const totalCell = $row.find('.line-total'); // Total column
                            
                            // Update quantity
                            qtyCell.text(newQuantity);
                            
                            // Update line total
                            const price = parseFloat(priceCell.text().replace('₹', ''));
                            const newTotal = (price * newQuantity).toFixed(2);
                            totalCell.text('₹' + newTotal);
                            
                            // Show success message
                            SwalHelper.success('Quantity Updated', `Quantity decreased to ${newQuantity}.`, 1500);
                        }

                        // ✅ update totals instantly
                        recalcTotals();

                        // ✅ update header count instantly (if function exists)
                        if (typeof loadCartCount === 'function') {
                            loadCartCount();
                        }

                    } else {
                        $btn.prop("disabled", false);
                        SwalHelper.ecommerce.cartError(res.message || "Unable to remove item from cart. Please try again.");
                    }
                },
                error: function (xhr) {
                    $btn.prop("disabled", false);
                    console.log("HTTP:", xhr.status);
                    console.log("RAW:", xhr.responseText);
                    
                    if (xhr.status === 404) {
                        SwalHelper.error('404 Error', 'The cart service could not be found. Please refresh the page and try again.');
                    } else {
                        SwalHelper.ecommerce.serverError();
                    }
                }
            });
        }); // End of confirmation then()
    });

    function recalcTotals() {
        let subtotal = 0;

        $("#cartTable tr").each(function () {
            const line = $(this).find(".line-total").text();
            const value = parseFloat(String(line).replace(/[^0-9.]/g, "")) || 0;
            subtotal += value;
        });

        const delivery = 0;
        const discount = 3;
        const total = Math.max(0, subtotal + delivery);

        $("#subtotal").text(subtotal.toFixed(2));
        $("#total").text(total.toFixed(2));
    }

}

// Update totals in frontend
function updateTotals() {
    let subtotal = 0;
    $('#cartTable tr').each(function () {
        let total = parseFloat($(this).find('td').eq(6).text().replace('₹', ''));
        subtotal += total;
    });
    $('#subtotal').text(`₹${subtotal.toFixed(2)}`);
    $('#total').text(`₹${subtotal.toFixed(2)}`);
}
function proceedtoCheckout() {
    $("#checkoutBtn").on("click", function (e) {
        e.preventDefault();

        // Get current cart data
        $.ajax({
            url: "assets/db_query/cart/cart_query.php?action=fetch",
            type: "GET",
            dataType: "json",
            success: function (res) {
                if (res.status === "success" && res.cart && res.cart.length > 0) {
                    // Calculate totals
                    let subtotal = 0;
                    res.cart.forEach(item => {
                        subtotal += item.dis_price * item.quantity;
                    });
                    
                    const delivery = 0.00;
                    const discount = 3.00;
                    const total = Math.max(0, subtotal + delivery - discount);

                    // Store cart data in sessionStorage
                    sessionStorage.setItem('checkout_cart', JSON.stringify(res.cart));
                    sessionStorage.setItem('checkout_subtotal', subtotal.toFixed(2));
                    sessionStorage.setItem('checkout_delivery', delivery.toFixed(2));
                    sessionStorage.setItem('checkout_discount', discount.toFixed(2));
                    sessionStorage.setItem('checkout_total', total.toFixed(2));

                    // Also store in PHP session via AJAX
                    $.ajax({
                        url: "assets/db_query/cart/cart_query.php",
                        type: "POST",
                        dataType: "json",
                        data: { 
                            action: "proceedtocheckout",
                            subtotal: subtotal,
                            total: total
                        },
                        success: function (sessionRes) {
                            SwalHelper.loading('Preparing checkout...');
                            window.location.href = "checkout.php";
                        },
                        error: function () {
                            // Even if session save fails, proceed with sessionStorage
                            SwalHelper.loading('Preparing checkout...');
                            window.location.href = "checkout.php";
                        }
                    });
                } else {
                    SwalHelper.ecommerce.cartError("Your cart is empty. Please add items to proceed to checkout.");
                }
            },
            error: function () {
                SwalHelper.ecommerce.networkError();
            }
        });
    });
}

