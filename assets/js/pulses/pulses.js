// Holds whichever product list is currently on screen (full list or a search result) so
// the sort dropdown can re-order it client-side without a new request. Sorting only uses
// real columns already returned by pulses_query.php (price / dis_price / timestamp) - no
// "featured" or "popular" option, since product_details has no such data to sort by.
var currentPulsesDataset = [];
var currentPulsesSort = "default";

function sortPulsesProducts(products) {
    var list = products.slice();
    if (currentPulsesSort === "price-asc") {
        list.sort(function (a, b) {
            return parseFloat(a.dis_price || a.price || 0) - parseFloat(b.dis_price || b.price || 0);
        });
    } else if (currentPulsesSort === "price-desc") {
        list.sort(function (a, b) {
            return parseFloat(b.dis_price || b.price || 0) - parseFloat(a.dis_price || a.price || 0);
        });
    } else if (currentPulsesSort === "newest") {
        list.sort(function (a, b) {
            // timestamp column falls back to id (pulses_products already orders by id DESC,
            // i.e. newest first) for any row where timestamp isn't present.
            var ta = a.timestamp ? new Date(a.timestamp).getTime() : parseInt(a.id, 10) || 0;
            var tb = b.timestamp ? new Date(b.timestamp).getTime() : parseInt(b.id, 10) || 0;
            return tb - ta;
        });
    }
    return list;
}

function renderSortedPulses(products, containerId) {
    currentPulsesDataset = products || [];
    var sorted = sortPulsesProducts(currentPulsesDataset);
    var html = "";
    sorted.forEach(function (product) {
        html += buildCategoryProductCard(product);
    });
    $(containerId).html(html);
}

$(document).ready(function () {
    $(document).on("change", "#pulses-sort", function () {
        currentPulsesSort = $(this).val();
        renderSortedPulses(currentPulsesDataset, "#products-pulses");
    });

    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();

        // If empty, show all pulses again
        if (query.length === 0) {
            loadPulses();
            return;
        }

        // Require 2 chars
        if (query.length < 2) {
            $("#products-pulses").html('<p class="w-100 text-center text-muted py-4">Type at least 2 characters to search...</p>');
            return;
        }

        renderCategorySkeleton("#products-pulses");

        $.ajax({
            url: "assets/db_query/pulses/pulses_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "product_search_pulses", query: query },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-pulses", "search", "pulses.php");
                    updateCategoryCount(0, "#pulses-count");
                    return;
                }
                renderProducts(res.data, "#products-pulses");
                updateCategoryCount(res.data.length, "#pulses-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-pulses", "error");
            }
        });
    });

    // Load all pulses products
    function loadPulses() {
        renderCategorySkeleton("#products-pulses");
        $.ajax({
            url: "assets/db_query/pulses/pulses_query.php",
            method: "GET",
            dataType: "json",
            cache: false,
            data: { action: "pulses_products" },
            success: function (res) {
                if (res.status !== "success" || !res.data || !res.data.length) {
                    renderCategoryEmptyState("#products-pulses", "empty");
                    updateCategoryCount(0, "#pulses-count");
                    return;
                }
                renderProducts(res.data, "#products-pulses");
                updateCategoryCount(res.data.length, "#pulses-count");
            },
            error: function (xhr) {
                console.log(xhr.status, xhr.responseText);
                renderCategoryEmptyState("#products-pulses", "error");
            }
        });
    }

    // Single renderer used for both search + all products - now routes through
    // renderSortedPulses so whichever sort is selected stays applied.
    function renderProducts(products, containerId) {
        renderSortedPulses(products, containerId);
    }

    fetchPulsesProducts();
});

function fetchPulsesProducts() {
    renderCategorySkeleton("#products-pulses");
    $.ajax({
        url: 'assets/db_query/pulses/pulses_query.php?action=pulses_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                renderSortedPulses(res.data, '#products-pulses');
                updateCategoryCount(res.data.length, "#pulses-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-pulses', 'empty');
                updateCategoryCount(0, "#pulses-count");
            } else {
                renderCategoryEmptyState('#products-pulses', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-pulses', 'error');
        }
    });
}
