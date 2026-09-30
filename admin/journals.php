<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Journal Entries', 'Every double-entry journal — posted automatically from bills, payments, sales, receipts, returns, expenses and stock, plus manual journals',
    '<button class="adm-btn adm-btn-ghost" id="syncBtn"><i class="fas fa-rotate"></i> Post new entries now</button> <button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> Manual journal</button>');
?>
<div id="warn"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2>Journals</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fModule"><option value="">All sources</option><option value="purchase">Purchases</option><option value="sales">Sales</option><option value="cashbook">Cash book</option><option value="expense">Expenses</option><option value="inventory">Inventory</option><option value="manual">Manual</option><option value="closing">Closing</option></select>
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="posted">Posted</option><option value="reversed">Reversed</option><option value="draft">Draft</option><option value="submitted">Waiting approval</option><option value="cancelled">Cancelled</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo"><input class="adm-input" id="fQ" placeholder="Number, reference, narration, party">
        </div></div>
    <div class="adm-card-body"><div id="list"></div><div id="pager"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let PAGE = 1, ACC = [];
E.api('accounting_api.php', { action: 'coa_list' }, { silent: true }).then(r => ACC = r.rows.filter(a => a.is_active == 1)).catch(() => {});
E.api('accounting_api.php', { action: 'settings' }, { silent: true }).then(s => { if (!s.gl_start_date) $('#warn').html('<div class="erp-warn">Automatic journals are not switched on yet. Set the ledger start date in <a class="erp-link" href="financial_periods.php">Periods & settings</a>.</div>'); }).catch(() => {});
$('#fModule,#fStatus,#fFrom,#fTo').on('change', () => { PAGE = 1; load(); });
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(() => { PAGE = 1; load(); }, 300); });
$('#syncBtn').on('click', () => E.api('accounting_api.php', { action: 'sync', force: 1 }).then(r => { E.toast(r.message); load(); }));
$('#newBtn').on('click', () => form({}));
function load() {
    E.loading($('#list'));
    E.api('accounting_api.php', { action: 'jr_list', module: $('#fModule').val(), status: $('#fStatus').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val(), page: PAGE, per_page: 50 }, { silent: true }).then(r => {
        E.table($('#list'), [
            { label: 'Journal', render: j => `<a class="erp-link" data-view="${j.id}">${E.esc(j.journal_number)}</a>` }, { label: 'Date', render: j => E.date(j.entry_date) },
            { label: 'Source', render: j => E.esc(j.source_module) + ' · ' + E.esc(j.event) + `<div class="erp-muted">${E.esc(j.source_ref || '')}</div>` },
            { label: 'Narration', render: j => E.esc(j.narration || '') }, { label: 'Amount', num: true, render: j => E.money(j.total) }, { label: 'Status', render: j => E.badge(j.status) },
        ], r.rows, { empty: 'No journals yet', icon: 'fa-book' });
        E.pager($('#pager'), r.total, PAGE, 50, p => { PAGE = p; load(); });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { view($(this).data('view')); });
function view(id) {
    E.api('accounting_api.php', { action: 'jr_get', id }).then(r => {
        const j = r.record;
        const html = E.kv([['Date', E.date(j.entry_date)], ['Source', E.esc(j.source_module + ' · ' + j.source_type + ' · ' + j.event)], ['Reference', j.source_link ? `<a class="erp-link" href="${E.esc(j.source_link)}">${E.esc(j.source_ref || 'open')}</a>` : E.esc(j.source_ref || '—')],
                           ['Status', E.badge(j.status)], ['Created by', E.esc(j.created_by || '') + ' · ' + E.date(j.created_at)], j.approved_by ? ['Approved by', E.esc(j.approved_by)] : null])
            + `<p>${E.esc(j.narration || '')}</p><div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Account</th><th>Party / memo</th><th>Channel</th><th class="erp-num">Debit</th><th class="erp-num">Credit</th></tr></thead><tbody>`
            + j.lines.map(l => `<tr><td><a class="erp-link" href="general_ledger.php?account_id=${l.account_id}">${E.esc(l.code + ' ' + l.account_name)}</a></td><td>${E.esc(l.party_name || '')} <span class="erp-muted">${E.esc(String(l.memo || '').startsWith('k=') ? '' : (l.memo || ''))}</span></td>
                 <td>${E.esc(l.channel || '')}</td><td class="erp-num">${l.debit > 0 ? E.money(l.debit) : ''}</td><td class="erp-num">${l.credit > 0 ? E.money(l.credit) : ''}</td></tr>`).join('')
            + `</tbody><tfoot><tr><td colspan="3"><strong>Total</strong></td><td class="erp-num"><strong>${E.money(j.lines.reduce((a, l) => a + E.num(l.debit), 0))}</strong></td><td class="erp-num"><strong>${E.money(j.lines.reduce((a, l) => a + E.num(l.credit), 0))}</strong></td></tr></tfoot></table></div>`
            + (j.related.length ? '<div class="erp-section-title">Related journals (same document)</div>' + j.related.map(x => `<div class="erp-muted"><a class="erp-link" data-rel="${x.id}">${E.esc(x.journal_number)}</a> · ${E.esc(x.event)} · ${E.date(x.entry_date)} · ${E.money(x.total)} · ${E.esc(x.status)}</div>`).join('') : '')
            + (j.approvals.length ? '<div class="erp-section-title">Approvals</div>' + j.approvals.map(a => `<div class="erp-muted">${E.esc(a.request_number)} · ${E.esc(a.status)} · ${E.esc(a.submitted_by || '')} → ${E.esc(a.decided_by || '')}</div>`).join('') : '') + '<div id="vDocs"></div>';
        E.view(j.journal_number, html, [
            j.is_manual == 1 && ['draft'].includes(j.status) && { label: 'Edit', icon: 'fa-pen', run: () => form(j) },
            j.is_manual == 1 && ['draft', 'submitted'].includes(j.status) && { label: 'Cancel', icon: 'fa-ban', run: () => E.post('accounting_api.php', { action: 'jr_manual_delete', id: j.id }).then(x => { E.toast(x.message); load(); }) },
            j.is_manual == 1 && j.status === 'submitted' && { label: 'Open in Approvals', icon: 'fa-stamp', run: () => location.href = 'approvals.php?module=manual_journal' },
            j.is_manual == 1 && j.status === 'posted' && { label: 'Reverse', icon: 'fa-rotate-left', run: () => E.confirmAction('Reverse ' + j.journal_number + '?', 'A mirror-image journal is posted; the original stays.', { danger: true, reason: 'Reason' }).then(reason => E.post('accounting_api.php', { action: 'jr_reverse', id: j.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
            { label: 'Print', icon: 'fa-print', run: () => window.open('print_erp.php?type=jv&id=' + j.id, '_blank') },
        ], { didOpen: p => { E.docs($('#vDocs'), 'journal', j.id, 'OTHER'); $(p).on('click', '[data-rel]', function () { view($(this).data('rel')); }); } });
    }).catch(() => {});
}
function form(j) {
    const opts = sel => '<option value="">Account…</option>' + ACC.map(a => `<option value="${a.id}" ${String(a.id) === String(sel) ? 'selected' : ''}>${E.esc(a.code + ' ' + a.name)}</option>`).join('');
    const row = l => `<tr><td style="min-width:240px"><select class="adm-select" data-f="a">${opts(l.account_id)}</select></td><td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="d" value="${l.debit > 0 ? l.debit : ''}"></td>
        <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="c" value="${l.credit > 0 ? l.credit : ''}"></td><td><input class="adm-input" data-f="p" value="${E.esc(l.party_name || '')}" placeholder="Party"></td>
        <td><input class="adm-input" data-f="m" value="${E.esc(l.memo || '')}" placeholder="Memo"></td><td><button type="button" class="adm-icon-btn is-danger" data-f="x"><i class="fas fa-xmark"></i></button></td></tr>`;
    const lines = (j.lines && j.lines.length ? j.lines : [{}, {}]);
    const html = `<div class="erp-grid">${E.field('Date *', E.input('jD', j.entry_date || E.today(), 'type="date"'))}${E.field('Reference', E.input('jR', j.source_ref || ''))}${E.field('Narration *', E.input('jN', j.narration || ''), 'span-2')}</div>
        <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Account</th><th>Debit ₹</th><th>Credit ₹</th><th>Party</th><th>Memo</th><th></th></tr></thead><tbody id="jL">${lines.map(row).join('')}</tbody></table></div>
        <button type="button" class="adm-btn adm-btn-ghost" id="jAdd" style="margin-top:8px"><i class="fas fa-plus"></i> Add line</button><div class="erp-totals" id="jT"></div>
        <p class="erp-note">Debit must equal credit. For opening balances use "Opening balance equity" as the other side. The journal posts only after approval.</p>`;
    const get = () => $('#jL tr').map(function () { const $r = $(this); return { account_id: $r.find('[data-f=a]').val(), debit: $r.find('[data-f=d]').val(), credit: $r.find('[data-f=c]').val(), party_name: $r.find('[data-f=p]').val(), memo: $r.find('[data-f=m]').val() }; }).get().filter(l => l.account_id);
    const tot = () => { const l = get(); const d = l.reduce((a, x) => a + E.num(x.debit), 0), c = l.reduce((a, x) => a + E.num(x.credit), 0); $('#jT').html(`<span>Debit <strong>${E.money(d)}</strong></span><span>Credit <strong>${E.money(c)}</strong></span><span class="${Math.abs(d - c) > 0.004 ? 'erp-neg' : 'erp-pos'}">Difference <strong>${E.money(d - c)}</strong></span>`); };
    E.form(j.id ? 'Edit ' + j.journal_number : 'Manual journal', html, btn => E.post('accounting_api.php', { action: btn === 'deny' ? 'jr_manual_save' : 'jr_manual_submit', id: j.id || '', entry_date: $('#jD').val(), reference: $('#jR').val(), narration: $('#jN').val(), lines: get() }, { silent: true })
        .then(x => { E.toast(x.message); load(); }), { confirmText: 'Submit for approval', denyText: 'Save draft', width: 1080, didOpen: p => {
            $(p).on('input change', '#jL', tot); $('#jAdd').on('click', () => { $('#jL').append(row({})); tot(); }); $(p).on('click', '[data-f=x]', function () { $(this).closest('tr').remove(); tot(); }); tot(); } });
}
load();
if (E.param('id')) view(E.param('id'));
JS
);
