<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Expenses', 'Operating expenses — saved in Accounts → Transactions (type: expense) and used by Profit & Loss',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> Record expense</button>');
?>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>Expenses</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fCat"><option value="">All categories</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input type="text" class="adm-input" id="fQ" placeholder="Payee, description">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
const CATS = ['Salary', 'Rent', 'Electricity', 'Transport', 'Marketing', 'Packaging', 'Maintenance', 'Internet', 'Bank Charges', 'Other Operating Expenses'];
const MODES = [['cash', 'Cash'], ['bank_transfer', 'Bank transfer'], ['upi', 'UPI'], ['card', 'Card'], ['cheque', 'Cheque'], ['other', 'Other']];
$('#fCat').append(CATS.map(c => `<option>${c}</option>`).join(''));
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
$('#fCat,#fFrom,#fTo').on('change', load);
let t; $('#fQ').on('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm({}));
function load() {
    E.loading($('#list'));
    E.api('get_accounts_transactions.php', { type: 'expense', category: $('#fCat').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true }).then(r => {
        const rows = r.transactions || [];
        const live = rows.filter(x => x.status !== 'cancelled');
        const byCat = {}; live.forEach(x => byCat[x.category] = (byCat[x.category] || 0) + E.num(x.amount));
        $('#kpis').html(`<div class="adm-stat is-amber"><h3>${E.money(live.reduce((a, x) => a + E.num(x.amount), 0))}</h3><p>Total in period</p></div>` +
            Object.keys(byCat).sort((a, b) => byCat[b] - byCat[a]).slice(0, 5).map(c => `<div class="adm-stat is-neutral"><h3>${E.money(byCat[c])}</h3><p>${E.esc(c)}</p></div>`).join(''));
        E.table($('#list'), [
            { label: 'ID', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.transaction_id)}</a>` }, { label: 'Date', render: x => E.date(x.date) },
            { label: 'Category', render: x => E.esc(x.category) }, { label: 'Payee', render: x => E.esc(x.party_name || '—') }, { label: 'Mode', render: x => E.esc(x.payment_mode.replace('_', ' ')) },
            { label: 'Account', render: x => E.esc(x.account || '—') }, { label: 'Reference', render: x => E.esc(x.reference_number || '—') },
            { label: 'Amount', num: true, render: x => `<strong>${E.money(x.amount)}</strong>` }, { label: 'Status', render: x => E.badge(x.status) },
        ], rows, { empty: 'No expenses in this period', icon: 'fa-receipt' });
        $('#list').data('rows', rows);
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () {
    const x = ($('#list').data('rows') || []).find(r => String(r.id) === String($(this).data('view'))); if (!x) return;
    E.view(x.transaction_id + ' · ' + x.category, E.kv([['Date', E.date(x.date)], ['Amount', E.money(x.amount)], ['Payee', E.esc(x.party_name || '—')], ['Mode', E.esc(x.payment_mode)], ['Account', E.esc(x.account || '—')],
        ['Reference', E.esc(x.reference_number || '—')], ['By', E.esc(x.created_by || '')], ['Status', E.badge(x.status)]]) + (x.description ? `<p class="erp-note">${E.esc(x.description)}</p>` : '') +
        E.chain([['Open in Transactions', 'accounts.php']]) + '<div id="vDocs"></div>', [
        x.status !== 'cancelled' && { label: 'Cancel expense', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + x.transaction_id + '?', 'Cancelled expenses stay on record but are left out of Profit & Loss.', { danger: true })
            .then(() => E.post('cancel_accounts_transaction.php', { id: x.id })).then(() => { E.toast('Expense cancelled'); load(); }).catch(() => {}) },
    ], { width: 820, didOpen: () => E.docs($('#vDocs'), 'expense', x.id) });
});
function openForm(x) {
    const html = `<div class="erp-grid">${E.field('Category *', E.select('xC', CATS.map(c => `<option>${c}</option>`).join('')))}
        ${E.field('Amount ₹ *', E.input('xA', '', 'type="number" min="0" step="0.01"'))}${E.field('Date *', E.input('xD', E.today(), 'type="date"'))}
        ${E.field('Payment mode', E.select('xM', MODES.map(m => `<option value="${m[0]}">${m[1]}</option>`).join('')))}${E.field('Paid from (account)', E.input('xAc', ''))}
        ${E.field('Vendor / payee', E.input('xP', ''))}${E.field('Reference / bill no.', E.input('xR', ''))}${E.field('Notes', E.input('xN', ''), 'span-all')}</div>
        <p class="erp-note">Receipts can be attached after saving. Do not record stock purchases here — use Purchase Orders / Goods Receipts so the stock and its cost are tracked.</p>`;
    E.form('Record expense', html, () => E.post('save_accounts_transaction.php', { date: $('#xD').val(), type: 'expense', category: $('#xC').val(), amount: $('#xA').val(), payment_mode: $('#xM').val(),
        account: $('#xAc').val(), party_name: $('#xP').val(), reference_type: 'expense', reference_number: $('#xR').val(), description: $('#xN').val(), status: 'completed' }, { silent: true })
        .then(r => { E.toast('Expense recorded (' + (r.transaction_id || '') + ')'); load(); }), { width: 820 });
}
load();
JS
);
