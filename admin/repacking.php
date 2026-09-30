<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Repacking', 'Turn bulk raw material (kg / L) into sellable product packs — cost flows into the packs',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-box"></i> New repack</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Repack jobs</h2></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let RAW = [], PRODUCTS = [];
Promise.all([E.api('inventory_ops_api.php', { action: 'rm_list' }, { silent: true }), E.items('product')]).then(([r, p]) => {
    RAW = r.rows.filter(x => x.status === 'active'); PRODUCTS = p; load(); if (E.param('raw')) openForm(E.param('raw'));
}).catch(m => E.errorBox($('#list'), m));
$('#newBtn').on('click', () => openForm(''));
function load() {
    E.loading($('#list'));
    E.api('inventory_ops_api.php', { action: 'repack_list' }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Repack', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.repack_number)}</a>` }, { label: 'Date', render: x => E.date(x.repack_date) },
        { label: 'From', render: x => E.esc(x.raw_name) }, { label: 'Consumed', num: true, render: x => E.qty(x.consumed_qty, x.raw_unit) },
        { label: 'Packs made', num: true, render: x => E.qty(x.packs) }, { label: 'Process loss', num: true, render: x => E.qty(x.process_loss_qty, x.raw_unit) },
        { label: 'Material cost', num: true, render: x => E.money(x.material_cost) }, { label: 'Packing cost', num: true, render: x => E.money(x.packing_cost) },
        { label: 'Stock In', render: x => E.esc(x.stock_in_number || '—') },
    ], r.rows, { empty: 'No repacking yet', icon: 'fa-box' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () {
    E.api('inventory_ops_api.php', { action: 'repack_get', id: $(this).data('view') }).then(r => {
        const j = r.record;
        E.view(j.repack_number, E.kv([['Date', E.date(j.repack_date)], ['Raw material', E.esc(j.raw_name)], ['Consumed', E.qty(j.consumed_qty, j.raw_unit)], ['In packs', E.qty(j.output_qty_total, j.raw_unit)],
            ['Process loss', E.qty(j.process_loss_qty, j.raw_unit)], ['Material cost', E.money(j.material_cost)], ['Packing cost', E.money(j.packing_cost)], ['Stock In', E.esc(j.stock_in_number || '—')]]) +
            `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Product</th><th class="erp-num">Packs</th><th class="erp-num">Per pack</th><th class="erp-num">Cost / pack</th><th>Batch</th><th>Expiry</th></tr></thead><tbody>` +
            j.outputs.map(o => `<tr><td>${E.esc(o.product_name)} ${o.pack ? '(' + E.esc(o.pack) + ')' : ''}</td><td class="erp-num">${o.packs}</td><td class="erp-num">${E.qty(o.pack_size_qty, j.raw_unit)}</td><td class="erp-num"><strong>${E.money(o.unit_cost)}</strong></td><td>${E.esc(o.batch_number || '—')}</td><td>${E.date(o.expiry_date)}</td></tr>`).join('') +
            `</tbody></table></div>${j.notes ? '<p class="erp-note">' + E.esc(j.notes) + '</p>' : ''}<div id="vDocs"></div>`, [], { didOpen: () => E.docs($('#vDocs'), 'repack', j.id) });
    }).catch(() => {});
});
function outRow() {
    return `<tr><td style="min-width:260px">${E.select('', '<option value="">Select product…</option>' + PRODUCTS.map(p => `<option value="${p.item_id}" data-pack="${E.esc(p.pack || '')}">${E.esc(p.label)}</option>`).join(''), 'data-f="p"')}</td>
      <td class="w-num"><input class="adm-input" type="number" min="0" step="1" data-f="n"></td><td class="erp-num" data-f="w">—</td>
      <td><input class="adm-input" data-f="b"></td><td><input class="adm-input" type="date" data-f="e"></td><td><button type="button" class="adm-icon-btn is-danger" data-f="del"><i class="fas fa-xmark"></i></button></td></tr>`;
}
const packKg = t => { const m = String(t || '').match(/(\d+(?:\.\d+)?)\s*(kg|g|gm|ml|l)\b/i); if (!m) return null; const n = +m[1], u = m[2].toLowerCase(); return (u === 'g' || u === 'gm' || u === 'ml') ? n / 1000 : n; };
function openForm(rawId) {
    const html = `<div class="erp-grid">
        ${E.field('Bulk raw material *', E.select('kR', E.options(RAW, 'id', r => `${r.name} — ${E.qty(r.stock)} ${r.unit} in stock @ ₹${E.num(r.avg_cost).toFixed(2)}/${r.unit}`, rawId, 'Choose'), ''), 'span-2')}
        ${E.field('Date *', E.input('kD', E.today(), 'type="date"'))}
        ${E.field('Quantity consumed *', E.input('kQ', '', 'type="number" min="0" step="any"'))}
        ${E.field('Packing / labour cost ₹', E.input('kP', 0, 'type="number" min="0" step="0.01"'))}
        ${E.field('Notes', E.input('kN', ''), 'span-all')}
      </div><div class="erp-section-title">Packs produced</div>
      <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Product pack</th><th>No. of packs</th><th>Content</th><th>Batch</th><th>Expiry</th><th></th></tr></thead><tbody id="kOut">${outRow()}</tbody></table></div>
      <button type="button" class="adm-btn adm-btn-ghost" id="kAdd" style="margin-top:8px;"><i class="fas fa-plus"></i> Add product</button>
      <div class="erp-totals" id="kTot"></div>
      <p class="erp-note">Pack content is read from each product's Quantity (e.g. 500g = 0.5 kg). Total cost (consumed × average cost + packing cost) is shared across the packs by weight. Use packing cost only if those materials are not already recorded as an expense.</p>`;
    const recalc = () => {
        const raw = RAW.find(r => String(r.id) === $('#kR').val()); let content = 0;
        const base = raw ? (['L', 'ml'].includes(raw.unit) ? 'L' : 'kg') : '';          // pack contents are compared in kg / L
        const factor = raw && ['g', 'ml'].includes(raw.unit) ? 1000 : 1;                // raw unit → base unit
        $('#kOut tr').each(function () { const pk = packKg($(this).find('[data-f=p] option:selected').data('pack')); const n = E.num($(this).find('[data-f=n]').val());
            $(this).find('[data-f=w]').text(pk ? E.qty(pk * n) + ' ' + base : 'no size'); if (pk) content += pk * n; });
        const used = E.num($('#kQ').val()), usedBase = used / factor, cost = raw ? used * E.num(raw.avg_cost) + E.num($('#kP').val()) : 0;
        $('#kTot').html(`<span>In packs <strong>${E.qty(content)} ${base}</strong></span><span>Process loss <strong>${E.qty(Math.max(0, usedBase - content))} ${base}</strong></span><span>Total cost <strong>${E.money(cost)}</strong></span>`);
    };
    E.form('New repack', html, () => E.post('inventory_ops_api.php', { action: 'repack_post', raw_material_id: $('#kR').val(), repack_date: $('#kD').val(), consumed_qty: $('#kQ').val(), packing_cost: $('#kP').val(), notes: $('#kN').val(),
        outputs: $('#kOut tr').map(function () { return { product_id: $(this).find('[data-f=p]').val(), packs: $(this).find('[data-f=n]').val(), batch_number: $(this).find('[data-f=b]').val(), expiry_date: $(this).find('[data-f=e]').val() }; }).get() }, { silent: true })
        .then(r => { E.toast(r.message); load(); Swal.fire({ icon: 'success', title: 'Repack posted', text: r.message, confirmButtonColor: '#1c5034' }); }),
      { width: 1080, confirmText: 'Post repack', didOpen: () => {
        $('#kAdd').on('click', () => { $('#kOut').append(outRow()); recalc(); });
        $('#kOut').on('click', '[data-f=del]', function () { $(this).closest('tr').remove(); recalc(); });
        $('.swal2-popup').on('input change', 'input, select', recalc); recalc();
      }});
}
JS
);
