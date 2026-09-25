<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads — Valluvam Admin</title>
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
                    <h1>Leads</h1>
                    <div class="adm-sub">Messages submitted through the site's contact form</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All leads</h2>
                    <select id="filterStatus" class="adm-input" style="width:180px;">
                        <option value="">All statuses</option>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="adm-card-body" id="leadsTable">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
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
            loadLeads();
            $('#filterStatus').on('change', loadLeads);
        });

        function loadLeads() {
            const status = $('#filterStatus').val();
            $.ajax({
                url: '../assets/db_query/admin/get_leads.php' + (status ? '?status=' + encodeURIComponent(status) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayLeads(data.leads);
                    } else {
                        $('#leadsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load leads.') + '</div>');
                    }
                },
                error: function() {
                    $('#leadsTable').html('<div class="adm-error">Could not reach the server while loading leads.</div>');
                }
            });
        }

        function displayLeads(leads) {
            if (leads.length === 0) {
                $('#leadsTable').html('<div class="adm-empty"><i class="fas fa-inbox"></i><p><strong>No leads found</strong></p><p>Contact form submissions will show up here.</p></div>');
                return;
            }

            let rows = '';
            leads.forEach(l => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(l.name)}</td>
                    <td>${escapeHtml(l.email)}<br><span class="adm-cell-sub">${escapeHtml(l.phone || '—')}</span></td>
                    <td>${escapeHtml(l.subject || '—')}</td>
                    <td style="max-width:280px;">${escapeHtml(l.message)}</td>
                    <td>
                        <select class="adm-input lead-status" data-id="${l.id}" style="width:130px;">
                            <option value="new" ${l.lead_status === 'new' ? 'selected' : ''}>New</option>
                            <option value="contacted" ${l.lead_status === 'contacted' ? 'selected' : ''}>Contacted</option>
                            <option value="closed" ${l.lead_status === 'closed' ? 'selected' : ''}>Closed</option>
                        </select>
                    </td>
                    <td class="adm-cell-sub">${formatDate(l.created_at)}</td>
                </tr>`;
            });

            $('#leadsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Name</th><th>Contact</th><th>Subject</th><th>Message</th><th>Status</th><th>Received</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.lead-status').on('change', function() {
                updateLeadStatus($(this).data('id'), $(this).val());
            });
        }

        function updateLeadStatus(id, status) {
            $.ajax({
                url: '../assets/db_query/admin/update_lead_status.php',
                type: 'POST',
                data: { id: id, status: status },
                dataType: 'json',
                success: function(response) {
                    if (response.status !== 'success') {
                        Swal.fire({ title: 'Could not update', text: response.message || 'The status was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
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
