// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by honey_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentHoneyDataset = [];
var currentHoneySort = "default";

function sortHoneyProducts(products) {
    var list = products.slice();
    if (currentHoneySort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentHoneySort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentHoneySort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (honey_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedHoney(products, containerId) {
    currentHoneyDataset = products || [];
    var sorted = sortHoneyProducts(currentHoneyDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#honey-sort", function () {
        currentHoneySort = $(this).val();
        renderSortedHoney(currentHoneyDataset, "#products-honey");
    });

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all honey again
        if (query.length === 0) {
            loadHoney();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-honey").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-honey");

        $.ajax({
            url: "assets/db_query/honey/honey_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_honey", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-honey", "search", "honey.php");
                    updateCategoryCount(0, "#honey-count");
                    return;
                }
                renderProducts(res.data, "#products-honey");
                updateCategoryCount(res.data.length, "#honey-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-honey", "error");
            }
        });
    });

    // Load all honey products
    function loadHoney() {
        renderCategorySkeleton("#products-honey");
        $.ajax({
            url: "assets/db_query/honey/honey_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "honey_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-honey", "empty");
                    updateCategoryCount(0, "#honey-count");
                    return;
                }
                renderProducts(res.data, "#products-honey");
                updateCategoryCount(res.data.length, "#honey-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-honey", "error");
            }
        });
    }

    // Single renderer used for both search + all products - now routes through
    // renderSortedHoney so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedHoney(products, containerId);
    }

    fetchHoneyProducts();
});

function fetchHoneyProducts() {
    renderCategorySkeleton("#products-honey");
    $.ajax({
        url: 'assets/db_query/honey/honey_query.php?action=honey_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                renderSortedHoney(res.data, '#products-honey');
                updateCategoryCount(res.data.length, "#honey-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-honey', 'empty');
                updateCategoryCount(0, "#honey-count");
            } else {
                renderCategoryEmptyState('#products-honey', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-honey', 'error');
        }
    });
}
