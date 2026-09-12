$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all spices again
        if (query.length === 0) {
            loadSpices();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-spices").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-spices");

        $.ajax({
            url: "assets/db_query/spices/spices_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_spices", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-spices", "search", "spices.php");
                    updateCategoryCount(0, "#spices-count");
                    return;
                }
                renderProducts(res.data, "#products-spices");
                updateCategoryCount(res.data.length, "#spices-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-spices", "error");
            }
        });
    });

    // Load all spices products
    function loadSpices() {
        renderCategorySkeleton("#products-spices");
        $.ajax({
            url: "assets/db_query/spices/spices_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "spices_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-spices", "empty");
                    updateCategoryCount(0, "#spices-count");
                    return;
                }
                renderProducts(res.data, "#products-spices");
                updateCategoryCount(res.data.length, "#spices-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-spices", "error");
            }
        });
    }

    // Single renderer used for both search + all products
    function renderProducts(products, containerId) {
        let html = "";
        products.forEach(function (product) {
            html += buildCategoryProductCard(product);
        });
        $(containerId).html(html);
    }

    fetchSpicesProducts();
});

function fetchSpicesProducts() {
    renderCategorySkeleton("#products-spices");
    $.ajax({
        url: 'assets/db_query/spices/spices_query.php?action=spices_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-spices').html(html);
                updateCategoryCount(res.data.length, "#spices-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-spices', 'empty');
                updateCategoryCount(0, "#spices-count");
            } else {
                renderCategoryEmptyState('#products-spices', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-spices', 'error');
        }
    });
}
