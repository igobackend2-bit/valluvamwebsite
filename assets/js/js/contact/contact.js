$(document).ready(function () {
    $("form#contactForm").on("submit", function (e) {
        e.preventDefault();

        $.ajax({
            url: "assets/db_query/contact/contact_query.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    SwalHelper.success('Message Sent!', response.message || 'Thank you for contacting us. We will get back to you soon.');
                    $("#contactForm")[0].reset();
                } else {
                    SwalHelper.error('Message Failed', response.message || 'Unable to send your message. Please try again or contact us directly.');
                }
            },
            error: function () {
                SwalHelper.ecommerce.networkError();
            }
        });
    });
});
