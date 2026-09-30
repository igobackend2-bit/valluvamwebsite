<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Chart of Accounts', 'Ledger accounts with their balances · system accounts are used by the automatic journals',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New account</button>');
?>
<div class="erp-filters" style="margin-bottom:12px"><label>Balances as of</label> <input type="date" class="adm-input" id="fAsOf">
    <select class="adm-select" id="fType"><option value="">All types</option><option value="asset">Assets</option><option value="liability">Liabilities</option><option value="equity">Equity</option><option value="income">Income</option><option value="expense">Expenses</option></select>
    <input class="adm-input" id="fQ" placeholder="Code or name"></div>
<section class="adm-card"><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
$('#fAsOf').val(E.today()).on('change', load);
$('#fType').on('change', render); $('#fQ').on('input', render);
function load() { E.loading($('#list')); E.api('accounting_api.php', { action: 'coa_list', as_of: $('#fAsOf').val() }).then(r => { ROWS = r.rows; render(); }).catch(m => E.errorBox($('#list'), m, load)); }
function render() {
    const q = ($('#fQ').val() || '').toLowerCase(), t = $('#fType').val();
    E.table($('#list'), [
        { label: 'Code', render: a => `<strong>${E.esc(a.code)}</strong>` },
        { label: 'Account', render: a => `<a class="erp-link" href="general_ledger.php?account_id=${a.id}">${E.esc(a.name)}</a><div class="erp-muted">${E.esc(a.description || '')}</div>` },
        { label: 'Type', render: a => E.esc(a.account_type) + (a.sub_type ? ' · ' + E.esc(a.sub_type) : '') },
        { label: 'Debit', num: true, render: a => E.money(a.debit) }, { label: 'Credit', num: true, render: a => E.money(a.credit) },
        { label: 'Balance', num: true, render: a => `<strong>${E.money(a.balance)}</strong>` },
        { label: '', render: a => (a.is_system == 1 ? E.badge('info', 'system') : '') + (a.is_active == 1 ? '' : E.badge('inactive')) + ` <button class="adm-btn adm-btn-ghost" data-edit="${a.id}">Edit</button>` + (a.is_system == 1 ? '' : ` <button class="adm-btn adm-btn-ghost" data-act="${a.id}" data-to="${a.is_active == 1 ? 0 : 1}">${a.is_active == 1 ? 'Deactivate' : 'Activate'}</button>`) },
    ], ROWS.filter(a => (!t || a.account_type === t) && (!q || (a.code + ' ' + a.name).toLowerCase().includes(q))), { empty: 'No accounts' });
}
function form(a) {
    a = a || {};
    const sys = a.is_system == 1;
    E.form(a.id ? 'Edit ' + a.code : 'New account', `<div class="erp-grid">${E.field('Code *', E.input('aC', a.code || '', sys ? 'disabled' : ''))}${E.field('Name *', E.input('aN', a.name || ''), 'span-2')}
        ${E.field('Type *', E.select('aT', ['asset', 'liability', 'equity', 'income', 'expense'].map(x => `<option ${a.account_type === x ? 'selected' : ''}>${x}</option>`).join(''), sys ? 'disabled' : ''))}
        ${E.field('Group', E.select('aS', ['', 'operating', 'cogs', 'sales', 'other', 'cash', 'bank', 'receivable', 'payable', 'inventory', 'tax', 'fixed', 'equity'].map(x => `<option value="${x}" ${a.sub_type === x ? 'selected' : ''}>${x || '—'}</option>`).join('')))}
        ${E.field('Parent', E.select('aP', '<option value="">—</option>' + ROWS.filter(x => x.id !== a.id).map(x => `<option value="${x.id}" ${String(x.id) === String(a.parent_id) ? 'selected' : ''}>${E.esc(x.code + ' ' + x.name)}</option>`).join('')))}
        ${E.field('Description', E.input('aD', a.description || ''), 'span-all')}</div>
        <p class="erp-note">New expense accounts of group "operating" appear as categories in Expenses.</p>`,
        () => E.post('accounting_api.php', { action: 'coa_save', id: a.id || '', code: $('#aC').val(), name: $('#aN').val(), account_type: $('#aT').val(), sub_type: $('#aS').val(), parent_id: $('#aP').val(), description: $('#aD').val() }, { silent: true }).then(x => { E.toast(x.message); load(); }), { width: 760 });
}
$('#newBtn').on('click', () => form());
$('#list').on('click', '[data-edit]', function () { form(ROWS.find(a => a.id == $(this).data('edit'))); });
$('#list').on('click', '[data-act]', function () { E.post('accounting_api.php', { action: 'coa_status', id: $(this).data('act'), active: $(this).data('to') }).then(() => load()); });
load();
JS
);
