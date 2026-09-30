<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Sales Channels', 'Website, offline and B2B sales side by side: net sales, COGS and gross profit per channel',
    '<button class="adm-btn adm-btn-ghost" id="newBtn"><i class="fas fa-plus"></i> New channel</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="profit">Channel profitability</button><button class="erp-tab" data-tab="tag">Tag sales</button><button class="erp-tab" data-tab="list">Channels</button></div>
<div class="erp-filters" style="margin-bottom:12px"><input type="date" class="adm-input" id="fFrom"> to <input type="date" class="adm-input" id="fTo">
    <span data-t="tag"><select class="adm-select" id="fType"><option value="">All sale types</option><option value="invoice">Invoices</option><option value="manual_sale">Manual sales</option><option value="credit_sale">Credit sales</option><option value="website_order">Website orders</option></select>
    <select class="adm-select" id="fCh"><option value="">All channels</option></select></span></div>
<section class="adm-card"><div class="adm-card-body" id="pane"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let TAB = 'profit', CH = [];
$('#fFrom').val(E.monthStart()); $('#fTo').val(E.today());
E.tabs($('#tabs'), t => { TAB = t; load(); });
$('#fFrom,#fTo,#fType,#fCh').on('change', load);
function channels() { return E.api('sales_ext_api.php', { action: 'channels' }).then(r => { CH = r.rows; $('#fCh').html('<option value="">All channels</option>' + CH.map(c => `<option value="${c.code}">${E.esc(c.name)}</option>`).join('')); }); }
function load() {
    $('[data-t]').toggle(TAB === 'tag');
    const $p = $('#pane'); E.loading($p);
    if (TAB === 'profit') E.api('sales_ext_api.php', { action: 'channel_profitability', date_from: $('#fFrom').val(), date_to: $('#fTo').val() }).then(r => {
        E.table($p, [{ label: 'Channel', render: x => `<strong>${E.esc(x.name)}</strong>` }, { label: 'Sales docs', num: true, render: x => x.documents }, { label: 'Gross sales (ex GST)', num: true, render: x => E.money(x.gross_sales) },
            { label: 'Returns', num: true, render: x => E.money(x.returns) }, { label: 'Net sales', num: true, render: x => E.money(x.net_sales) }, { label: 'COGS', num: true, render: x => E.money(x.cogs) },
            { label: 'Gross profit', num: true, render: x => `<strong class="${x.gross_profit < 0 ? 'erp-neg' : ''}">${E.money(x.gross_profit)}</strong>` }, { label: 'Margin', num: true, render: x => x.margin_pct === null ? '—' : x.margin_pct + '%' },
            { label: 'GST collected', num: true, render: x => E.money(x.gst_collected) }], r.rows, { empty: 'No sales in this period' });
        $p.append(r.notes.map(n => `<p class="erp-note">${E.esc(n)}</p>`).join(''));
    }).catch(m => E.errorBox($p, m, load));
    if (TAB === 'tag') E.api('sales_ext_api.php', { action: 'channel_docs', date_from: $('#fFrom').val(), date_to: $('#fTo').val(), type: $('#fType').val(), channel: $('#fCh').val() }).then(r => {
        $p.html(`<div class="erp-filters" style="margin-bottom:10px"><label>Tag selected as</label> <select class="adm-select" id="tCh">${CH.filter(c => c.status === 'active').map(c => `<option value="${c.id}">${E.esc(c.name)}</option>`).join('')}</select>
            <button class="adm-btn adm-btn-primary" id="tBtn">Apply</button> <span class="erp-muted">Select sales of one type at a time.</span></div><div id="tl"></div>`);
        E.table($('#tl'), [{ label: '<input type="checkbox" id="tAll">', render: x => `<input type="checkbox" class="tS" data-type="${x.type}" value="${x.id}">` }, { label: 'Sale', render: x => E.esc(x.type.replace('_', ' ')) + ' ' + E.esc(x.number) },
            { label: 'Date', render: x => E.date(x.date) }, { label: 'Customer', render: x => E.esc(x.customer || '') }, { label: 'Net', num: true, render: x => E.money(x.revenue) }, { label: 'Channel', render: x => E.badge('info', x.channel) }], r.rows, { empty: 'No sales in this period' });
        $('#tl thead th').first().html('<input type="checkbox" id="tAll">');
        $('#tAll').on('change', function () { $('.tS').prop('checked', this.checked); });
        $('#tBtn').on('click', () => { const sel = $('.tS:checked'); const types = [...new Set(sel.map(function () { return $(this).data('type'); }).get())];
            if (!sel.length) return E.alertError('Select sales first'); if (types.length > 1) return E.alertError('Select sales of one type at a time (use the type filter).');
            E.post('sales_ext_api.php', { action: 'channel_tag', channel_id: $('#tCh').val(), source_type: types[0], source_ids: sel.map(function () { return this.value; }).get() }).then(x => { E.toast(x.message); load(); }); });
    }).catch(m => E.errorBox($p, m, load));
    if (TAB === 'list') channels().then(() => E.table($p, [{ label: 'Channel', render: c => `<strong>${E.esc(c.name)}</strong> <span class="erp-muted">${E.esc(c.code)}</span>` }, { label: 'Type', render: c => E.esc(c.channel_type) },
        { label: 'Tagged sales', num: true, render: c => c.tagged }, { label: 'Status', render: c => E.badge(c.status) }, { label: '', render: c => `<button class="adm-btn adm-btn-ghost" data-edit="${c.id}">Edit</button>` }], CH));
}
function form(c) {
    c = c || {};
    E.form(c.id ? 'Edit channel' : 'New channel', `<div class="erp-grid">${E.field('Name *', E.input('cN', c.name || ''))}${E.field('Type', E.select('cT', ['offline', 'website', 'b2b', 'other'].map(x => `<option ${c.channel_type === x ? 'selected' : ''}>${x}</option>`).join('')))}
        ${c.id ? E.field('Status', E.select('cS', ['active', 'inactive'].map(x => `<option ${c.status === x ? 'selected' : ''}>${x}</option>`).join(''))) : ''}</div>`,
        () => E.post('sales_ext_api.php', { action: 'channel_save', id: c.id || '', name: $('#cN').val(), channel_type: $('#cT').val(), status: $('#cS').val() || 'active' }, { silent: true }).then(x => { E.toast(x.message); channels().then(load); }), { width: 620 });
}
$('#newBtn').on('click', () => form());
$('#pane').on('click', '[data-edit]', function () { form(CH.find(c => c.id == $(this).data('edit'))); });
channels().then(load);
JS
);
