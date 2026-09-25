<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers — Valluvam Admin</title>
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
                    <h1>Customers</h1>
                    <div class="adm-sub">Everyone who has created an account</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All customers</h2>
                    <input type="text" id="searchCustomer" class="adm-input" placeholder="Search name, email, phone…" style="width:260px;">
                </div>
                <div class="adm-card-body" id="customersTable">
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
        let searchTimer = null;

        $(document).ready(function() {
            loadCustomers();
            $('#searchCustomer').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadCustomers, 250);
            });
        });

        function loadCustomers() {
            const search = $('#searchCustomer').val();
            $.ajax({
                url: '../assets/db_query/admin/get_customers.php' + (search ? '?search=' + encodeURIComponent(search) : ''),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayCustomers(data.customers);
                    } else {
                        $('#customersTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load customers.') + '</div>');
                    }
                },
                error: function() {
                    $('#customersTable').html('<div class="adm-error">Could not reach the server while loading customers.</div>');
                }
            });
        }

        function displayCustomers(customers) {
            if (customers.length === 0) {
                $('#customersTable').html('<div class="adm-empty"><i class="fas fa-users"></i><p><strong>No customers found</strong></p><p>Try a different search.</p></div>');
                return;
            }

            let rows = '';
            customers.forEach(c => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(c.username)}</td>
                    <td>${escapeHtml(c.email)}</td>
                    <td>${escapeHtml(c.phone_number || '—')}</td>
                    <td class="adm-money">${c.order_count ?? 0}</td>
                    <td class="adm-cell-sub">${formatDate(c.created_at)}</td>
                </tr>`;
            });

            $('#customersTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Username</th><th>Email</th><th>Phone</th><th>Orders</th><th>Joined</th></tr></thead>
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
