<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Management — Valluvam Admin</title>
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
                    <h1>Waste Management</h1>
                    <div class="adm-sub">Damaged, expired and production waste records</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addWasteBtn"><i class="fas fa-plus"></i> Report waste</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Filters</h2></div>
                <div class="adm-card-body">
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <button class="adm-btn adm-btn-ghost" id="viewAllBtn">All</button>
                        <button class="adm-btn adm-btn-ghost" id="viewPendingBtn">Pending approval</button>
                    </div>
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:10px;">
                        <input type="text" id="fltSearch" class="adm-input" placeholder="Search waste ID / reason / SKU" style="max-width:260px;">
                        <select id="fltType" class="adm-select" style="max-width:180px;">
                            <option value="">All types</option>
                            <option value="product_waste">Product waste</option>
                            <option value="damaged_stock">Damaged stock</option>
                            <option value="expired_stock">Expired stock</option>
                            <option value="production_waste">Production waste</option>
                            <option value="packaging_waste">Packaging waste</option>
                            <option value="other">Other</option>
                        </select>
                        <select id="fltStatus" class="adm-select" style="max-width:180px;">
                            <option value="">All statuses</option>
                            <option value="reported">Reported</option>
                            <option value="approved">Approved</option>
                            <option value="processed">Processed</option>
                            <option value="disposed">Disposed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input type="date" id="fltDateFrom" class="adm-input" style="max-width:160px;">
                        <input type="date" id="fltDateTo" class="adm-input" style="max-width:160px;">
                        <button class="adm-btn adm-btn-ghost" id="fltApplyBtn"><i class="fas fa-filter"></i> Apply</button>
                        <button class="adm-btn adm-btn-ghost" id="fltClearBtn"><i class="fas fa-xmark"></i> Clear</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2 id="listHeading">All waste records</h2></div>
                <div class="adm-card-body" id="wasteTable">
                    <div class="adm-table-wrap"><table class="adm-table"><tbody>
                        <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                    </tbody></table></div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let pendingOnly = false;

        $(document).ready(function() {
            loadWaste();
            $('#addWasteBtn').on('click', reportWaste);
            $('#fltApplyBtn').on('click', loadWaste);
            $('#fltClearBtn').on('click', function() {
                $('#fltSearch').val(''); $('#fltType').val(''); $('#fltStatus').val('');
                $('#fltDateFrom').val(''); $('#fltDateTo').val('');
                pendingOnly = false;
                $('#listHeading').text('All waste records');
                loadWaste();
            });
            $('#viewAllBtn').on('click', function() { pendingOnly = false; $('#listHeading').text('All waste records'); loadWaste(); });
            $('#viewPendingBtn').on('click', function() { pendingOnly = true; $('#listHeading').text('Pending approval'); loadWaste(); });
        });

        function loadWaste() {
            $.ajax({
                url: '../assets/db_query/admin/get_waste_records.php',
                type: 'GET',
                data: {
                    q: $('#fltSearch').val(),
                    waste_type: $('#fltType').val(),
                    status: pendingOnly ? 'reported' : $('#fltStatus').val(),
                    date_from: $('#fltDateFrom').val(),
                    date_to: $('#fltDateTo').val()
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayWaste(data.waste_records);
                    } else {
                        $('#wasteTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load waste records.') + '</div>');
                    }
                },
                error: function() {
                    $('#wasteTable').html('<div class="adm-error">Could not reach the server while loading waste records.</div>');
                }
            });
        }

        function statusBadge(status) {
            const map = { reported: 'is-amber', approved: 'is-info', processed: 'is-info', disposed: 'is-green', cancelled: 'is-neutral' };
            const cls = map[status] || 'is-neutral';
            return `<span class="adm-badge ${cls}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
        }

        function displayWaste(records) {
            if (records.length === 0) {
                $('#wasteTable').html('<div class="adm-empty"><i class="fas fa-trash"></i><p><strong>No waste records</strong></p><p>Report one to get started.</p></div>');
                return;
            }

            let rows = '';
            records.forEach(w => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(w.waste_id)}
                        <div class="adm-cell-sub">${formatDate(w.date)}</div></td>
                    <td>${escapeHtml(w.waste_type.replace(/_/g,' '))}</td>
                    <td>${w.product_name ? escapeHtml(w.product_name) : (w.sku ? escapeHtml(w.sku) : '<span class="adm-cell-sub">—</span>')}</td>
                    <td>${w.quantity !== null ? w.quantity + ' ' + escapeHtml(w.unit) : '—'}</td>
                    <td>${escapeHtml(w.reason)}</td>
                    <td class="adm-money">${w.estimated_value ? '₹' + parseFloat(w.estimated_value).toFixed(2) : '—'}</td>
                    <td>${statusBadge(w.status)}</td>
                    <td>
                        ${w.status === 'reported' ? `<button class="adm-btn adm-btn-ghost approve-waste" data-id="${w.id}" data-code="${escapeHtml(w.waste_id)}">Approve</button>` : ''}
                        ${w.status === 'approved' ? `<button class="adm-btn adm-btn-ghost process-waste" data-id="${w.id}" data-code="${escapeHtml(w.waste_id)}">Mark processed</button>` : ''}
                        ${w.status === 'processed' ? `<button class="adm-btn adm-btn-ghost dispose-waste" data-id="${w.id}" data-code="${escapeHtml(w.waste_id)}">Mark disposed</button>` : ''}
                    </td>
                </tr>`;
            });

            $('#wasteTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Waste</th><th>Type</th><th>Product / SKU</th><th>Qty</th><th>Reason</th><th>Est. value</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.approve-waste').on('click', function() { transitionWaste($(this).data('id'), $(this).data('code'), 'approved', 'Approve this waste record?', 'Approving reduces stock (if linked to a product) and cannot be undone from here.'); });
            $('.process-waste').on('click', function() { transitionWaste($(this).data('id'), $(this).data('code'), 'processed', 'Mark as processed?', ''); });
            $('.dispose-waste').on('click', function() { transitionWaste($(this).data('id'), $(this).data('code'), 'disposed', 'Mark as disposed?', ''); });
        }

        function transitionWaste(id, code, newStatus, title, text) {
            Swal.fire({
                title: title,
                text: text || `"${code}" will move to "${newStatus}".`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/approve_waste.php',
                    type: 'POST',
                    data: { id: id, status: newStatus },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Updated', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                            loadWaste();
                        } else {
                            Swal.fire({ title: 'Could not update', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                });
            });
        }

        function reportWaste() {
            Swal.fire({
                title: 'Report waste',
                width: 600,
                html: `
                    <input id="swal-date" type="date" class="swal2-input" value="${new Date().toISOString().substring(0,10)}">
                    <select id="swal-type" class="swal2-input">
                        <option value="product_waste">Product waste</option>
                        <option value="damaged_stock">Damaged stock</option>
                        <option value="expired_stock">Expired stock</option>
                        <option value="production_waste">Production waste</option>
                        <option value="packaging_waste">Packaging waste</option>
                        <option value="other">Other</option>
                    </select>
                    <input id="swal-product-id" type="number" class="swal2-input" placeholder="Product ID (optional — leave blank for non-product waste)">
                    <input id="swal-sku" class="swal2-input" placeholder="SKU (optional)">
                    <input id="swal-quantity" type="number" class="swal2-input" placeholder="Quantity (optional)">
                    <input id="swal-unit" class="swal2-input" placeholder="Unit (default pcs)" value="pcs">
                    <input id="swal-reason" class="swal2-input" placeholder="Reason">
                    <input id="swal-estimated-value" type="number" step="0.01" class="swal2-input" placeholder="Estimated value (optional)">
                    <input id="swal-disposal-method" class="swal2-input" placeholder="Disposal method (optional)">
                `,
                confirmButtonText: 'Report',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const date = $('#swal-date').val();
                    const reason = $('#swal-reason').val().trim();
                    if (!date || !reason) {
                        Swal.showValidationMessage('Date and reason are required');
                        return false;
                    }
                    return {
                        date: date,
                        waste_type: $('#swal-type').val(),
                        product_id: $('#swal-product-id').val(),
                        sku: $('#swal-sku').val().trim(),
                        quantity: $('#swal-quantity').val(),
                        unit: $('#swal-unit').val().trim() || 'pcs',
                        reason: reason,
                        estimated_value: $('#swal-estimated-value').val(),
                        disposal_method: $('#swal-disposal-method').val().trim()
                    };
                }
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '../assets/db_query/admin/save_waste_record.php',
                    type: 'POST',
                    data: result.value,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({ title: 'Reported', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                            loadWaste();
                        } else {
                            Swal.fire({ title: 'Could not report waste', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'Could not report waste', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
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
