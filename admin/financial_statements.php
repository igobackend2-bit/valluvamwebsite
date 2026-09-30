<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Financial Statements', 'Trial balance, profit & loss, balance sheet and cash flow — from the double-entry ledger',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="tb">Trial balance</button><button class="erp-tab" data-tab="pl">Profit &amp; loss (ledger)</button><button class="erp-tab" data-tab="bs">Balance sheet</button><button class="erp-tab" data-tab="cf">Cash flow</button></div>
<div class="erp-filters" style="margin-bottom:12px"><span id="rng"><input type="date" class="adm-input" id="fFrom"> to </span><input type="date" class="adm-input" id="fTo"></div>
<div id="warn"></div>
<section class="adm-card"><div class="adm-card-body" id="pane"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let TAB = E.param('tab') || 'tb', CSV = [];
E.tabs($('#tabs'), t => { TAB = t; load(); }, TAB);
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
$('#fFrom,#fTo').on('change', load);
$('#csvBtn').on('click', () => E.csv(CSV, [{ label: 'Line', key: 'label' }, { label: 'Amount', key: 'amount' }, { label: 'Debit', key: 'debit' }, { label: 'Credit', key: 'credit' }], 'statement_' + TAB + '.csv'));
E.api('accounting_api.php', { action: 'settings' }, { silent: true }).then(s => { if (!s.gl_start_date) $('#warn').html('<div class="erp-warn">The ledger is not started yet — set the start date in <a class="erp-link" href="financial_periods.php">Periods & settings</a>. Until then use the operational <a class="erp-link" href="profit_loss.php">Profit & Loss</a>.</div>'); }).catch(() => {});
const row = (l, v, cls, link) => `<tr class="${cls || ''}"><td>${link ? `<a class="erp-link" href="${link}">${E.esc(l)}</a>` : E.esc(l)}</td><td>${E.money(v)}</td></tr>`;
function load() {
    $('#rng').toggle(['pl', 'cf'].includes(TAB));
    const $p = $('#pane'); E.loading($p); CSV = [];
    if (TAB === 'tb') E.api('accounting_api.php', { action: 'trial_balance', as_of: $('#fTo').val() }).then(r => {
        CSV = r.rows.map(x => ({ label: x.code + ' ' + x.name, debit: x.debit, credit: x.credit }));
        E.table($p, [{ label: 'Account', render: x => `<a class="erp-link" href="general_ledger.php?account_id=${x.id}">${E.esc(x.code + ' ' + x.name)}</a>` }, { label: 'Type', render: x => E.esc(x.account_type) },
            { label: 'Debit', num: true, render: x => x.debit ? E.money(x.debit) : '' }, { label: 'Credit', num: true, render: x => x.credit ? E.money(x.credit) : '' }], r.rows,
            { empty: 'No posted entries', footer: `<td colspan="2"><strong>Total</strong> ${r.balanced ? E.badge('ok', 'balanced') : E.badge('rejected', 'NOT balanced')}</td><td class="erp-num"><strong>${E.money(r.total_debit)}</strong></td><td class="erp-num"><strong>${E.money(r.total_credit)}</strong></td>` });
    }).catch(m => E.errorBox($p, m, load));
    if (TAB === 'pl') E.api('accounting_api.php', { action: 'pnl_gl', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }).then(r => {
        const g = r.groups, sec = (t, list, sign) => `<tr class="head"><td>${t}</td><td></td></tr>` + list.map(a => row(a.name, sign * a.amount, 'sub', 'general_ledger.php?account_id=' + a.id)).join('');
        [['Net sales', r.net_sales], ['Cost of goods sold', r.cogs], ['Gross profit', r.gross_profit], ['Operating expenses', r.operating_expenses], ['Other income', r.other_income], ['Net profit', r.net_profit]].forEach(x => CSV.push({ label: x[0], amount: x[1] }));
        $p.html(`<table class="erp-pl">${sec('Sales', g.sales, 1)}${row('Net sales', r.net_sales, 'total')}${sec('Cost of goods sold', g.cogs, -1)}${row('Gross profit', r.gross_profit, 'total')}
            ${sec('Operating expenses', g.operating, -1)}${g.other_income.length ? sec('Other income', g.other_income, 1) : ''}${row(r.net_profit >= 0 ? 'Net profit' : 'Net loss', r.net_profit, 'total')}</table>
            <p class="erp-note">From posted journals. The operational <a class="erp-link" href="profit_loss.php">Profit & Loss</a> page works directly from documents; differences come from purchase price variances on returns and entries moved out of closed periods.</p>`);
    }).catch(m => E.errorBox($p, m, load));
    if (TAB === 'bs') E.api('accounting_api.php', { action: 'balance_sheet', as_of: $('#fTo').val() }).then(r => {
        const g = r.groups, sec = (t, list) => `<tr class="head"><td>${t}</td><td></td></tr>` + list.map(a => row(a.name, a.amount, 'sub', a.id ? 'general_ledger.php?account_id=' + a.id : null)).join('');
        [['Total assets', r.total_assets], ['Total liabilities', r.total_liabilities], ['Total equity', r.total_equity]].forEach(x => CSV.push({ label: x[0], amount: x[1] }));
        $p.html(`<table class="erp-pl">${sec('Assets', g.asset)}${row('Total assets', r.total_assets, 'total')}${sec('Liabilities', g.liability)}${row('Total liabilities', r.total_liabilities, 'total')}
            ${sec('Equity', g.equity)}${row('Total equity', r.total_equity, 'total')}${row('Liabilities + equity', r.total_liabilities + r.total_equity, 'total')}</table>
            <p class="erp-note">${r.balanced ? E.badge('ok', 'Assets = liabilities + equity') : E.badge('rejected', 'Does not balance — contact support')}</p>`);
    }).catch(m => E.errorBox($p, m, load));
    if (TAB === 'cf') E.api('accounting_api.php', { action: 'cash_flow', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }).then(r => {
        CSV = r.lines.map(l => ({ label: l.section + ' · ' + l.label, amount: l.net }));
        const bySec = s => r.lines.filter(l => l.section === s);
        const sec = (t, s) => bySec(s).length ? `<tr class="head"><td>${t}</td><td></td></tr>` + bySec(s).map(l => `<tr class="sub"><td>${E.esc(l.label)} <span class="erp-muted">in ${E.money(l.in)} · out ${E.money(l.out)}</span></td><td>${E.money(l.net)}</td></tr>`).join('') : '';
        $p.html(`<table class="erp-pl">${row('Opening cash & bank', r.opening, 'total')}${sec('Operating', 'operating')}${sec('Investing', 'investing')}${sec('Financing', 'financing')}
            ${row('Net change', r.net_change, 'total')}${row('Closing cash & bank', r.closing, 'total')}</table>
            <div class="erp-section-title">By account</div>` + r.by_account.map(a => `<div class="erp-muted">${E.esc(a.code + ' ' + a.name)}: ${E.money(a.opening)} → ${E.money(a.closing)}</div>`).join(''));
    }).catch(m => E.errorBox($p, m, load));
}
load();
JS
);
