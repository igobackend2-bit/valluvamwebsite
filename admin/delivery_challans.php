<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challans — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .dc-item-row { display:grid; grid-template-columns: 2fr 1fr 1fr 32px; gap:6px; align-items:center; margin-bottom:6px; }
        .dc-item-row select, .dc-item-row input { width:100%; padding:6px; border:1px solid #ccc; border-radius:6px; font-size:13px; }
        .dc-item-head { display:grid; grid-template-columns: 2fr 1fr 1fr 32px; gap:6px; font-size:11px; color:#777; margin-bottom:4px; }
        .dc-remove-row { background:none; border:none; color:#a8442f; cursor:pointer; font-size:14px; }
        .swal-wide { width: 760px !important; }
        .dc-tracker { display:flex; gap:4px; font-size:11px; }
        .dc-tracker span { padding:2px 6px; border-radius:8px; background:#eee; }
        .dc-tracker span.active { background:#1c5034; color:#fff; }
    </style>
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Delivery Challans</h1>
                    <div class="adm-sub">Dispatch documents — standalone or converted from a Sales Order</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addDcBtn"><i class="fas fa-plus"></i> New DC</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>All delivery challans</h2>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="text" id="dcSearch" class="adm-input" placeholder="Search DC#, customer, mobile" style="width:220px;">
                        <select id="dcStatusFilter" class="adm-select">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="ready">Ready</option>
                            <option value="loaded">Loaded</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="in_transit">In transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button class="adm-btn adm-btn-ghost" id="dcFilterBtn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
                <div class="adm-card-body" id="dcTable">
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
        let PRODUCTS = [];
        const DC_STATUSES = ['draft','ready','loaded','dispatched','in_transit','delivered'];
        const STATUS_BADGE = { draft:'is-neutral', ready:'is-info', loaded:'is-info', dispatched:'is-amber', in_transit:'is-amber', delivered:'is-green', cancelled:'is-danger' };

        $(document).ready(function() {
            loadProducts().then(function() {
                loadDeliveryChallans();
                const fromSo = new URLSearchParams(window.location.search).get('from_so');
                if (fromSo) openDcModalFromSo(fromSo);
            });
            $('#addDcBtn').on('click', function() { openDcModal(null); });
            $('#dcFilterBtn').on('click', loadDeliveryChallans);
            $('#dcSearch').on('keyup', function(e) { if (e.key === 'Enter') loadDeliveryChallans(); });
        });

        function loadProducts() {
            return $.ajax({ url: '../assets/db_query/admin/get_products_for_sales.php', type: 'GET', dataType: 'json' })
                .done(function(data) { if (data && data.status === 'success') PRODUCTS = data.products || []; })
                .fail(function() { PRODUCTS = []; });
        }

        function loadDeliveryChallans() {
            const params = { q: $('#dcSearch').val() || '', status: $('#dcStatusFilter').val() || '' };
            $.ajax({
                url: '../assets/db_query/admin/get_delivery_challans.php', type: 'GET', data: params, dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') displayDCs(data.delivery_challans);
                    else $('#dcTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load delivery challans.') + '</div>');
                },
                error: function() { $('#dcTable').html('<div class="adm-error">Could not reach the server while loading delivery challans.</div>'); }
            });
        }

        function trackerHtml(status) {
            if (status === 'cancelled') return '<span class="adm-badge is-danger">Cancelled</span>';
            const idx = DC_STATUSES.indexOf(status);
            return '<div class="dc-tracker">' + DC_STATUSES.map((s, i) => `<span class="${i <= idx ? 'active' : ''}">${s.replace('_',' ')}</span>`).join('') + '</div>';
        }

        function displayDCs(rows) {
            if (rows.length === 0) {
                $('#dcTable').html('<div class="adm-empty"><i class="fas fa-truck"></i><p><strong>No delivery challans yet</strong></p><p>Create one to get started.</p></div>');
                return;
            }
            let html = '';
            rows.forEach(d => {
                const badge = STATUS_BADGE[d.delivery_status] || 'is-neutral';
                const canEdit = !['dispatched','in_transit','delivered','cancelled'].includes(d.delivery_status);
                html += `<tr>
                    <td class="adm-cell-title">${escapeHtml(d.dc_number)}<div class="adm-cell-sub">${d.so_number ? 'From ' + escapeHtml(d.so_number) : 'Standalone'}</div></td>
                    <td>${escapeHtml(d.customer_name || '—')}<div class="adm-cell-sub">${escapeHtml(d.customer_mobile || '')}</div></td>
                    <td><span class="adm-badge ${badge}">${escapeHtml(d.delivery_status.replace(/_/g,' '))}</span></td>
                    <td>${trackerHtml(d.delivery_status)}</td>
                    <td style="white-space:nowrap;">
                        <button class="adm-icon-btn view-dc" data-id="${d.id}" title="View"><i class="fas fa-eye"></i></button>
                        ${canEdit ? `<button class="adm-icon-btn edit-dc" data-id="${d.id}" title="Edit"><i class="fas fa-pen"></i></button>` : ''}
                        <button class="adm-icon-btn print-dc" data-id="${d.id}" title="Print"><i class="fas fa-print"></i></button>
                    </td>
                </tr>`;
            });
            $('#dcTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>DC #</th><th>Customer</th><th>Status</th><th>Progress</th><th>Actions</th></tr></thead>
                <tbody>${html}</tbody></table></div>`);

            $('.view-dc').on('click', function() { viewDC($(this).data('id')); });
            $('.edit-dc').on('click', function() { openDcModalById($(this).data('id')); });
            $('.print-dc').on('click', function() { window.open('print_dc.php?id=' + $(this).data('id'), '_blank'); });
        }

        function viewDC(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_delivery_challan.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load DC', 'error'); return; }
                    const d = data.delivery_challan;
                    let itemRows = (d.items || []).map(it => `<tr><td>${escapeHtml(it.product_name || ('#' + it.product_id))}</td><td>${it.quantity} ${escapeHtml(it.unit)}</td></tr>`).join('');
                    Swal.fire({
                        title: d.dc_number, width: 600,
                        html: `<div style="text-align:left; font-size:14px;">
                            <p><strong>Customer:</strong> ${escapeHtml(d.customer_name || '—')} (${escapeHtml(d.customer_mobile || '—')})</p>
                            <p><strong>Status:</strong> ${escapeHtml(d.delivery_status.replace(/_/g,' '))}</p>
                            <p><strong>Vehicle:</strong> ${escapeHtml(d.vehicle_number || '—')} &nbsp; <strong>Driver:</strong> ${escapeHtml(d.driver_name || '—')}</p>
                            <table class="adm-table" style="width:100%; font-size:13px;"><thead><tr><th>Product</th><th>Qty</th></tr></thead><tbody>${itemRows}</tbody></table>
                            ${d.remarks ? `<p><strong>Remarks:</strong> ${escapeHtml(d.remarks)}</p>` : ''}
                        </div>`,
                        confirmButtonText: 'Close', confirmButtonColor: '#1c5034'
                    });
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function productOptions(selectedId) {
            let opts = '<option value="">Select product…</option>';
            PRODUCTS.forEach(p => { opts += `<option value="${p.id}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${escapeHtml(p.product_name)}</option>`; });
            return opts;
        }

        function dcItemRowHtml(item) {
            item = item || {};
            return `<div class="dc-item-row">
                <select class="dc-product">${productOptions(item.product_id)}</select>
                <input type="number" class="dc-qty" min="0" step="0.01" placeholder="Qty" value="${item.quantity || ''}">
                <input type="text" class="dc-unit" placeholder="Unit" value="${item.unit || 'pcs'}">
                <button type="button" class="dc-remove-row" title="Remove"><i class="fas fa-times"></i></button>
            </div>`;
        }

        function bindDcRowEvents() {
            $('#dc-items .dc-remove-row').off('click').on('click', function() {
                if ($('#dc-items .dc-item-row').length > 1) $(this).closest('.dc-item-row').remove();
                else $(this).closest('.dc-item-row').replaceWith(dcItemRowHtml({}));
                bindDcRowEvents();
            });
        }

        function collectDcRows() {
            const rows = [];
            $('#dc-items .dc-item-row').each(function() {
                const productId = $(this).find('.dc-product').val();
                const qty = parseFloat($(this).find('.dc-qty').val());
                if (!productId || !qty || qty <= 0) return;
                rows.push({ product_id: productId, quantity: qty, unit: $(this).find('.dc-unit').val() || 'pcs' });
            });
            return rows;
        }

        function openDcModalById(id) {
            $.ajax({
                url: '../assets/db_query/admin/get_delivery_challan.php', type: 'GET', data: { id: id }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load DC', 'error'); return; }
                    openDcModal(data.delivery_challan);
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function openDcModalFromSo(soId) {
            $.ajax({
                url: '../assets/db_query/admin/get_sales_order.php', type: 'GET', data: { id: soId }, dataType: 'json',
                success: function(data) {
                    if (data.status !== 'success') { Swal.fire('Error', data.message || 'Could not load sales order', 'error'); return; }
                    const so = data.sales_order;
                    openDcModal({
                        sales_order_id: so.id, so_number: so.so_number,
                        customer_name: so.customer_name, customer_mobile: so.customer_mobile, customer_email: so.customer_email,
                        delivery_address: so.shipping_address || so.customer_address,
                        warehouse_id: so.warehouse_id, delivery_status: 'draft',
                        items: (so.items || []).map(it => ({ product_id: it.product_id, quantity: it.quantity, unit: it.unit }))
                    });
                },
                error: function() { Swal.fire('Error', 'Could not reach the server', 'error'); }
            });
        }

        function openDcModal(dc) {
            const isEdit = dc && dc.id;
            const items = (dc && dc.items && dc.items.length) ? dc.items : [{}];
            const itemsHtml = items.map(dcItemRowHtml).join('');

            Swal.fire({
                title: isEdit ? `Edit ${dc.dc_number}` : (dc && dc.sales_order_id ? `New DC from ${dc.so_number}` : 'New Delivery Challan'),
                customClass: { popup: 'swal-wide' },
                html: `
                    <div style="text-align:left; font-size:13px;">
                        <input type="hidden" id="dc-so-id" value="${dc && dc.sales_order_id ? dc.sales_order_id : ''}">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="dc-cust-name" class="swal2-input" style="margin:2px 0;" placeholder="Customer name" value="${dc ? escapeHtml(dc.customer_name || '') : ''}">
                            <input id="dc-cust-mobile" class="swal2-input" style="margin:2px 0;" placeholder="Mobile" value="${dc ? escapeHtml(dc.customer_mobile || '') : ''}">
                        </div>
                        <textarea id="dc-address" class="swal2-textarea" placeholder="Delivery address" style="margin:2px 0;">${dc ? escapeHtml(dc.delivery_address || '') : ''}</textarea>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="dc-vehicle" class="swal2-input" style="margin:2px 0;" placeholder="Vehicle number" value="${dc ? escapeHtml(dc.vehicle_number || '') : ''}">
                            <input id="dc-driver" class="swal2-input" style="margin:2px 0;" placeholder="Driver name" value="${dc ? escapeHtml(dc.driver_name || '') : ''}">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <input id="dc-driver-mobile" class="swal2-input" style="margin:2px 0;" placeholder="Driver mobile" value="${dc ? escapeHtml(dc.driver_mobile || '') : ''}">
                            <input id="dc-warehouse" class="swal2-input" style="margin:2px 0;" placeholder="Warehouse ID" value="${dc ? (dc.warehouse_id || 1) : 1}">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div><label>Dispatch date</label><input type="date" id="dc-dispatch-date" class="swal2-input" style="margin:2px 0;" value="${dc && dc.dispatch_date ? dc.dispatch_date.substring(0,10) : ''}"></div>
                            <select id="dc-status" class="swal2-input" style="margin:2px 0;">
                                ${DC_STATUSES.map(s => `<option value="${s}" ${dc && dc.delivery_status === s ? 'selected' : ''}>${s.replace(/_/g,' ')}</option>`).join('')}
                            </select>
                        </div>

                        <div style="margin-top:8px;"><strong>Items</strong></div>
                        <div class="dc-item-head"><div>Product</div><div>Qty</div><div>Unit</div><div></div></div>
                        <div id="dc-items">${itemsHtml}</div>
                        <button type="button" id="dc-add-row" class="adm-btn adm-btn-ghost" style="margin-top:4px;"><i class="fas fa-plus"></i> Add item</button>

                        <textarea id="dc-remarks" class="swal2-textarea" placeholder="Remarks" style="margin-top:8px;">${dc ? escapeHtml(dc.remarks || '') : ''}</textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: isEdit ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                cancelButtonColor: '#6b6459',
                focusConfirm: false,
                didOpen: () => {
                    bindDcRowEvents();
                    $('#dc-add-row').on('click', function() { $('#dc-items').append(dcItemRowHtml({})); bindDcRowEvents(); });
                },
                preConfirm: () => {
                    const rows = collectDcRows();
                    if (rows.length === 0) { Swal.showValidationMessage('Add at least one valid line item'); return false; }
                    return {
                        id: isEdit ? dc.id : null,
                        sales_order_id: $('#dc-so-id').val(),
                        customer_name: $('#dc-cust-name').val().trim(),
                        customer_mobile: $('#dc-cust-mobile').val().trim(),
                        delivery_address: $('#dc-address').val().trim(),
                        warehouse_id: $('#dc-warehouse').val() || 1,
                        vehicle_number: $('#dc-vehicle').val().trim(),
                        driver_name: $('#dc-driver').val().trim(),
                        driver_mobile: $('#dc-driver-mobile').val().trim(),
                        dispatch_date: $('#dc-dispatch-date').val(),
                        delivery_status: $('#dc-status').val(),
                        remarks: $('#dc-remarks').val().trim(),
                        items: JSON.stringify(rows)
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveDC(result.value);
            });
        }

        function saveDC(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_delivery_challan.php', type: 'POST', data: data, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', text: response.dc_number || '', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                        loadDeliveryChallans();
                        if (window.location.search.includes('from_so')) history.replaceState(null, '', 'delivery_challans.php');
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The delivery challan was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() { Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' }); }
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
