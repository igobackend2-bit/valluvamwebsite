<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Expenses', 'Record expenses with GST, payee, receipt and approval · posted expenses go to Transactions, the ledger and Profit & Loss',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New expense</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="exp">Expenses</button><button class="erp-tab" data-tab="cash">Entered in Transactions</button></div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2 id="title">Expenses</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fAcc" data-t="exp"><option value="">All categories</option></select>
            <select class="adm-select" id="fStatus" data-t="exp"><option value="">All status</option><option value="draft">Draft</option><option value="submitted">Waiting approval</option><option value="posted">Posted</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></select>
            <select class="adm-select" id="fPay" data-t="exp"><option value="">Paid & unpaid</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo"><input class="adm-input" id="fQ" placeholder="Number, payee, description, reference" data-t="exp">
        </div></div>
    <div class="adm-card-body"><div id="list"></div><div id="pager"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ACC = [], BANKS = [], POL = null, APR = false, TAB = 'exp', PAGE = 1;
const MODES = ['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'];
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
E.api('accounting_api.php', { action: 'exp_accounts' }).then(r => { ACC = r.rows; BANKS = r.banks; POL = r.policy; APR = r.is_approver;
    $('#fAcc').append(ACC.map(a => `<option value="${a.id}">${E.esc(a.name)}</option>`).join('')); load(); if (E.param('id')) view(E.param('id')); });
