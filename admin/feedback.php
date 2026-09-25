<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback — Valluvam Admin</title>
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
                    <h1>Feedback</h1>
                    <div class="adm-sub">Delivery/order feedback left by customers</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>All feedback</h2></div>
                <div class="adm-card-body" id="feedbackTable">
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
    <script>
        $(document).ready(function() { loadFeedback(); });

        function loadFeedback() {
            $.ajax({
                url: '../assets/db_query/admin/get_feedback.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayFeedback(data.feedback);
                    } else {
                        $('#feedbackTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load feedback.') + '</div>');
                    }
                },
                error: function() {
                    $('#feedbackTable').html('<div class="adm-error">Could not reach the server while loading feedback.</div>');
                }
            });
        }

        function displayFeedback(feedback) {
            if (feedback.length === 0) {
                $('#feedbackTable').html('<div class="adm-empty"><i class="fas fa-comment"></i><p><strong>No feedback yet</strong></p><p>Post-delivery feedback from customers will show up here.</p></div>');
                return;
            }

            let rows = '';
            feedback.forEach(f => {
                const stars = '★'.repeat(f.rating) + '☆'.repeat(5 - f.rating);
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(f.receipt || ('#' + f.order_id))}</td>
                    <td>${escapeHtml(f.first_name || '') + ' ' + escapeHtml(f.last_name || '')}</td>
                    <td>${stars}</td>
                    <td style="max-width:320px;">${escapeHtml(f.comments || '—')}</td>
                    <td class="adm-cell-sub">${formatDate(f.created_at)}</td>
                </tr>`;
            });

            $('#feedbackTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Rating</th><th>Comments</th><th>Submitted</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>
