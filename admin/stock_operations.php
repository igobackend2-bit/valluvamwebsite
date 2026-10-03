<?php
// Stock lifecycle — controlled stock out, damage, sales-return receiving + QC, opening stock, purchase-return dispatch, registers (added 2 Oct 2026)
require_once __DIR__ . '/includes/erp_page.php';
require_once __DIR__ . '/includes/stock_flow_common.php';
// FIX (3 Oct 2026): renamed from 'Stock Out, Damage & Returns' — stock out is only on the Stock Out page
erp_page_start('Damage & Returns', 'Damage (identify → verify → approve), sales-return receiving + QC, opening stock, return dispatch to suppliers',
    '<button class="adm-btn adm-btn-primary" id="newBtn" style="display:none"><i class="fas fa-plus"></i> <span id="newLbl">New</span></button>');
?>
<div class="erp-tabs" id="tabs">
    <!-- FIX (3 Oct 2026): "Stock out" and "Stock out register" removed here — stock out is only on the Stock Out page (one place, with CSV / Excel download) -->
    <button class="erp-tab active" data-tab="damage">Damage</button><button class="erp-tab" data-tab="returns">Sales return QC</button>
    <button class="erp-tab" data-tab="opening">Opening stock</button><button class="erp-tab" data-tab="dispatch">Return dispatch</button>
    <button class="erp-tab" data-tab="location">Location stock</button>