E.tabs($('#tabs'), t => { TAB = t; PAGE = 1; $('[data-t]').toggle(t === 'exp'); load(); });
$('#fAcc,#fStatus,#fPay,#fFrom,#fTo').on('change', () => { PAGE = 1; load(); });
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(() => { PAGE = 1; load(); }, 300); });
$('#newBtn').on('click', () => form({}));
function load() {
    E.loading($('#list'));
    if (TAB === 'cash') return E.api('accounting_api.php', { action: 'cashbook_expenses', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }).then(r => {
        $('#title').text('Expenses entered directly in Accounts → Transactions'); $('#kpis').html(E.kpi('Total', E.money(r.rows.filter(x => x.status === 'completed').reduce((a, x) => a + E.num(x.amount), 0))));
        E.table($('#list'), [{ label: 'Txn', render: x => E.esc(x.transaction_id) }, { label: 'Date', render: x => E.date(x.date) }, { label: 'Category', render: x => E.esc(x.category) }, { label: 'Party', render: x => E.esc(x.party_name || '') },
            { label: 'Mode', render: x => E.esc(x.payment_mode) }, { label: 'Amount', num: true, render: x => E.money(x.amount) }, { label: 'Status', render: x => E.badge(x.status) }], r.rows, { empty: 'None', emptyHint: 'These still count in Profit & Loss. Manage them in Accounts → Transactions.' });
        $('#pager').html('');
    });
    $('#title').text('Expenses');
    E.api('accounting_api.php', { action: 'exp_list', account_id: $('#fAcc').val(), status: $('#fStatus').val(), payment_status: $('#fPay').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val(), page: PAGE, per_page: 50 }, { silent: true }).then(r => {
        $('#kpis').html(E.kpi('Posted (before GST)', E.money(r.posted_totals.amount), 'is-primary') + E.kpi('GST on expenses', E.money(r.posted_totals.tax)) + E.kpi('Posted total', E.money(r.posted_totals.total)));
        E.table($('#list'), [
            { label: 'Expense', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.expense_number)}</a>` }, { label: 'Date', render: x => E.date(x.expense_date) },
            { label: 'Category', render: x => E.esc(x.category) }, { label: 'Payee', render: x => E.esc(x.payee || '') + `<div class="erp-muted">${E.esc(x.description || '')}</div>` },
            { label: 'Amount', num: true, render: x => E.money(x.amount) }, { label: 'GST', num: true, render: x => x.tax_amount > 0 ? E.money(x.tax_amount) : '' }, { label: 'Total', num: true, render: x => `<strong>${E.money(x.total)}</strong>` },
            { label: 'Payment', render: x => E.badge(x.payment_status === 'paid' ? 'paid' : 'unpaid') + `<div class="erp-muted">${E.esc(x.payment_mode || '')} ${E.esc(x.bank_name || '')}</div>` },
            { label: 'Status', render: x => E.badge(x.status) },
        ], r.rows, { empty: 'No expenses in this period', icon: 'fa-receipt' });
        E.pager($('#pager'), r.total, PAGE, 50, p => { PAGE = p; load(); });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { view($(this).data('view')); });
function form(x) {
    const needApr = POL && POL.enabled == 1 && !APR;
    const html = `<div class="erp-grid">${E.field('Category *', E.select('eA', E.options(ACC, 'id', a => a.name, x.account_id, 'Choose')))}${E.field('Date *', E.input('eD', x.expense_date || E.today(), 'type="date"'))}
        ${E.field('Payee / vendor', E.input('eP', x.payee || ''))}${E.field('Amount before GST ₹ *', E.input('eAm', x.amount || '', 'type="number" min="0" step="any"'))}${E.field('GST ₹', E.input('eT', x.tax_amount || 0, 'type="number" min="0" step="any"'))}
        ${E.field('Total', '<div class="adm-input" id="eTot" style="background:#faf8f2">₹0.00</div>')}
        ${E.field('Payment', E.select('ePs', `<option value="paid" ${x.payment_status !== 'unpaid' ? 'selected' : ''}>Paid</option><option value="unpaid" ${x.payment_status === 'unpaid' ? 'selected' : ''}>Unpaid (to pay later)</option>`))}
        ${E.field('Mode', E.select('eM', MODES.map(m => `<option value="${m}" ${x.payment_mode === m ? 'selected' : ''}>${m.replace('_', ' ')}</option>`).join('')))}
        ${E.field('Paid from', E.select('eB', E.options(BANKS, 'id', b => b.name, x.bank_account_id, 'By payment mode')))}${E.field('Reference / UTR', E.input('eR', x.reference_number || ''))}
        ${E.field('Description', E.textarea('eDs', x.description || ''), 'span-all')}</div>
        ${needApr ? `<p class="erp-note">Expenses${E.num(POL.min_amount) > 0 ? ' from ' + E.money(POL.min_amount) : ''} need approval — "Post" sends it to Approvals.</p>` : ''}
        <p class="erp-note">Attach the bill / receipt after saving (open the expense → Documents).</p>`;
    const tot = () => $('#eTot').text(E.money(E.num($('#eAm').val()) + E.num($('#eT').val())));
    E.form(x.id ? 'Edit ' + x.expense_number : 'New expense', html, btn => E.post('accounting_api.php', { action: btn === 'deny' ? 'exp_save' : 'exp_post', id: x.id || '', account_id: $('#eA').val(), expense_date: $('#eD').val(), payee: $('#eP').val(),
        amount: $('#eAm').val(), tax_amount: $('#eT').val(), payment_status: $('#ePs').val(), payment_mode: $('#eM').val(), bank_account_id: $('#eB').val(), reference_number: $('#eR').val(), description: $('#eDs').val(), shipment_id: x.shipment_id || '' }, { silent: true })
        .then(r => { E.toast(r.message); load(); setTimeout(() => view(r.id), 300); }), { confirmText: needApr ? 'Send for approval' : 'Post', denyText: 'Save draft', width: 880, didOpen: p => { $(p).on('input', '#eAm,#eT', tot); tot(); } });
}
function view(id) {
    E.api('accounting_api.php', { action: 'exp_get', id }).then(r => {
        const x = r.record;
        const html = E.kv([['Category', E.esc(x.account_code + ' ' + x.account_name)], ['Date', E.date(x.expense_date)], ['Payee', E.esc(x.payee || '—')], ['Amount', E.money(x.amount)], ['GST', E.money(x.tax_amount)], ['Total', '<strong>' + E.money(x.total) + '</strong>'],
                           ['Payment', E.badge(x.payment_status === 'paid' ? 'paid' : 'unpaid') + ' ' + E.esc(x.payment_mode || '') + ' ' + E.esc(x.bank_name || '') + ' ' + E.esc(x.reference_number || '')], x.paid_date ? ['Paid on', E.date(x.paid_date)] : null,
                           ['Status', E.badge(x.status)], ['Created', E.esc(x.created_by || '') + ' · ' + E.date(x.created_at)], x.approved_by ? ['Approved', E.esc(x.approved_by) + ' · ' + E.date(x.approved_at)] : null, x.cancel_reason ? ['Note', E.esc(x.cancel_reason)] : null])
            + E.chain([x.txn_number ? ['Transactions ' + x.txn_number, 'accounts.php'] : null, x.shipment_id ? ['Transport ' + x.shipment_number, 'shipments.php?id=' + x.shipment_id] : null].concat(x.journals.map(j => ['Journal ' + j.journal_number + ' (' + j.event + ')', 'journals.php?id=' + j.id])))
            + (x.description ? `<p>${E.esc(x.description)}</p>` : '') + '<div id="vDocs"></div>';
        E.view(x.expense_number, html, [
            ['draft', 'rejected', 'submitted'].includes(x.status) && { label: 'Edit', icon: 'fa-pen', run: () => form(x) },
            x.status === 'submitted' && { label: 'Open in Approvals', icon: 'fa-stamp', run: () => location.href = 'approvals.php?module=expense' },
            x.status === 'posted' && x.payment_status === 'unpaid' && { label: 'Record payment', cls: 'adm-btn-primary', icon: 'fa-money-bill', run: () => E.form('Pay ' + x.expense_number, `<div class="erp-grid">${E.field('Date *', E.input('pD', E.today(), 'type="date"'))}
                ${E.field('Mode', E.select('pM', MODES.map(m => `<option value="${m}">${m.replace('_', ' ')}</option>`).join('')))}${E.field('Paid from', E.select('pB', E.options(BANKS, 'id', b => b.name, '', 'By payment mode')))}${E.field('Reference / UTR', E.input('pR', ''))}</div>`,
                () => E.post('accounting_api.php', { action: 'exp_pay', id: x.id, paid_date: $('#pD').val(), payment_mode: $('#pM').val(), bank_account_id: $('#pB').val(), reference_number: $('#pR').val() }, { silent: true }).then(m => { E.toast(m.message); load(); }), { width: 700 }) },
            x.status !== 'cancelled' && { label: 'Cancel', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + x.expense_number + '?', x.status === 'posted' ? 'The Transactions entry is cancelled and the journal reversed.' : '', { danger: true, reason: 'Reason' }).then(reason => E.post('accounting_api.php', { action: 'exp_cancel', id: x.id, reason })).then(m => { E.toast(m.message); load(); }).catch(() => {}) },
        ], { didOpen: () => E.docs($('#vDocs'), 'expense', x.id, 'EXPENSE_RECEIPT') });
    }).catch(() => {});
}
JS
);
