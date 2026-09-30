<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Goods Receipts (GRN)', 'Receive goods against a PO — only ACCEPTED quantity is added to stock (through Stock In)',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-dolly"></i> Receive goods</button>');
?>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>Goods receipts</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="draft">Draft</option><option value="posted">Posted</option><option value="cancelled">Cancelled</option></select>
            <select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input type="text" class="adm-input" id="fQ" placeholder="GRN, PO or supplier">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [], WH = [], ITEMS = [];
Promise.all([E.suppliers(), E.warehouses(), E.items()]).then(([s, w, i]) => {
    SUP = s; WH = w; ITEMS = i;
    $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join(''));
    if (E.param('status')) $('#fStatus').val(E.param('status'));
    load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('po_id')) startFromPo(E.param('po_id'));
});
$('#fStatus,#fSupplier,#fFrom,#fTo').on('change', load);
let t; $('#fQ').on('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
$('#newBtn').on('click', chooseSource);

function load() {
    const $l = $('#list'); E.loading($l);
    E.api('purchase_api.php', { action: 'grn_list', status: $('#fStatus').val(), supplier_id: $('#fSupplier').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true })
     .then(r => E.table($l, [
        { label: 'GRN', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.grn_number)}</a>` },
        { label: 'Received', render: x => E.date(x.received_date) },
        { label: 'Supplier', render: x => E.esc(x.supplier_name) },
        { label: 'PO', render: x => x.po_number ? `<a class="erp-link" href="purchase_orders.php?id=${x.po_id}">${E.esc(x.po_number)}</a>` : '<span class="erp-muted">Direct</span>' },
        { label: 'Accepted', num: true, render: x => E.qty(x.accepted_qty) },
        { label: 'Rejected', num: true, render: x => E.num(x.rejected_qty) ? `<span class="erp-neg">${E.qty(x.rejected_qty)}</span>` : '0' },
        { label: 'Value', num: true, render: x => E.money(x.accepted_value) },
        { label: 'Stock In', render: x => x.stock_in_number ? E.esc(x.stock_in_number) : '—' },
        { label: 'Status', render: x => E.badge(x.status) },
     ], r.rows, { empty: 'No goods receipts yet', icon: 'fa-dolly' }))
     .catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function chooseSource() {
    E.api('purchase_api.php', { action: 'po_list', status: 'open' }).then(r => {
        Swal.fire({ title: 'Receive goods', customClass: { popup: 'erp-modal' }, width: 620, showCancelButton: true, confirmButtonColor: '#1c5034',
            html: E.field('Against purchase order', E.select('srcPo', E.options(r.rows, 'id', p => `${p.po_number} — ${p.supplier_name} (${p.status.replace('_', ' ')})`, '', r.rows.length ? 'Choose an approved PO' : 'No approved POs open'))) +
                  '<p class="erp-note">Or leave empty and continue to receive goods without a PO (direct purchase).</p>',
            confirmButtonText: 'Continue' }).then(x => { if (!x.isConfirmed) return; const po = $('#srcPo').val(); po ? startFromPo(po) : openForm({ lines: [] }); });
    }).catch(() => {});
}
function startFromPo(poId) {
    E.api('purchase_api.php', { action: 'grn_prefill', po_id: poId }).then(r => {
        openForm({ po: r.po, lines: r.items.filter(i => i.pending_qty > 0).map(i => ({ po_item_id: i.id, item_type: i.item_type, item_id: i.item_id, item_name: i.item_name, unit: i.unit,
            ordered_qty: i.quantity, pending: i.pending_qty, received_qty: i.pending_qty, rejected_qty: 0, rate: i.net_rate })) });
    }).catch(() => {});
}

function lineRow(l, direct) {
    const itemCell = direct ? `<td style="min-width:220px">${E.select('', E.itemOptions(ITEMS, l.item_type, l.item_id), 'data-f="item"')}</td>`
                            : `<td style="min-width:180px">${E.esc(l.item_name)}<div class="erp-muted">Ordered ${E.qty(l.ordered_qty)} · pending ${E.qty(l.pending)}</div></td>`;
    return `<tr data-po-item="${l.po_item_id || ''}">${itemCell}
        ${direct ? `<td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="rate" value="${E.esc(l.rate ?? '')}"></td>` : ''}
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="rec" value="${E.esc(l.received_qty ?? '')}"></td>
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="rej" value="${E.esc(l.rejected_qty ?? 0)}"></td>
        <td class="erp-num" data-f="acc">0</td>
        <td><input class="adm-input" data-f="reason" placeholder="If rejected" value="${E.esc(l.rejection_reason || '')}"></td>
        <td><input class="adm-input" data-f="batch" value="${E.esc(l.batch_number || '')}"></td>
        <td><input class="adm-input" data-f="lot" value="${E.esc(l.lot_number || '')}"></td>
        <td><input class="adm-input" type="date" data-f="mfg" value="${E.esc(l.manufacturing_date || '')}"></td>
        <td><input class="adm-input" type="date" data-f="exp" value="${E.esc(l.expiry_date || '')}"></td>
        <td>${E.select('', ['passed', 'partial', 'failed', 'pending'].map(q => `<option ${q === (l.qc_status || '') ? 'selected' : ''}>${q}</option>`).join(''), 'data-f="qc"')}</td>
        ${direct ? '<td><button type="button" class="adm-icon-btn is-danger" data-f="del"><i class="fas fa-xmark"></i></button></td>' : ''}</tr>`;
}

function openForm(ctx) {
    const g = ctx.record || {};
    const po = ctx.po || null;
    const direct = !po && !g.po_id;
    const html = `<div class="erp-grid">
        ${po ? E.field('Purchase order', `<div><strong>${E.esc(po.po_number)}</strong> · ${E.esc(po.supplier_name)}</div>`) :
               E.field('Supplier *', E.select('gSup', E.options(SUP, 'id', s => s.supplier_name, g.supplier_id, 'Choose supplier'), g.po_id ? 'disabled' : ''))}
        ${E.field('Received date *', E.input('gDate', g.received_date || E.today(), 'type="date" max="' + E.today() + '"'))}
        ${E.field('Warehouse', E.select('gWh', E.options(WH, 'id', w => w.name, g.warehouse_id || (po && po.warehouse_id) || 1, false)))}
        ${E.field('Received by', E.input('gBy', g.received_by || ''))}
        ${E.field('Supplier challan / DC no.', E.input('gDc', g.supplier_challan_no || ''))}
        ${E.field('Vehicle no.', E.input('gVeh', g.vehicle_number || ''))}
        ${E.field('Notes', E.textarea('gNotes', g.notes || ''), 'span-all')}
      </div>
      <div class="erp-section-title">Received items</div>
      <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th>${direct ? '<th>Rate ₹</th>' : ''}<th>Received</th><th>Rejected</th><th>Accepted</th><th>Rejection reason</th>
        <th>Batch</th><th>Lot</th><th>Mfg date</th><th>Expiry</th><th>QC</th>${direct ? '<th></th>' : ''}</tr></thead><tbody id="gLines">${ctx.lines.map(l => lineRow(l, direct)).join('') || (direct ? lineRow({}, true) : '')}</tbody></table></div>
      ${direct ? '<button type="button" class="adm-btn adm-btn-ghost" id="gAdd" style="margin-top:8px;"><i class="fas fa-plus"></i> Add line</button>' : ''}
      <p class="erp-note">Accepted = received − rejected. Only accepted quantity goes into sellable stock when you <strong>Post</strong>. Product packs must be whole numbers; bulk items (kg / L) can have decimals.</p>`;
    const recalc = () => $('#gLines tr').each(function () { const $r = $(this); $r.find('[data-f=acc]').text(E.qty(Math.max(0, E.num($r.find('[data-f=rec]').val()) - E.num($r.find('[data-f=rej]').val())))); });
    const collect = () => $('#gLines tr').map(function () {
        const $r = $(this), it = String($r.find('[data-f=item]').val() || ':').split(':');
        return { po_item_id: $r.data('po-item') || '', item_type: it[0], item_id: it[1], rate: $r.find('[data-f=rate]').val(), received_qty: $r.find('[data-f=rec]').val(), rejected_qty: $r.find('[data-f=rej]').val(),
                 rejection_reason: $r.find('[data-f=reason]').val(), batch_number: $r.find('[data-f=batch]').val(), lot_number: $r.find('[data-f=lot]').val(),
                 manufacturing_date: $r.find('[data-f=mfg]').val(), expiry_date: $r.find('[data-f=exp]').val(), qc_status: $r.find('[data-f=qc]').val() };
    }).get();
    E.form(g.id ? 'Edit ' + g.grn_number : 'Receive goods' + (po ? ' — ' + po.po_number : ' (direct)'), html, (btn) => {
        const payload = { action: btn === 'deny' ? 'grn_save' : 'grn_post', id: g.id || '', po_id: (po && po.id) || g.po_id || '', supplier_id: $('#gSup').val() || g.supplier_id || '',
                          received_date: $('#gDate').val(), warehouse_id: $('#gWh').val(), received_by: $('#gBy').val(), supplier_challan_no: $('#gDc').val(), vehicle_number: $('#gVeh').val(),
                          notes: $('#gNotes').val(), items: collect() };
        const go = () => E.post('purchase_api.php', payload, { silent: true }).then(r => { E.toast(r.message); load(); setTimeout(() => openView(r.id), 300); });
        if (btn === 'deny') return go();
        return go();
    }, { width: 1180, confirmText: 'Post GRN (add accepted stock)', denyText: 'Save draft', didOpen: () => {
        recalc();
        $('#gLines').on('input change', 'input', recalc);
        $('#gAdd').on('click', () => $('#gLines').append(lineRow({}, true)));
        $('#gLines').on('click', '[data-f=del]', function () { $(this).closest('tr').remove(); });
    }});
}

function openView(id) {
    E.api('purchase_api.php', { action: 'grn_get', id }).then(r => {
        const g = r.record;
        const rows = g.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.ordered_qty)}</td><td class="erp-num">${E.qty(i.received_qty)}</td>
            <td class="erp-num">${E.qty(i.accepted_qty, i.unit)}</td><td class="erp-num ${E.num(i.rejected_qty) ? 'erp-neg' : ''}">${E.qty(i.rejected_qty)}</td><td>${E.esc(i.rejection_reason || '')}</td>
            <td class="erp-num">${E.money(i.rate)}</td><td>${E.esc(i.batch_number || '—')}${i.lot_number ? ' / ' + E.esc(i.lot_number) : ''}</td><td>${E.date(i.expiry_date)}</td><td>${E.badge(i.qc_status)}</td></tr>`).join('');
        const html = E.kv([['Supplier', `<a class="erp-link" href="supplier_ledger.php?id=${g.supplier_id}">${E.esc(g.supplier_name)}</a>`], ['Received', E.date(g.received_date)],
                           ['Warehouse', E.esc(g.warehouse_name || '—')], ['Received by', E.esc(g.received_by || '—')], ['Challan', E.esc(g.supplier_challan_no || '—')], ['Status', E.badge(g.status)],
                           g.posted_at ? ['Posted', E.esc(g.posted_by || '') + ' · ' + E.date(g.posted_at)] : null])
            + E.chain([g.po_id ? ['PO ' + g.po_number, 'purchase_orders.php?id=' + g.po_id] : null, g.stock_in_number ? ['Stock In ' + g.stock_in_number, 'stock_in.php'] : null]
                .concat(g.invoices.map(v => ['Bill ' + v.supplier_invoice_no + ' (' + v.status + ')', 'purchase_invoices.php?id=' + v.id]))
                .concat(g.returns.map(x => ['Return ' + x.return_number, 'purchase_returns.php?id=' + x.id])))
            + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Ordered</th><th class="erp-num">Received</th><th class="erp-num">Accepted</th>
               <th class="erp-num">Rejected</th><th>Reason</th><th class="erp-num">Rate</th><th>Batch / lot</th><th>Expiry</th><th>QC</th></tr></thead><tbody>${rows}</tbody></table></div><div id="vDocs"></div>`;
        const run = (a, msg) => () => E.confirmAction(msg, '').then(() => E.post('purchase_api.php', { action: a, id: g.id })).then(x => { E.toast(x.message); load(); openView(g.id); }).catch(() => {});
        E.view(g.grn_number, html, [
            g.status === 'draft' && { label: 'Edit draft', icon: 'fa-pen', run: () => E.api('purchase_api.php', g.po_id ? { action: 'grn_prefill', po_id: g.po_id } : { action: 'grn_get', id: g.id }).then(p => {
                const pend = {}; (p.items || []).forEach(i => pend[i.id] = i);
                openForm({ record: g, po: p.po || null, lines: g.items.map(i => Object.assign({}, i, { pending: pend[i.po_item_id] ? pend[i.po_item_id].pending_qty : '', ordered_qty: i.ordered_qty })) });
            }).catch(() => {}) },
            g.status === 'posted' && { label: 'Enter vendor bill', cls: 'adm-btn-primary', icon: 'fa-file-invoice', run: () => location.href = 'purchase_invoices.php?grn_id=' + g.id },
            g.status === 'posted' && { label: 'Return to supplier', icon: 'fa-rotate-left', run: () => location.href = 'purchase_returns.php?grn_id=' + g.id },
            g.status === 'draft' && { label: 'Cancel draft', icon: 'fa-ban', run: run('grn_cancel', 'Cancel ' + g.grn_number + '?') },
            { label: 'Transport', icon: 'fa-truck', run: () => location.href = 'shipments.php?grn_id=' + g.id + (g.po_id ? '&po_id=' + g.po_id : '') },
        ], { width: 1180, didOpen: () => {
            E.docs($('#vDocs'), 'grn', g.id, 'GRN');
            // quality check + transport for this receipt (1 Oct 2026)
            $('#vDocs').before('<div id="vQc"></div>');
            E.api('procurement_api.php', { action: 'qc_for_grn', grn_id: g.id }, { silent: true }).then(q => {
                let h = '';
                if (q.qc) h += `<div class="erp-docs-row"><i class="fas fa-microscope"></i> Quality check <a class="erp-link" href="quality_checks.php?id=${q.qc.id}">${E.esc(q.qc.qc_number)}</a> ${E.badge(q.qc.status)}</div>`;
                else if (g.status === 'draft') h += `<div class="${q.required ? 'erp-warn' : 'erp-docs-row'}">${q.required ? 'A quality check is required before posting. ' : ''}<button class="adm-btn adm-btn-ghost" id="vQcBtn"><i class="fas fa-microscope"></i> Start quality check</button> — goods stay in quarantine until checked.</div>`;
                q.shipments.forEach(s => { h += `<div class="erp-docs-row"><i class="fas fa-truck"></i> Transport <a class="erp-link" href="shipments.php?id=${s.id}">${E.esc(s.shipment_number)}</a> ${E.badge(s.status)} ${E.money(s.total_cost)} ${s.cost_applied == 1 ? '· in stock cost' : ''}</div>`; });
                $('#vQc').html(h);
                $('#vQcBtn').on('click', () => E.post('procurement_api.php', { action: 'qc_create', grn_id: g.id }).then(x => location.href = 'quality_checks.php?id=' + x.id).catch(() => {}));
            }).catch(() => {});
        } });
    }).catch(() => {});
}
JS
);
