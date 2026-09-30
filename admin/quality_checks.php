<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Quality Check', 'Inspect goods on a draft goods receipt — only accepted quantity becomes stock when the GRN is posted',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New quality check</button>');
?>
<section class="adm-card">
    <div class="adm-card-head"><h2>Quality checks</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="pending">Pending</option><option value="passed">Passed</option><option value="partially_passed">Partially passed</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input class="adm-input" id="fQ" placeholder="QC, GRN or supplier">
        </div></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
if (E.param('status')) $('#fStatus').val(E.param('status'));
$('#fStatus,#fFrom,#fTo').on('change', load);
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
function load() {
    E.loading($('#list'));
    E.api('procurement_api.php', { action: 'qc_list', status: $('#fStatus').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'QC', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.qc_number)}</a>` },
        { label: 'Goods receipt', render: x => `<a class="erp-link" href="goods_receipts.php?id=${x.grn_id}">${E.esc(x.grn_number)}</a> ${E.badge(x.grn_status)}` },
        { label: 'Supplier', render: x => E.esc(x.supplier_name) },
        { label: 'Received', num: true, render: x => E.qty(x.received_qty) }, { label: 'Accepted', num: true, render: x => E.qty(x.accepted_qty) }, { label: 'Rejected / damaged', num: true, render: x => E.qty(x.rejected_qty) },
        { label: 'Inspected', render: x => x.inspection_date ? E.date(x.inspection_date) + '<div class="erp-muted">' + E.esc(x.inspected_by || '') + '</div>' : '—' },
        { label: 'Status', render: x => E.badge(x.status) },
    ], r.rows, { empty: 'No quality checks yet', emptyHint: 'Save a goods receipt as draft, then create a quality check for it.', icon: 'fa-microscope' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
$('#newBtn').on('click', () => {
    E.api('purchase_api.php', { action: 'grn_list', status: 'draft' }).then(r => {
        if (!r.rows.length) return E.alertError('There is no draft goods receipt. In Goods Receipts, enter what arrived and choose "Save draft" first.');
        E.form('Quality check for which goods receipt?', E.field('Draft goods receipt', E.select('qGrn', r.rows.map(g => `<option value="${g.id}">${E.esc(g.grn_number)} · ${E.esc(g.supplier_name)} · ${E.date(g.received_date)}</option>`).join(''))),
            () => E.post('procurement_api.php', { action: 'qc_create', grn_id: $('#qGrn').val() }, { silent: true }).then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); }), { width: 620, confirmText: 'Create' });
    });
});
function openView(id) {
    E.api('procurement_api.php', { action: 'qc_get', id }).then(res => {
        const q = res.record, edit = q.status === 'pending';
        const rows = q.items.map(i => `<tr data-id="${i.id}"><td>${E.esc(i.item_name)}<div class="erp-muted">${E.esc(i.batch_number || '')}</div></td><td class="erp-num">${E.qty(i.received_qty)}</td>
            ${edit ? `<td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="a" value="${E.num(i.accepted_qty)}"></td><td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="r" value="${E.num(i.rejected_qty)}"></td>
                      <td class="w-num"><input class="adm-input" type="number" min="0" step="any" data-f="d" value="${E.num(i.damaged_qty)}"></td><td><input class="adm-input" data-f="why" value="${E.esc(i.rejection_reason || '')}" placeholder="Reason if rejected / damaged"></td>`
                   : `<td class="erp-num">${E.qty(i.accepted_qty)}</td><td class="erp-num">${E.qty(i.rejected_qty)}</td><td class="erp-num">${E.qty(i.damaged_qty)}</td><td>${E.esc(i.rejection_reason || '')}</td>`}
            <td>${E.badge(i.result)}</td></tr>`).join('');
        const html = E.kv([['Goods receipt', `<a class="erp-link" href="goods_receipts.php?id=${q.grn_id}">${E.esc(q.grn_number)}</a> ${E.badge(q.grn_status)}`], ['Supplier', E.esc(q.supplier_name)], ['PO', E.esc(q.po_number || '—')], ['Status', E.badge(q.status)]])
            + (edit ? `<div class="erp-grid">${E.field('Inspection date', E.input('qDate', q.inspection_date || E.today(), 'type="date"'))}${E.field('Inspected by', E.input('qBy', q.inspected_by || ''))}${E.field('Notes', E.textarea('qNotes', q.notes || ''), 'span-2')}</div>`
                    : E.kv([['Inspected', E.date(q.inspection_date) + ' · ' + E.esc(q.inspected_by || '')], ['Notes', E.esc(q.notes || '—')]]))
            + `<div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Received</th><th>Accepted</th><th>Rejected</th><th>Damaged</th><th>Reason</th><th>Result</th></tr></thead><tbody>${rows}</tbody></table></div>
               <p class="erp-note">Accepted + rejected + damaged must equal received. Rejected and damaged goods never become sellable stock; they are held in "Rejected stock" until returned or destroyed.</p><div id="vDocs"></div>`;
        const items = () => $('.swal2-popup tr[data-id]').map(function () { const $r = $(this); return { id: $r.data('id'), accepted_qty: $r.find('[data-f=a]').val(), rejected_qty: $r.find('[data-f=r]').val(), damaged_qty: $r.find('[data-f=d]').val(), rejection_reason: $r.find('[data-f=why]').val() }; }).get();
        const send = a => () => E.post('procurement_api.php', { action: a, id: q.id, inspection_date: $('#qDate').val(), inspected_by: $('#qBy').val(), notes: $('#qNotes').val(), items: items() })
            .then(x => { E.toast(x.message); load(); if (a === 'qc_complete') location.href = 'goods_receipts.php?id=' + x.grn_id; else openView(q.id); }).catch(() => {});
        E.view(q.qc_number, html, [
            edit && { label: 'Save', icon: 'fa-floppy-disk', run: send('qc_save') },
            edit && { label: 'Complete QC', cls: 'adm-btn-primary', icon: 'fa-check', run: send('qc_complete') },
            q.status !== 'cancelled' && q.grn_status === 'draft' && { label: 'Cancel QC', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + q.qc_number + '?', '', { danger: true, reason: 'Reason' }).then(reason => E.post('procurement_api.php', { action: 'qc_cancel', id: q.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
        ], { didOpen: (p) => {
            E.docs($('#vDocs'), 'qc', q.id, 'QC_DOCUMENT');
            $(p).on('input', '[data-f=r],[data-f=d]', function () { const $r = $(this).closest('tr'); const rec = E.num($r.find('td').eq(1).text()); $r.find('[data-f=a]').val(Math.max(0, rec - E.num($r.find('[data-f=r]').val()) - E.num($r.find('[data-f=d]').val()))); });
        } });
    }).catch(() => {});
}
load();
if (E.param('id')) openView(E.param('id'));
JS
);
