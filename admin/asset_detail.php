<?php
require_once __DIR__ . '/includes/check_admin.php';
$assetPk = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset detail — Valluvam Admin</title>
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
                    <h1 id="pageTitle">Asset detail</h1>
                    <div class="adm-sub"><a href="assets.php">&larr; Back to all assets</a></div>
                </div>
                <div>
                    <button class="adm-btn adm-btn-ghost" id="assignBtn"><i class="fas fa-user-check"></i> Assign</button>
                    <button class="adm-btn adm-btn-ghost" id="maintBtn"><i class="fas fa-screwdriver-wrench"></i> Log maintenance</button>
                    <button class="adm-btn adm-btn-primary" id="statusBtn"><i class="fas fa-arrows-rotate"></i> Change status</button>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Details</h2></div>
                <div class="adm-card-body" id="assetDetails">
                    <span class="adm-skel" style="width:100%;height:18px;"></span>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Assignment history</h2></div>
                <div class="adm-card-body" id="assignmentHistory">
                    <span class="adm-skel" style="width:100%;height:18px;"></span>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Maintenance history</h2></div>
                <div class="adm-card-body" id="maintenanceHistory">
                    <span class="adm-skel" style="width:100%;height:18px;"></span>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const assetPk = <?php echo (int)$assetPk; ?>;
        let currentAsset = null;

        $(document).ready(function() {
            if (!assetPk) {
                $('#assetDetails').html('<div class="adm-error">No asset id given.</div>');
                return;
            }
            loadAsset();
            $('#assignBtn').on('click', promptAssign);
            $('#maintBtn').on('click', promptMaintenance);
            $('#statusBtn').on('click', promptStatus);
        });

        function loadAsset() {
            $.ajax({
                url: '../assets/db_query/admin/get_asset.php',
                type: 'GET',
                data: { id: assetPk },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        currentAsset = data.asset;
                        displayAsset(data.asset);
                        displayAssignments(data.asset.assignments);
                        displayMaintenance(data.asset.maintenance);
                    } else {
                        $('#assetDetails').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load asset.') + '</div>');
                    }
                },
                error: function() {
                    $('#assetDetails').html('<div class="adm-error">Could not reach the server while loading the asset.</div>');
                }
            });
        }

        function statusBadge(status) {
            const map = {
                active: 'is-green', assigned: 'is-info', under_maintenance: 'is-amber',
                damaged: 'is-amber', lost: 'is-danger', disposed: 'is-neutral', retired: 'is-neutral'
            };
            const cls = map[status] || 'is-neutral';
            const label = status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            return `<span class="adm-badge ${cls}">${label}</span>`;
        }

        function displayAsset(a) {
            $('#pageTitle').text(a.asset_name + ' (' + a.asset_id + ')');
            $('#assetDetails').html(`
                <div class="adm-table-wrap"><table class="adm-table"><tbody>
                    <tr><td><strong>Asset ID</strong></td><td>${escapeHtml(a.asset_id)}</td></tr>
                    <tr><td><strong>Name</strong></td><td>${escapeHtml(a.asset_name)}</td></tr>
                    <tr><td><strong>Category</strong></td><td>${escapeHtml(a.asset_category)}${a.asset_type ? ' · ' + escapeHtml(a.asset_type) : ''}</td></tr>
                    <tr><td><strong>Status</strong></td><td>${statusBadge(a.status)}</td></tr>
                    <tr><td><strong>Condition</strong></td><td>${escapeHtml(a.asset_condition)}</td></tr>
                    <tr><td><strong>Purchase date</strong></td><td>${a.purchase_date ? formatDate(a.purchase_date) : '—'}</td></tr>
                    <tr><td><strong>Purchase cost</strong></td><td class="adm-money">₹${parseFloat(a.purchase_cost).toFixed(2)}</td></tr>
                    <tr><td><strong>Current value</strong></td><td class="adm-money">${a.current_value ? '₹' + parseFloat(a.current_value).toFixed(2) : '—'}</td></tr>
                    <tr><td><strong>Supplier</strong></td><td>${a.supplier_name ? escapeHtml(a.supplier_name) : '—'}</td></tr>
                    <tr><td><strong>Serial / Model</strong></td><td>${a.serial_number ? escapeHtml(a.serial_number) : '—'} / ${a.model_number ? escapeHtml(a.model_number) : '—'}</td></tr>
                    <tr><td><strong>Location</strong></td><td>${a.location ? escapeHtml(a.location) : '—'}</td></tr>
                    <tr><td><strong>Department</strong></td><td>${a.department ? escapeHtml(a.department) : '—'}</td></tr>
                    <tr><td><strong>Assigned to</strong></td><td>${a.assigned_employee ? escapeHtml(a.assigned_employee) : '<span class="adm-cell-sub">Unassigned</span>'}</td></tr>
                    <tr><td><strong>Warranty</strong></td><td>${a.warranty_start_date ? formatDate(a.warranty_start_date) : '—'} to ${a.warranty_end_date ? formatDate(a.warranty_end_date) : '—'}</td></tr>
                    <tr><td><strong>Document note</strong></td><td>${a.document_note ? escapeHtml(a.document_note) : '<span class="adm-cell-sub">—</span>'}</td></tr>
                    <tr><td><strong>Notes</strong></td><td>${a.notes ? escapeHtml(a.notes) : '—'}</td></tr>
                </tbody></table></div>
            `);
        }

        function displayAssignments(list) {
            if (!list || list.length === 0) {
                $('#assignmentHistory').html('<div class="adm-empty"><i class="fas fa-user-clock"></i><p>No assignment history yet.</p></div>');
                return;
            }
            let rows = '';
            list.forEach(x => {
                rows += `<tr>
                    <td>${escapeHtml(x.assigned_to)}</td>
                    <td>${formatDate(x.assigned_date)}</td>
                    <td>${x.returned_date ? formatDate(x.returned_date) : '<span class="adm-cell-sub">Current</span>'}</td>
                    <td>${x.notes ? escapeHtml(x.notes) : '—'}</td>
                </tr>`;
            });
            $('#assignmentHistory').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Assigned to</th><th>Assigned date</th><th>Returned date</th><th>Notes</th></tr></thead>
                <tbody>${rows}</tbody></table></div>`);
        }

        function displayMaintenance(list) {
            if (!list || list.length === 0) {
                $('#maintenanceHistory').html('<div class="adm-empty"><i class="fas fa-screwdriver-wrench"></i><p>No maintenance history yet.</p></div>');
                return;
            }
            let rows = '';
            list.forEach(x => {
                rows += `<tr>
                    <td>${formatDate(x.maintenance_date)}</td>
                    <td>${escapeHtml(x.description)}</td>
                    <td class="adm-money">${x.cost ? '₹' + parseFloat(x.cost).toFixed(2) : '—'}</td>
                    <td>${x.performed_by ? escapeHtml(x.performed_by) : '—'}</td>
                    <td>${x.next_due_date ? formatDate(x.next_due_date) : '—'}</td>
                </tr>`;
            });
            $('#maintenanceHistory').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Date</th><th>Description</th><th>Cost</th><th>Performed by</th><th>Next due</th></tr></thead>
                <tbody>${rows}</tbody></table></div>`);
        }

        function promptAssign() {
            Swal.fire({
                title: 'Assign asset',
                html: `
                    <input id="swal-assigned-to" class="swal2-input" placeholder="Assigned to (employee name)">
                    <input id="swal-assigned-date" type="date" class="swal2-input" value="${new Date().toISOString().substring(0,10)}">
                    <textarea id="swal-assign-notes" class="swal2-textarea" placeholder="Notes (optional)"></textarea>
                `,
                confirmButtonText: 'Assign',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const to = $('#swal-assigned-to').val().trim();
                    const date = $('#swal-assigned-date').val();
                    if (!to || !date) {
                        Swal.showValidationMessage('Assigned to and assigned date are required');
                        return false;
                    }
                    return { assigned_to: to, assigned_date: date, notes: $('#swal-assign-notes').val().trim() };
                }
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/assign_asset.php',
                    type: 'POST',
                    data: Object.assign({ asset_id: assetPk }, result.value),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Assigned', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                            loadAsset();
                        } else {
                            Swal.fire({ title: 'Could not assign', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not assign', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                });
            });
        }

        function promptMaintenance() {
            Swal.fire({
                title: 'Log maintenance',
                html: `
                    <input id="swal-maint-date" type="date" class="swal2-input" value="${new Date().toISOString().substring(0,10)}">
                    <textarea id="swal-maint-desc" class="swal2-textarea" placeholder="Description"></textarea>
                    <input id="swal-maint-cost" type="number" step="0.01" class="swal2-input" placeholder="Cost (optional)">
                    <input id="swal-maint-by" class="swal2-input" placeholder="Performed by (optional)">
                    <input id="swal-maint-next" type="date" class="swal2-input" placeholder="Next due date (optional)">
                `,
                confirmButtonText: 'Log',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const date = $('#swal-maint-date').val();
                    const desc = $('#swal-maint-desc').val().trim();
                    if (!date || !desc) {
                        Swal.showValidationMessage('Maintenance date and description are required');
                        return false;
                    }
                    return {
                        maintenance_date: date, description: desc,
                        cost: $('#swal-maint-cost').val(), performed_by: $('#swal-maint-by').val().trim(),
                        next_due_date: $('#swal-maint-next').val()
                    };
                }
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/log_asset_maintenance.php',
                    type: 'POST',
                    data: Object.assign({ asset_id: assetPk }, result.value),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Logged', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                            loadAsset();
                        } else {
                            Swal.fire({ title: 'Could not log maintenance', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not log maintenance', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                });
            });
        }

        function promptStatus() {
            if (!currentAsset) return;
            Swal.fire({
                title: 'Change status',
                html: `
                    <select id="swal-new-status" class="swal2-input">
                        <option value="active" ${currentAsset.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="assigned" ${currentAsset.status === 'assigned' ? 'selected' : ''}>Assigned</option>
                        <option value="under_maintenance" ${currentAsset.status === 'under_maintenance' ? 'selected' : ''}>Under maintenance</option>
                        <option value="damaged" ${currentAsset.status === 'damaged' ? 'selected' : ''}>Damaged</option>
                        <option value="lost" ${currentAsset.status === 'lost' ? 'selected' : ''}>Lost</option>
                        <option value="disposed" ${currentAsset.status === 'disposed' ? 'selected' : ''}>Disposed</option>
                        <option value="retired" ${currentAsset.status === 'retired' ? 'selected' : ''}>Retired</option>
                    </select>
                `,
                confirmButtonText: 'Update status',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => ({ status: $('#swal-new-status').val() })
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/update_asset_status.php',
                    type: 'POST',
                    data: Object.assign({ asset_id: assetPk }, result.value),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Status updated', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                            loadAsset();
                        } else {
                            Swal.fire({ title: 'Could not update status', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not update status', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                });
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
