<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Sales Returns', 'Goods returned by customers — good stock goes back to inventory, damaged stock does not',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-rotate-left"></i> New sales return</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Sales returns</h2><div class="erp-filters"><input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo"></div></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
const SRC = [['invoice', 'Invoice (INV-…)'], ['manual_sale', 'Manual sale (MS-…)'], ['credit_sale', 'Credit sale (CR-…)'], ['website_order', 'Website order (receipt no.)']];
$('#fFrom,#fTo').on('change', load);
$('#newBtn').on('click', openForm);
function load() {
    E.loading($('#list'));
    E.api('inventory_ops_api.php', { action: 'sret_list', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Return', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.return_number)}</a>` }, { label: 'Date', render: x => E.date(x.return_date) },
        { label: 'Against', render: x => E.esc(x.source_type.replace('_', ' ')) + ' ' + E.esc(x.source_number || '') }, { label: 'Customer', render: x => E.esc(x.customer_name || '—') },
        { label: 'Reason', render: x => E.esc(x.reason) }, { label: 'Settlement', render: x => E.badge(x.settlement) },
        { label: 'Value (ex-tax)', num: true, render: x => E.money(x.total_value) }, { label: 'Refund / credit', num: true, render: x => E.money(x.refund_amount) },
        { label: 'Restocked via', render: x => E.esc(x.stock_in_number || '—') },
    ], r.rows, { empty: 'No sales returns', icon: 'fa-rotate-left' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () {
    E.api('inventory_ops_api.php', { action: 'sret_get', id: $(this).data('view') }).then(r => {
        const x = r.record;
        E.view(x.return_number, E.kv([['Date', E.date(x.return_date)], ['Against', E.esc(x.source_type.replace('_', ' ') + ' ' + (x.source_number || ''))], ['Customer', E.esc(x.customer_name || '—')],
            ['Reason', E.esc(x.reason)], ['Settlement', E.badge(x.settlement)], ['Refund / credit', E.money(x.refund_amount)], ['Mode', E.esc(x.refund_mode || '—')]]) +
            `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Product</th><th class="erp-num">Returned</th><th class="erp-num">Restocked</th><th class="erp-num">Damaged</th><th>Condition</th><th class="erp-num">Value</th></tr></thead><tbody>` +
            x.items.map(i => `<tr><td>${E.esc(i.product_name)}</td><td class="erp-num">${E.qty(i.quantity)}</td><td class="erp-num">${E.qty(i.restock_qty)}</td><td class="erp-num">${E.qty(i.damaged_qty)}</td><td>${E.badge(i.stock_condition === 'good' ? 'ok' : 'expiring', i.stock_condition)}</td><td class="erp-num">${E.money(i.line_value)}</td></tr>`).join('') +
            '</tbody></table></div><div id="vDocs"></div>', [], { didOpen: () => E.docs($('#vDocs'), 'sales_return', x.id) });
    }).catch(() => {});
});
function openForm() {
    const html = `<div class="erp-grid">${E.field('Returned against', E.select('sT', SRC.map(s => `<option value="${s[0]}">${s[1]}</option>`).join('')))}
        ${E.field('Document number *', E.input('sN', '', 'placeholder="e.g. INV-2026-000012"'))}
        <div class="adm-field"><label>&nbsp;</label><button type="button" class="adm-btn adm-btn-ghost" id="sLoad"><i class="fas fa-magnifying-glass"></i> Find</button></div></div><div id="sBody" class="erp-note">Find the original sale first.</div>`;
    let SRCDATA = null;
    E.form('New sales return', html, () => {
        if (!SRCDATA) return Promise.reject('Find the original sale first');
        return E.post('inventory_ops_api.php', { action: 'sret_post', source_type: SRCDATA.source_type, source_ref: SRCDATA.source_number, return_date: $('#sD').val(), reason: $('#sR').val(),
            settlement: $('#sS').val(), refund_amount: $('#sA').val(), refund_mode: $('#sM').val(), damage_kind: $('#sK').val(), notes: $('#sNo').val(),
            items: $('#sLines tr').map(function () { return { product_id: $(this).data('p'), restock_qty: $(this).find('[data-f=g]').val(), damaged_qty: $(this).find('[data-f=d]').val() }; }).get() }, { silent: true })
            .then(r => { E.toast(r.message); load(); Swal.fire({ icon: 'success', title: 'Sales return posted', text: r.message, confirmButtonColor: '#1c5034' }); });
    }, { width: 1000, confirmText: 'Post return', didOpen: () => {
        $('#sLoad').on('click', () => E.api('inventory_ops_api.php', { action: 'sret_source', source_type: $('#sT').val(), source_ref: $('#sN').val() }).then(s => {
            SRCDATA = s;
            const rows = s.lines.map(l => `<tr data-p="${l.product_id}" data-rate="${l.net_rate}"><td>${E.esc(l.product_name)}<div class="erp-muted">Sold ${E.qty(l.sold_qty)} · already returned ${E.qty(l.returned_qty)} · @ ${E.money(l.net_rate)} ex-tax</div></td>
                <td class="erp-num">${E.qty(l.returnable_qty)}</td><td class="w-num"><input class="adm-input" type="number" min="0" step="1" data-f="g" value="0"></td><td class="w-num"><input class="adm-input" type="number" min="0" step="1" data-f="d" value="0"></td><td class="erp-num" data-f="v">₹0.00</td></tr>`).join('');
            $('#sBody').removeClass('erp-note').html(E.kv([['Customer', E.esc(s.customer_name || '—')], ['Mobile', E.esc(s.customer_mobile || '—')], ['Billed', E.money(s.grand_total)], ['Max refund left', E.money(s.max_refund)]]) +
                `<div class="erp-grid">${E.field('Return date *', E.input('sD', E.today(), 'type="date"'))}${E.field('Reason *', E.input('sR', ''), 'span-2')}
                 ${E.field('Settlement', E.select('sS', '<option value="refund">Refund money</option><option value="credit_note">Credit note (reduce what they owe)</option><option value="replacement">Replacement</option>'))}
                 ${E.field('Refund / credit amount ₹', E.input('sA', 0, 'type="number" min="0" step="0.01"'))}
                 ${E.field('Refund mode', E.select('sM', '<option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option><option value="card">Card</option>'))}
                 ${E.field('Damaged stock is', E.select('sK', '<option value="damaged">Damaged</option><option value="expired">Expired</option>'))}${E.field('Notes', E.input('sNo', ''), 'span-all')}</div>
                 <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Product</th><th>Returnable</th><th>Good (restock)</th><th>Damaged (not restocked)</th><th>Value</th></tr></thead><tbody id="sLines">${rows}</tbody></table></div>
                 <p class="erp-note">Good quantity goes back into sellable stock at the current average cost; damaged quantity is recorded but never becomes sellable stock. Refunds are recorded in Transactions.</p>`);
            $('#sLines').on('input', 'input', () => { let t = 0; $('#sLines tr').each(function () { const v = E.r2((E.num($(this).find('[data-f=g]').val()) + E.num($(this).find('[data-f=d]').val())) * E.num($(this).data('rate'))); t += v; $(this).find('[data-f=v]').text(E.money(v)); }); $('#sA').val(t.toFixed(2)); });
        }).catch(() => {}));
    }});
}
load();
JS
);
