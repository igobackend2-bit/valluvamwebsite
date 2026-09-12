$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all millets again
        if (query.length === 0) {
            loadMillets();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-millets").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-millets");

        $.ajax({
            url: "assets/db_query/millets/millets_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_millets", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-millets", "search", "millets.php");
                    updateCategoryCount(0, "#millets-count");
                    return;
                }
                renderProducts(res.data, "#products-millets");
                updateCategoryCount(res.data.length, "#millets-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-millets", "error");
            }
        });
    });

    // Load all millets products
    function loadMillets() {
        renderCategorySkeleton("#products-millets");
        $.ajax({
            url: "assets/db_query/millets/millets_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "millets_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-millets", "empty");
                    updateCategoryCount(0, "#millets-count");
                    return;
                }
                renderProducts(res.data, "#products-millets");
                updateCategoryCount(res.data.length, "#millets-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-millets", "error");
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

    fetchMilletsProducts();
});

function fetchMilletsProducts() {
    renderCategorySkeleton("#products-millets");
    $.ajax({
        url: 'assets/db_query/millets/millets_query.php?action=millets_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-millets').html(html);
                updateCategoryCount(res.data.length, "#millets-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-millets', 'empty');
                updateCategoryCount(0, "#millets-count");
            } else {
                renderCategoryEmptyState('#products-millets', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-millets', 'error');
        }
    });
}
