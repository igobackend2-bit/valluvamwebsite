<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Stock Valuation', 'Stock value at weighted average cost, batches & expiry, opening costs and ledger checks',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-tabs" id="tabs">
    <button class="erp-tab active" data-tab="value">Valuation</button>
    <button class="erp-tab" data-tab="batches">Batches &amp; expiry</button>
    <button class="erp-tab" data-tab="opening">Opening cost</button>
    <button class="erp-tab" data-tab="variance">Ledger check</button>
</div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2 id="title">Valuation</h2>
        <div class="erp-filters"><span data-for="value"><label>As of</label> <input type="date" class="adm-input" id="fAsOf"></span>
            <span data-for="batches"><label>Expiring within</label> <input type="number" class="adm-input" id="fDays" value="60" style="width:80px"> days</span>
            <select class="adm-select" id="fType" data-for="value opening"><option value="">All items</option><option value="product">Product packs</option><option value="raw_material">Raw materials</option></select>
            <input class="adm-input" id="fQ" placeholder="Search"></div></div>
    <div class="adm-card-body"><div id="warn"></div><div id="list"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let TAB = 'value', DATA = [], COLS = [];
$('#fAsOf').val(E.today());
$('#tabs').on('click', '.erp-tab', function () { $('.erp-tab').removeClass('active'); $(this).addClass('active'); TAB = $(this).data('tab'); load(); });
$('#fAsOf,#fDays,#fType').on('change', load);
$('#fQ').on('input', render);
$('#csvBtn').on('click', () => E.csv(filtered(), COLS.map(c => Object.assign({}, c, { csv: c.csv || (x => x[c.key]) })), 'stock_' + TAB + '.csv'));

function filtered() { const q = ($('#fQ').val() || '').toLowerCase(); const t = $('#fType').val(); return DATA.filter(x => (!q || JSON.stringify(x).toLowerCase().includes(q)) && (!t || !x.item_type || x.item_type === t)); }
function render() { E.table($('#list'), COLS, filtered(), { empty: 'Nothing to show', icon: 'fa-warehouse' }); }

