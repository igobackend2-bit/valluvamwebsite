<?php
// Stock lifecycle — purchase order → loading + machine weight → transport → unloading → QC → stock in (added 2 Oct 2026)
require_once __DIR__ . '/includes/erp_page.php';
require_once __DIR__ . '/includes/stock_flow_common.php';
erp_page_start('Loading → QC → Stock In', 'Every purchase consignment: loading check with machine weight, internal vehicle or courier, unloading, QC, then stock in at the location',
    '<button class="adm-btn adm-btn-primary" id="newBtn" style="display:none"><i class="fas fa-plus"></i> Material ready (new consignment)</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="cns">Consignments</button><button class="erp-tab" data-tab="qcp">QC checklist (per product / category)</button></div>
<div id="tabCns">
    <div class="sf-kpis" id="kpis"></div>
    <section class="adm-card">
        <div class="adm-card-head"><h2>Consignments</h2>
            <div class="erp-filters"><select class="adm-select" id="fStatus"><option value="">All status</option></select>
                <select class="adm-select" id="fWh"><option value="">All locations</option></select>
                <input class="adm-input" id="fQ" placeholder="Search number, PO, supplier, tracking, vehicle"></div></div>
        <div class="adm-card-body" id="list"></div>
    </section>
</div>
<div id="tabQcp" style="display:none">
    <section class="adm-card"><div class="adm-card-head"><h2>QC checks</h2><button class="adm-btn adm-btn-ghost" id="qcpNew" style="display:none"><i class="fas fa-plus"></i> Add check</button></div>
        <div class="adm-card-body" id="qcpList"></div></section>
</div>
<?php erp_page_end(sf_common_js() . <<<'JS'
const E = ERP;
const FLOW = ['ready_for_loading', 'loaded', 'in_transit', 'delivered', 'received', 'qc_pending', 'qc_approved', 'stocked'];
let CUR = null;
SF.meta().then(m => {
    $('#fWh').append(SF.whOptions('', false));
    $('#fStatus').append(Object.keys(SF.LABEL).slice(0, 11).map(s => `<option value="${s}">${SF.LABEL[s]}</option>`).join(''));
    if (SF.can('stockflow.loading')) $('#newBtn').show();
    if (SF.can('stockflow.config')) $('#qcpNew').show();
    load();
    if (E.param('id')) openView(E.param('id'));
}).catch(m => E.errorBox($('#list'), m));
E.tabs($('#tabs'), t => { $('#tabCns').toggle(t === 'cns'); $('#tabQcp').toggle(t === 'qcp'); if (t === 'qcp') loadQcp(); });
$('#fStatus,#fWh').on('change', load);
let qT; $('#fQ').on('input', () => { clearTimeout(qT); qT = setTimeout(load, 300); });
$('#kpis').on('click', '[data-st]', function () { $('#fStatus').val($(this).data('st')); load(); });

function load() {
    E.loading($('#list'));
    E.api(SF.API, { action: 'cns_list', status: $('#fStatus').val(), warehouse_id: $('#fWh').val(), q: $('#fQ').val() }, { silent: true }).then(r => {
        const n = {}; r.counts.forEach(c => n[c.status] = +c.n);
        $('#kpis').html(['ready_for_loading', 'loaded', 'in_transit', 'delivered', 'received', 'qc_pending', 'qc_hold', 'qc_approved', 'qc_failed', 'stocked']
            .map(s => `<div data-st="${s}">${E.kpi(SF.LABEL[s], n[s] || 0, s === 'qc_failed' ? 'is-red' : (['qc_pending', 'qc_hold', 'in_transit'].includes(s) ? 'is-amber' : (s === 'stocked' ? 'is-green' : 'is-neutral')))}</div>`).join(''));
        E.table($('#list'), [
            { label: 'Consignment', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.consignment_number)}</a>` },
            { label: 'PO', render: x => E.esc(x.po_number) }, { label: 'Supplier', render: x => E.esc(x.supplier_name || '') },
            { label: 'Destination', render: x => E.esc(x.warehouse_name || '') }, { label: 'Status', render: x => SF.badge(x.status) },
            { label: 'Loaded', num: true, render: x => E.qty(x.loaded_qty) }, { label: 'Unloaded', num: true, render: x => x.unloaded_qty > 0 ? E.qty(x.unloaded_qty) : '—' },
            { label: 'Weight diff', num: true, render: x => +x.loaded_qty ? SF.diff(x.weight_diff, 'kg') : '—' },
            { label: 'Transport', render: x => x.transport_type === 'courier' ? `<i class="fas fa-box"></i> ${E.esc(x.courier_name || '')} · ${E.esc(x.tracking_number || '')}` : (x.transport_type === 'internal' ? `<i class="fas fa-truck"></i> ${E.esc(x.vehicle_number || '')}` : '—') },
            { label: 'GRN / QC', render: x => [x.grn_number, x.qc_number].filter(Boolean).map(E.esc).join(' · ') || '—' },
            { label: 'Proofs', num: true, render: x => x.proofs > 0 ? `<i class="fas fa-paperclip"></i> ${x.proofs}` : '<span class="erp-muted">none</span>' },
        ], r.rows, { empty: 'No consignments yet', emptyHint: 'When material is ready at the supplier, start a consignment from its approved purchase order.', icon: 'fa-truck-ramp-box' });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

// ------------------------------------------------------------ new consignment
$('#newBtn').on('click', () => {
    E.api(SF.API, { action: 'po_list' }).then(r => {
        if (!r.rows.length) return E.alertError('There is no approved purchase order waiting for goods.');
        E.form('Material ready — new consignment', `<div class="erp-grid">${E.field('Purchase order *', E.select('nPo', E.options(r.rows, 'id', p => `${p.po_number} — ${p.supplier_name} (open ${E.qty(p.pending_qty)})`, null, 'Choose…')))}
            ${E.field('Destination location *', E.select('nWh', SF.whOptions(1)))}${E.field('Supplier invoice / reference', E.input('nInv', ''))}${E.field('Loading location', E.input('nLoc', '', 'placeholder="Supplier godown, city"'))}</div>
            <div id="nLines" class="sf-note">Choose the purchase order to see its lines.</div>`,
            () => {
                const lines = $('.swal2-popup tr[data-pi]').filter(function () { return $(this).find('[data-f=on]').is(':checked'); })
                    .map(function () { return { po_item_id: $(this).data('pi'), batch_number: $(this).find('[data-f=b]').val(), manufacturing_date: $(this).find('[data-f=m]').val(), expiry_date: $(this).find('[data-f=e]').val() }; }).get();
                return E.post(SF.API, { action: 'cns_create', po_id: SF.v('nPo'), destination_warehouse_id: SF.v('nWh'), invoice_ref: SF.v('nInv'), loading_location: SF.v('nLoc'), lines }, { silent: true })
                    .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); });
            }, { width: 1000, confirmText: 'Create', didOpen: p => $(p).on('change', '#nPo', function () {
                if (!this.value) return;
                E.api(SF.API, { action: 'po_lines', po_id: this.value }, { silent: true }).then(x => {
                    $('.swal2-popup #nWh').val(x.po.warehouse_id || 1);
                    $('.swal2-popup #nLines').html(`<div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th></th><th>Item</th><th>Ordered</th><th>Received</th><th>In transit</th><th>Open</th><th>Unit wt</th><th>Batch</th><th>Mfg</th><th>Expiry</th></tr></thead><tbody>` +
                        x.items.map(i => `<tr data-pi="${i.id}"><td><input type="checkbox" data-f="on" ${i.pending_qty > i.in_transit_qty ? 'checked' : ''}></td><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity)}</td>
                            <td class="erp-num">${E.qty(i.received_qty)}</td><td class="erp-num">${E.qty(i.in_transit_qty)}</td><td class="erp-num">${E.qty(i.pending_qty)}</td><td>${SF.kg(i.unit_weight_kg)}</td>
                            <td><input class="adm-input" data-f="b"></td><td><input class="adm-input" type="date" data-f="m"></td><td><input class="adm-input" type="date" data-f="e"></td></tr>`).join('') +
                        '</tbody></table></div><p class="sf-note">Another batch of the same item can be added as a second line in the loading check.</p>');
                }).catch(() => {});
            }) });
    }).catch(() => {});
});

// ------------------------------------------------------------ detail + every step
function grnPayload(c) {
    return { action: 'grn_save', po_id: c.po_id, warehouse_id: c.unload_warehouse_id || c.destination_warehouse_id, received_date: c.unload_date, received_by: c.receiver_name, vehicle_number: c.vehicle_number || '',
             supplier_challan_no: c.invoice_ref || '', notes: 'Stock lifecycle ' + c.consignment_number,
             items: c.lines.filter(l => +l.unloaded_qty > 0).map(l => ({ po_item_id: l.po_item_id, item_type: l.item_type, item_id: l.item_id, received_qty: l.unloaded_qty, rejected_qty: 0, batch_number: l.batch_number || '',
                                                                        manufacturing_date: l.manufacturing_date || '', expiry_date: l.expiry_date || '', qc_status: 'pending' })) };
}
function toQcPending(c, payload) {   // existing GRN draft → existing QC → link
    let grnId = c.grn_id;
    return SF.chain([
        () => grnId ? null : E.post('purchase_api.php', payload || grnPayload(c), { silent: true }).then(g => { grnId = g.id; }),
        () => E.api('procurement_api.php', { action: 'qc_for_grn', grn_id: grnId }, { silent: true }).then(q => q.qc ? null : E.post('procurement_api.php', { action: 'qc_create', grn_id: grnId }, { silent: true })),
        () => E.post(SF.API, { action: 'cns_link_qc', id: c.id, grn_id: grnId }, { silent: true }),
    ]);
}
function postStock(c, payload) {
    const p = payload || { id: c.grn_id, po_id: c.po_id, warehouse_id: c.unload_warehouse_id || c.destination_warehouse_id, received_date: c.unload_date, received_by: c.receiver_name || '', items: [] };
    return SF.chain([
        () => c.grn_status === 'posted' ? null : E.post('purchase_api.php', Object.assign({}, p, { action: 'grn_post' }), { silent: true }),
        () => E.post(SF.API, { action: 'cns_stocked', id: c.id }, { silent: true }),
    ]);
}
const done = (id, msg) => { E.toast(msg || 'Saved'); load(); openView(id); };
const fail = m => { if (m !== 'cancelled') E.alertError(m); };

function openView(id) {
    E.api(SF.API, { action: 'cns_get', id }).then(r => {
        const c = CUR = r.record, st = c.status, L = c.lines;
        const head = E.kv([['PO', E.esc(c.po_number)], ['Supplier', E.esc(c.supplier_name || '')], ['Invoice / ref', E.esc(c.invoice_ref || '—')], ['Destination', E.esc(c.warehouse_name || '')],
            ['Status', SF.badge(st)], c.qc_result ? ['QC result', SF.badge(c.qc_result)] : null, c.grn_number ? ['GRN', `<a class="erp-link" href="goods_receipts.php?id=${c.grn_id}">${E.esc(c.grn_number)}</a> ${E.badge(c.grn_status)}`] : null,
            c.qc_number ? ['QC', `<a class="erp-link" href="quality_checks.php?id=${c.qc_id}">${E.esc(c.qc_number)}</a> ${E.badge(c.qc_status)}`] : null,
            c.stock_in_number ? ['Stock in', E.esc(c.stock_in_number)] : null, c.shipment_number ? ['Transport', `<a class="erp-link" href="shipments.php?id=${c.shipment_id}">${E.esc(c.shipment_number)}</a>`] : null]);
        let html = SF.steps(FLOW, st === 'qc_hold' ? 'qc_pending' : (st === 'qc_failed' ? 'qc_approved' : st)) + head;
        // ---- summary of every figure (read-only, differences calculated by the database)
        html += `<div class="sf-sec"><h3>Lines</h3><div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Item</th><th>Batch</th><th>Expiry</th><th>Ordered</th><th>Loaded</th><th>Expected wt</th><th>Machine wt</th><th>Wt diff</th><th>Diff %</th><th>Count</th>
                 <th>Unloaded</th><th>Qty diff</th><th>Unload wt</th><th>Transit wt diff</th><th>Accepted</th><th>Rejected</th><th>Damaged</th><th>Short</th><th>Excess</th><th>QC</th>${SF.can('stockflow.approve') ? '<th></th>' : ''}</tr></thead><tbody>` +
            L.map(l => `<tr><td>${E.esc(l.item_name)}</td><td>${E.esc(l.batch_number || '—')}</td><td>${E.date(l.expiry_date)}</td><td class="erp-num">${E.qty(l.ordered_qty)}</td><td class="erp-num">${E.qty(l.loaded_qty)}</td>
                <td class="erp-num">${SF.kg(l.expected_weight)}</td><td class="erp-num">${SF.kg(l.actual_weight)}</td><td class="erp-num">${SF.diff(l.weight_diff)}</td><td class="erp-num">${SF.pct(l.weight_diff_pct)}</td>
                <td class="erp-num">${l.physical_count === null ? '—' : E.qty(l.physical_count)}</td><td class="erp-num">${l.unloaded_qty === null ? '—' : E.qty(l.unloaded_qty)}</td><td class="erp-num">${SF.diff(l.unload_qty_diff)}</td>
                <td class="erp-num">${SF.kg(l.unload_machine_weight)}</td><td class="erp-num">${SF.diff(l.transit_weight_diff)}</td><td class="erp-num">${l.qc_accepted === null ? '—' : E.qty(l.qc_accepted)}</td>
                <td class="erp-num">${l.qc_rejected === null ? '—' : E.qty(l.qc_rejected)}</td><td class="erp-num">${l.qc_damaged === null ? '—' : E.qty(l.qc_damaged)}</td><td class="erp-num">${l.qc_shortage > 0 ? SF.diff(-l.qc_shortage) : '—'}</td>
                <td class="erp-num">${l.qc_excess > 0 ? SF.diff(l.qc_excess) : '—'}</td><td>${SF.badge(l.qc_line_result)}</td>${SF.can('stockflow.approve') ? `<td><button class="adm-icon-btn" data-corr="${l.id}" title="Controlled correction"><i class="fas fa-pen-ruler"></i></button></td>` : ''}</tr>`).join('') +
            '</tbody></table></div><p class="sf-note">Weight differences, percentages, shortage and excess are calculated by the database and cannot be typed over. Corrections need an approver and a reason (logged).</p></div>';
        const acts = [];
        // ---- LOADING
        if (st === 'ready_for_loading' && SF.can('stockflow.loading')) {
            html += `<div class="sf-sec"><h3><i class="fas fa-dolly"></i> Loading check + machine weight</h3><div class="erp-grid">
                ${E.field('Loading date *', E.input('lDate', c.loading_date || E.today(), 'type="date"'))}${E.field('Loading time', E.input('lTime', (c.loading_time || SF.now()).slice(0, 5), 'type="time"'))}
                ${E.field('Loading location', E.input('lLoc', c.loading_location || ''))}${E.field('Invoice / reference', E.input('lInv', c.invoice_ref || ''))}
                ${E.field('Loaded by *', E.input('lBy', c.loaded_by || ''))}${E.field('Checked by * (another person)', E.input('lChk', c.checked_by || ''))}${E.field('Remarks', E.input('lRem', c.loading_remarks || ''), 'span-all')}</div>
                <div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Item</th><th>Open qty</th><th>Loaded qty *</th><th>Unit wt</th><th>Expected wt</th><th>Machine wt (kg) *</th><th>Diff</th><th>Physical count *</th><th>Batch</th><th>Expiry</th><th></th></tr></thead><tbody>` +
                L.map(l => `<tr data-l="${l.id}" data-uw="${l.unit_weight_kg ?? ''}"><td>${E.esc(l.item_name)}</td><td class="erp-num">${E.qty(l.ordered_qty)}</td><td><input class="adm-input" type="number" min="0" step="any" data-f="q" value="${+l.loaded_qty || ''}"></td>
                    <td>${SF.kg(l.unit_weight_kg)}</td><td class="sf-calc erp-num" data-f="exp">${SF.kg(l.expected_weight)}</td><td><input class="adm-input" type="number" min="0" step="any" data-f="w" value="${l.actual_weight ?? ''}"></td>
                    <td class="sf-calc erp-num" data-f="d"></td><td><input class="adm-input" type="number" min="0" step="any" data-f="c" value="${l.physical_count ?? ''}"></td>
                    <td><input class="adm-input" data-f="b" value="${E.esc(l.batch_number || '')}"></td><td><input class="adm-input" type="date" data-f="e" value="${l.expiry_date || ''}"></td>
                    <td><button class="adm-icon-btn" data-split="${l.id}" title="Another batch of this item"><i class="fas fa-code-branch"></i></button></td></tr>`).join('') +
                '</tbody></table></div><p class="sf-note">Expected weight = loaded qty × pack weight. The difference shown is a preview — the database calculates the stored figure. Attach the loading photo, weight machine photo, invoice and packing list below.</p></div>';
            const send = confirm => () => E.post(SF.API, { action: 'cns_loading_save', id: c.id, confirm: confirm ? 1 : 0, loading_date: SF.v('lDate'), loading_time: SF.v('lTime'), loading_location: SF.v('lLoc'), invoice_ref: SF.v('lInv'),
                loaded_by: SF.v('lBy'), checked_by: SF.v('lChk'), loading_remarks: SF.v('lRem'),
                lines: $('.swal2-popup tr[data-l]').map(function () { const $r = $(this); return { id: $r.data('l'), loaded_qty: $r.find('[data-f=q]').val(), actual_weight: $r.find('[data-f=w]').val(), physical_count: $r.find('[data-f=c]').val(), batch_number: $r.find('[data-f=b]').val(), expiry_date: $r.find('[data-f=e]').val() }; }).get() }, { silent: true })
                .then(x => done(c.id, x.message)).catch(fail);
            acts.push({ label: 'Save', icon: 'fa-floppy-disk', run: send(false) }, { label: 'Confirm LOADED', cls: 'adm-btn-primary', icon: 'fa-check', run: send(true) });
        }
        // ---- TRANSPORT
        if (['loaded', 'in_transit'].includes(st) && SF.can('stockflow.loading')) {
            const t = c.transport_type || 'internal';
            html += `<div class="sf-sec"><h3><i class="fas fa-truck"></i> Transportation</h3>
                <div style="display:flex;gap:16px;margin-bottom:8px"><label><input type="radio" name="tType" value="internal" ${t === 'internal' ? 'checked' : ''}> Internal vehicle</label><label><input type="radio" name="tType" value="courier" ${t === 'courier' ? 'checked' : ''}> Courier</label></div>
                <div class="erp-grid" data-t="internal" ${t === 'internal' ? '' : 'style="display:none"'}>${E.field('Vehicle', E.input('tVeh', c.vehicle_name || '', 'placeholder="e.g. Tata Ace"'))}${E.field('Vehicle number *', E.input('tVno', c.vehicle_number || ''))}
                    ${E.field('Driver name *', E.input('tDrv', c.driver_name || ''))}${E.field('Driver employee ID', E.input('tEmp', c.driver_employee_id || ''))}${E.field('Driver phone *', E.input('tPh', c.driver_phone || '', 'type="tel"'))}</div>
                <div class="erp-grid" data-t="courier" ${t === 'courier' ? '' : 'style="display:none"'}>${E.field('Courier company *', E.select('tCs', E.options((SF._m = SF._m || []).length ? SF._m : [], 'id', x => x.name, c.courier_service_id)))}
                    ${E.field('Name (if Other)', E.input('tCn', c.courier_name || ''))}${E.field('Service type', E.input('tSt', c.courier_service_type || '', 'placeholder="Surface / Air / Express"'))}
                    ${E.field('Tracking number *', E.input('tTr', c.tracking_number || ''))}${E.field('Courier phone (optional)', E.input('tCp', c.courier_phone || '', 'type="tel"'))}</div>
                <div class="erp-grid">${E.field('Pickup / loading location', E.input('tPick', c.pickup_location || c.loading_location || ''))}${E.field('Destination', `<input class="adm-input" value="${E.esc(c.warehouse_name || '')}" disabled>`)}
                    ${E.field('Departure / dispatch date *', E.input('tDd', c.departure_date || E.today(), 'type="date"'))}${E.field('Time', E.input('tDt', (c.departure_time || SF.now()).slice(0, 5), 'type="time"'))}
                    ${E.field('Expected arrival / delivery', E.input('tEa', c.expected_arrival_date || '', 'type="date"'))}${E.field('Expected time', E.input('tEt', (c.expected_arrival_time || '').slice(0, 5), 'type="time"'))}
                    ${E.field('Remarks', E.input('tRem', c.transport_remarks || ''), 'span-all')}</div><p class="sf-note">Attach the courier receipt / tracking proof / vehicle photo below.</p></div>`;
            const send = dispatch => () => E.post(SF.API, { action: 'cns_transport_save', id: c.id, dispatch: dispatch ? 1 : 0, transport_type: $('.swal2-popup [name=tType]:checked').val(),
                vehicle_name: SF.v('tVeh'), vehicle_number: SF.v('tVno'), driver_name: SF.v('tDrv'), driver_employee_id: SF.v('tEmp'), driver_phone: SF.v('tPh'),
                courier_service_id: SF.v('tCs'), courier_name: SF.v('tCn'), courier_service_type: SF.v('tSt'), tracking_number: SF.v('tTr'), courier_phone: SF.v('tCp'),
                pickup_location: SF.v('tPick'), departure_date: SF.v('tDd'), departure_time: SF.v('tDt'), expected_arrival_date: SF.v('tEa'), expected_arrival_time: SF.v('tEt'), transport_remarks: SF.v('tRem') }, { silent: true })
                .then(x => done(c.id, x.message)).catch(fail);
            acts.push({ label: 'Save transport', icon: 'fa-floppy-disk', run: send(false) });
            if (st === 'loaded') acts.push({ label: 'Dispatch → IN TRANSIT', cls: 'adm-btn-primary', icon: 'fa-truck-fast', run: send(true) });
        }
        if (st === 'in_transit' && (SF.can('stockflow.loading') || SF.can('stockflow.receive'))) {
            html += `<div class="sf-sec"><h3><i class="fas fa-flag-checkered"></i> Arrival</h3><div class="erp-grid">${E.field('Actual arrival / delivery date *', E.input('aDate', E.today(), 'type="date"'))}${E.field('Time', E.input('aTime', SF.now(), 'type="time"'))}</div>
                <p class="sf-note">Delivery does NOT make stock available — it must be unloaded, received and pass QC first.</p></div>`;
            acts.push({ label: 'Mark DELIVERED', cls: 'adm-btn-primary', icon: 'fa-flag-checkered', run: () => E.post(SF.API, { action: 'cns_deliver', id: c.id, actual_arrival_date: SF.v('aDate'), actual_arrival_time: SF.v('aTime') }, { silent: true }).then(x => done(c.id, x.message)).catch(fail) });
        }
        // ---- UNLOADING
        if (st === 'delivered' && SF.can('stockflow.receive')) {
            const uw = c.unload_warehouse_id || c.destination_warehouse_id;
            html += `<div class="sf-sec"><h3><i class="fas fa-people-carry-box"></i> Unloading / receiving</h3><div class="erp-grid">
                ${E.field('Location *', E.select('uWh', SF.whOptions(uw)))}${E.field('Rack / bin', E.select('uLoc', ''))}${E.field('Receiver *', E.input('uRec', c.receiver_name || ''))}${E.field('Receiver employee ID *', E.input('uEmp', c.receiver_employee_id || ''))}
                ${E.field('Date *', E.input('uDate', c.unload_date || E.today(), 'type="date"'))}${E.field('Time', E.input('uTime', (c.unload_time || SF.now()).slice(0, 5), 'type="time"'))}${E.field('Remarks', E.input('uRem', c.unload_remarks || ''), 'span-all')}</div>
                <div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Item</th><th>Loaded</th><th>Loading machine wt</th><th>Unloaded qty *</th><th>Machine wt (kg) *</th><th>Physical count *</th><th>Batch</th><th>Expiry</th></tr></thead><tbody>` +
                L.map(l => `<tr data-l="${l.id}"><td>${E.esc(l.item_name)}</td><td class="erp-num">${E.qty(l.loaded_qty)}</td><td class="erp-num">${SF.kg(l.actual_weight)}</td>
                    <td><input class="adm-input" type="number" min="0" step="any" data-f="q" value="${l.unloaded_qty ?? ''}"></td><td><input class="adm-input" type="number" min="0" step="any" data-f="w" value="${l.unload_machine_weight ?? ''}"></td>
                    <td><input class="adm-input" type="number" min="0" step="any" data-f="c" value="${l.unload_physical_count ?? ''}"></td><td><input class="adm-input" data-f="b" value="${E.esc(l.batch_number || '')}"></td>
                    <td><input class="adm-input" type="date" data-f="e" value="${l.expiry_date || ''}"></td></tr>`).join('') +
                '</tbody></table></div><p class="sf-note">On confirm: RECEIVED → a draft goods receipt and a quality check are created (QC PENDING). Nothing is sellable until QC and stock in. Attach the unloading photo and weight machine photo.</p></div>';
            const send = confirm => () => E.post(SF.API, { action: 'cns_unload_save', id: c.id, confirm: confirm ? 1 : 0, unload_warehouse_id: SF.v('uWh'), unload_location_id: SF.v('uLoc'), receiver_name: SF.v('uRec'), receiver_employee_id: SF.v('uEmp'),
                unload_date: SF.v('uDate'), unload_time: SF.v('uTime'), unload_remarks: SF.v('uRem'),
                lines: $('.swal2-popup tr[data-l]').map(function () { const $r = $(this); return { id: $r.data('l'), unloaded_qty: $r.find('[data-f=q]').val(), unload_machine_weight: $r.find('[data-f=w]').val(), unload_physical_count: $r.find('[data-f=c]').val(), batch_number: $r.find('[data-f=b]').val(), expiry_date: $r.find('[data-f=e]').val() }; }).get() }, { silent: true })
                .then(x => confirm ? E.api(SF.API, { action: 'cns_get', id: c.id }, { silent: true }).then(rr => toQcPending(rr.record, Object.assign({ action: 'grn_save' }, x.grn_payload))).then(() => done(c.id, 'RECEIVED — goods receipt + quality check created (QC PENDING).')) : done(c.id, x.message))
                .catch(fail);
            acts.push({ label: 'Save', icon: 'fa-floppy-disk', run: send(false) }, { label: 'Confirm RECEIVED', cls: 'adm-btn-primary', icon: 'fa-check', run: send(true) });
        }
        if (st === 'received' && SF.can('stockflow.receive')) {
            html += '<div class="erp-warn">The goods are received but the goods receipt / quality check were not created yet (the last step was interrupted).</div>';
            acts.push({ label: 'Create goods receipt + QC', cls: 'adm-btn-primary', icon: 'fa-rotate', run: () => toQcPending(c).then(() => done(c.id, 'QC PENDING')).catch(fail) });
        }
        // ---- QC
        if (['qc_pending', 'qc_hold'].includes(st) && SF.can('stockflow.qc')) {
            const qcDone = c.qc_status && !['pending', 'cancelled'].includes(c.qc_status);
            if (qcDone) {
                html += '<div class="erp-warn">The quality check is completed — finish it here.</div>';
                acts.push({ label: 'Finish QC', cls: 'adm-btn-primary', icon: 'fa-check', run: () => E.post(SF.API, { action: 'cns_qc_done', id: c.id }, { silent: true }).then(x => done(c.id, x.message)).catch(fail) });
            } else {
                html += `<div class="sf-sec"><h3><i class="fas fa-microscope"></i> Quality check</h3><div class="erp-grid">${E.field('QC by', E.input('qBy', c.qc_by || (SF._name || '')))}
                    ${E.field('Result', E.select('qRes', '<option value="">Work it out from the quantities</option><option value="pass">PASS</option><option value="partial">PARTIAL ACCEPTANCE</option><option value="hold">HOLD (re-check later)</option><option value="fail">FAIL</option>'))}
                    ${E.field('Remarks', E.input('qRem', c.qc_remarks || ''), 'span-all')}</div>` +
                    L.filter(l => +l.unloaded_qty > 0).map(l => `<div class="sf-sec" data-l="${l.id}" data-u="${l.unloaded_qty}"><strong>${E.esc(l.item_name)}</strong> <span class="erp-muted">batch ${E.esc(l.batch_number || '—')} · PO ${E.qty(l.ordered_qty)} · loaded ${E.qty(l.loaded_qty)} · unloaded ${E.qty(l.unloaded_qty)} · unload weight ${SF.kg(l.unload_machine_weight)}</span>
                        <div class="erp-grid">${E.field('Invoice qty', `<input class="adm-input" type="number" step="any" data-f="inv" value="${l.invoice_qty ?? l.loaded_qty}">`)}${E.field('QC machine wt (kg)', `<input class="adm-input" type="number" step="any" data-f="mw" value="${l.qc_machine_weight ?? ''}">`)}
                        ${E.field('QC physical count', `<input class="adm-input" type="number" step="any" data-f="pc" value="${l.qc_physical_count ?? ''}">`)}
                        ${E.field('Packaging', `<select class="adm-select" data-f="pk"><option value="ok">OK</option><option value="damaged" ${l.qc_packaging === 'damaged' ? 'selected' : ''}>Damaged</option><option value="na" ${l.qc_packaging === 'na' ? 'selected' : ''}>N/A</option></select>`)}
                        ${E.field('Physical condition', `<select class="adm-select" data-f="cd"><option value="good">Good</option><option value="fair" ${l.qc_condition === 'fair' ? 'selected' : ''}>Fair</option><option value="poor" ${l.qc_condition === 'poor' ? 'selected' : ''}>Poor</option></select>`)}
                        ${E.field('Quality grade', `<input class="adm-input" data-f="gr" value="${E.esc(l.qc_grade || '')}" placeholder="A / B / C">`)}
                        ${E.field('Accepted *', `<input class="adm-input" type="number" step="any" min="0" data-f="a" value="${l.qc_accepted ?? l.unloaded_qty}">`)}${E.field('Rejected', `<input class="adm-input" type="number" step="any" min="0" data-f="r" value="${l.qc_rejected ?? 0}">`)}
                        ${E.field('Damaged', `<input class="adm-input" type="number" step="any" min="0" data-f="d" value="${l.qc_damaged ?? 0}">`)}${E.field('Batch', `<input class="adm-input" data-f="b" value="${E.esc(l.batch_number || '')}">`)}
                        ${E.field('Expiry', `<input class="adm-input" type="date" data-f="e" value="${l.expiry_date || ''}">`)}${E.field('Reason / remarks', `<input class="adm-input" data-f="rm" value="${E.esc(l.qc_remarks || '')}">`)}
                        ${l.qc_params.map(q => E.field(q.check_name + (q.min_value !== null || q.max_value !== null ? ` (${q.min_value ?? ''}–${q.max_value ?? ''})` : ''),
                            q.check_type === 'yesno' ? `<select class="adm-select" data-chk="${E.esc(q.check_name)}"><option value="">—</option><option ${((l.qc_checklist || {})[q.check_name] === 'yes') ? 'selected' : ''}>yes</option><option ${((l.qc_checklist || {})[q.check_name] === 'no') ? 'selected' : ''}>no</option></select>`
                                                     : `<input class="adm-input" data-chk="${E.esc(q.check_name)}" ${q.check_type === 'number' ? 'type="number" step="any"' : ''} value="${E.esc((l.qc_checklist || {})[q.check_name] || '')}">`)).join('')}</div></div>`).join('') +
                    '<p class="sf-note">Accepted + rejected + damaged must equal the unloaded quantity. Excess over the invoice must be rejected. PASS / PARTIAL → stock in; FAIL → rejected stock (return to supplier); HOLD → stays in quarantine. Attach the QC report / photos.</p></div>';
                acts.push({ label: 'Save QC result', cls: 'adm-btn-primary', icon: 'fa-microscope', run: () => {
                    const lines = $('.swal2-popup .sf-sec[data-l]').map(function () {
                        const $s = $(this), ch = {}; $s.find('[data-chk]').each(function () { if (this.value !== '') ch[$(this).data('chk')] = this.value; });
                        return { id: $s.data('l'), invoice_qty: $s.find('[data-f=inv]').val(), qc_machine_weight: $s.find('[data-f=mw]').val(), qc_physical_count: $s.find('[data-f=pc]').val(), qc_packaging: $s.find('[data-f=pk]').val(),
                                 qc_condition: $s.find('[data-f=cd]').val(), qc_grade: $s.find('[data-f=gr]').val(), qc_accepted: $s.find('[data-f=a]').val(), qc_rejected: $s.find('[data-f=r]').val(), qc_damaged: $s.find('[data-f=d]').val(),
                                 batch_number: $s.find('[data-f=b]').val(), expiry_date: $s.find('[data-f=e]').val(), qc_remarks: $s.find('[data-f=rm]').val(), qc_checklist: ch };
                    }).get();
                    E.post(SF.API, { action: 'cns_qc_save', id: c.id, result: SF.v('qRes'), qc_by: SF.v('qBy'), qc_remarks: SF.v('qRem'), lines }, { silent: true }).then(x => {
                        if (!x.qc_payload) return done(c.id, x.message);
                        return E.post('procurement_api.php', Object.assign({ action: 'qc_complete' }, x.qc_payload), { silent: true })
                            .then(() => E.post(SF.API, { action: 'cns_qc_done', id: c.id }, { silent: true })).then(y => done(c.id, y.message));
                    }).catch(fail);
                } });
            }
        }
        // ---- STOCK IN
        if (['qc_approved', 'qc_failed'].includes(st) && SF.can('stockflow.receive')) {
            html += `<div class="sf-sec"><h3><i class="fas fa-warehouse"></i> ${st === 'qc_failed' ? 'Move to rejected stock' : 'Stock in'}</h3><p>${st === 'qc_failed' ? 'QC failed: posting the receipt puts the goods into REJECTED stock (not sellable). Then create a return dispatch to the supplier from Stock Out, Damage & Returns.' : 'Posts the existing goods receipt: accepted goods become AVAILABLE at ' + E.esc(c.unload_warehouse_name || c.warehouse_name || '') + (c.unload_location_code ? ' (rack/bin ' + E.esc(c.unload_location_code) + ')' : '') + '; rejected / damaged go to REJECTED stock.'}</p></div>`;
            acts.push({ label: st === 'qc_failed' ? 'Post rejection' : 'Post STOCK IN', cls: 'adm-btn-primary', icon: 'fa-warehouse', run: () => postStock(c).then(() => done(c.id, st === 'qc_failed' ? 'Rejected goods held.' : 'STOCKED')).catch(fail) });
        }
        if (['ready_for_loading', 'loaded', 'in_transit', 'delivered'].includes(st) && (SF.can('stockflow.loading') || SF.can('stockflow.approve')))
            acts.push({ label: 'Cancel', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + c.consignment_number + '?', '', { danger: true, reason: 'Reason' }).then(reason => E.post(SF.API, { action: 'cns_cancel', id: c.id, reason }, { silent: true })).then(x => done(c.id, x.message)).catch(fail) });
        html += '<div id="vDocs"></div>' + (c.history.length ? '<div class="sf-sec"><h3>History</h3>' + c.history.map(h => `<div class="erp-muted">${E.date(h.created_at)} ${E.esc(String(h.created_at).slice(11, 16))} · <strong>${E.esc(h.action)}</strong> · ${E.esc(h.username || '')}</div>`).join('') + '</div>' : '');
        E.view(c.consignment_number, html, acts, { width: 1250, didOpen: p => {
            E.docs($('#vDocs'), 'consignment', c.id, st === 'ready_for_loading' ? 'LOADING_PHOTO' : (st === 'delivered' ? 'UNLOADING_PHOTO' : (['loaded', 'in_transit'].includes(st) ? 'COURIER_RECEIPT' : 'QC_DOCUMENT')));
            // couriers + racks
            SF.meta().then(m => {
                $(p).find('#tCs').html(E.options(m.couriers, 'id', x => x.name, c.courier_service_id, 'Choose…'));
                const racks = () => $(p).find('#uLoc').html('<option value="">—</option>' + m.locations.filter(x => String(x.warehouse_id) === String($(p).find('#uWh').val())).map(x => `<option value="${x.id}" ${String(x.id) === String(c.unload_location_id) ? 'selected' : ''}>${E.esc(x.code)} ${E.esc(x.name || '')} (${x.level})</option>`).join(''));
                racks(); $(p).on('change', '#uWh', racks);
                if (!$(p).find('#qBy').val()) $(p).find('#qBy').val(m.name);
            });
            $(p).on('change', '[name=tType]', function () { $(p).find('[data-t]').hide(); $(p).find(`[data-t="${this.value}"]`).show(); });
            const calc = function () { const $r = $(this).closest('tr'); const uw = $r.data('uw'); if (uw === '' || uw === undefined) return;
                const ex = E.num($r.find('[data-f=q]').val()) * E.num(uw), w = $r.find('[data-f=w]').val();
                $r.find('[data-f=exp]').text(SF.kg(ex)); $r.find('[data-f=d]').html(w === '' ? '' : SF.diff(E.num(w) - ex, 'kg') + (ex > 0 ? ' (' + SF.pct((E.num(w) - ex) / ex * 100) + ')' : '')); };
            $(p).on('input', 'tr[data-uw] input', calc); $(p).find('tr[data-uw] [data-f=q]').each(calc);
            $(p).on('click', '[data-split]', function () {
                const id = $(this).data('split');
                E.confirmAction('Add another batch line for this item?', 'Enter the batch number', { reason: 'Batch number' }).then(b => E.post(SF.API, { action: 'cns_loading_save', id: c.id, add_lines: [{ copy_of: id, batch_number: b }],
                    lines: [], loading_date: SF.v('lDate') || '', loaded_by: SF.v('lBy') || '', checked_by: SF.v('lChk') || '' }, { silent: true })).then(() => done(c.id, 'Batch line added')).catch(fail);
            });
            $(p).on('click', '[data-corr]', function () {
                const lid = $(this).data('corr');
                Swal.fire({ title: 'Controlled correction', html: `<div class="erp-grid">${E.field('Figure', E.select('cF', '<option value="loaded_qty">Loaded qty</option><option value="actual_weight">Loading machine weight</option><option value="physical_count">Loading physical count</option><option value="unloaded_qty">Unloaded qty</option><option value="unload_machine_weight">Unloading machine weight</option><option value="unload_physical_count">Unloading physical count</option>'))}
                    ${E.field('Correct value', E.input('cV', '', 'type="number" step="any"'))}${E.field('Reason *', E.input('cR', ''), 'span-all')}</div>`, showCancelButton: true, confirmButtonText: 'Correct', confirmButtonColor: '#1c5034',
                    preConfirm: () => E.post(SF.API, { action: 'cns_correct', id: c.id, line_id: lid, field: $('#cF').val(), value: $('#cV').val(), reason: $('#cR').val() }, { silent: true }).catch(m => { Swal.showValidationMessage(m); return false; })
                }).then(r => { if (r.isConfirmed) done(c.id, r.value.message); });
            });
        } });
    }).catch(() => {});
}

// ------------------------------------------------------------ QC checklist master
function loadQcp() {
    E.loading($('#qcpList'));
    E.api(SF.API, { action: 'qcp_list' }, { silent: true }).then(r => E.table($('#qcpList'), [
        { label: 'Applies to', render: x => x.scope === 'all' ? 'All items' : (x.scope === 'category' ? 'Category: ' + E.esc(x.category) : 'Product: ' + E.esc(x.product_name || '#' + x.product_id)) },
        { label: 'Check', render: x => E.esc(x.check_name) }, { label: 'Type', render: x => E.esc(x.check_type) },
        { label: 'Range', render: x => x.min_value !== null || x.max_value !== null ? `${x.min_value ?? ''} – ${x.max_value ?? ''}` : '—' },
        { label: 'Status', render: x => E.badge(+x.is_active ? 'active' : 'inactive') },
        { label: '', render: x => SF.can('stockflow.config') ? `<button class="adm-btn adm-btn-ghost" data-qcpt="${x.id}" data-a="${+x.is_active ? 0 : 1}">${+x.is_active ? 'Disable' : 'Enable'}</button>` : '' },
    ], r.rows, { empty: 'No product-specific QC checks yet', emptyHint: 'Add checks such as moisture %, insects, aroma, seal intact — they appear in the QC form for that product / category.', icon: 'fa-list-check' })).catch(m => E.errorBox($('#qcpList'), m, loadQcp));
}
$('#qcpList').on('click', '[data-qcpt]', function () { E.post(SF.API, { action: 'qcp_status', id: $(this).data('qcpt'), active: $(this).data('a') }).then(() => loadQcp()).catch(() => {}); });
$('#qcpNew').on('click', () => E.items('product').then(items => E.form('Add QC check', `<div class="erp-grid">${E.field('Applies to', E.select('pS', '<option value="all">All items</option><option value="category">A category</option><option value="product">One product</option>'))}
        ${E.field('Category', E.input('pC', '', 'placeholder="e.g. Nuts"'))}${E.field('Product', E.select('pP', E.options(items, 'item_id', i => i.label, null, 'Choose…')))}${E.field('Check name *', E.input('pN', '', 'placeholder="e.g. Moisture %"'))}
        ${E.field('Type', E.select('pT', '<option value="yesno">Yes / No</option><option value="number">Number</option><option value="text">Text</option>'))}${E.field('Min', E.input('pMin', '', 'type="number" step="any"'))}${E.field('Max', E.input('pMax', '', 'type="number" step="any"'))}</div>`,
    () => E.post(SF.API, { action: 'qcp_save', scope: SF.v('pS'), category: SF.v('pC'), product_id: SF.v('pP'), check_name: SF.v('pN'), check_type: SF.v('pT'), min_value: SF.v('pMin'), max_value: SF.v('pMax') }, { silent: true }).then(x => { E.toast(x.message); loadQcp(); }), { width: 800 })));
JS
);