</div>
<section class="adm-card"><div class="adm-card-head"><h2 id="secTitle">Damage reports</h2><div class="erp-filters" id="filters"></div></div><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(sf_common_js() . <<<'JS'
const E = ERP;
let TAB = E.param('tab') || 'damage', ITEMS = [];   // FIX (3 Oct 2026): opens on Damage (stock out is on the Stock Out page)
const NEW = { issue: ['New stock out', 'stockflow.issue'], damage: ['Report damage', 'stockflow.issue'], returns: ['Receive returned goods', 'stockflow.returns'], opening: ['Enter opening stock', 'stockflow.receive'], dispatch: ['New return dispatch', 'stockflow.issue'] };
const TITLE = { issue: 'Stock out (production, sample, executive issue, office use)', damage: 'Damage reports', returns: 'Sales return — receiving and QC', opening: 'Opening stock', dispatch: 'Purchase return dispatch', register: 'Stock out register (every outflow from the ledger)', location: 'Stock by location' };
const PURPOSE = { production: 'Production', sample: 'Sample', executive_issue: 'Executive issue', office_consumption: 'Office consumption', other: 'Other approved reason' };
const done = msg => { E.toast(msg || 'Saved'); Swal.close(); load(); };
const fail = m => { if (m !== 'cancelled') E.alertError(m); };
Promise.all([SF.meta(), E.items('')]).then(([m, it]) => { ITEMS = it; E.tabs($('#tabs'), t => { TAB = t; show(); }, TAB); show(); if (E.param('id')) setTimeout(() => openRow(TAB, E.param('id')), 400); }).catch(m => E.errorBox($('#list'), m));
function show() {
    $('#secTitle').text(TITLE[TAB]);
    const n = NEW[TAB]; $('#newBtn').toggle(!!n && SF.can(n[1])); if (n) $('#newLbl').text(n[0]);
    $('#filters').html(['register', 'location'].includes(TAB) ? (TAB === 'register' ? `<input type="date" class="adm-input" id="fFrom" value="${E.monthStart()}"><input type="date" class="adm-input" id="fTo" value="${E.today()}"><select class="adm-select" id="fWh"><option value="">All locations</option>${SF.whOptions('', false)}</select><button class="adm-btn adm-btn-ghost" id="fCsv"><i class="fas fa-file-csv"></i> CSV</button>` : '') : '');
    load();
}
$('#filters').on('change', 'input,select', load);
const itemSel = (id, sel) => E.select(id, E.itemOptions(ITEMS, sel && sel[0], sel && sel[1]));
const pickItem = id => { const v = String(SF.v(id) || ''); return v ? { item_type: v.split(':')[0], item_id: v.split(':')[1] } : {}; };
function batchSelect(id) { return E.select(id, '<option value="">Any batch (FEFO)</option>'); }
function loadBatches($p, itemSel, batchSel) {
    const it = pickItem(itemSel); if (!it.item_id) return;
    E.api('warehouse_api.php', { action: 'batches', item_type: it.item_type, item_id: it.item_id }, { silent: true }).then(r => $p.find('#' + batchSel).html('<option value="">Any batch (FEFO)</option>' + (r.rows || []).filter(b => b.remaining > 0).map(b => `<option value="${b.id}">${E.esc(b.batch_number || 'Batch #' + b.id)} · left ${E.qty(b.remaining)} · exp ${E.date(b.expiry_date)} · ${E.esc(b.warehouse_name || '')}</option>`).join(''))).catch(() => {});
}

function load() {
    E.loading($('#list'));
    const A = { issue: 'iss_list', damage: 'dmg_list', returns: 'rr_list', opening: 'ops_list', dispatch: 'rd_list', register: 'out_register', location: 'location_stock' }[TAB];
    const q = { action: A }; if (TAB === 'register') Object.assign(q, { date_from: $('#fFrom').val(), date_to: $('#fTo').val(), warehouse_id: $('#fWh').val() });
    E.api(SF.API, q, { silent: true }).then(r => {
        const cols = {
            issue: [{ label: 'Number', render: x => `<a class="erp-link" data-row="${x.id}">${E.esc(x.issue_number)}</a>` }, { label: 'Date', render: x => E.date(x.issue_date) }, { label: 'Purpose', render: x => E.esc(PURPOSE[x.purpose] || x.purpose) },
                    { label: 'Location', render: x => E.esc(x.warehouse_name) }, { label: 'Qty', num: true, render: x => E.qty(x.qty) }, { label: 'Weight', num: true, render: x => +x.weight ? SF.kg(x.weight) : '—' },
                    { label: 'Requested by', render: x => E.esc(x.requested_by) }, { label: 'Approved by', render: x => E.esc(x.approved_by || '—') }, { label: 'Issued by', render: x => E.esc(x.issued_by || '—') }, { label: 'Received by', render: x => E.esc(x.received_by || '—') },
                    { label: 'Status', render: x => SF.badge(x.status) }],
            damage: [{ label: 'Number', render: x => `<a class="erp-link" data-row="${x.id}">${E.esc(x.damage_number)}</a>` }, { label: 'Date', render: x => E.date(x.damage_date) }, { label: 'Item', render: x => E.esc(x.item_name) },
                     { label: 'Batch', render: x => E.esc(x.batch_number || '—') }, { label: 'Location', render: x => E.esc(x.warehouse_name) }, { label: 'Qty', num: true, render: x => E.qty(x.quantity) }, { label: 'Weight', num: true, render: x => SF.kg(x.weight_kg) },
                     { label: 'Reason', render: x => E.esc(x.reason) }, { label: 'Identified', render: x => E.esc(x.identified_by) }, { label: 'Verified', render: x => E.esc(x.verified_by || '—') }, { label: 'Approved', render: x => E.esc(x.approved_by || '—') },
                     { label: 'Status', render: x => SF.badge(x.status) }, { label: 'Disposal', render: x => E.badge(x.disposal_status) }],
            returns: [{ label: 'Receipt', render: x => `<a class="erp-link" data-row="${x.id}">${E.esc(x.receipt_number)}</a>` }, { label: 'Date', render: x => E.date(x.return_date) }, { label: 'Sale', render: x => E.esc(x.source_type.replace('_', ' ') + ' ' + x.source_ref) },
                      { label: 'Customer', render: x => E.esc(x.customer_name || '') }, { label: 'Qty', num: true, render: x => E.qty(x.qty) }, { label: 'Received by', render: x => E.esc(x.received_by) }, { label: 'QC by', render: x => E.esc(x.qc_by || '—') },
                      { label: 'Sales return', render: x => E.esc(x.return_number || '—') }, { label: 'Status', render: x => SF.badge(x.status) }],
            opening: [{ label: 'Number', render: x => `<a class="erp-link" data-row="${x.id}">${E.esc(x.opening_number)}</a>` }, { label: 'Date', render: x => E.date(x.opening_date) }, { label: 'Item', render: x => E.esc(x.item_name) },
                      { label: 'Location', render: x => E.esc(x.warehouse_name) }, { label: 'Batch', render: x => E.esc(x.batch_number || '—') }, { label: 'Expiry', render: x => E.date(x.expiry_date) }, { label: 'Qty', num: true, render: x => E.qty(x.quantity) },
                      { label: 'Weight', num: true, render: x => SF.kg(x.weight_kg) }, { label: 'Entered', render: x => E.esc(x.entered_by) }, { label: 'Verified', render: x => E.esc(x.verified_by || '—') }, { label: 'Status', render: x => SF.badge(x.status) }],
            dispatch: [{ label: 'Number', render: x => `<a class="erp-link" data-row="${x.id}">${E.esc(x.dispatch_number)}</a>` }, { label: 'From', render: x => x.source === 'qc_rejected' ? 'QC rejected · ' + E.esc(x.grn_number || '') : 'Purchase return · ' + E.esc(x.return_number || '') },
                       { label: 'Supplier', render: x => E.esc(x.supplier_name || '') }, { label: 'Transport', render: x => x.transport_type === 'courier' ? E.esc((x.courier_name || '') + ' · ' + (x.tracking_number || '')) : E.esc((x.vehicle_number || '') + ' · ' + (x.driver_name || '')) },
                       { label: 'Dispatched', render: x => E.date(x.dispatch_date) }, { label: 'Delivered', render: x => E.date(x.delivered_date) }, { label: 'Status', render: x => SF.badge(x.status === 'delivered' ? 'delivered_' : x.status) }],
            register: [{ label: 'Date', render: x => E.date(x.created_at) + ' ' + E.esc(String(x.created_at).slice(11, 16)) }, { label: 'Location', render: x => E.esc(x.warehouse_name || '') }, { label: 'Item', render: x => E.esc(x.item_name) },
                       { label: 'Qty out', num: true, render: x => E.qty(x.quantity) }, { label: 'Weight', num: true, render: x => SF.kg(x.weight_kg) }, { label: 'Reason', render: x => E.esc((PURPOSE[x.category] || x.category).replace(/_/g, ' ')) },
                       { label: 'Reference', render: x => E.esc(x.reference_number || '') }, { label: 'Requested', render: x => E.esc(x.requested_by || '—') }, { label: 'Approved', render: x => E.esc(x.approved_by || '—') },
                       { label: 'Issued', render: x => E.esc(x.issued_by || '') }, { label: 'Received', render: x => E.esc(x.received_by || '—') }],
            location: null,
        }[TAB];
        if (TAB === 'location') {
            const wh = r.warehouses;
            E.table($('#list'), [{ label: 'Item', render: x => E.esc(x.item_name) }].concat(wh.map(w => ({ label: w.name, num: true, render: x => { const b = x.by_location[w.name] || {}; return (b.available ? E.qty(b.available) : '—') + (b.damaged ? ` <span class="erp-neg">+${E.qty(b.damaged)} dmg</span>` : '') + (b.rejected ? ` <span class="erp-neg">+${E.qty(b.rejected)} rej</span>` : ''); } })))
                .concat([{ label: 'Total available', num: true, render: x => '<strong>' + E.qty(x.total_available) + '</strong>' }, { label: 'Weight', num: true, render: x => x.unit_weight_kg ? SF.kg(x.total_available * x.unit_weight_kg) : '—' }]), r.rows, { empty: 'No stock', icon: 'fa-cubes' });
            return;
        }
        E.table($('#list'), cols, r.rows, { empty: 'Nothing here yet', icon: 'fa-boxes-stacked' });
        if (TAB === 'register') $('#fCsv').off('click').on('click', () => E.csv(r.rows, [{ label: 'Date', key: 'created_at' }, { label: 'Location', key: 'warehouse_name' }, { label: 'Item', key: 'item_name' }, { label: 'Qty', key: 'quantity' }, { label: 'Weight kg', key: 'weight_kg' },
            { label: 'Reason', key: 'category' }, { label: 'Reference', key: 'reference_number' }, { label: 'Requested', key: 'requested_by' }, { label: 'Approved', key: 'approved_by' }, { label: 'Issued', key: 'issued_by' }, { label: 'Received', key: 'received_by' }], 'stock_out_register.csv'));
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-row]', function () { openRow(TAB, $(this).data('row')); });

// ------------------------------------------------------------ NEW
$('#newBtn').on('click', () => ({ issue: newIssue, damage: newDamage, returns: newReturn, opening: newOpening, dispatch: newDispatch })[TAB]());
function lineRows() { return `<div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Item</th><th>Batch</th><th>Qty</th><th>Weight kg (optional)</th><th></th></tr></thead><tbody></tbody></table></div><button type="button" class="adm-btn adm-btn-ghost" data-addl><i class="fas fa-plus"></i> Add line</button>`; }
function addLine($p) { const n = Date.now(); $p.find('tbody').append(`<tr><td style="min-width:260px">${E.select('li' + n, E.itemOptions(ITEMS), 'data-f="it"')}</td><td>${E.select('lb' + n, '<option value="">Any batch (FEFO)</option>', 'data-f="b"')}</td><td><input class="adm-input" type="number" min="0" step="any" data-f="q"></td><td><input class="adm-input" type="number" min="0" step="any" data-f="w"></td><td><button class="adm-icon-btn is-danger" data-dell><i class="fas fa-xmark"></i></button></td></tr>`); }
function linesOf($p) { return $p.find('tbody tr').map(function () { const v = String($(this).find('[data-f=it]').val() || ''); return v ? { item_type: v.split(':')[0], item_id: v.split(':')[1], batch_id: $(this).find('[data-f=b]').val(), quantity: $(this).find('[data-f=q]').val(), weight_kg: $(this).find('[data-f=w]').val() } : null; }).get().filter(Boolean); }
function lineEvents(p) {
    const $p = $(p); addLine($p);
    $p.on('click', '[data-addl]', () => addLine($p)); $p.on('click', '[data-dell]', function () { $(this).closest('tr').remove(); });
    $p.on('change', '[data-f=it]', function () { const $r = $(this).closest('tr'); loadBatches($r, this.id, $r.find('[data-f=b]').attr('id')); });
}
function newIssue() {
    E.form('New stock out', `<div class="erp-grid">${E.field('Purpose *', E.select('iP', Object.keys(PURPOSE).map(k => `<option value="${k}">${PURPOSE[k]}</option>`).join('')))}${E.field('From location *', E.select('iW', SF.whOptions(1)))}
        ${E.field('Date *', E.input('iD', E.today(), 'type="date"'))}${E.field('Requested by *', E.input('iR', ''))}${E.field('Will be received by', E.input('iRc', ''))}${E.field('Purpose details *', E.input('iN', '', 'placeholder="e.g. Trade show samples for Chennai expo"'))}
        ${E.field('Remarks', E.input('iRem', ''), 'span-all')}</div>${lineRows()}<p class="sf-note">Sales, transfers, damage and returns have their own screens (they reduce stock there). Stock is reduced only when the approved request is issued.</p>`,
        () => E.post(SF.API, { action: 'iss_create', purpose: SF.v('iP'), warehouse_id: SF.v('iW'), issue_date: SF.v('iD'), requested_by: SF.v('iR'), received_by: SF.v('iRc'), purpose_note: SF.v('iN'), remarks: SF.v('iRem'), lines: linesOf($('.swal2-popup')) }, { silent: true }).then(x => done(x.message)),
        { width: 1000, confirmText: 'Request', didOpen: lineEvents });
}
function newDamage() {
    E.form('Report damage', `<div class="erp-grid">${E.field('Item *', itemSel('dI'))}${E.field('Batch', batchSelect('dB'))}${E.field('Location *', E.select('dW', SF.whOptions(1)))}${E.field('Quantity *', E.input('dQ', '', 'type="number" min="0" step="any"'))}
        ${E.field('Weight kg', E.input('dKg', '', 'type="number" min="0" step="any"'))}${E.field('Date', E.input('dD', E.today(), 'type="date"'))}${E.field('Identified by *', E.input('dBy', ''))}
        ${E.field('Found during', E.select('dS', '<option value="stock">Stock check</option><option value="sales_return">Sales return</option><option value="qc">QC</option><option value="audit">Audit</option>'))}${E.field('Reason *', E.input('dR', ''), 'span-all')}</div>
        <p class="sf-note">Nothing leaves sellable stock now. Another person verifies, then an approver approves — only then the quantity moves to DAMAGED stock. Attach photos after saving.</p>`,
        () => E.post(SF.API, Object.assign({ action: 'dmg_create', batch_id: SF.v('dB'), warehouse_id: SF.v('dW'), quantity: SF.v('dQ'), weight_kg: SF.v('dKg'), damage_date: SF.v('dD'), identified_by: SF.v('dBy'), source: SF.v('dS'), reason: SF.v('dR') }, pickItem('dI')), { silent: true })
            .then(x => { done(x.message); setTimeout(() => openRow('damage', x.id), 400); }),
        { width: 900, didOpen: p => $(p).on('change', '#dI', () => loadBatches($(p), 'dI', 'dB')) });
}
function newReturn() {
    E.form('Receive returned goods', `<div class="erp-grid">${E.field('Sale type *', E.select('rT', '<option value="invoice">Invoice</option><option value="manual_sale">Manual sale</option><option value="credit_sale">Credit sale</option><option value="website_order">Website order</option>'))}
        ${E.field('Invoice / sale number *', E.input('rN', ''))}<div class="adm-field"><label>&nbsp;</label><button type="button" class="adm-btn adm-btn-ghost" id="rLoad"><i class="fas fa-magnifying-glass"></i> Load sold items</button></div>
        ${E.field('Customer', E.input('rC', ''))}${E.field('Received at *', E.select('rW', SF.whOptions(1)))}${E.field('Return date *', E.input('rD', E.today(), 'type="date"'))}${E.field('Received by *', E.input('rBy', ''))}${E.field('Reason *', E.input('rR', ''), 'span-all')}</div>
        <div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Product</th><th>Sold / returnable</th><th>Returned qty</th><th>Batch</th><th>Weight kg</th></tr></thead><tbody id="rLines"><tr><td colspan="5" class="erp-muted">Load the sold items, or add products below.</td></tr></tbody></table></div>
        <button type="button" class="adm-btn adm-btn-ghost" id="rAdd"><i class="fas fa-plus"></i> Add product</button><p class="sf-note">The goods are NOT in stock yet — QC decides resalable / damaged / rejected next.</p>`,
        () => E.post(SF.API, { action: 'rr_create', source_type: SF.v('rT'), source_ref: SF.v('rN'), customer_name: SF.v('rC'), warehouse_id: SF.v('rW'), return_date: SF.v('rD'), received_by: SF.v('rBy'), reason: SF.v('rR'),
            lines: $('.swal2-popup #rLines tr[data-p], .swal2-popup #rLines tr[data-new]').map(function () { const $r = $(this); return { product_id: $r.data('p') || String($r.find('[data-f=it]').val() || '').split(':')[1], quantity: $r.find('[data-f=q]').val(), batch_number: $r.find('[data-f=b]').val(), weight_kg: $r.find('[data-f=w]').val() }; }).get() }, { silent: true })
            .then(x => { done(x.message); setTimeout(() => openRow('returns', x.id), 400); }),
        { width: 1000, confirmText: 'Receive', didOpen: p => {
            $(p).on('click', '#rLoad', () => E.api('inventory_ops_api.php', { action: 'sret_source', source_type: SF.v('rT'), source_ref: SF.v('rN') }).then(s => {
                $(p).find('#rC').val(s.customer_name || ''); if (s.warehouse_id) $(p).find('#rW').val(s.warehouse_id);
                $(p).find('#rLines').html(s.lines.map(l => `<tr data-p="${l.product_id}"><td>${E.esc(l.product_name)}</td><td class="erp-num">${E.qty(l.sold_qty)} / ${E.qty(l.returnable_qty)}</td><td><input class="adm-input" type="number" min="0" step="1" data-f="q"></td><td><input class="adm-input" data-f="b"></td><td><input class="adm-input" type="number" step="any" data-f="w"></td></tr>`).join(''));
            }).catch(() => {}));
            $(p).on('click', '#rAdd', () => { $(p).find('#rLines tr:not([data-p]):not([data-new])').remove(); $(p).find('#rLines').append(`<tr data-new="1"><td style="min-width:240px">${E.select('', E.itemOptions(ITEMS.filter(i => i.item_type === 'product')), 'data-f="it"')}</td><td>—</td><td><input class="adm-input" type="number" min="0" step="1" data-f="q"></td><td><input class="adm-input" data-f="b"></td><td><input class="adm-input" type="number" step="any" data-f="w"></td></tr>`); });
        } });
}
function newOpening() {
    E.form('Enter opening stock', `<div class="erp-grid">${E.field('Item *', itemSel('oI'))}${E.field('Location *', E.select('oW', SF.whOptions(1)))}${E.field('Opening date *', E.input('oD', E.today(), 'type="date"'))}
        ${E.field('Batch', E.input('oB', ''))}${E.field('Expiry', E.input('oE', '', 'type="date"'))}${E.field('Opening quantity *', E.input('oQ', '', 'type="number" min="0" step="any"'))}${E.field('Physical count * (must match)', E.input('oPc', '', 'type="number" min="0" step="any"'))}
        ${E.field('Opening weight kg', E.input('oKg', '', 'type="number" min="0" step="any"'))}${E.field('Unit cost ₹', E.input('oC', '', 'type="number" min="0" step="any"'))}${E.field('Entered by *', E.input('oBy', ''))}${E.field('Remarks', E.input('oR', ''), 'span-all')}</div>
        <p class="sf-note">Stock is added only when another person verifies it (Approvals). Attach the counting sheet / photo after saving.</p>`,
        () => E.post(SF.API, Object.assign({ action: 'ops_create', warehouse_id: SF.v('oW'), opening_date: SF.v('oD'), batch_number: SF.v('oB'), expiry_date: SF.v('oE'), quantity: SF.v('oQ'), physical_count: SF.v('oPc'), weight_kg: SF.v('oKg'), unit_cost: SF.v('oC'), entered_by: SF.v('oBy'), remarks: SF.v('oR') }, pickItem('oI')), { silent: true })
            .then(x => { done(x.message); setTimeout(() => openRow('opening', x.id), 400); }), { width: 900 });
}
function newDispatch() {
    E.api(SF.API, { action: 'rd_rejected_stock' }).then(r => SF.meta().then(m => E.form('New return dispatch', `<div class="erp-grid">${E.field('What is going back *', E.select('xS', '<option value="purchase_return">A posted purchase return</option><option value="qc_rejected">Goods rejected at QC (rejected stock)</option>'))}
        <div class="adm-field" data-src="purchase_return"><label>Purchase return *</label>${E.select('xR', E.options(r.returns, 'id', x => `${x.return_number} — ${x.supplier_name} (${E.money(x.total_value)})`, null, 'Choose…'))}</div>
        <div class="adm-field" data-src="qc_rejected" style="display:none"><label>Goods receipt *</label>${E.select('xG', E.options(r.grns, 'id', x => `${x.grn_number} — ${x.supplier_name}`, null, 'Choose…'))}</div>
        ${E.field('Reason *', E.input('xRe', ''))}${E.field('Transport *', E.select('xT', '<option value="internal">Internal vehicle</option><option value="courier">Courier</option>'))}
        ${E.field('Vehicle number', E.input('xV', ''))}${E.field('Driver', E.input('xD', ''))}${E.field('Driver phone', E.input('xDp', ''))}
        ${E.field('Courier company', E.select('xC', E.options(m.couriers, 'id', x => x.name, null, 'Choose…')))}${E.field('Courier name (if Other)', E.input('xCn', ''))}${E.field('Tracking number', E.input('xTr', ''))}${E.field('Courier phone (optional)', E.input('xCp', ''))}
        ${E.field('Planned dispatch date', E.input('xDd', E.today(), 'type="date"'))}</div>
        <div data-src="qc_rejected" style="display:none"><h4>Rejected stock</h4><div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Item</th><th>Location</th><th>Rejected stock</th><th>Return qty</th></tr></thead><tbody>${r.rows.map(x => `<tr data-it="${x.item_type}:${x.item_id}"><td>${E.esc(x.item_name)}</td><td>${E.esc(x.warehouse_name)}</td><td class="erp-num">${E.qty(x.quantity)}</td><td><input class="adm-input" type="number" min="0" step="any" data-f="q"></td></tr>`).join('') || '<tr><td colspan="4" class="erp-muted">No rejected stock.</td></tr>'}</tbody></table></div></div>
        <p class="sf-note">A purchase return already reduced the stock — the dispatch records the transport, tracking and proof. Rejected stock leaves the location when the approved dispatch is sent.</p>`,
        () => E.post(SF.API, { action: 'rd_create', source: SF.v('xS'), purchase_return_id: SF.v('xR'), grn_id: SF.v('xG'), reason: SF.v('xRe'), transport_type: SF.v('xT'), vehicle_number: SF.v('xV'), driver_name: SF.v('xD'), driver_phone: SF.v('xDp'),
            courier_service_id: SF.v('xC'), courier_name: SF.v('xCn'), tracking_number: SF.v('xTr'), courier_phone: SF.v('xCp'), dispatch_date: SF.v('xDd'),
            lines: $('.swal2-popup tr[data-it]').map(function () { const q = $(this).find('[data-f=q]').val(); if (!q) return null; const [t, i] = String($(this).data('it')).split(':'); return { item_type: t, item_id: i, quantity: q }; }).get() }, { silent: true })
            .then(x => { done(x.message); setTimeout(() => openRow('dispatch', x.id), 400); }),
        { width: 1000, didOpen: p => $(p).on('change', '#xS', function () { $(p).find('[data-src]').hide(); $(p).find(`[data-src="${this.value}"]`).show(); }) }))).catch(() => {});
}

// ------------------------------------------------------------ DETAIL + ACTIONS
function act(a, id, extra) { return E.post(SF.API, Object.assign({ action: a, id }, extra || {}), { silent: true }).then(x => done(x.message)).catch(fail); }
function ask(title, a, id, field, opts) { return E.confirmAction(title, '', Object.assign({ reason: field || 'Remarks' }, opts || {})).then(v => act(a, id, { reason: v, remarks: v, received_by: v, verified_by: v })).catch(fail); }
function openRow(tab, id) {
    const T = { issue: ['iss_get', 'stock_issue', 'OTHER'], damage: ['dmg_get', 'stock_damage', 'DAMAGE_PHOTO'], returns: ['rr_get', 'return_receipt', 'SALES_RETURN'], opening: ['ops_list', 'opening_stock', 'COUNTING_SHEET'], dispatch: ['rd_get', 'return_dispatch', 'TRACKING_PROOF'] }[tab];
    if (!T) return;
    E.api(SF.API, { action: T[0], id }).then(r => {
        const x = r.record || (r.rows || []).find(o => String(o.id) === String(id));
        if (!x) return;
        let html = '', acts = [];
        if (tab === 'issue') {
            html = E.kv([['Purpose', E.esc(PURPOSE[x.purpose] || x.purpose)], ['Location', E.esc(x.warehouse_name)], ['Date', E.date(x.issue_date)], ['Status', SF.badge(x.status)], ['Requested by', E.esc(x.requested_by)], ['Purpose details', E.esc(x.purpose_note)],
                ['Approved by', E.esc(x.approved_by || '—')], ['Issued by', E.esc(x.issued_by || '—')], ['Received by', E.esc(x.received_by || '—')], x.stock_out_ref ? ['Stock out', E.esc(x.stock_out_ref)] : null])
                + `<div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Batch</th><th>Qty</th><th>Weight</th></tr></thead><tbody>${x.lines.map(l => `<tr><td>${E.esc(l.item_name)}</td><td>${E.esc(l.batch_number || 'FEFO')}</td><td class="erp-num">${E.qty(l.quantity, l.unit)}</td><td class="erp-num">${SF.kg(l.weight_kg)}</td></tr>`).join('')}</tbody></table></div>`;
            if (x.status === 'requested' && SF.can('stockflow.approve')) acts.push({ label: 'Approve', cls: 'adm-btn-primary', icon: 'fa-check', run: () => act('iss_approve', x.id) }, { label: 'Reject', icon: 'fa-xmark', run: () => ask('Reject ' + x.issue_number + '?', 'iss_reject', x.id, 'Reason') });
            if (x.status === 'approved' && SF.can('stockflow.issue')) acts.push({ label: 'Issue (reduce stock)', cls: 'adm-btn-primary', icon: 'fa-hand-holding', run: () => E.confirmAction('Who received the goods?', '', { reason: 'Received by' }).then(v => act('iss_issue', x.id, { received_by: v })).catch(fail) });
            if (['requested', 'approved'].includes(x.status) && SF.can('stockflow.issue')) acts.push({ label: 'Cancel', icon: 'fa-ban', run: () => ask('Cancel?', 'iss_cancel', x.id, 'Reason') });
        } else if (tab === 'damage') {
            html = E.kv([['Item', E.esc(x.item_name)], ['Batch', E.esc(x.batch_number || '—')], ['Location', E.esc(x.warehouse_name)], ['Quantity', E.qty(x.quantity)], ['Weight', SF.kg(x.weight_kg)], ['Reason', E.esc(x.reason)],
                ['Identified by', E.esc(x.identified_by)], ['Verified by', E.esc(x.verified_by || '—')], ['Approved by', E.esc(x.approved_by || '—')], ['Status', SF.badge(x.status)], ['Disposal', E.badge(x.disposal_status)]])
                + (x.history.length ? x.history.map(h => `<div class="erp-muted">${E.date(h.created_at)} · ${E.esc(h.action)} · ${E.esc(h.username || '')}</div>`).join('') : '');
            if (x.status === 'identified' && (SF.can('stockflow.approve') || SF.can('stockflow.qc'))) acts.push({ label: 'Verify', cls: 'adm-btn-primary', icon: 'fa-magnifying-glass', run: () => ask('Verified by (name)', 'dmg_verify', x.id, 'Verified by') });
            if (x.status === 'verified' && SF.can('stockflow.approve')) acts.push({ label: 'Approve → damaged stock', cls: 'adm-btn-primary', icon: 'fa-check', run: () => act('dmg_approve', x.id) }, { label: 'Reject', icon: 'fa-xmark', run: () => ask('Reject?', 'dmg_reject', x.id, 'Reason') });
            if (x.status === 'approved' && x.disposal_status === 'pending' && SF.can('stockflow.approve'))
                ['disposed', 'destroyed', 'returned_to_supplier', 'restored'].forEach(d => acts.push({ label: d === 'restored' ? 'Restore to sellable' : d.replace(/_/g, ' ').replace(/^./, c => c.toUpperCase()), icon: 'fa-dumpster', run: () => E.confirmAction(d.replace(/_/g, ' ') + '?', '', { reason: 'Remarks', optional: true }).then(v => act('dmg_dispose', x.id, { disposal: d, reason: v })).catch(fail) }));
        } else if (tab === 'returns') {
            html = E.kv([['Sale', E.esc(x.source_type.replace('_', ' ') + ' ' + x.source_ref)], ['Customer', E.esc(x.customer_name || '')], ['Location', E.esc(x.warehouse_name || '')], ['Return date', E.date(x.return_date)], ['Reason', E.esc(x.reason)],
                ['Received by', E.esc(x.received_by)], ['QC by', E.esc(x.qc_by || '—')], ['Status', SF.badge(x.status)], x.return_number ? ['Sales return', E.esc(x.return_number)] : null]);
            const qc = x.status === 'received' && SF.can('stockflow.qc');
            html += `<div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Product</th><th>Batch</th><th>Returned</th><th>Weight</th><th>Resalable</th><th>Damaged</th><th>Rejected</th><th>Note</th><th>Result</th></tr></thead><tbody>` +
                x.lines.map(l => `<tr data-l="${l.id}"><td>${E.esc(l.product_name)}</td><td>${E.esc(l.batch_number || '—')}</td><td class="erp-num">${E.qty(l.quantity)}</td><td class="erp-num">${SF.kg(l.weight_kg)}</td>` +
                    (qc ? `<td><input class="adm-input" type="number" min="0" step="1" data-f="a" value="${l.quantity}"></td><td><input class="adm-input" type="number" min="0" step="1" data-f="d" value="0"></td><td><input class="adm-input" type="number" min="0" step="1" data-f="x" value="0"></td><td><input class="adm-input" data-f="n"></td><td></td>`
                        : `<td class="erp-num">${E.qty(l.resalable_qty)}</td><td class="erp-num">${E.qty(l.damaged_qty)}</td><td class="erp-num">${E.qty(l.rejected_qty)}</td><td>${E.esc(l.qc_note || '')}</td><td>${E.esc(l.qc_result || '—')}</td>`) + '</tr>').join('') + '</tbody></table></div>';
            if (qc) {
                html += `<div class="erp-grid">${E.field('QC by', E.input('rqBy', ''))}${E.field('QC remarks', E.input('rqRem', ''))}</div><p class="sf-note">Resalable → back to stock. Damaged → DAMAGED stock. Rejected → not taken back. Attach photos.</p>`;
                acts.push({ label: 'Save QC', cls: 'adm-btn-primary', icon: 'fa-microscope', run: () => E.post(SF.API, { action: 'rr_qc', id: x.id, qc_by: SF.v('rqBy'), qc_remarks: SF.v('rqRem'),
                    lines: $('.swal2-popup tr[data-l]').map(function () { const $r = $(this); return { id: $r.data('l'), resalable_qty: $r.find('[data-f=a]').val(), damaged_qty: $r.find('[data-f=d]').val(), rejected_qty: $r.find('[data-f=x]').val(), qc_note: $r.find('[data-f=n]').val() }; }).get() }, { silent: true })
                    .then(q => { E.toast(q.message); openRow('returns', x.id); }).catch(fail) });
            }
            if (x.status === 'qc_done' && SF.can('stockflow.returns')) acts.push({ label: 'Post sales return', cls: 'adm-btn-primary', icon: 'fa-rotate-left', run: () => postReturn(x) });
            if (['received', 'qc_done'].includes(x.status) && SF.can('stockflow.returns')) acts.push({ label: 'Cancel', icon: 'fa-ban', run: () => ask('Cancel?', 'rr_cancel', x.id, 'Reason') });
        } else if (tab === 'opening') {
            html = E.kv([['Item', E.esc(x.item_name)], ['Location', E.esc(x.warehouse_name)], ['Batch', E.esc(x.batch_number || '—')], ['Expiry', E.date(x.expiry_date)], ['Quantity', E.qty(x.quantity, x.unit)], ['Physical count', E.qty(x.physical_count)],
                ['Weight', SF.kg(x.weight_kg)], ['Unit cost', x.unit_cost ? E.money(x.unit_cost) : '—'], ['Opening date', E.date(x.opening_date)], ['Entered by', E.esc(x.entered_by)], ['Verified by', E.esc(x.verified_by || '—')], ['Status', SF.badge(x.status)], x.posted_ref ? ['Posted', E.esc(x.posted_ref)] : null]);
            if (x.status === 'entered' && SF.can('stockflow.approve')) acts.push({ label: 'Verify → add to stock', cls: 'adm-btn-primary', icon: 'fa-check', run: () => act('ops_verify', x.id) }, { label: 'Reject', icon: 'fa-xmark', run: () => ask('Reject?', 'ops_reject', x.id, 'Reason') });
        } else if (tab === 'dispatch') {
            html = E.kv([['From', x.source === 'qc_rejected' ? 'QC rejected stock · ' + E.esc(x.grn_number || '') : 'Purchase return ' + E.esc(x.return_number || '')], ['Supplier', E.esc(x.supplier_name || '')], ['Location', E.esc(x.warehouse_name)], ['Reason', E.esc(x.reason)],
                ['Transport', x.transport_type === 'courier' ? E.esc(`${x.courier_name || ''} · tracking ${x.tracking_number || ''}${x.courier_phone ? ' · ' + x.courier_phone : ''}`) : E.esc(`${x.vehicle_number || ''} · ${x.driver_name || ''} ${x.driver_phone || ''}`)],
                ['Approved by', E.esc(x.approved_by || '—')], ['Dispatched', E.date(x.dispatch_date)], ['Delivered', E.date(x.delivered_date)], ['Status', SF.badge(x.status === 'delivered' ? 'delivered_' : x.status)]])
                + `<div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Qty</th><th>Weight</th></tr></thead><tbody>${x.lines.map(l => `<tr><td>${E.esc(l.item_name)}</td><td class="erp-num">${E.qty(l.quantity)}</td><td class="erp-num">${SF.kg(l.weight_kg)}</td></tr>`).join('')}</tbody></table></div>`;
            if (x.status === 'requested' && SF.can('stockflow.approve')) acts.push({ label: 'Approve', cls: 'adm-btn-primary', icon: 'fa-check', run: () => act('rd_approve', x.id) }, { label: 'Reject', icon: 'fa-xmark', run: () => ask('Reject?', 'rd_reject', x.id, 'Reason') });
            if (x.status === 'approved' && SF.can('stockflow.issue')) acts.push({ label: 'Dispatch', cls: 'adm-btn-primary', icon: 'fa-truck-fast', run: () => act('rd_dispatch', x.id) });
            if (x.status === 'dispatched' && SF.can('stockflow.issue')) acts.push({ label: 'Delivered to supplier', cls: 'adm-btn-primary', icon: 'fa-flag-checkered', run: () => act('rd_deliver', x.id, { delivered_date: E.today() }) });
        }
        E.view(x.issue_number || x.damage_number || x.receipt_number || x.opening_number || x.dispatch_number, html + '<div id="vDocs"></div>', acts, { width: 1050, didOpen: () => E.docs($('#vDocs'), T[1], x.id, T[2]) });
    }).catch(() => {});
}
function postReturn(x) {
    const lines = x.lines.filter(l => +l.resalable_qty + +l.damaged_qty > 0);
    if (!lines.length) return act('rr_link', x.id);
    E.form('Post sales return ' + x.receipt_number, `<div class="erp-grid">${E.field('Settlement', E.select('sS', '<option value="credit_note">Credit note</option><option value="refund">Refund</option><option value="replacement">Replacement</option>'))}
        ${E.field('Refund / credit ₹ (blank = full value)', E.input('sA', '', 'type="number" min="0" step="any"'))}${E.field('Refund mode', E.select('sM', '<option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>'))}</div>
        <p class="sf-note">Posts through the existing Sales Returns module (credit note / refund / approval rules apply): resalable back to stock, damaged to damaged stock.</p>`,
        () => {
            const p = { action: 'sret_post', source_type: x.source_type, source_ref: x.source_ref, return_date: x.return_date, reason: x.reason, settlement: SF.v('sS'), refund_mode: SF.v('sM'), notes: 'Return receipt ' + x.receipt_number,
                        items: lines.map(l => ({ product_id: l.product_id, restock_qty: l.resalable_qty, damaged_qty: l.damaged_qty })) };
            if (SF.v('sA') !== '') p.refund_amount = SF.v('sA');
            return E.post('inventory_ops_api.php', p, { silent: true }).then(s => s.pending_approval ? E.toast(s.message, 'info') : E.post(SF.API, { action: 'rr_link', id: x.id, sales_return_id: s.id }, { silent: true }).then(y => done(y.message)));
        }, { width: 800, confirmText: 'Post' });
}
JS
);
