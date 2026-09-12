$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all nuts again
        if (query.length === 0) {
            loadNuts();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-nuts").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-nuts");

        $.ajax({
            url: "assets/db_query/nuts/nuts_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_nuts", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-nuts", "search", "nuts.php");
                    updateCategoryCount(0, "#nuts-count");
                    return;
                }
                renderProducts(res.data, "#products-nuts");
                updateCategoryCount(res.data.length, "#nuts-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-nuts", "error");
            }
        });
    });

    // Load all nuts products
    function loadNuts() {
        renderCategorySkeleton("#products-nuts");
        $.ajax({
            url: "assets/db_query/nuts/nuts_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "nuts_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-nuts", "empty");
                    updateCategoryCount(0, "#nuts-count");
                    return;
                }
                renderProducts(res.data, "#products-nuts");
                updateCategoryCount(res.data.length, "#nuts-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-nuts", "error");
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

    fetchNutsProducts();
});

function fetchNutsProducts() {
    renderCategorySkeleton("#products-nuts");
    $.ajax({
        url: 'assets/db_query/nuts/nuts_query.php?action=nuts_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-nuts').html(html);
                updateCategoryCount(res.data.length, "#nuts-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-nuts', 'empty');
                updateCategoryCount(0, "#nuts-count");
            } else {
                renderCategoryEmptyState('#products-nuts', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-nuts', 'error');
        }
    });
}
