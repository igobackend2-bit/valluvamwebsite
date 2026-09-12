$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all rice again
        if (query.length === 0) {
            loadRice();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-rice").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-rice");

        $.ajax({
            url: "assets/db_query/rice/rice_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_rice", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-rice", "search", "rice.php");
                    updateCategoryCount(0, "#rice-count");
                    return;
                }
                renderProducts(res.data, "#products-rice");
                updateCategoryCount(res.data.length, "#rice-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-rice", "error");
            }
        });
    });

    // Load all rice products
    function loadRice() {
        renderCategorySkeleton("#products-rice");
        $.ajax({
            url: "assets/db_query/rice/rice_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "rice_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-rice", "empty");
                    updateCategoryCount(0, "#rice-count");
                    return;
                }
                renderProducts(res.data, "#products-rice");
                updateCategoryCount(res.data.length, "#rice-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-rice", "error");
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

    fetchRiceProducts();
});

function fetchRiceProducts() {
    renderCategorySkeleton("#products-rice");
    $.ajax({
        url: 'assets/db_query/rice/rice_query.php?action=rice_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-rice').html(html);
                updateCategoryCount(res.data.length, "#rice-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-rice', 'empty');
                updateCategoryCount(0, "#rice-count");
            } else {
                renderCategoryEmptyState('#products-rice', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-rice', 'error');
        }
    });
}
