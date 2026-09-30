<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase History & Reports', 'What was bought, from whom, when, and at what price — from posted goods receipts',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div class="erp-tabs" id="tabs">
    <button class="erp-tab active" data-tab="report">Purchase report</button>
    <button class="erp-tab" data-tab="price">Price history</button>
    <button class="erp-tab" data-tab="returns">Purchase returns</button>
</div>
<section class="adm-card">
    <div class="adm-card-head">
        <h2 id="title">Purchases</h2>
        <div class="erp-filters">
            <span data-for="report"><label>Group by</label> <select class="adm-select" id="fGroup"><option value="month">Month</option><option value="date">Date</option><option value="vendor">Vendor</option>
                <option value="product">Product</option><option value="category">Category</option><option value="warehouse">Warehouse</option></select></span>
            <select class="adm-select" id="fSupplier"><option value="">All vendors</option></select>
            <select class="adm-select" id="fItem" style="max-width:260px;"><option value="">All items</option></select>
            <input class="adm-input" id="fCat" placeholder="Category" style="max-width:130px;" data-for="report">
            <select class="adm-select" id="fWh" data-for="report"><option value="">All warehouses</option></select>
            <input type="date" class="adm-input" id="fFrom" data-for="report returns"><input type="date" class="adm-input" id="fTo" data-for="report returns">
        </div>
    </div>
    <div class="adm-card-body"><div id="summary"></div><div id="list"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let TAB = 'report', LAST = { rows: [], cols: [] };
$('#fFrom').val(E.today().slice(0, 5) + '01-01'); $('#fTo').val(E.today());
Promise.all([E.suppliers(), E.items(), E.warehouses()]).then(([s, i, w]) => {
    $('#fSupplier').append(s.map(x => `<option value="${x.id}">${E.esc(x.supplier_name)}</option>`).join(''));
    $('#fItem').html(E.itemOptions(i).replace('Select item…', 'All items'));
    $('#fWh').append(w.map(x => `<option value="${x.id}">${E.esc(x.name)}</option>`).join(''));
    if (E.param('item')) { $('#fItem').val(E.param('item')); TAB = 'price'; $('.erp-tab').removeClass('active').filter('[data-tab=price]').addClass('active'); }
    load();
});
$('#tabs').on('click', '.erp-tab', function () { $('.erp-tab').removeClass('active'); $(this).addClass('active'); TAB = $(this).data('tab'); load(); });
$('.erp-filters').on('change', 'select, input', load);
$('#csvBtn').on('click', () => E.csv(LAST.rows, LAST.cols, 'purchases_' + TAB + '.csv'));

function load() {
    $('[data-for]').each(function () { $(this).toggle(String($(this).data('for')).split(' ').includes(TAB)); });
    const [itype, iid] = String($('#fItem').val() || ':').split(':');
    const $l = $('#list'); E.loading($l); $('#summary').html('');
    if (TAB === 'report') {
        $('#title').text('Purchases received');
        E.api('erp_reports.php', { report: 'purchases', group_by: $('#fGroup').val(), supplier_id: $('#fSupplier').val(), item_type: itype, item_id: iid, category: $('#fCat').val(),
                                   warehouse_id: $('#fWh').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val() }, { silent: true }).then(r => {
            const cols = [{ label: $('#fGroup option:selected').text(), key: 'group' }, { label: 'Lines', key: 'lines', num: true }, { label: 'Accepted qty', key: 'accepted_qty', num: true, render: x => E.qty(x.accepted_qty) },
                          { label: 'Rejected qty', key: 'rejected_qty', num: true, render: x => E.qty(x.rejected_qty) }, { label: 'Value (purchase rate)', key: 'value', num: true, render: x => E.money(x.value) },
                          { label: 'Landed value', key: 'landed_value', num: true, render: x => `<strong>${E.money(x.landed_value)}</strong>` }];
            $('#summary').html(`<div class="erp-kpis"><div class="adm-stat is-primary"><h3>${E.money(r.total_value)}</h3><p>Purchase value</p></div><div class="adm-stat is-neutral"><h3>${E.money(r.total_landed)}</h3><p>Landed value (with freight etc.)</p></div></div>`);
            E.table($l, cols, r.groups, { empty: 'No purchases in this period', icon: 'fa-cart-flatbed' });
            LAST = { rows: r.lines, cols: [{ label: 'Date', key: 'received_date' }, { label: 'GRN', key: 'grn_number' }, { label: 'Vendor', key: 'supplier_name' }, { label: 'Warehouse', key: 'warehouse_name' },
                     { label: 'Item', key: 'item_name' }, { label: 'Category', key: 'item_category' }, { label: 'Accepted', key: 'accepted_qty' }, { label: 'Rejected', key: 'rejected_qty' }, { label: 'Unit', key: 'unit' },
                     { label: 'Rate', key: 'rate' }, { label: 'Landed unit', key: 'landed_unit' }] };
        }).catch(m => E.errorBox($l, m, load));
    } else if (TAB === 'price') {
        $('#title').text('Purchase price history');
        E.api('erp_reports.php', { report: 'price_history', item_type: itype, item_id: iid }, { silent: true }).then(r => {
            const rows = $('#fSupplier').val() ? r.rows.filter(x => x.supplier_name === $('#fSupplier option:selected').text()) : r.rows;
            const cols = [{ label: 'Date', key: 'received_date', render: x => E.date(x.received_date) }, { label: 'Item', key: 'item_name' }, { label: 'Vendor', key: 'supplier_name' },
                          { label: 'Qty', key: 'accepted_qty', num: true, render: x => E.qty(x.accepted_qty, x.unit) }, { label: 'Purchase rate', key: 'rate', num: true, render: x => E.money(x.rate) },
                          { label: 'Landed / unit', key: 'landed_unit', num: true, render: x => E.money(x.landed_unit, true) },
                          { label: 'GRN', key: 'grn_number', render: x => `<a class="erp-link" href="goods_receipts.php?id=${x.grn_id}">${E.esc(x.grn_number)}</a>` },
                          { label: 'Bill', key: 'invoice_no', render: x => E.esc(x.invoice_no || '—') }, { label: 'Batch', key: 'batch_number', render: x => E.esc(x.batch_number || '—') }];
            E.table($l, cols, rows, { empty: 'No purchases recorded yet', icon: 'fa-chart-line' });
            LAST = { rows, cols };
        }).catch(m => E.errorBox($l, m, load));
    } else {
        $('#title').text('Purchase returns');
        E.api('purchase_api.php', { action: 'ret_list', supplier_id: $('#fSupplier').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val() }, { silent: true }).then(r => {
            const cols = [{ label: 'Return', key: 'return_number', render: x => `<a class="erp-link" href="purchase_returns.php?id=${x.id}">${E.esc(x.return_number)}</a>` }, { label: 'Date', key: 'return_date', render: x => E.date(x.return_date) },
                          { label: 'Vendor', key: 'supplier_name' }, { label: 'Reason', key: 'reason' }, { label: 'Settlement', key: 'settlement', render: x => E.badge(x.settlement) },
                          { label: 'Value', key: 'total_value', num: true, render: x => E.money(x.total_value) }];
            E.table($l, cols, r.rows, { empty: 'No purchase returns in this period', icon: 'fa-rotate-left' });
            LAST = { rows: r.rows, cols };
        }).catch(m => E.errorBox($l, m, load));
    }
}
JS
);
