<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Supplier Payments', 'Payments to vendors — each one is also recorded in Accounts → Transactions',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-money-bill-wave"></i> Record payment</button>');
?>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>Payments</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fSupplier"><option value="">All suppliers</option></select>
            <select class="adm-select" id="fStatus"><option value="">All</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input type="text" class="adm-input" id="fQ" placeholder="Payment no., UTR or supplier">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [];
const MODES = [['bank_transfer', 'Bank transfer'], ['upi', 'UPI'], ['cash', 'Cash'], ['cheque', 'Cheque'], ['card', 'Card'], ['other', 'Other']];
E.suppliers().then(s => {
    SUP = s;
    $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join(''));
    load();
    if (E.param('pinv_id')) E.api('purchase_api.php', { action: 'pinv_get', id: E.param('pinv_id') }).then(r => openForm(r.record.supplier_id, r.record.id)).catch(() => {});
    if (E.param('id')) openView(+E.param('id'));
});
$('#fSupplier,#fStatus,#fFrom,#fTo').on('change', load);
let t; $('#fQ').on('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm('', ''));

let ROWS = [];
function load() {
    const $l = $('#list'); E.loading($l);
    E.api('purchase_api.php', { action: 'pay_list', supplier_id: $('#fSupplier').val(), status: $('#fStatus').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true })
     .then(r => { ROWS = r.rows; E.table($l, [
        { label: 'Payment', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.payment_number)}</a>` },
        { label: 'Date', render: x => E.date(x.payment_date) },
        { label: 'Supplier', render: x => `<a class="erp-link" href="supplier_ledger.php?id=${x.supplier_id}">${E.esc(x.supplier_name)}</a>` },
        { label: 'Against bill', render: x => x.pinv_id ? `<a class="erp-link" href="purchase_invoices.php?id=${x.pinv_id}">${E.esc(x.supplier_invoice_no)}</a>` : '<span class="erp-muted">Advance</span>' },
        { label: 'Mode', render: x => E.esc(x.payment_mode.replace('_', ' ')) },
        { label: 'Reference', render: x => E.esc(x.reference_number || '—') },
        { label: 'Amount', num: true, render: x => `<strong>${E.money(x.amount)}</strong>` },
        { label: 'Txn', render: x => E.esc(x.txn_number || '—') },
        { label: 'Status', render: x => E.badge(x.status) },
     ], r.rows, { empty: 'No supplier payments yet', icon: 'fa-money-bill-wave' }); })
     .catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView(+$(this).data('view')); });

function openForm(supplierId, pinvId) {
    const html = `<div class="erp-grid">
        ${E.field('Supplier *', E.select('yS', E.options(SUP, 'id', s => s.supplier_name, supplierId, 'Choose supplier')))}
        ${E.field('Against bill', E.select('yI', '<option value="">Advance / on account</option>'), 'span-2')}
        ${E.field('Amount ₹ *', E.input('yA', '', 'type="number" min="0" step="0.01"'))}
        ${E.field('Payment date *', E.input('yD', E.today(), 'type="date"'))}
        ${E.field('Mode *', E.select('yM', MODES.map(m => `<option value="${m[0]}">${m[1]}</option>`).join('')))}
        ${E.field('Paid from (cash / bank account)', E.input('yAcc', ''))}
        ${E.field('Reference / UTR / cheque no.', E.input('yR', ''))}
        ${E.field('Notes', E.input('yN', ''), 'span-2')}
      </div><div id="yBal" class="erp-note"></div>
      <div id="yPayee" class="erp-note"></div>
      <p class="erp-note">Payment proof can be attached after saving. Amount cannot be more than the bill's balance (record extra as an advance).</p>`;
    let BILLS = [];
    const loadBills = (sid, sel) => {
        $('#yI').html('<option value="">Advance / on account</option>');
        if (!sid) return;
        E.api('purchase_api.php', { action: 'pinv_list', supplier_id: sid, status: '' }, { silent: true }).then(r => {
            BILLS = r.rows.filter(b => b.status === 'posted' && b.balance > 0);
            $('#yI').append(BILLS.map(b => `<option value="${b.id}" ${String(b.id) === String(sel) ? 'selected' : ''}>${E.esc(b.supplier_invoice_no)} · ${E.date(b.invoice_date)} · balance ${E.money(b.balance)}</option>`).join(''));
            $('#yI').trigger('change');
        });
    };
    E.form('Record supplier payment', html, () => {
        return E.post('purchase_api.php', { action: 'pay_save', supplier_id: $('#yS').val(), pinv_id: $('#yI').val(), amount: $('#yA').val(), payment_date: $('#yD').val(),
            payment_mode: $('#yM').val(), account: $('#yAcc').val(), reference_number: $('#yR').val(), notes: $('#yN').val() }, { silent: true })
            .then(r => { E.toast(r.message); load(); setTimeout(() => openView(r.id), 400); });
    }, { width: 820, confirmText: 'Save payment', didOpen: () => {
        const showPayee = sid => {
            const s = SUP.find(x => String(x.id) === String(sid));
            if (!s) return $('#yPayee').empty();
            const details = [s.owner_name && `Owner: ${E.esc(s.owner_name)}`, s.account_holder_name && `Account holder: ${E.esc(s.account_holder_name)}`,
                s.bank_name && `Bank: ${E.esc(s.bank_name)}`, s.bank_account_number && `A/c: ${E.esc(s.bank_account_number)}`,
                s.bank_ifsc && `IFSC: ${E.esc(s.bank_ifsc)}`, s.upi_id && `UPI: ${E.esc(s.upi_id)}`].filter(Boolean);
            $('#yPayee').html(details.length ? `<strong>Saved payee details</strong> · ${details.join(' · ')}` : 'No payee account details saved. Add them in Suppliers before making a bank or UPI payment.');
        };
        $('#yS').on('change', function () { loadBills(this.value, ''); showPayee(this.value); });
        $('#yI').on('change', function () { const b = BILLS.find(x => String(x.id) === this.value); $('#yBal').html(b ? `Bill total ${E.money(b.grand_total)} · paid ${E.money(b.amount_paid)} · <strong>balance ${E.money(b.balance)}</strong>` : ''); if (b) $('#yA').val(b.balance); });
        if (supplierId) { loadBills(supplierId, pinvId); showPayee(supplierId); }
    }});
}

function openView(id) {
    const load1 = ROWS.find(x => x.id === id) ? Promise.resolve(ROWS.find(x => x.id === id)) : E.api('purchase_api.php', { action: 'pay_list' }).then(r => r.rows.find(x => x.id === id));
    load1.then(p => {
        if (!p) return;
        const html = E.kv([['Supplier', `<a class="erp-link" href="supplier_ledger.php?id=${p.supplier_id}">${E.esc(p.supplier_name)}</a>`], ['Date', E.date(p.payment_date)], ['Amount', `<strong>${E.money(p.amount)}</strong>`],
                           ['Mode', E.esc(p.payment_mode)], ['Account', E.esc(p.account || '—')], ['Reference', E.esc(p.reference_number || '—')], ['Transactions entry', E.esc(p.txn_number || '—')], ['Status', E.badge(p.status)],
                           p.status === 'cancelled' ? ['Cancelled', E.esc(p.cancelled_by || '') + ': ' + E.esc(p.cancel_reason || '')] : null])
            + E.chain([p.pinv_id ? ['Bill ' + p.supplier_invoice_no, 'purchase_invoices.php?id=' + p.pinv_id] : null, ['Supplier ledger', 'supplier_ledger.php?id=' + p.supplier_id], ['Transactions', 'accounts.php']])
            + (p.notes ? `<p class="erp-note">${E.esc(p.notes)}</p>` : '') + '<div id="vDocs"></div>';
        E.view(p.payment_number, html, [
            p.status === 'completed' && { label: 'Cancel payment', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + p.payment_number + '?', 'The bill becomes payable again and the Transactions entry is cancelled.', { danger: true, reason: 'Reason' })
                .then(reason => E.post('purchase_api.php', { action: 'pay_cancel', id: p.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
        ], { width: 860, didOpen: () => E.docs($('#vDocs'), 'purchase_payment', p.id) });
    });
}
JS
);
