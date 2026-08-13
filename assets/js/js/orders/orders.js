$(document).on('click', '#place-order', function (e) {
    e.preventDefault();

    let formData = {
        first_name: $('#firstname').val(),
        last_name: $('#lastname').val(),
        state: $('#state').val(),
        street_address: $('#streetaddress').val(),
        apartment: $('#apartment').val(),
        city: $('#city').val(),
        postcode: $('#postcode').val(),
        phone: $('#phone').val(),
        email: $('#email').val(),
        payment_method: $('input[name="optradio"]:checked').val(),
        action: 'place_order'
    };

    $.ajax({
        url: 'assets/db_query/orders/orders_query.php?action=place_order',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                Swal.fire('Order Placed!', res.message, 'success');
            } else {
                Swal.fire('Error!', res.message, 'error');
            }
        }
    });
});
