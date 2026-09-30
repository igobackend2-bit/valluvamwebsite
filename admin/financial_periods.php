<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Periods & Accounting Settings', 'When the ledger starts, where online payments land, and monthly periods you can close');
?>
<section class="adm-card"><div class="adm-card-head"><h2>Accounting settings</h2></div><div class="adm-card-body" id="set"></div></section>
<section class="adm-card"><div class="adm-card-head"><h2>Financial periods</h2>
    <div class="erp-filters"><input type="date" class="adm-input" id="fy"><button class="adm-btn adm-btn-ghost" id="genBtn"><i class="fas fa-calendar-plus"></i> Create 12 monthly periods</button></div></div>
    <div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
function settings() {
    E.api('accounting_api.php', { action: 'settings' }).then(s => {
        $('#set').html(`<div class="erp-grid">${E.field('Ledger start date', E.input('sStart', s.gl_start_date || s.suggested_start, 'type="date"' + (s.gl_start_date && s.journals ? ' disabled' : '')))}
            ${E.field('Website online payments go to', E.select('sOnline', E.options(s.banks.filter(b => b.account_type !== 'cash'), 'name', b => b.name, s.online_payment_account, 'Default bank')))}
            ${E.field('GST on purchases', `<div class="adm-input" style="background:#faf8f2">${s.tax_in_cost ? 'Added to stock cost (not claimable)' : 'Claimable as input credit'}</div>`)}</div>
            <div class="erp-actions"><button class="adm-btn adm-btn-primary" id="sSave"><i class="fas fa-floppy-disk"></i> ${s.gl_start_date ? 'Save' : 'Start the ledger'}</button>
            ${s.gl_start_date ? `<button class="adm-btn adm-btn-ghost" id="sSync"><i class="fas fa-rotate"></i> Post new entries now</button>` : ''}</div>
            <p class="erp-note">${s.gl_start_date ? `Journals are posted automatically for everything dated from ${E.date(s.gl_start_date)} (${s.journals} journals so far${s.last_sync ? ', last run ' + new Date(s.last_sync * 1000).toLocaleString('en-IN') : ''}). The start date is fixed once journals exist.`
                : 'Choose the date from which bills, payments, sales, receipts, expenses and stock movements are journaled. Stock value on that date becomes the opening inventory. Enter other opening balances (bank, cash, capital) as a manual journal against "Opening balance equity".'}</p>`);
        $('#sSave').on('click', () => E.post('accounting_api.php', { action: 'settings_save', gl_start_date: $('#sStart').val(), online_payment_account: $('#sOnline').val() }).then(r => { E.toast(r.message); settings(); }));
        $('#sSync').on('click', () => E.api('accounting_api.php', { action: 'sync', force: 1 }).then(r => { E.toast(r.message); settings(); }));
    });
}
function load() {
    E.api('accounting_api.php', { action: 'per_list' }).then(r => {
        if (!$('#fy').val()) $('#fy').val(r.fy_start);
        E.table($('#list'), [{ label: 'Period', render: p => `<strong>${E.esc(p.name)}</strong>` }, { label: 'From', render: p => E.date(p.start_date) }, { label: 'To', render: p => E.date(p.end_date) },
            { label: 'Journals', num: true, render: p => p.journals + (p.unposted > 0 ? ` <span class="erp-neg">(${p.unposted} unposted)</span>` : '') }, { label: 'Status', render: p => E.badge(p.status === 'open' ? 'open' : 'closed') + (p.closed_by ? `<div class="erp-muted">${E.esc(p.closed_by)} · ${E.date(p.closed_at)}</div>` : '') },
            { label: '', render: p => p.status === 'open' ? `<button class="adm-btn adm-btn-ghost" data-close="${p.id}">Close</button>` : `<button class="adm-btn adm-btn-ghost" data-open="${p.id}">Reopen</button> <button class="adm-btn adm-btn-ghost" data-ce="${p.id}">Closing entry</button>` }],
            r.rows, { empty: 'No periods yet', emptyHint: 'Create monthly periods, then close each month when its books are final.' });
    });
}
$('#genBtn').on('click', () => E.post('accounting_api.php', { action: 'per_generate', fy_start: $('#fy').val() }).then(r => { E.toast(r.message); load(); }));
$('#list').on('click', '[data-close]', function () { const id = $(this).data('close'); E.confirmAction('Close this period?', 'No new journals can be dated in it; late entries are dated the first open day after it.').then(() => E.post('accounting_api.php', { action: 'per_close', id })).then(r => { E.toast(r.message); load(); }).catch(() => {}); });
$('#list').on('click', '[data-open]', function () { const id = $(this).data('open'); E.confirmAction('Reopen this period?', '', { reason: 'Reason' }).then(reason => E.post('accounting_api.php', { action: 'per_reopen', id, reason })).then(r => { E.toast(r.message); load(); }).catch(() => {}); });
$('#list').on('click', '[data-ce]', function () { const id = $(this).data('ce'); E.confirmAction('Post the closing entry?', 'Moves this period\'s income and expenses into retained earnings (year-end). Posted once per period.').then(() => E.post('accounting_api.php', { action: 'per_closing_entry', id })).then(r => { E.toast(r.message); load(); }).catch(() => {}); });
settings(); load();
JS
);
