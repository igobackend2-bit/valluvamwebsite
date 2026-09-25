<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Requests — Valluvam Admin</title>
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
                    <h1>Account Requests</h1>
                    <div class="adm-sub">Account deletion, data export, wholesale upgrade and reactivation requests</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>Requests</h2>
                    <select id="filterStatus" class="adm-input" style="width:180px;">
                        <option value="">All statuses</option>
                        <option value="pending" selected>Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="adm-card-body" id="requestsTable">
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
            loadRequests();
            $('#filterStatus').on('change', loadRequests);
        });

        function loadRequests() {
            const status = $('#filterStatus').val();
            $.ajax({
                url: '../assets/db_query/admin/get_account_requests.php' + (status ? '?status=' + encodeURIComponent(status) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayRequests(data.requests);
                    } else {
                        $('#requestsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load requests.') + '</div>');
                    }
                },
                error: function() {
                    $('#requestsTable').html('<div class="adm-error">Could not reach the server while loading requests.</div>');
                }
            });
        }

        const typeLabels = {
            account_deletion: 'Account deletion',
            data_export: 'Data export',
            wholesale_upgrade: 'Wholesale upgrade',
            reactivation: 'Reactivation',
            email_change: 'Email change',
            other: 'Other'
        };

        function displayRequests(requests) {
            if (requests.length === 0) {
                $('#requestsTable').html('<div class="adm-empty"><i class="fas fa-user-xmark"></i><p><strong>No requests found</strong></p></div>');
                return;
            }

            let rows = '';
            requests.forEach(r => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(r.username || r.email)}</td>
                    <td><span class="adm-badge is-info">${escapeHtml(typeLabels[r.request_type] || r.request_type)}</span></td>
                    <td style="max-width:260px;">${escapeHtml(r.details || '—')}</td>
                    <td>${statusBadge(r.status)}</td>
                    <td class="adm-cell-sub">${formatDate(r.created_at)}</td>
                    <td>${r.status === 'pending' ? `
                        <button class="adm-icon-btn resolve-btn" data-id="${r.id}" data-status="approved" title="Approve"><i class="fas fa-check"></i></button>
                        <button class="adm-icon-btn is-danger resolve-btn" data-id="${r.id}" data-status="rejected" title="Reject"><i class="fas fa-xmark"></i></button>
                        <button class="adm-icon-btn resolve-btn" data-id="${r.id}" data-status="completed" title="Mark completed"><i class="fas fa-flag-checkered"></i></button>
                    ` : '<span class="adm-cell-sub">—</span>'}</td>
                </tr>`;
            });

            $('#requestsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Customer</th><th>Type</th><th>Details</th><th>Status</th><th>Requested</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.resolve-btn').on('click', function() {
                resolveRequest($(this).data('id'), $(this).data('status'));
            });
        }

        function statusBadge(status) {
            const map = {
                pending: ['is-amber', 'Pending'],
                approved: ['is-green', 'Approved'],
                rejected: ['is-danger', 'Rejected'],
                completed: ['is-neutral', 'Completed']
            };
            const [cls, label] = map[status] || map.pending;
            return `<span class="adm-badge ${cls}">${label}</span>`;
        }

        function resolveRequest(id, status) {
            Swal.fire({
                title: `Mark this request as ${status}?`,
                input: 'text',
                inputPlaceholder: 'Optional note…',
                showCancelButton: true,
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/resolve_account_request.php',
                        type: 'POST',
                        data: { id: id, status: status, admin_notes: result.value || '' },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Updated', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadRequests();
                            } else {
                                Swal.fire({ title: 'Could not update', text: response.message || 'The request was not updated.', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
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
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>
