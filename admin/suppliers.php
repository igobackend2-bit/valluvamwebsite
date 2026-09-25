<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers — Valluvam Admin</title>
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
                    <h1>Suppliers</h1>
                    <div class="adm-sub">Vendors and supplier contacts</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addSupplierBtn"><i class="fas fa-plus"></i> New supplier</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All suppliers</h2>
                    <input type="text" class="adm-input" id="searchInput" placeholder="Search name, company, mobile, email, GST..." style="max-width:320px;">
                </div>
                <div class="adm-card-body" id="suppliersTable">
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
        let searchTimer = null;

        $(document).ready(function() {
            loadSuppliers();
            $('#addSupplierBtn').on('click', function() { editSupplier(null); });
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadSuppliers, 300);
            });
        });

        function loadSuppliers() {
            $.ajax({
                url: '../assets/db_query/admin/get_suppliers.php',
                type: 'GET',
                data: { q: $('#searchInput').val() || '' },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displaySuppliers(data.suppliers);
                    } else {
                        $('#suppliersTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load suppliers.') + '</div>');
                    }
                },
                error: function() {
                    $('#suppliersTable').html('<div class="adm-error">Could not reach the server while loading suppliers.</div>');
                }
            });
        }

        function displaySuppliers(suppliers) {
            if (suppliers.length === 0) {
                $('#suppliersTable').html('<div class="adm-empty"><i class="fas fa-truck"></i><p><strong>No suppliers yet</strong></p><p>Create one to get started.</p></div>');
                return;
            }

            let rows = '';
            suppliers.forEach(s => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(s.supplier_name)}${s.company_name ? '<div class="adm-cell-sub">' + escapeHtml(s.company_name) + '</div>' : ''}</td>
                    <td>${escapeHtml(s.mobile || '—')}</td>
                    <td>${escapeHtml(s.email || '—')}</td>
                    <td>${escapeHtml(s.gst_number || '—')}</td>
                    <td>${escapeHtml(s.payment_terms || '—')}</td>
                    <td>${s.status === 'active' ? '<span class="adm-badge is-green">Active</span>' : '<span class="adm-badge is-neutral">Inactive</span>'}</td>
                    <td>
                        <button class="adm-icon-btn edit-supplier" data-supplier='${JSON.stringify(s).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="adm-icon-btn is-danger delete-supplier" data-id="${s.id}" data-name="${escapeHtml(s.supplier_name)}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });

            $('#suppliersTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Supplier</th><th>Mobile</th><th>Email</th><th>GST</th><th>Payment terms</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-supplier').on('click', function() { editSupplier($(this).data('supplier')); });
            $('.delete-supplier').on('click', function() { deleteSupplier($(this).data('id'), $(this).data('name')); });
        }

        function editSupplier(supplier) {
            Swal.fire({
                title: supplier ? `Edit ${supplier.supplier_name}` : 'New supplier',
                html: `
                    <input id="swal-name" class="swal2-input" placeholder="Supplier name" value="${supplier ? escapeHtml(supplier.supplier_name) : ''}">
                    <input id="swal-company" class="swal2-input" placeholder="Company name" value="${supplier ? escapeHtml(supplier.company_name || '') : ''}">
                    <input id="swal-mobile" class="swal2-input" placeholder="Mobile" value="${supplier ? escapeHtml(supplier.mobile || '') : ''}">
                    <input id="swal-email" class="swal2-input" placeholder="Email" value="${supplier ? escapeHtml(supplier.email || '') : ''}">
                    <input id="swal-address" class="swal2-input" placeholder="Address" value="${supplier ? escapeHtml(supplier.address || '') : ''}">
                    <input id="swal-gst" class="swal2-input" placeholder="GST number" value="${supplier ? escapeHtml(supplier.gst_number || '') : ''}">
                    <input id="swal-terms" class="swal2-input" placeholder="Payment terms" value="${supplier ? escapeHtml(supplier.payment_terms || '') : ''}">
                    <textarea id="swal-bank" class="swal2-textarea" placeholder="Bank details">${supplier ? escapeHtml(supplier.bank_details || '') : ''}</textarea>
                    <textarea id="swal-notes" class="swal2-textarea" placeholder="Notes">${supplier ? escapeHtml(supplier.notes || '') : ''}</textarea>
                    <select id="swal-status" class="swal2-input">
                        <option value="active" ${!supplier || supplier.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${supplier && supplier.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                `,
                confirmButtonText: supplier ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const name = $('#swal-name').val().trim();
                    if (!name) {
                        Swal.showValidationMessage('Supplier name is required');
                        return false;
                    }
                    return {
                        id: supplier ? supplier.id : null,
                        supplier_name: name,
                        company_name: $('#swal-company').val().trim(),
                        mobile: $('#swal-mobile').val().trim(),
                        email: $('#swal-email').val().trim(),
                        address: $('#swal-address').val().trim(),
                        gst_number: $('#swal-gst').val().trim(),
                        payment_terms: $('#swal-terms').val().trim(),
                        bank_details: $('#swal-bank').val().trim(),
                        notes: $('#swal-notes').val().trim(),
                        status: $('#swal-status').val()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveSupplier(result.value);
            });
        }

        function saveSupplier(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_supplier.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadSuppliers();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The supplier was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function deleteSupplier(id, name) {
            Swal.fire({
                title: 'Delete this supplier?',
                text: `"${name}" will be permanently removed.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#a8442f',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/delete_supplier.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Deleted', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadSuppliers();
                            } else {
                                Swal.fire({ title: 'Could not delete', text: response.message || 'The supplier was not removed.', icon: 'error', confirmButtonColor: '#1c5034' });
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
    </script>
</body>
</html>
