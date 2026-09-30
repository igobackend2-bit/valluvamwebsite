<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Returns', 'Return received goods to the supplier — reduces stock and the amount payable',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-rotate-left"></i> New return</button>');
?>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>Returns to suppliers</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
E.suppliers().then(s => {
    $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join(''));
    load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('grn_id')) openForm(E.param('grn_id'));
});
$('#fSupplier,#fFrom,#fTo').on('change', load);
$('#newBtn').on('click', chooseGrn);

function load() {
    const $l = $('#list'); E.loading($l);
    E.api('purchase_api.php', { action: 'ret_list', supplier_id: $('#fSupplier').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val() }, { silent: true })
     .then(r => E.table($l, [
        { label: 'Return', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.return_number)}</a>` },
        { label: 'Date', render: x => E.date(x.return_date) },
        { label: 'Supplier', render: x => E.esc(x.supplier_name) },
        { label: 'GRN', render: x => x.grn_id ? `<a class="erp-link" href="goods_receipts.php?id=${x.grn_id}">${E.esc(x.grn_number)}</a>` : '—' },
        { label: 'Reason', render: x => E.esc(x.reason) },
        { label: 'Settlement', render: x => E.badge(x.settlement) },
        { label: 'Value', num: true, render: x => E.money(x.total_value) },
        { label: 'Stock Out', render: x => E.esc(x.stock_out_number || '—') },
     ], r.rows, { empty: 'No purchase returns', icon: 'fa-rotate-left' }))
     .catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function chooseGrn() {
    E.api('purchase_api.php', { action: 'grn_list', status: 'posted' }).then(r => Swal.fire({ title: 'Return goods from which receipt?', width: 620, customClass: { popup: 'erp-modal' }, showCancelButton: true,
        confirmButtonColor: '#1c5034', confirmButtonText: 'Continue',
        html: E.field('Goods receipt', E.select('srcG', E.options(r.rows, 'id', g => `${g.grn_number} — ${g.supplier_name} (${g.received_date})`, '', 'Choose a posted GRN')))
    }).then(x => { if (x.isConfirmed && $('#srcG').val()) openForm($('#srcG').val()); })).catch(() => {});
}

function openForm(grnId) {
    E.api('purchase_api.php', { action: 'ret_prefill', grn_id: grnId }).then(r => {
        const rows = r.items.map(i => `<tr data-gi="${i.id}"><td>${E.esc(i.item_name)}<div class="erp-muted">Accepted ${E.qty(i.accepted_qty)} · returned ${E.qty(i.returned_qty)} · in stock ${E.qty(i.current_stock)}</div></td>
            <td class="erp-num">${E.qty(i.returnable_qty, i.unit)}</td>
            <td class="w-num"><input class="adm-input" type="number" min="0" max="${i.returnable_qty}" step="any" data-f="qty" value="0"></td>
            <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="rate" value="${i.return_rate}"></td><td class="erp-num" data-f="val">₹0.00</td></tr>`).join('');
        const html = E.kv([['Supplier', E.esc(r.grn.supplier_name)], ['GRN', E.esc(r.grn.grn_number)], ['Bill', r.invoice ? E.esc(r.invoice.pinv_number) : 'Not billed yet']]) +
          `<div class="erp-grid">
            ${E.field('Return date *', E.input('rD', E.today(), 'type="date"'))}
            ${E.field('Reason *', E.input('rR', ''), 'span-2')}
            ${E.field('Settlement', E.select('rS', '<option value="credit_note">Credit note (reduce payable)</option><option value="refund">Refund (supplier pays back)</option><option value="replacement">Replacement goods</option>'))}
            ${E.field('Vendor credit note no.', E.input('rCN', ''))}
            ${E.field('Refund received ₹', E.input('rRF', 0, 'type="number" min="0" step="0.01"'))}
            ${E.field('Refund mode', E.select('rRM', '<option value="bank_transfer">Bank transfer</option><option value="upi">UPI</option><option value="cash">Cash</option><option value="cheque">Cheque</option>'))}
            ${E.field('Notes', E.input('rN', ''), 'span-all')}
          </div><div class="erp-section-title">Items to return</div>
          <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Returnable</th><th>Return qty</th><th>Rate ₹</th><th>Value</th></tr></thead><tbody id="rLines">${rows}</tbody></table></div>
          <div class="erp-totals" id="rTot"></div><p class="erp-note">Returned quantity leaves stock through a Stock Out. For replacement, receive the replacement goods with a new GRN.</p>`;
        const recalc = () => { let t = 0; $('#rLines tr').each(function () { const v = E.r2(E.num($(this).find('[data-f=qty]').val()) * E.num($(this).find('[data-f=rate]').val())); t += v; $(this).find('[data-f=val]').text(E.money(v)); }); $('#rTot').html(`<span>Return value <strong>${E.money(t)}</strong></span>`); };
        E.form('Return to supplier — ' + r.grn.grn_number, html, () => E.post('purchase_api.php', { action: 'ret_post', grn_id: grnId, pinv_id: r.invoice ? r.invoice.id : '',
            return_date: $('#rD').val(), reason: $('#rR').val(), settlement: $('#rS').val(), credit_note_number: $('#rCN').val(), refund_received: $('#rRF').val(), refund_mode: $('#rRM').val(), notes: $('#rN').val(),
            items: $('#rLines tr').map(function () { return { grn_item_id: $(this).data('gi'), quantity: $(this).find('[data-f=qty]').val(), rate: $(this).find('[data-f=rate]').val() }; }).get() }, { silent: true })
            .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); }),
          { width: 1000, confirmText: 'Post return (reduce stock)', didOpen: () => { $('#rLines').on('input', 'input', recalc); recalc(); } });
    }).catch(() => {});
}

function openView(id) {
    E.api('purchase_api.php', { action: 'ret_get', id }).then(r => {
        const x = r.record;
        const html = E.kv([['Supplier', `<a class="erp-link" href="supplier_ledger.php?id=${x.supplier_id}">${E.esc(x.supplier_name)}</a>`], ['Date', E.date(x.return_date)], ['Reason', E.esc(x.reason)],
                           ['Settlement', E.badge(x.settlement)], ['Credit note', E.esc(x.credit_note_number || '—')], ['Value', E.money(x.total_value)], ['Refund received', E.money(x.refund_received)], ['Status', E.badge(x.status)]])
            + E.chain([x.po_id ? ['PO ' + x.po_number, 'purchase_orders.php?id=' + x.po_id] : null, x.grn_id ? ['GRN ' + x.grn_number, 'goods_receipts.php?id=' + x.grn_id] : null,
                       x.pinv_id ? ['Bill ' + x.pinv_number, 'purchase_invoices.php?id=' + x.pinv_id] : null, ['Stock Out', 'stock_out.php']])
            + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Rate</th><th class="erp-num">Value</th></tr></thead><tbody>` +
              x.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.money(i.line_value)}</td></tr>`).join('') +
              '</tbody></table></div><div id="vDocs"></div>';
        E.view(x.return_number, html, [{ label: 'Debit note', icon: 'fa-file-invoice', run: () => location.href = 'debit_notes.php' }], { didOpen: () => E.docs($('#vDocs'), 'purchase_return', x.id, 'CREDIT_DEBIT_NOTE') });
    }).catch(() => {});
}
JS
);
