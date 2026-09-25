<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets — Valluvam Admin</title>
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
                    <h1>Assets</h1>
                    <div class="adm-sub">Company assets — vehicles, machinery, furniture, electronics</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addAssetBtn"><i class="fas fa-plus"></i> New asset</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>Filters</h2>
                </div>
                <div class="adm-card-body">
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:10px;">
                        <input type="text" id="fltSearch" class="adm-input" placeholder="Search name / asset ID / serial no / employee" style="max-width:260px;">
                        <select id="fltStatus" class="adm-select" style="max-width:180px;">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="assigned">Assigned</option>
                            <option value="under_maintenance">Under maintenance</option>
                            <option value="damaged">Damaged</option>
                            <option value="lost">Lost</option>
                            <option value="disposed">Disposed</option>
                            <option value="retired">Retired</option>
                        </select>
                        <select id="fltCategory" class="adm-select" style="max-width:180px;">
                            <option value="">All categories</option>
                            <option value="Vehicle">Vehicle</option>
                            <option value="Machinery">Machinery</option>
                            <option value="Furniture">Furniture</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="fltDepartment" class="adm-input" placeholder="Department" style="max-width:180px;">
                        <button class="adm-btn adm-btn-ghost" id="fltApplyBtn"><i class="fas fa-filter"></i> Apply</button>
                        <button class="adm-btn adm-btn-ghost" id="fltClearBtn"><i class="fas fa-xmark"></i> Clear</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>All assets</h2></div>
                <div class="adm-card-body" id="assetsTable">
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
        $(document).ready(function() {
            loadAssets();
            $('#addAssetBtn').on('click', function() { editAsset(null); });
            $('#fltApplyBtn').on('click', loadAssets);
            $('#fltClearBtn').on('click', function() {
                $('#fltSearch').val(''); $('#fltStatus').val(''); $('#fltCategory').val(''); $('#fltDepartment').val('');
                loadAssets();
            });
        });

        function loadAssets() {
            $.ajax({
                url: '../assets/db_query/admin/get_assets.php',
                type: 'GET',
                data: {
                    q: $('#fltSearch').val(),
                    status: $('#fltStatus').val(),
                    asset_category: $('#fltCategory').val(),
                    department: $('#fltDepartment').val()
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayAssets(data.assets);
                    } else {
                        $('#assetsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load assets.') + '</div>');
                    }
                },
                error: function() {
                    $('#assetsTable').html('<div class="adm-error">Could not reach the server while loading assets.</div>');
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

        function displayAssets(assets) {
            if (assets.length === 0) {
                $('#assetsTable').html('<div class="adm-empty"><i class="fas fa-boxes-stacked"></i><p><strong>No assets yet</strong></p><p>Add one to get started.</p></div>');
                return;
            }

            let rows = '';
            assets.forEach(a => {
                rows += `<tr>
                    <td class="adm-cell-title"><a href="asset_detail.php?id=${a.id}">${escapeHtml(a.asset_id)}</a>
                        <div class="adm-cell-sub">${escapeHtml(a.asset_name)}</div></td>
                    <td>${escapeHtml(a.asset_category)}${a.asset_type ? ' · ' + escapeHtml(a.asset_type) : ''}</td>
                    <td>${a.department ? escapeHtml(a.department) : '<span class="adm-cell-sub">—</span>'}</td>
                    <td>${a.assigned_employee ? escapeHtml(a.assigned_employee) : '<span class="adm-cell-sub">Unassigned</span>'}</td>
                    <td class="adm-money">₹${parseFloat(a.purchase_cost).toFixed(2)}</td>
                    <td>${statusBadge(a.status)}</td>
                    <td>
                        <a class="adm-icon-btn" href="asset_detail.php?id=${a.id}" title="View"><i class="fas fa-eye"></i></a>
                        <button class="adm-icon-btn edit-asset" data-asset='${JSON.stringify(a).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>
                    </td>
                </tr>`;
            });

            $('#assetsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Asset</th><th>Category</th><th>Department</th><th>Assigned to</th><th>Purchase cost</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-asset').on('click', function() { editAsset($(this).data('asset')); });
        }

        function editAsset(asset) {
            Swal.fire({
                title: asset ? `Edit ${asset.asset_id}` : 'New asset',
                width: 640,
                html: `
                    <input id="swal-name" class="swal2-input" placeholder="Asset name" value="${asset ? escapeHtml(asset.asset_name) : ''}">
                    <input id="swal-category" class="swal2-input" placeholder="Category (Vehicle / Machinery / Furniture / Electronics / Other)" value="${asset ? escapeHtml(asset.asset_category) : ''}" list="categoryList">
                    <datalist id="categoryList">
                        <option value="Vehicle"><option value="Machinery"><option value="Furniture"><option value="Electronics"><option value="Other">
                    </datalist>
                    <input id="swal-type" class="swal2-input" placeholder="Type (optional, e.g. Delivery Van)" value="${asset && asset.asset_type ? escapeHtml(asset.asset_type) : ''}">
                    <input id="swal-purchase-date" type="date" class="swal2-input" placeholder="Purchase date" value="${asset && asset.purchase_date ? asset.purchase_date.substring(0,10) : ''}">
                    <input id="swal-purchase-cost" type="number" step="0.01" class="swal2-input" placeholder="Purchase cost" value="${asset ? asset.purchase_cost : ''}">
                    <input id="swal-current-value" type="number" step="0.01" class="swal2-input" placeholder="Current value (optional)" value="${asset && asset.current_value ? asset.current_value : ''}">
                    <input id="swal-serial" class="swal2-input" placeholder="Serial number (optional)" value="${asset && asset.serial_number ? escapeHtml(asset.serial_number) : ''}">
                    <input id="swal-model" class="swal2-input" placeholder="Model number (optional)" value="${asset && asset.model_number ? escapeHtml(asset.model_number) : ''}">
                    <input id="swal-location" class="swal2-input" placeholder="Location (optional)" value="${asset && asset.location ? escapeHtml(asset.location) : ''}">
                    <input id="swal-department" class="swal2-input" placeholder="Department (optional)" value="${asset && asset.department ? escapeHtml(asset.department) : ''}">
                    <input id="swal-warranty-start" type="date" class="swal2-input" placeholder="Warranty start" value="${asset && asset.warranty_start_date ? asset.warranty_start_date.substring(0,10) : ''}">
                    <input id="swal-warranty-end" type="date" class="swal2-input" placeholder="Warranty end" value="${asset && asset.warranty_end_date ? asset.warranty_end_date.substring(0,10) : ''}">
                    <select id="swal-condition" class="swal2-input">
                        <option value="new" ${!asset || asset.asset_condition === 'new' ? 'selected' : ''}>New</option>
                        <option value="good" ${asset && asset.asset_condition === 'good' ? 'selected' : ''}>Good</option>
                        <option value="fair" ${asset && asset.asset_condition === 'fair' ? 'selected' : ''}>Fair</option>
                        <option value="damaged" ${asset && asset.asset_condition === 'damaged' ? 'selected' : ''}>Damaged</option>
                        <option value="critical" ${asset && asset.asset_condition === 'critical' ? 'selected' : ''}>Critical</option>
                    </select>
                    <input id="swal-document-note" class="swal2-input" placeholder="Document note (e.g. 'invoice filed physically') — no file upload in this build" value="${asset && asset.document_note ? escapeHtml(asset.document_note) : ''}">
                    <textarea id="swal-notes" class="swal2-textarea" placeholder="Notes">${asset && asset.notes ? escapeHtml(asset.notes) : ''}</textarea>
                `,
                confirmButtonText: asset ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const name = $('#swal-name').val().trim();
                    const category = $('#swal-category').val().trim();
                    const cost = $('#swal-purchase-cost').val();
                    if (!name || !category) {
                        Swal.showValidationMessage('Asset name and category are required');
                        return false;
                    }
                    return {
                        id: asset ? asset.id : null,
                        asset_name: name,
                        asset_category: category,
                        asset_type: $('#swal-type').val().trim(),
                        purchase_date: $('#swal-purchase-date').val(),
                        purchase_cost: cost || 0,
                        current_value: $('#swal-current-value').val(),
                        serial_number: $('#swal-serial').val().trim(),
                        model_number: $('#swal-model').val().trim(),
                        location: $('#swal-location').val().trim(),
                        department: $('#swal-department').val().trim(),
                        warranty_start_date: $('#swal-warranty-start').val(),
                        warranty_end_date: $('#swal-warranty-end').val(),
                        asset_condition: $('#swal-condition').val(),
                        document_note: $('#swal-document-note').val().trim(),
                        notes: $('#swal-notes').val().trim()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveAsset(result.value);
            });
        }

        function saveAsset(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_asset.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadAssets();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The asset was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
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

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
        }
    </script>
</body>
</html>
