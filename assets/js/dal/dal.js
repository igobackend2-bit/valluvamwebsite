// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by dal_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentDalDataset = [];
var currentDalSort = "default";

function sortDalProducts(products) {
    var list = products.slice();
    if (currentDalSort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentDalSort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentDalSort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (dal_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedDal(products, containerId) {
    currentDalDataset = products || [];
    var sorted = sortDalProducts(currentDalDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#dal-sort", function () {
        currentDalSort = $(this).val();
        renderSortedDal(currentDalDataset, "#products-dal");
    });

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all dal again
        if (query.length === 0) {
            loadDal();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-dal").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-dal");

        $.ajax({
            url: "assets/db_query/dal/dal_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_dal", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-dal", "search", "dal.php");
                    updateCategoryCount(0, "#dal-count");
                    return;
                }
                renderProducts(res.data, "#products-dal");
                updateCategoryCount(res.data.length, "#dal-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-dal", "error");
            }
        });
    });

    // Load all dal products
    function loadDal() {
        renderCategorySkeleton("#products-dal");
        $.ajax({
            url: "assets/db_query/dal/dal_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "dal_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-dal", "empty");
                    updateCategoryCount(0, "#dal-count");
                    return;
                }
                renderProducts(res.data, "#products-dal");
                updateCategoryCount(res.data.length, "#dal-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-dal", "error");
            }
        });
    }

    // Single renderer used for both search + all products - now routes through
    // renderSortedDal so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedDal(products, containerId);
    }

    fetchDalProducts();
});

function fetchDalProducts() {
    renderCategorySkeleton("#products-dal");
    $.ajax({
        url: 'assets/db_query/dal/dal_query.php?action=dal_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                renderSortedDal(res.data, '#products-dal');
                updateCategoryCount(res.data.length, "#dal-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-dal', 'empty');
                updateCategoryCount(0, "#dal-count");
            } else {
                renderCategoryEmptyState('#products-dal', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-dal', 'error');
        }
    });
}
