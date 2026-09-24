// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by ghee_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentGheeDataset = [];
var currentGheeSort = "default";

function sortGheeProducts(products) {
    var list = products.slice();
    if (currentGheeSort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentGheeSort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentGheeSort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (ghee_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedGhee(products, containerId) {
    currentGheeDataset = products || [];
    var sorted = sortGheeProducts(currentGheeDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#ghee-sort", function () {
        currentGheeSort = $(this).val();
        renderSortedGhee(currentGheeDataset, "#products-ghee");
    });

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all ghee again
        if (query.length === 0) {
            loadGhee();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-ghee").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-ghee");

        $.ajax({
            url: "assets/db_query/ghee/ghee_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_ghee", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-ghee", "search", "ghee.php");
                    updateCategoryCount(0, "#ghee-count");
                    return;
                }
                renderProducts(res.data, "#products-ghee");
                updateCategoryCount(res.data.length, "#ghee-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-ghee", "error");
            }
        });
    });

    // Load all ghee products
    function loadGhee() {
        renderCategorySkeleton("#products-ghee");
        $.ajax({
            url: "assets/db_query/ghee/ghee_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "ghee_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-ghee", "empty");
                    updateCategoryCount(0, "#ghee-count");
                    return;
                }
                renderProducts(res.data, "#products-ghee");
                updateCategoryCount(res.data.length, "#ghee-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-ghee", "error");
            }
        });
    }

    // Single renderer used for both search + all products - now routes through
    // renderSortedGhee so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedGhee(products, containerId);
    }

    fetchGheeProducts();
});

function fetchGheeProducts() {
    renderCategorySkeleton("#products-ghee");
    $.ajax({
        url: 'assets/db_query/ghee/ghee_query.php?action=ghee_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                renderSortedGhee(res.data, '#products-ghee');
                updateCategoryCount(res.data.length, "#ghee-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-ghee', 'empty');
                updateCategoryCount(0, "#ghee-count");
            } else {
                renderCategoryEmptyState('#products-ghee', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-ghee', 'error');
        }
    });
}
