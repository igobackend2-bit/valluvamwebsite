<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Bank & Cash', 'Cash and bank accounts with book balances · import bank statements and reconcile them with the ledger',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New bank / cash account</button>');
?>
<section class="adm-card"><div class="adm-card-head"><h2>Accounts</h2></div><div class="adm-card-body" id="list"></div></section>
<section class="adm-card" id="recCard" style="display:none">
    <div class="adm-card-head"><h2 id="recTitle">Reconciliation</h2>
        <div class="erp-filters"><input type="date" class="adm-input" id="rFrom"> to <input type="date" class="adm-input" id="rTo">
            <select class="adm-select" id="rStatus"><option value="">All lines</option><option value="unmatched">Unmatched</option><option value="matched">Matched</option><option value="ignored">Ignored</option></select>
            <button class="adm-btn adm-btn-ghost" id="impBtn"><i class="fas fa-file-import"></i> Import statement (CSV)</button>
            <button class="adm-btn adm-btn-primary" id="autoBtn"><i class="fas fa-wand-magic-sparkles"></i> Auto-match</button></div></div>
    <div class="adm-card-body"><div class="erp-kpis" id="recKpis"></div>
        <div class="erp-section-title">Bank statement lines</div><div id="stmt"></div>
        <div class="erp-section-title">Ledger entries not on the statement</div><div id="book"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ACC = null, BOOK = [];
