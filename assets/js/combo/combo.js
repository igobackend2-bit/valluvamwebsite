$(document).ready(function () {
    fetchComboProducts();
});

function fetchComboProducts() {
    renderCategorySkeleton("#products-combo");
    $.ajax({
        url: 'assets/db_query/combo/combo_query.php?action=combo_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success' && res.data && res.data.length) {
                let html = '';
                res.data.forEach(function (product) {
                    html += buildCategoryProductCard(product);
                });
                $('#products-combo').html(html);
                updateCategoryCount(res.data.length, "#combo-count");
            } else if (res.status === 'success') {
                renderCategoryEmptyState('#products-combo', 'empty');
                updateCategoryCount(0, "#combo-count");
            } else {
                renderCategoryEmptyState('#products-combo', 'error');
            }
        },
        error: function () {
            renderCategoryEmptyState('#products-combo', 'error');
        }
    });
}
