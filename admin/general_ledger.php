<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('General Ledger', 'Every entry on one account with its running balance',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-filters" style="margin-bottom:12px">
    <select class="adm-select" id="fAcc" style="min-width:280px"></select>
    <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo"><input class="adm-input" id="fParty" placeholder="Party (supplier / customer)">
</div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card"><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
const COLS = [
    { label: 'Date', key: 'entry_date', render: r => E.date(r.entry_date) },
    { label: 'Journal', key: 'journal_number', render: r => `<a class="erp-link" href="journals.php?id=${r.journal_id}">${E.esc(r.journal_number)}</a>` },
    { label: 'Narration', key: 'narration', render: r => E.esc(r.narration || '') + `<div class="erp-muted">${E.esc(r.source_ref || '')}</div>` },
    { label: 'Party', key: 'party_name', render: r => E.esc(r.party_name || '') },
    { label: 'Debit', key: 'debit', num: true, render: r => r.debit > 0 ? E.money(r.debit) : '' },
    { label: 'Credit', key: 'credit', num: true, render: r => r.credit > 0 ? E.money(r.credit) : '' },
    { label: 'Balance', key: 'balance', num: true, render: r => `<strong>${E.money(r.balance)}</strong>` },
];
E.api('accounting_api.php', { action: 'coa_list' }).then(r => {
    $('#fAcc').html(r.rows.map(a => `<option value="${a.id}" data-key="${E.esc(a.system_key || '')}">${E.esc(a.code + ' ' + a.name)}</option>`).join(''));
    const want = E.param('account_id') || (E.param('account') ? (r.rows.find(a => a.system_key === E.param('account')) || {}).id : null);
    if (want) $('#fAcc').val(want);
    load();
});
$('#fAcc,#fFrom,#fTo').on('change', load);
let tq; $('#fParty').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
$('#csvBtn').on('click', () => E.csv(ROWS, COLS, 'ledger.csv'));
function load() {
    E.loading($('#list'));
    E.api('accounting_api.php', { action: 'gl', account_id: $('#fAcc').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), party: $('#fParty').val() }, { silent: true }).then(r => {
        ROWS = r.rows;
        if (!$('#fFrom').val()) $('#fFrom').val(r.from); if (!$('#fTo').val()) $('#fTo').val(r.to);
        $('#kpis').html(E.kpi('Opening ' + E.date(r.from), E.money(r.opening)) + E.kpi('Debits', E.money(r.totals.debit)) + E.kpi('Credits', E.money(r.totals.credit)) + E.kpi('Closing ' + E.date(r.to), E.money(r.closing), 'is-primary'));
        E.table($('#list'), COLS, ROWS, { empty: 'No entries in this period', icon: 'fa-book-open' });
    }).catch(m => E.errorBox($('#list'), m, load));
}
JS
);
