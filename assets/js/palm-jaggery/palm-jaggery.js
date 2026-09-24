// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by palm-jaggery_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentPalmJaggeryDataset = [];
var currentPalmJaggerySort = "default";

function sortPalmJaggeryProducts(products) {
    var list = products.slice();
    if (currentPalmJaggerySort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentPalmJaggerySort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentPalmJaggerySort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (palmjaggery_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedPalmJaggery(products, containerId) {
    currentPalmJaggeryDataset = products || [];
    var sorted = sortPalmJaggeryProducts(currentPalmJaggeryDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#palm-jaggery-sort", function () {
        currentPalmJaggerySort = $(this).val();
        renderSortedPalmJaggery(currentPalmJaggeryDataset, "#products-palm_jaggery");
    });

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all palm jaggery again
        if (query.length === 0) {
            loadPalmJaggery();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-palm_jaggery").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-palm_jaggery");

        $.ajax({
            url: "assets/db_query/palm-jaggery/palm-jaggery_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_palmjaggery", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-palm_jaggery", "search", "palm-jaggery.php");
                    updateCategoryCount(0, "#palm-jaggery-count");
                    return;
                }
                renderProducts(res.data, "#products-palm_jaggery");
                updateCategoryCount(res.data.length, "#palm-jaggery-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-palm_jaggery", "error");
            }
        });
    });

    // Load all palm jaggery products
    function loadPalmJaggery() {
        renderCategorySkeleton("#products-palm_jaggery");
        $.ajax({
            url: "assets/db_query/palm-jaggery/palm-jaggery_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "palmjaggery_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-palm_jaggery", "empty");
                    updateCategoryCount(0, "#palm-jaggery-count");
                    return;
                }
                renderProducts(res.data, "#products-palm_jaggery");
                updateCategoryCount(res.data.length, "#palm-jaggery-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-palm_jaggery", "error");
            }
        });
    }

    // Single renderer used for both search + all products - now routes through
    // renderSortedPalmJaggery so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedPalmJaggery(products, containerId);
    }

    fetchPalmJaggeryProducts();
});

function fetchPalmJaggeryProducts() {
    renderCategorySkeleton("#products-palm_jaggery");
    $.ajax({
        url: 'assets/db_query/palm-jaggery/palm-jaggery_query.php?action=palmjaggery_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                renderSortedPalmJaggery(res.data, '#products-palm_jaggery');
                updateCategoryCount(res.data.length, "#palm-jaggery-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-palm_jaggery', 'empty');
                updateCategoryCount(0, "#palm-jaggery-count");
            } else {
                renderCategoryEmptyState('#products-palm_jaggery', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-palm_jaggery', 'error');
        }
    });
}
