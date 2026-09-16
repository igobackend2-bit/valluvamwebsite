// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by rice_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentRiceDataset = [];
var currentRiceSort = "default";

function sortRiceProducts(products) {
    var list = products.slice();
    if (currentRiceSort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentRiceSort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentRiceSort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (rice_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedRice(products, containerId) {
    currentRiceDataset = products || [];
    var sorted = sortRiceProducts(currentRiceDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#rice-sort", function () {
        currentRiceSort = $(this).val();
        renderSortedRice(currentRiceDataset, "#products-rice");
    });

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

    // Single renderer used for both search + all products - now routes through
    // renderSortedRice so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedRice(products, containerId);
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
                renderSortedRice(res.data, '#products-rice');
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
