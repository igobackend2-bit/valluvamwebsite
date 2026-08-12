$("#checkoutForm").on("submit", function(e) {
    e.preventDefault(); // Stop form from reloading page

    $.ajax({
        url: "assets/db_query/checkout/checkout_query.php",
        type: "POST",
        data: $(this).serialize(),
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                SwalHelper.ecommerce.orderSuccess(response.order_id).then(() => {
                    $("#checkoutForm")[0].reset();
                    // Optionally redirect to order confirmation page
                    // window.location.href = "order-confirmation.php?id=" + response.order_id;
                });
            } else {
                SwalHelper.ecommerce.orderError(response.message);
            }
        },
        error: function() {
            SwalHelper.ecommerce.networkError();
        }
    });
});
