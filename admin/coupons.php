<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons — Valluvam Admin</title>
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
                    <h1>Coupons</h1>
                    <div class="adm-sub">Discount codes</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addCouponBtn"><i class="fas fa-plus"></i> New coupon</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>All coupons</h2></div>
                <div class="adm-card-body" id="couponsTable">
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
            loadCoupons();
            $('#addCouponBtn').on('click', function() { editCoupon(null); });
        });

        function loadCoupons() {
            $.ajax({
                url: '../assets/db_query/admin/get_coupons.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayCoupons(data.coupons);
                    } else {
                        $('#couponsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load coupons.') + '</div>');
                    }
                },
                error: function() {
                    $('#couponsTable').html('<div class="adm-error">Could not reach the server while loading coupons.</div>');
                }
            });
        }

        function displayCoupons(coupons) {
            if (coupons.length === 0) {
                $('#couponsTable').html('<div class="adm-empty"><i class="fas fa-ticket"></i><p><strong>No coupons yet</strong></p><p>Create one to get started.</p></div>');
                return;
            }

            let rows = '';
            coupons.forEach(c => {
                const discount = c.discount_type === 'percent' ? `${c.discount_value}%` : `₹${parseFloat(c.discount_value).toFixed(2)}`;
                rows += `<tr>
                    <td class="adm-cell-title"><code>${escapeHtml(c.code)}</code></td>
                    <td>${escapeHtml(c.description || '—')}</td>
                    <td>${discount}</td>
                    <td>${c.used_count}${c.max_uses ? ' / ' + c.max_uses : ''}</td>
                    <td>${c.expires_at ? formatDate(c.expires_at) : '<span class="adm-cell-sub">No expiry</span>'}</td>
                    <td>${Number(c.is_active) === 1 ? '<span class="adm-badge is-green">Active</span>' : '<span class="adm-badge is-neutral">Inactive</span>'}</td>
                    <td>
                        <button class="adm-icon-btn edit-coupon" data-coupon='${JSON.stringify(c).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="adm-icon-btn is-danger delete-coupon" data-id="${c.id}" data-code="${escapeHtml(c.code)}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });

            $('#couponsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Code</th><th>Description</th><th>Discount</th><th>Uses</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-coupon').on('click', function() { editCoupon($(this).data('coupon')); });
            $('.delete-coupon').on('click', function() { deleteCoupon($(this).data('id'), $(this).data('code')); });
        }

        function editCoupon(coupon) {
            Swal.fire({
                title: coupon ? `Edit ${coupon.code}` : 'New coupon',
                html: `
                    <input id="swal-code" class="swal2-input" placeholder="Code (e.g. WELCOME10)" value="${coupon ? escapeHtml(coupon.code) : ''}">
                    <input id="swal-desc" class="swal2-input" placeholder="Description" value="${coupon ? escapeHtml(coupon.description || '') : ''}">
                    <select id="swal-type" class="swal2-input">
                        <option value="percent" ${!coupon || coupon.discount_type === 'percent' ? 'selected' : ''}>Percent off</option>
                        <option value="flat" ${coupon && coupon.discount_type === 'flat' ? 'selected' : ''}>Flat amount off</option>
                    </select>
                    <input id="swal-value" type="number" step="0.01" class="swal2-input" placeholder="Discount value" value="${coupon ? coupon.discount_value : ''}">
                    <input id="swal-min" type="number" step="0.01" class="swal2-input" placeholder="Minimum order amount (optional)" value="${coupon ? coupon.min_order_amount : ''}">
                    <input id="swal-max-uses" type="number" class="swal2-input" placeholder="Max uses (optional)" value="${coupon && coupon.max_uses ? coupon.max_uses : ''}">
                    <input id="swal-expires" type="date" class="swal2-input" value="${coupon && coupon.expires_at ? coupon.expires_at.substring(0,10) : ''}">
                `,
                confirmButtonText: coupon ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const code = $('#swal-code').val().trim();
                    const value = parseFloat($('#swal-value').val());
                    if (!code || !value) {
                        Swal.showValidationMessage('Code and discount value are required');
                        return false;
                    }
                    return {
                        id: coupon ? coupon.id : null,
                        code: code,
                        description: $('#swal-desc').val().trim(),
                        discount_type: $('#swal-type').val(),
                        discount_value: value,
                        min_order_amount: $('#swal-min').val() || 0,
                        max_uses: $('#swal-max-uses').val() || '',
                        expires_at: $('#swal-expires').val() || ''
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveCoupon(result.value);
            });
        }

        function saveCoupon(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_coupon.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadCoupons();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The coupon was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function deleteCoupon(id, code) {
            Swal.fire({
                title: 'Delete this coupon?',
                text: `"${code}" will be permanently removed.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#a8442f',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/delete_coupon.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Deleted', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadCoupons();
                            } else {
                                Swal.fire({ title: 'Could not delete', text: response.message || 'The coupon was not removed.', icon: 'error', confirmButtonColor: '#1c5034' });
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