const MODES = ['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other'];
$('#rFrom').val(new Date(Date.now() - 60 * 864e5).toISOString().slice(0, 10)); $('#rTo').val(E.today());
function load() {
    E.api('accounting_api.php', { action: 'bank_list' }).then(r => E.table($('#list'), [
        { label: 'Account', render: b => `<strong>${E.esc(b.name)}</strong>${b.is_default == 1 ? ' ' + E.badge('info', 'default bank') : ''}<div class="erp-muted">${E.esc(b.bank_name || '')} ${b.account_last4 ? 'XXXX' + E.esc(b.account_last4) : ''} ${E.esc(b.ifsc || '')}</div>` },
        { label: 'Type', render: b => E.esc(b.account_type) }, { label: 'Ledger', render: b => `<a class="erp-link" href="general_ledger.php?account_id=${b.coa_account_id}">${E.esc(b.code)}</a>` },
        { label: 'Receives modes', render: b => E.esc((b.payment_modes || '').replace(/_/g, ' ')) },
        { label: 'Book balance', num: true, render: b => `<strong>${E.money(b.book_balance)}</strong>` },
        { label: 'Statement', render: b => b.last_statement ? 'to ' + E.date(b.last_statement) + (b.unmatched_lines ? ` · <span class="erp-neg">${b.unmatched_lines} unmatched</span>` : '') : '—' },
        { label: '', render: b => `<button class="adm-btn adm-btn-ghost" data-edit="${b.id}">Edit</button> ${b.account_type !== 'cash' ? `<button class="adm-btn adm-btn-primary" data-rec="${b.id}">Reconcile</button>` : ''}` },
    ], r.rows, { empty: 'No accounts' }) || ($('#list').data('rows', r.rows))).catch(m => E.errorBox($('#list'), m, load));
}
function form(b) {
    b = b || {};
    E.form(b.id ? 'Edit ' + b.name : 'New account', `<div class="erp-grid">${E.field('Name *', E.input('bN', b.name || '', 'placeholder="e.g. HDFC current a/c"'))}${E.field('Type', E.select('bT', ['bank', 'cash', 'wallet'].map(x => `<option ${b.account_type === x ? 'selected' : ''}>${x}</option>`).join(''), b.id ? 'disabled' : ''))}
        ${E.field('Bank name', E.input('bB', b.bank_name || ''))}${E.field('Account no. (only last 4 kept)', E.input('bA', '', 'placeholder="' + (b.account_last4 ? 'XXXX' + E.esc(b.account_last4) : '') + '"'))}${E.field('IFSC', E.input('bI', b.ifsc || ''))}
        ${E.field('Payments by these modes land here', MODES.map(m => `<label style="margin-right:10px"><input type="checkbox" class="bM" value="${m}" ${(b.payment_modes || '').split(',').includes(m) ? 'checked' : ''}> ${m.replace('_', ' ')}</label>`).join(''), 'span-all')}
        ${E.field('Default bank', `<label><input type="checkbox" id="bD" ${b.is_default == 1 ? 'checked' : ''}> used when no other account matches</label>`, 'span-2')}</div>`,
        () => E.post('accounting_api.php', { action: 'bank_save', id: b.id || '', name: $('#bN').val(), account_type: $('#bT').val(), bank_name: $('#bB').val(), account_number: $('#bA').val(), ifsc: $('#bI').val(),
            payment_modes: $('.bM:checked').map(function () { return this.value; }).get().join(','), is_default: $('#bD').is(':checked') ? 1 : 0 }, { silent: true }).then(x => { E.toast(x.message); load(); }), { width: 760 });
}
$('#newBtn').on('click', () => form());
$('#list').on('click', '[data-edit]', function () { form(($('#list').data('rows') || []).find(b => b.id == $(this).data('edit'))); });
$('#list').on('click', '[data-rec]', function () { ACC = $(this).data('rec'); $('#recCard').show(); rec(); $('html,body').animate({ scrollTop: $('#recCard').offset().top - 20 }); });
$('#rFrom,#rTo,#rStatus').on('change', rec);
function rec() {
    if (!ACC) return;
    E.loading($('#stmt'));
    E.api('accounting_api.php', { action: 'stmt_list', bank_account_id: ACC, date_from: $('#rFrom').val(), date_to: $('#rTo').val(), status: $('#rStatus').val() }).then(r => {
        $('#recTitle').text('Reconciliation — ' + r.account.name);
        const s = r.summary;
        BOOK = r.book_lines;
        $('#recKpis').html(E.kpi('Book balance ' + E.date(r.to), E.money(s.book_balance), 'is-primary') + E.kpi('Statement balance', s.statement_balance === null ? 'not in file' : E.money(s.statement_balance)) +
            E.kpi('In books, not on statement', E.money(s.book_not_in_statement)) + E.kpi('On statement, not in books', E.money(s.statement_not_in_book), s.statement_not_in_book ? 'is-amber' : '') +
            (s.reconciled_difference !== null ? E.kpi('Unexplained difference', E.money(s.reconciled_difference), Math.abs(s.reconciled_difference) < 0.01 ? 'is-green' : 'is-amber') : ''));
        E.table($('#stmt'), [{ label: 'Date', render: x => E.date(x.txn_date) }, { label: 'Description', render: x => E.esc(x.description || '') + `<div class="erp-muted">${E.esc(x.reference || '')}</div>` },
            { label: 'Amount', num: true, render: x => `<span class="${x.amount < 0 ? 'erp-neg' : 'erp-pos'}">${E.money(x.amount)}</span>` }, { label: 'Balance', num: true, render: x => x.balance === null ? '' : E.money(x.balance) },
            { label: 'Status', render: x => E.badge(x.status) + (x.journal_number ? `<div class="erp-muted">${E.esc(x.journal_number)} · ${E.esc(x.matched_narration || '')}</div>` : '') },
            { label: '', render: x => x.status === 'unmatched' ? `<button class="adm-btn adm-btn-ghost" data-match="${x.id}" data-amt="${x.amount}">Match</button> <button class="adm-btn adm-btn-ghost" data-ign="${x.id}">Ignore</button>` : `<button class="adm-btn adm-btn-ghost" data-un="${x.id}">Unmatch</button>` }],
            r.statement_lines, { empty: 'No statement lines — import a CSV from your bank' });
        E.table($('#book'), [{ label: 'Date', render: x => E.date(x.entry_date) }, { label: 'Journal', render: x => E.esc(x.journal_number) }, { label: 'Narration', render: x => E.esc(x.narration || '') + `<div class="erp-muted">${E.esc(x.party_name || '')} ${E.esc(x.source_ref || '')}</div>` },
            { label: 'In', num: true, render: x => x.debit > 0 ? E.money(x.debit) : '' }, { label: 'Out', num: true, render: x => x.credit > 0 ? E.money(x.credit) : '' }], BOOK.filter(b => !b.statement_line_id), { empty: 'Every ledger entry is on the statement' });
    }).catch(m => E.errorBox($('#stmt'), m, rec));
}
$('#autoBtn').on('click', () => E.post('accounting_api.php', { action: 'stmt_auto_match', bank_account_id: ACC }).then(r => { E.toast(r.message); rec(); load(); }));
$('#stmt').on('click', '[data-un]', function () { E.post('accounting_api.php', { action: 'stmt_unmatch', line_id: $(this).data('un') }).then(() => rec()); });
$('#stmt').on('click', '[data-ign]', function () { const id = $(this).data('ign'); E.confirmAction('Ignore this line?', 'e.g. bank charge you will book separately', { reason: 'Reason' }).then(reason => E.post('accounting_api.php', { action: 'stmt_ignore', line_id: id, reason })).then(() => rec()).catch(() => {}); });
$('#stmt').on('click', '[data-match]', function () {
    const id = $(this).data('match'), amt = E.num($(this).data('amt'));
    const c = BOOK.filter(b => !b.statement_line_id && Math.abs((E.num(b.debit) - E.num(b.credit)) - amt) < 0.005);
    if (!c.length) return E.alertError('No unmatched ledger entry with the same amount. Record the missing transaction (e.g. bank charges as an expense) and try again.');
    E.form('Match with a ledger entry', E.field('Ledger entry', E.select('mL', c.map(b => `<option value="${b.id}">${E.date(b.entry_date)} · ${E.esc(b.journal_number)} · ${E.esc(b.narration || '')}</option>`).join(''))),
        () => E.post('accounting_api.php', { action: 'stmt_match', line_id: id, journal_line_id: $('#mL').val() }, { silent: true }).then(() => { rec(); load(); }), { width: 760, confirmText: 'Match' });
});
function normDate(v) {
    v = String(v || '').trim(); let m;
    if ((m = v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/))) return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`;
    if ((m = v.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/))) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return `${y}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
    const mo = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };
    if ((m = v.match(/^(\d{1,2})[\s\-]([A-Za-z]{3})[a-z]*[\s\-,]+(\d{2,4})/)) && mo[m[2].toLowerCase()]) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return `${y}-${String(mo[m[2].toLowerCase()]).padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
    return '';
}
function parseCSV(t) {
    const rows = []; let row = [], cur = '', q = false;
    for (let i = 0; i < t.length; i++) { const c = t[i];
        if (q) { if (c === '"' && t[i + 1] === '"') { cur += '"'; i++; } else if (c === '"') q = false; else cur += c; }
        else if (c === '"') q = true; else if (c === ',') { row.push(cur); cur = ''; } else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(cur); rows.push(row); row = []; cur = ''; } else cur += c; }
    if (cur || row.length) { row.push(cur); rows.push(row); }
    return rows.filter(r => r.some(x => String(x).trim() !== ''));
}
$('#impBtn').on('click', () => {
    E.form('Import bank statement', `<div class="erp-grid">${E.field('CSV file from your bank *', '<input type="file" class="adm-input" id="iF" accept=".csv,text/csv">', 'span-all')}</div><div id="iMap"></div>
        <p class="erp-note">Download the statement from net-banking as CSV (Excel users: Save As → CSV). Choose which columns hold the date, description, reference and amount (or withdrawal / deposit). Lines already imported are skipped.</p>`,
        () => {
            const data = $('#iMap').data('rows');
            if (!data) return Promise.reject('Choose the CSV file');
            const col = k => +$('#iC_' + k).val(), H = +$('#iH').val();
            const out = data.slice(H).map(r => {
                const num = v => E.num(String(v || '').replace(/[^\d.\-]/g, ''));
                let amt = col('amt') >= 0 ? num(r[col('amt')]) : num(r[col('cr')]) - num(r[col('dr')]);
                if (col('amt') >= 0 && $('#iSign').val() === 'dr_cr' && col('dc') >= 0) amt = /^\s*d/i.test(r[col('dc')] || '') ? -Math.abs(amt) : Math.abs(amt);
                return { date: normDate(r[col('date')]), description: col('desc') >= 0 ? r[col('desc')] : '', reference: col('ref') >= 0 ? r[col('ref')] : '', amount: amt, balance: col('bal') >= 0 ? String(r[col('bal')] || '').replace(/[^\d.\-]/g, '') : '' };
            }).filter(x => x.date && x.amount);
            if (!out.length) return Promise.reject('No lines with a date and an amount — check the column choices.');
            return E.post('accounting_api.php', { action: 'stmt_import', bank_account_id: ACC, rows: out }, { silent: true }).then(r => { E.toast(r.message); rec(); load(); });
        }, { confirmText: 'Import', width: 900, didOpen: () => $('#iF').on('change', function () {
            const f = this.files[0]; if (!f) return;
            const rd = new FileReader(); rd.onload = () => {
                const rows = parseCSV(String(rd.result)); if (!rows.length) return $('#iMap').html('<div class="erp-warn">The file is empty.</div>');
                const hIdx = Math.max(0, rows.findIndex(r => r.some(c => /date/i.test(c))));
                const hdr = rows[hIdx], opt = (re, none) => (none ? '<option value="-1">— none —</option>' : '') + hdr.map((h, i) => `<option value="${i}" ${re && re.test(h) ? 'selected' : ''}>${E.esc(h || 'Column ' + (i + 1))}</option>`).join('');
                $('#iMap').data('rows', rows).html(`<div class="erp-grid">${E.field('Header row', E.input('iH', hIdx + 1, 'type="number" min="0"'))}${E.field('Date', E.select('iC_date', opt(/date/i)))}${E.field('Description', E.select('iC_desc', opt(/narr|desc|particular|remark/i, true)))}
                    ${E.field('Reference', E.select('iC_ref', opt(/ref|chq|cheque|utr/i, true)))}${E.field('Amount (single column)', E.select('iC_amt', opt(/^amount$/i, true)))}${E.field('Dr / Cr column', E.select('iC_dc', opt(/^(dr|cr|type|dr\/cr)/i, true)))}
                    ${E.field('Sign', E.select('iSign', '<option value="signed">Amount is + / −</option><option value="dr_cr">Use Dr / Cr column</option>'))}
                    ${E.field('Withdrawal', E.select('iC_dr', opt(/withdraw|debit/i, true)))}${E.field('Deposit', E.select('iC_cr', opt(/deposit|credit/i, true)))}${E.field('Balance', E.select('iC_bal', opt(/bal/i, true)))}</div>
                    <p class="erp-note">${rows.length - hIdx - 1} data lines found.</p>`);
            }; rd.readAsText(f);
        }) });
});
load();
if (E.param('id')) { ACC = E.param('id'); $('#recCard').show(); rec(); }
JS
);
