$(document).ready(function () {
    $("form#b2bEnquiryForm").on("submit", function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find(".btn-submit-enquiry");
        var originalText = $btn.text();
        $btn.prop("disabled", true).text("Submitting...");

        $.ajax({
            url: "assets/db_query/b2b/b2b_enquiry_query.php",
            type: "POST",
            data: $form.serialize(),
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    if (typeof SwalHelper !== "undefined") {
                        SwalHelper.success("Enquiry Sent!", response.message || "Thank you. Our team will contact you within 24 hrs.");
                    } else {
                        alert(response.message || "Thank you. Our team will contact you within 24 hrs.");
                    }
                    $form[0].reset();
                } else {
                    if (typeof SwalHelper !== "undefined") {
                        SwalHelper.error("Submission Failed", response.message || "Unable to send your enquiry. Please try again.");
                    } else {
                        alert(response.message || "Unable to send your enquiry. Please try again.");
                    }
                }
            },
            error: function () {
                if (typeof SwalHelper !== "undefined" && SwalHelper.ecommerce) {
                    SwalHelper.ecommerce.networkError();
                } else {
                    alert("Network error. Please try again.");
                }
            },
            complete: function () {
                $btn.prop("disabled", false).text(originalText);
            }
        });
    });
});
