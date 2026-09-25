<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouses — Valluvam Admin</title>
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
                    <h1>Warehouses</h1>
                    <div class="adm-sub">Storage locations used across inventory operations</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addWarehouseBtn"><i class="fas fa-plus"></i> New warehouse</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>All warehouses</h2></div>
                <div class="adm-card-body" id="warehousesTable">
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
            loadWarehouses();
            $('#addWarehouseBtn').on('click', function() { editWarehouse(null); });
        });

        function loadWarehouses() {
            $.ajax({
                url: '../assets/db_query/admin/get_warehouses.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayWarehouses(data.warehouses);
                    } else {
                        $('#warehousesTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load warehouses.') + '</div>');
                    }
                },
                error: function() {
                    $('#warehousesTable').html('<div class="adm-error">Could not reach the server while loading warehouses.</div>');
                }
            });
        }

        function displayWarehouses(warehouses) {
            if (warehouses.length === 0) {
                $('#warehousesTable').html('<div class="adm-empty"><i class="fas fa-warehouse"></i><p><strong>No warehouses yet</strong></p><p>Create one to get started.</p></div>');
                return;
            }

            let rows = '';
            warehouses.forEach(w => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(w.name)}<div class="adm-cell-sub">${escapeHtml(w.code)}</div></td>
                    <td>${escapeHtml(w.location || '—')}</td>
                    <td>${escapeHtml(w.manager_name || '—')}</td>
                    <td>${escapeHtml(w.contact || '—')}</td>
                    <td>${escapeHtml(w.capacity || '—')}</td>
                    <td>${w.status === 'active' ? '<span class="adm-badge is-green">Active</span>' : '<span class="adm-badge is-neutral">Inactive</span>'}</td>
                    <td>
                        <button class="adm-icon-btn edit-warehouse" data-warehouse='${JSON.stringify(w).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>
                    </td>
                </tr>`;
            });

            $('#warehousesTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Warehouse</th><th>Location</th><th>Manager</th><th>Contact</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-warehouse').on('click', function() { editWarehouse($(this).data('warehouse')); });
        }

        function editWarehouse(warehouse) {
            Swal.fire({
                title: warehouse ? `Edit ${warehouse.name}` : 'New warehouse',
                html: `
                    <input id="swal-name" class="swal2-input" placeholder="Warehouse name" value="${warehouse ? escapeHtml(warehouse.name) : ''}">
                    <input id="swal-code" class="swal2-input" placeholder="Code (e.g. WH-EAST)" value="${warehouse ? escapeHtml(warehouse.code) : ''}">
                    <input id="swal-location" class="swal2-input" placeholder="Location" value="${warehouse ? escapeHtml(warehouse.location || '') : ''}">
                    <input id="swal-manager" class="swal2-input" placeholder="Manager name" value="${warehouse ? escapeHtml(warehouse.manager_name || '') : ''}">
                    <input id="swal-contact" class="swal2-input" placeholder="Contact number" value="${warehouse ? escapeHtml(warehouse.contact || '') : ''}">
                    <input id="swal-capacity" class="swal2-input" placeholder="Capacity (e.g. 5000 sq ft)" value="${warehouse ? escapeHtml(warehouse.capacity || '') : ''}">
                    <select id="swal-status" class="swal2-input">
                        <option value="active" ${!warehouse || warehouse.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${warehouse && warehouse.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                `,
                confirmButtonText: warehouse ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const name = $('#swal-name').val().trim();
                    const code = $('#swal-code').val().trim();
                    if (!name || !code) {
                        Swal.showValidationMessage('Name and code are required');
                        return false;
                    }
                    return {
                        id: warehouse ? warehouse.id : null,
                        name: name,
                        code: code,
                        location: $('#swal-location').val().trim(),
                        manager_name: $('#swal-manager').val().trim(),
                        contact: $('#swal-contact').val().trim(),
                        capacity: $('#swal-capacity').val().trim(),
                        status: $('#swal-status').val()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveWarehouse(result.value);
            });
        }

        function saveWarehouse(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_warehouse.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadWarehouses();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The warehouse was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
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
