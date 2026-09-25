<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Categories</h1>
                    <div class="adm-sub">Product categories — new ones appear immediately in the product form's Category dropdown, and on the homepage category slider if you add a thumbnail and link.</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addCategoryBtn"><i class="fas fa-plus"></i> New category</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>All categories</h2></div>
                <div class="adm-card-body" id="categoriesTable">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                        </tbody></table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            loadCategories();
            $('#addCategoryBtn').on('click', function() { editCategory(null); });
        });

        function loadCategories() {
            $.ajax({
                url: '../assets/db_query/admin/get_categories.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayCategories(data.categories);
                    } else {
                        $('#categoriesTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load categories.') + '</div>');
                    }
                },
                error: function() {
                    $('#categoriesTable').html('<div class="adm-error">Could not reach the server while loading categories.</div>');
                }
            });
        }

        function displayCategories(categories) {
            if (categories.length === 0) {
                $('#categoriesTable').html('<div class="adm-empty"><i class="fas fa-tags"></i><p><strong>No categories yet</strong></p><p>Add one to get started.</p></div>');
                return;
            }

            let rows = '';
            categories.forEach(c => {
                const thumb = c.thumbnali ? `<img src="../assets/thumbnail/${encodeURI(c.thumbnali)}" class="adm-thumb" alt="${escapeHtml(c.category_name)}">` : '<span class="adm-cell-sub">No thumbnail</span>';
                rows += `<tr>
                    <td>${thumb}</td>
                    <td class="adm-cell-title">${escapeHtml(c.category_name)}</td>
                    <td>${c.link ? escapeHtml(c.link) : '<span class="adm-cell-sub">—</span>'}</td>
                    <td>${c.thumbnali && c.link ? '<span class="adm-badge is-green">Shows on homepage slider</span>' : '<span class="adm-badge is-neutral">Product form only</span>'}</td>
                    <td>
                        <button class="adm-icon-btn edit-category" data-category='${JSON.stringify(c).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>
                    </td>
                </tr>`;
            });

            $('#categoriesTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Thumbnail</th><th>Category</th><th>Link</th><th>Homepage slider</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-category').on('click', function() { editCategory($(this).data('category')); });
        }

        function editCategory(category) {
            Swal.fire({
                title: category ? `Edit ${category.category_name}` : 'New category',
                html: `
                    <input id="swal-cat-name" class="swal2-input" placeholder="Category name" value="${category ? escapeHtml(category.category_name) : ''}">
                    <input id="swal-cat-link" class="swal2-input" placeholder="Link (e.g. rice.php) — optional" value="${category ? escapeHtml(category.link || '') : ''}">
                    <label style="display:block;text-align:left;margin:8px 12px 2px;font-size:13px;color:#5a5650;">Thumbnail image (optional — needed for the homepage slider)</label>
                    <input type="file" id="swal-cat-thumb" class="swal2-file" accept=".jpg,.jpeg,.png,.webp,.gif">
                    ${category && category.thumbnali ? `<div style="margin-top:6px;"><img src="../assets/thumbnail/${encodeURI(category.thumbnali)}" style="max-width:120px;border-radius:6px;"></div>` : ''}
                `,
                confirmButtonText: category ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const name = $('#swal-cat-name').val().trim();
                    if (!name) {
                        Swal.showValidationMessage('Category name is required');
                        return false;
                    }
                    return {
                        id: category ? category.id : null,
                        category_name: name,
                        link: $('#swal-cat-link').val().trim(),
                        thumbnail: document.getElementById('swal-cat-thumb').files[0] || null
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveCategory(result.value);
            });
        }

        function saveCategory(data) {
            const formData = new FormData();
            if (data.id) formData.append('id', data.id);
            formData.append('category_name', data.category_name);
            formData.append('link', data.link);
            if (data.thumbnail) formData.append('thumbnail', data.thumbnail);

            $.ajax({
                url: '../assets/db_query/admin/save_category.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadCategories();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The category was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }
    </script>
</body>
</html>
