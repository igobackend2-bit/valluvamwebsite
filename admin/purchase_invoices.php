<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Invoices', 'Vendor bills — freight, loading and other direct costs are added to the landed cost of the goods',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-file-invoice"></i> Enter vendor bill</button>');
?>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>Vendor bills</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All</option><option value="unpaid">Unpaid</option><option value="partially_paid">Partially paid</option>
                <option value="overdue">Overdue</option><option value="paid">Paid</option><option value="draft">Draft</option><option value="cancelled">Cancelled</option></select>
            <select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input type="text" class="adm-input" id="fQ" placeholder="Bill no. or supplier">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [], ITEMS = [];
const CH = ['freight', 'transport', 'loading', 'unloading', 'packaging', 'other'];
Promise.all([E.suppliers(), E.items()]).then(([s, i]) => {
    SUP = s; ITEMS = i;
    $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join(''));
    if (E.param('status')) $('#fStatus').val(E.param('status'));
    load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('grn_id')) fromGrn(E.param('grn_id'));
});
$('#fStatus,#fSupplier,#fFrom,#fTo').on('change', load);
let t; $('#fQ').on('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
$('#newBtn').on('click', chooseSource);

function load() {
    const $l = $('#list'); E.loading($l);
    E.api('purchase_api.php', { action: 'pinv_list', status: $('#fStatus').val(), supplier_id: $('#fSupplier').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true })
     .then(r => E.table($l, [
        { label: 'Bill', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.supplier_invoice_no)}</a><div class="erp-muted">${E.esc(x.pinv_number)}</div>` },
        { label: 'Date', render: x => E.date(x.invoice_date) },
        { label: 'Supplier', render: x => E.esc(x.supplier_name) },
        { label: 'GRN', render: x => x.grn_number ? `<a class="erp-link" href="goods_receipts.php?id=${x.grn_id}">${E.esc(x.grn_number)}</a>` : '—' },
        { label: 'Due', render: x => E.date(x.due_date) },
        { label: 'Total', num: true, render: x => E.money(x.grand_total) },
        { label: 'Paid', num: true, render: x => E.money(x.amount_paid) },
        { label: 'Balance', num: true, render: x => `<strong>${E.money(x.balance)}</strong>` },
        { label: 'Status', render: x => E.badge(x.payment_status) },
     ], r.rows, { empty: 'No vendor bills yet', icon: 'fa-file-invoice' }))
     .catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function chooseSource() {
    E.api('erp_reports.php', { report: 'pending' }).then(r => {
        Swal.fire({ title: 'Enter vendor bill', width: 620, customClass: { popup: 'erp-modal' }, showCancelButton: true, confirmButtonColor: '#1c5034', confirmButtonText: 'Continue',
            html: E.field('For goods receipt', E.select('srcGrn', E.options(r.unbilled_grns, 'id', g => `${g.grn_number} — ${g.supplier_name} (${g.received_date})`, '', r.unbilled_grns.length ? 'Choose a received GRN' : 'All GRNs are billed'))) +
                  '<p class="erp-note">Link the bill to its GRN so price differences and freight flow into the product cost. Leave empty for a bill without a GRN (no stock or cost effect).</p>'
        }).then(x => { if (!x.isConfirmed) return; const g = $('#srcGrn').val(); g ? fromGrn(g) : openForm({}); });
    }).catch(() => {});
}
function fromGrn(grnId) {
    E.api('purchase_api.php', { action: 'pinv_prefill', grn_id: grnId }).then(r => openForm({ grn: r.grn,
        lines: r.items.filter(i => i.billable_qty > 0).map(i => ({ grn_item_id: i.id, item_name: i.item_name, unit: i.unit, quantity: i.billable_qty, rate: i.rate, tax_percent: i.tax_percent, discount_amount: 0 })) }))
     .catch(() => {});
}

function chargeRow(c) {
    c = c || {};
    return `<tr><td>${E.select('', CH.map(x => `<option ${x === c.charge_type ? 'selected' : ''}>${x}</option>`).join(''), 'data-f="type"')}</td>
        <td><input class="adm-input" data-f="desc" value="${E.esc(c.description || '')}"></td>
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="amt" value="${E.esc(c.amount ?? '')}"></td>
        <td style="text-align:center"><input type="checkbox" data-f="cost" ${c.add_to_cost === undefined || +c.add_to_cost ? 'checked' : ''} title="Add to product cost"></td>
        <td><button type="button" class="adm-icon-btn is-danger" data-f="cdel"><i class="fas fa-xmark"></i></button></td></tr>`;
}
function grnLineRow(l) {
    return `<tr data-grn-item="${l.grn_item_id}"><td style="min-width:200px">${E.esc(l.item_name)}</td>
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="qty" value="${E.esc(l.quantity)}"></td>
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="rate" value="${E.esc(l.rate)}"></td>
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="discount" value="${E.esc(l.discount_amount || 0)}"></td>
        <td class="w-sm"><input class="adm-input" type="number" min="0" step="any" data-f="tax" value="${E.esc(l.tax_percent || 0)}"></td>
        <td class="erp-num" data-f="total"></td></tr>`;
}

function openForm(ctx) {
    const v = ctx.record || {};
    const grn = ctx.grn || (v.grn_id ? { id: v.grn_id, grn_number: v.grn_number, supplier_name: v.supplier_name, supplier_id: v.supplier_id } : null);
    const html = `<div class="erp-grid">
        ${grn ? E.field('Goods receipt', `<div><strong>${E.esc(grn.grn_number)}</strong> · ${E.esc(grn.supplier_name)}</div>`) : E.field('Supplier *', E.select('iSup', E.options(SUP, 'id', s => s.supplier_name, v.supplier_id, 'Choose supplier')))}
        ${E.field("Vendor's bill no. *", E.input('iNo', v.supplier_invoice_no || ''))}
        ${E.field('Bill date *', E.input('iDate', v.invoice_date || E.today(), 'type="date"'))}
        ${E.field('Due date', E.input('iDue', v.due_date || '', 'type="date"'))}
        ${E.field('Notes', E.textarea('iNotes', v.notes || ''), 'span-all')}
      </div>
      <div class="erp-section-title">Items (as billed)</div>
      ${grn ? `<div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Qty</th><th>Rate ₹</th><th>Discount ₹</th><th>GST %</th><th>Line total</th></tr></thead>
               <tbody id="iLines">${ctx.lines.map(grnLineRow).join('')}</tbody></table></div>` : '<div id="iEditor"></div>'}
      <div class="erp-section-title">Freight &amp; other charges</div>
      <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Type</th><th>Description</th><th>Amount ₹</th><th>Add to cost</th><th></th></tr></thead><tbody id="iCharges">${(v.charges || []).map(chargeRow).join('')}</tbody></table></div>
      <button type="button" class="adm-btn adm-btn-ghost" id="iAddCh" style="margin-top:8px;"><i class="fas fa-plus"></i> Add charge</button>
      <div class="erp-totals" id="iTotals"></div>
      <p class="erp-note">Charges marked “add to cost” are spread over the items by value and become part of their landed cost (and COGS when sold). Posting a bill makes it payable.</p>`;
    let editor;
    const charges = () => $('#iCharges tr').map(function () { const $r = $(this); return { charge_type: $r.find('[data-f=type]').val(), description: $r.find('[data-f=desc]').val(), amount: $r.find('[data-f=amt]').val(), add_to_cost: $r.find('[data-f=cost]').is(':checked') ? 1 : 0 }; }).get();
    const chTotal = () => charges().reduce((a, c) => a + E.num(c.amount), 0);
    const recalc = () => {
        if (editor) return editor.recalc();
        let sub = 0, d = 0, tx = 0;
        $('#iLines tr').each(function () { const $r = $(this); const g = E.r2(E.num($r.find('[data-f=qty]').val()) * E.num($r.find('[data-f=rate]').val())); const dd = Math.min(E.num($r.find('[data-f=discount]').val()), g);
            const n = E.r2(g - dd), t = E.r2(n * E.num($r.find('[data-f=tax]').val()) / 100); sub += g; d += dd; tx += t; $r.find('[data-f=total]').text(E.money(n + t)); });
        $('#iTotals').html(`<span>Subtotal <strong>${E.money(sub)}</strong></span><span>Discount <strong>${E.money(d)}</strong></span><span>GST <strong>${E.money(tx)}</strong></span><span>Charges <strong>${E.money(chTotal())}</strong></span><span>Bill total <strong>${E.money(sub - d + tx + chTotal())}</strong></span>`);
    };
    E.form(v.id ? 'Edit ' + v.pinv_number : 'Enter vendor bill', html, (btn) => {
        const items = editor ? editor.get() : $('#iLines tr').map(function () { const $r = $(this); return { grn_item_id: $r.data('grn-item'), quantity: $r.find('[data-f=qty]').val(), rate: $r.find('[data-f=rate]').val(), discount_amount: $r.find('[data-f=discount]').val(), tax_percent: $r.find('[data-f=tax]').val() }; }).get();
        return E.post('purchase_api.php', { action: btn === 'deny' ? 'pinv_save' : 'pinv_post', id: v.id || '', grn_id: grn ? grn.id : '', supplier_id: $('#iSup').val() || v.supplier_id || '',
            supplier_invoice_no: $('#iNo').val(), invoice_date: $('#iDate').val(), due_date: $('#iDue').val(), notes: $('#iNotes').val(), items, charges: charges() }, { silent: true })
            .then(r => { E.toast(r.message); load(); setTimeout(() => openView(r.id), 300); });
    }, { width: 1100, confirmText: 'Post bill', denyText: 'Save draft', didOpen: () => {
        if (!grn) editor = E.lineEditor($('#iEditor'), { items: ITEMS, lines: v.items || [], extra: chTotal });
        $('#iAddCh').on('click', () => { $('#iCharges').append(chargeRow({})); recalc(); });
        $('#iCharges').on('click', '[data-f=cdel]', function () { $(this).closest('tr').remove(); recalc(); });
        $('#iCharges, #iLines').on('input change', 'input, select', recalc);
        recalc();
    }});
}

function openView(id) {
    E.api('purchase_api.php', { action: 'pinv_get', id }).then(r => {
        const v = r.record;
        const items = v.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.money(i.discount_amount)}</td>
            <td class="erp-num">${E.money(i.tax_amount)}</td><td class="erp-num">${E.money(i.line_total)}</td><td class="erp-num">${E.money(i.allocated_charges)}</td><td class="erp-num"><strong>${E.money(i.landed_unit_cost)}</strong></td></tr>`).join('');
        const pays = v.payments.map(p => `<tr><td>${E.esc(p.payment_number)}</td><td>${E.date(p.payment_date)}</td><td>${E.esc(p.payment_mode)}</td><td>${E.esc(p.reference_number || '')}</td><td class="erp-num">${E.money(p.amount)}</td><td>${E.badge(p.status)}</td></tr>`).join('');
        const html = E.kv([['Supplier', `<a class="erp-link" href="supplier_ledger.php?id=${v.supplier_id}">${E.esc(v.supplier_name)}</a>`], ['Our ref', E.esc(v.pinv_number)], ['Bill date', E.date(v.invoice_date)], ['Due', E.date(v.due_date)],
                           ['Total', E.money(v.grand_total)], ['Paid', E.money(v.amount_paid)], ['Credit notes', E.money(v.credit_amount)], ['Balance', `<strong>${E.money(v.balance)}</strong>`], ['Status', E.badge(v.payment_status)]])
            + E.chain([v.po_id ? ['PO ' + v.po_number, 'purchase_orders.php?id=' + v.po_id] : null, v.grn_id ? ['GRN ' + v.grn_number, 'goods_receipts.php?id=' + v.grn_id] : null,
                       ['Supplier ledger', 'supplier_ledger.php?id=' + v.supplier_id]].concat(v.returns.map(x => ['Return ' + x.return_number, 'purchase_returns.php?id=' + x.id])))
            + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Rate</th><th class="erp-num">Discount</th><th class="erp-num">GST</th>
               <th class="erp-num">Line total</th><th class="erp-num">Charges share</th><th class="erp-num">Landed / unit</th></tr></thead><tbody>${items}</tbody></table></div>
               ${v.charges.length ? '<div class="erp-section-title">Charges</div>' + v.charges.map(c => `<div class="erp-muted">${E.esc(c.charge_type)} ${E.esc(c.description || '')}: <strong>${E.money(c.amount)}</strong>${+c.add_to_cost ? ' (added to cost)' : ' (not in cost)'}</div>`).join('') : ''}
               <div class="erp-section-title">Payments</div>${pays ? `<div class="adm-table-wrap"><table class="adm-table"><tbody>${pays}</tbody></table></div>` : '<span class="erp-muted">No payments yet.</span>'}
               <div id="vDocs"></div>`;
        E.view(v.supplier_invoice_no + ' · ' + v.supplier_name, html, [
            v.status === 'posted' && v.balance > 0 && { label: 'Record payment', cls: 'adm-btn-primary', icon: 'fa-money-bill-wave', run: () => location.href = 'purchase_payments.php?pinv_id=' + v.id },
            v.status === 'draft' && { label: 'Edit draft', icon: 'fa-pen', run: () => (v.grn_id ? E.api('purchase_api.php', { action: 'pinv_prefill', grn_id: v.grn_id }) : Promise.resolve(null)).then(p =>
                openForm({ record: v, grn: p ? p.grn : null, lines: v.items.map(i => Object.assign({}, i)) })).catch(() => {}) },
            v.status !== 'cancelled' && !v.payments.some(p => p.status === 'completed') && { label: 'Cancel bill', icon: 'fa-ban', run: () =>
                E.confirmAction('Cancel ' + v.supplier_invoice_no + '?', 'Landed-cost entries from this bill are reversed.', { danger: true, reason: 'Reason' })
                 .then(reason => E.post('purchase_api.php', { action: 'pinv_cancel', id: v.id, reason })).then(x => { E.toast(x.message); load(); openView(v.id); }).catch(() => {}) },
        ], { width: 1100, didOpen: () => E.docs($('#vDocs'), 'purchase_invoice', v.id) });
    }).catch(() => {});
}
JS
);
