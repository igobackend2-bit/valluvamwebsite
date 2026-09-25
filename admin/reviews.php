<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews — Valluvam Admin</title>
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
                    <h1>Reviews</h1>
                    <div class="adm-sub">Reviews go live on the product page as soon as a customer submits them. Reject hides one from the site without deleting it; Delete removes it permanently.</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All reviews</h2>
                    <select id="filterApproved" class="adm-input" style="width:180px;">
                        <option value="">All</option>
                        <option value="0">Hidden</option>
                        <option value="1">Live on site</option>
                    </select>
                </div>
                <div class="adm-card-body" id="reviewsTable">
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
            loadReviews();
            $('#filterApproved').on('change', loadReviews);
        });

        function loadReviews() {
            const approved = $('#filterApproved').val();
            $.ajax({
                url: '../assets/db_query/admin/get_reviews.php' + (approved !== '' ? '?approved=' + approved : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayReviews(data.reviews);
                    } else {
                        $('#reviewsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load reviews.') + '</div>');
                    }
                },
                error: function() {
                    $('#reviewsTable').html('<div class="adm-error">Could not reach the server while loading reviews.</div>');
                }
            });
        }

        function displayReviews(reviews) {
            if (reviews.length === 0) {
                $('#reviewsTable').html('<div class="adm-empty"><i class="fas fa-star"></i><p><strong>No reviews yet</strong></p><p>Reviews submitted on product pages will show up here.</p></div>');
                return;
            }

            let rows = '';
            reviews.forEach(r => {
                const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(r.product_name || ('#' + r.product_id))}</td>
                    <td>${escapeHtml(r.reviewer_name || 'Anonymous')}</td>
                    <td>${stars}</td>
                    <td style="max-width:280px;">${escapeHtml(r.review_text || '—')}</td>
                    <td>${Number(r.is_approved) === 1 ? '<span class="adm-badge is-green">Live on site</span>' : '<span class="adm-badge is-amber">Hidden</span>'}</td>
                    <td class="adm-cell-sub">${formatDate(r.created_at)}</td>
                    <td>
                        ${Number(r.is_approved) === 1
                            ? `<button class="adm-icon-btn reject-review" data-id="${r.id}" title="Reject (hide from site)"><i class="fas fa-eye-slash"></i></button>`
                            : `<button class="adm-icon-btn approve-review" data-id="${r.id}" title="Approve (show on site)"><i class="fas fa-check"></i></button>`}
                        <button class="adm-icon-btn is-danger delete-review" data-id="${r.id}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });

            $('#reviewsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Product</th><th>Reviewer</th><th>Rating</th><th>Review</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.approve-review').on('click', function() { setReviewApproved($(this).data('id'), 'approve'); });
            $('.reject-review').on('click', function() { setReviewApproved($(this).data('id'), 'reject'); });
            $('.delete-review').on('click', function() { deleteReview($(this).data('id')); });
        }

        function setReviewApproved(id, action) {
            $.ajax({
                url: '../assets/db_query/admin/moderate_review.php',
                type: 'POST',
                data: { id: id, action: action },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        loadReviews();
                    } else {
                        Swal.fire({ title: 'Could not update review', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not update review', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function deleteReview(id) {
            Swal.fire({
                title: 'Delete this review?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#a8442f',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/moderate_review.php',
                        type: 'POST',
                        data: { id: id, action: 'delete' },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Deleted', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadReviews();
                            } else {
                                Swal.fire({ title: 'Could not delete', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not delete', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    });
                }
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN');
        }
    </script>
</body>
</html>
