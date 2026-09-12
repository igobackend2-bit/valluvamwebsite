$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all dryfruits again
        if (query.length === 0) {
            loadDryfruits();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-dryfruits").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-dryfruits");

        $.ajax({
            url: "assets/db_query/dryfruits/dryfruits_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_dryfruits", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-dryfruits", "search", "dryfruits.php");
                    updateCategoryCount(0, "#dryfruits-count");
                    return;
                }
                renderProducts(res.data, "#products-dryfruits");
                updateCategoryCount(res.data.length, "#dryfruits-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-dryfruits", "error");
            }
        });
    });

    // Load all dryfruits products
    function loadDryfruits() {
        renderCategorySkeleton("#products-dryfruits");
        $.ajax({
            url: "assets/db_query/dryfruits/dryfruits_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "dryfruits_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-dryfruits", "empty");
                    updateCategoryCount(0, "#dryfruits-count");
                    return;
                }
                renderProducts(res.data, "#products-dryfruits");
                updateCategoryCount(res.data.length, "#dryfruits-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-dryfruits", "error");
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

    fetchDryfruitsProducts();
});

function fetchDryfruitsProducts() {
    renderCategorySkeleton("#products-dryfruits");
    $.ajax({
        url: 'assets/db_query/dryfruits/dryfruits_query.php?action=dryfruits_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-dryfruits').html(html);
                updateCategoryCount(res.data.length, "#dryfruits-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-dryfruits', 'empty');
                updateCategoryCount(0, "#dryfruits-count");
            } else {
                renderCategoryEmptyState('#products-dryfruits', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-dryfruits', 'error');
        }
    });
}