function load() {
    $('[data-for]').each(function () { $(this).toggle(String($(this).data('for')).split(' ').includes(TAB)); });
    E.loading($('#list')); $('#warn').html(''); $('#kpis').html('');
    if (TAB === 'value') {
        $('#title').text('Stock valuation');
        E.api('erp_reports.php', { report: 'valuation', date_to: $('#fAsOf').val() }, { silent: true }).then(r => {
            DATA = r.rows;
            $('#kpis').html(`<div class="adm-stat is-primary"><h3>${E.money(r.total_value)}</h3><p>Total inventory value</p></div>
                <div class="adm-stat is-neutral"><h3>${E.money(r.rows.filter(x => x.item_type === 'product').reduce((a, x) => a + x.value, 0))}</h3><p>Product packs</p></div>
                <div class="adm-stat is-neutral"><h3>${E.money(r.rows.filter(x => x.item_type === 'raw_material').reduce((a, x) => a + x.value, 0))}</h3><p>Raw materials</p></div>`);
            if (r.items_without_cost) $('#warn').html(`<div class="erp-warn">${r.items_without_cost} item(s) have stock but no purchase cost yet — their value shows as ₹0. Set an <a class="erp-link" data-go="opening">opening cost</a>.</div>`);
            COLS = [{ label: 'SKU', key: 'sku' }, { label: 'Item', key: 'name', render: x => `<a class="erp-link" data-trace="${x.item_type}:${x.item_id}">${E.esc(x.name)}</a>${x.pack && x.item_type === 'product' ? ' <span class="erp-muted">' + E.esc(x.pack) + '</span>' : ''}` },
                    { label: 'Type', key: 'item_type', render: x => x.item_type === 'product' ? 'Pack' : 'Bulk' }, { label: 'Category', key: 'category', render: x => E.esc(x.category || '—') },
                    { label: 'Qty', key: 'qty', num: true, render: x => E.qty(x.qty) }, { label: 'Avg cost', key: 'avg_cost', num: true, render: x => x.cost_missing ? '<span class="adm-badge is-amber">no cost</span>' : E.money(x.avg_cost) },
                    { label: 'Stock value', key: 'value', num: true, render: x => `<strong>${E.money(x.value)}</strong>` }];
            render();
        }).catch(m => E.errorBox($('#list'), m, load));
    } else if (TAB === 'batches') {
        $('#title').text('Batches & expiry');
        E.api('erp_reports.php', { report: 'batches', days: $('#fDays').val() }, { silent: true }).then(r => {
            DATA = r.rows;
            const exp = r.rows.filter(b => b.expiry_state === 'expired' && b.qty_available_est > 0), soon = r.rows.filter(b => b.expiry_state === 'expiring' && b.qty_available_est > 0);
            $('#kpis').html(`<div class="adm-stat is-amber"><h3>${soon.length}</h3><p>Batches expiring soon (${E.money(soon.reduce((a, b) => a + b.value_est, 0))})</p></div>
                <div class="adm-stat is-neutral"><h3>${exp.length}</h3><p>Expired batches still in stock (${E.money(exp.reduce((a, b) => a + b.value_est, 0))})</p></div>`);
            $('#warn').html('<p class="erp-note">Sales do not record which batch was sold, so remaining quantity per batch is an estimate: current stock is assigned to the newest batches first (oldest sold first).</p>');
            COLS = [{ label: 'Item', key: 'item_name' }, { label: 'Batch', key: 'batch_number', render: x => E.esc(x.batch_number || '—') + (x.lot_number ? ' / ' + E.esc(x.lot_number) : '') },
                    { label: 'Supplier', key: 'supplier_name', render: x => E.esc(x.supplier_name || (x.source_type === 'repack' ? 'Repacked' : '—')) },
                    { label: 'GRN', key: 'grn_number', render: x => x.grn_number ? `<a class="erp-link" href="goods_receipts.php?id=${x.source_id}">${E.esc(x.grn_number)}</a>` : '—' },
                    { label: 'Received', key: 'received_date', render: x => E.date(x.received_date) }, { label: 'Mfg', key: 'manufacturing_date', render: x => E.date(x.manufacturing_date) },
                    { label: 'Expiry', key: 'expiry_date', render: x => E.date(x.expiry_date) + (x.days_to_expiry !== null ? ` <span class="erp-muted">(${x.days_to_expiry} d)</span>` : '') },
                    { label: 'Received qty', key: 'qty_received', num: true, render: x => E.qty(x.qty_received) }, { label: 'Est. remaining', key: 'qty_available_est', num: true, render: x => E.qty(x.qty_available_est) },
                    { label: 'Unit cost', key: 'unit_cost', num: true, render: x => E.money(x.unit_cost) }, { label: 'State', key: 'expiry_state', render: x => E.badge(x.expiry_state) }];
            render();
        }).catch(m => E.errorBox($('#list'), m, load));
    } else if (TAB === 'opening') {
        $('#title').text('Opening cost');
        const type = $('#fType').val() || 'product';
        E.api('inventory_ops_api.php', { action: 'opening_list', item_type: type }, { silent: true }).then(r => {
            DATA = r.rows.map(x => Object.assign({ item_type: type }, x));
            $('#warn').html('<p class="erp-note">Stock that existed before purchase tracking started has no recorded cost. Enter what one pack / unit cost you, once. It is used only for that opening stock; every later purchase uses its real cost.</p>');
            COLS = [{ label: 'Item', key: 'name', render: x => E.esc(x.name) + (x.pack ? ' <span class="erp-muted">' + E.esc(x.pack) + '</span>' : '') }, { label: 'Category', key: 'category', render: x => E.esc(x.category || '—') },
                    { label: 'Stock', key: 'stock', num: true, render: x => E.qty(x.stock) }, { label: 'Current avg cost', key: 'avg_cost', num: true, render: x => x.cost_missing ? '<span class="adm-badge is-amber">missing</span>' : E.money(x.avg_cost) },
                    { label: 'Opening cost / unit', key: 'opening_unit_cost', render: x => `<input class="adm-input" type="number" min="0" step="0.01" style="width:120px" data-oc="${x.id}" value="${x.opening_unit_cost ?? ''}"> <button class="adm-btn adm-btn-ghost" data-save="${x.id}">Save</button>` }];
            render();
        }).catch(m => E.errorBox($('#list'), m, load));
    } else {
        $('#title').text('Ledger check');
        E.api('erp_reports.php', { report: 'variance' }, { silent: true }).then(r => {
            DATA = r.rows;
            $('#warn').html('<p class="erp-note">Compares the current stock figure with the stock ledger (Stock Movement). A difference means a change was made without a ledger entry. Use a Stock Adjustment to correct it with a reason.</p>');
            COLS = [{ label: 'Item', key: 'name' }, { label: 'Current stock', key: 'current_stock', num: true, render: x => E.qty(x.current_stock) }, { label: 'Ledger stock', key: 'ledger_stock', num: true, render: x => E.qty(x.ledger_stock) },
                    { label: 'Difference', key: 'difference', num: true, render: x => `<strong class="${x.difference < 0 ? 'erp-neg' : ''}">${E.qty(x.difference)}</strong>` },
                    { label: 'Unrecorded changes', key: 'unrecorded_in_ledger', num: true, render: x => E.qty(x.unrecorded_in_ledger) }, { label: 'Value', key: 'difference_value', num: true, render: x => E.money(x.difference_value) }];
            E.table($('#list'), COLS, DATA, { empty: 'Stock and ledger agree for every item', icon: 'fa-circle-check' });
        }).catch(m => E.errorBox($('#list'), m, load));
    }
}
$('#warn').on('click', '[data-go]', function () { $('.erp-tab[data-tab=' + $(this).data('go') + ']').click(); });
$('#list').on('click', '[data-save]', function () {
    const id = $(this).data('save'), v = $(`[data-oc=${id}]`).val();
    E.post('inventory_ops_api.php', { action: 'opening_save', item_type: $('#fType').val() || 'product', item_id: id, unit_cost: v }).then(r => E.toast(r.message)).catch(() => {});
});
$('#list').on('click', '[data-trace]', function () {
    const [t, id] = String($(this).data('trace')).split(':');
    E.api('erp_reports.php', { report: 'item_trace', item_type: t, item_id: id }).then(r => {
        const c = r.costing || {}, p = r.profitability_all_time || {};
        const html = E.kv([['Stock', E.qty(c.closing_qty)], ['Average cost', E.money(c.avg_cost)], ['Stock value', E.money(c.closing_value)], p.qty_sold !== undefined ? ['Sold (all time)', E.qty(p.qty_sold)] : null,
                           p.gross_profit !== undefined ? ['Gross profit (all time)', E.money(p.gross_profit)] : null])
            + E.chain([['Purchase history', 'purchase_history.php?item=' + t + ':' + id], ['Profitability', 'product_profitability.php'], ['Stock movements', 'stock_movements.php']])
            + '<div class="erp-section-title">Cost history (how the average was built)</div>' + (r.cost_entries.length ? `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>When</th><th>Type</th><th class="erp-num">Qty</th><th class="erp-num">Value</th><th>Reference</th><th>Note</th></tr></thead><tbody>` +
              r.cost_entries.map(e => `<tr><td>${E.date(e.entry_date)}</td><td>${E.esc(e.entry_type)}</td><td class="erp-num">${E.qty(e.quantity)}</td><td class="erp-num">${E.money(e.value)}</td><td>${E.esc(e.reference_number || '')}</td><td>${E.esc(e.note || '')} ${e.status === 'cancelled' ? E.badge('cancelled') : ''}</td></tr>`).join('') + '</tbody></table></div>' : '<span class="erp-muted">No purchase cost recorded.</span>')
            + '<div class="erp-section-title">Recent stock ledger</div>' + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>When</th><th>Type</th><th class="erp-num">Change</th><th class="erp-num">Balance</th><th>Reference</th></tr></thead><tbody>` +
              r.ledger.slice(0, 40).map(v => `<tr><td>${E.date(v.created_at)}</td><td>${E.esc(v.movement_type)} · ${E.esc(v.reference_type || '')}</td><td class="erp-num ${v.new_stock - v.previous_stock < 0 ? 'erp-neg' : 'erp-pos'}">${E.qty(v.new_stock - v.previous_stock)}</td><td class="erp-num">${E.qty(v.new_stock)}</td><td>${E.esc(v.reference_number || '')}</td></tr>`).join('') + '</tbody></table></div>';
        E.view(r.item.name, html, [], { width: 1000 });
    }).catch(() => {});
});
load();
JS
);
