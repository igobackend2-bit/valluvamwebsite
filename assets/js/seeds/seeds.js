// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by seeds_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentSeedsDataset = [];
var currentSeedsSort = "default";

function sortSeedsProducts(products) {
    var list = products.slice();
    if (currentSeedsSort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentSeedsSort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentSeedsSort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (seeds_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedSeeds(products, containerId) {
    currentSeedsDataset = products || [];
    var sorted = sortSeedsProducts(currentSeedsDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#seeds-sort", function () {
        currentSeedsSort = $(this).val();
        renderSortedSeeds(currentSeedsDataset, "#products-seeds");
    });

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all seeds again
        if (query.length === 0) {
            loadSeeds();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-seeds").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-seeds");

        $.ajax({
            url: "assets/db_query/seeds/seeds_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_seeds", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-seeds", "search", "seeds.php");
                    updateCategoryCount(0, "#seeds-count");
                    return;
                }
                renderProducts(res.data, "#products-seeds");
                updateCategoryCount(res.data.length, "#seeds-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-seeds", "error");
            }
        });
    });

    // Load all seeds products
    function loadSeeds() {
        renderCategorySkeleton("#products-seeds");
        $.ajax({
            url: "assets/db_query/seeds/seeds_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "seeds_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-seeds", "empty");
                    updateCategoryCount(0, "#seeds-count");
                    return;
                }
                renderProducts(res.data, "#products-seeds");
                updateCategoryCount(res.data.length, "#seeds-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-seeds", "error");
            }
        });
    }

    // Single renderer used for both search + all products - now routes through
    // renderSortedSeeds so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedSeeds(products, containerId);
    }

    fetchSeedsProducts();
});

function fetchSeedsProducts() {
    renderCategorySkeleton("#products-seeds");
    $.ajax({
        url: 'assets/db_query/seeds/seeds_query.php?action=seeds_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                renderSortedSeeds(res.data, '#products-seeds');
                updateCategoryCount(res.data.length, "#seeds-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-seeds', 'empty');
                updateCategoryCount(0, "#seeds-count");
            } else {
                renderCategoryEmptyState('#products-seeds', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-seeds', 'error');
        }
    });
}
