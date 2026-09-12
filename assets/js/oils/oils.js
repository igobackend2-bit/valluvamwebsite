$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all oils again
        if (query.length === 0) {
            loadOils();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-oils").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-oils");

        $.ajax({
            url: "assets/db_query/oils/oils_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_oils", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-oils", "search", "oils.php");
                    updateCategoryCount(0, "#oils-count");
                    return;
                }
                renderProducts(res.data, "#products-oils");
                updateCategoryCount(res.data.length, "#oils-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-oils", "error");
            }
        });
    });

    // Load all oils products
    function loadOils() {
        renderCategorySkeleton("#products-oils");
        $.ajax({
            url: "assets/db_query/oils/oils_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "oils_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-oils", "empty");
                    updateCategoryCount(0, "#oils-count");
                    return;
                }
                renderProducts(res.data, "#products-oils");
                updateCategoryCount(res.data.length, "#oils-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-oils", "error");
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

    fetchOilsProducts();
});

function fetchOilsProducts() {
    renderCategorySkeleton("#products-oils");
    $.ajax({
        url: 'assets/db_query/oils/oils_query.php?action=oils_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-oils').html(html);
                updateCategoryCount(res.data.length, "#oils-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-oils', 'empty');
                updateCategoryCount(0, "#oils-count");
            } else {
                renderCategoryEmptyState('#products-oils', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-oils', 'error');
        }
    });
}
