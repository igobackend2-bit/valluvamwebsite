<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Requests', 'Ask for stock to be bought — approved requests become purchase orders',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New request</button>');
?>
<section class="adm-card">
    <div class="adm-card-head">
        <h2>Requests</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="draft">Draft</option><option value="submitted">Waiting for Manager</option>
                <option value="manager_approved">Waiting for Backend</option><option value="approved">Backend approved</option><option value="converted">Converted to PO</option><option value="manager_rejected">Rejected by Manager</option><option value="backend_rejected">Rejected by Backend</option><option value="cancelled">Cancelled</option></select>
            <input type="text" class="adm-input" id="fQ" placeholder="Search">
        </div>
    </div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [], WH = [], ITEMS = [], PR_PERMS = { manager: false, backend: false };
Promise.all([E.suppliers(), E.warehouses(), E.items(), E.api('purchase_api.php', { action: 'pr_permissions' }, { silent: true })]).then(([s, w, i, p]) => { SUP = s; WH = w; ITEMS = i; PR_PERMS = p; load(); if (E.param('id')) openView(E.param('id')); });
$('#fStatus').on('change', load);
let t; $('#fQ').on('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm({}));

function load() {
    const $l = $('#list'); E.loading($l);
    E.api('purchase_api.php', { action: 'pr_list', status: $('#fStatus').val(), q: $('#fQ').val() }, { silent: true }).then(r => E.table($l, [
        { label: 'Request', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.pr_number)}</a>` },
        { label: 'Date', render: x => E.date(x.request_date) },
        { label: 'Needed by', render: x => E.date(x.required_by) },
        { label: 'Requested by', render: x => E.esc(x.requested_by || '—') },
        { label: 'Items', num: true, render: x => E.qty(x.item_count) },
        { label: 'Status', render: x => E.badge(x.status) },
    ], r.rows, { empty: 'No purchase requests', icon: 'fa-clipboard-list' })).catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function openForm(pr) {
    let ed;
    const html = `<div class="erp-grid">
        ${E.field('Request date *', E.input('qD', pr.request_date || E.today(), 'type="date"'))}
        ${E.field('Needed by', E.input('qN', pr.required_by || '', 'type="date"'))}
        ${E.field('Warehouse', E.select('qW', E.options(WH, 'id', w => w.name, pr.warehouse_id || 1, false)))}
        ${E.field('Requested by', E.input('qB', pr.requested_by || '', 'placeholder="Name of the person who needs the items" maxlength="100"'))}
        ${E.field('Notes', E.textarea('qNotes', pr.notes || ''), 'span-all')}
      </div><div class="erp-section-title">Items needed</div><div id="qLines"></div>`;
    E.form(pr.id ? 'Edit ' + pr.pr_number : 'New purchase request', html, btn => E.post('purchase_api.php', { action: btn === 'deny' ? 'pr_save' : 'pr_submit', id: pr.id || '',
        request_date: $('#qD').val(), required_by: $('#qN').val(), warehouse_id: $('#qW').val(), requested_by: $('#qB').val(), notes: $('#qNotes').val(), items: ed.get() }, { silent: true })
        .then(r => { E.toast(r.message); load(); }),
      { confirmText: 'Submit for approval', denyText: 'Save draft', didOpen: () => { ed = E.lineEditor($('#qLines'), { items: ITEMS, columns: ['item', 'qty', 'uom', 'rate'],
          lines: (pr.items || []).map(i => Object.assign({}, i, { rate: i.estimated_rate })) }); } });
}

function openView(id) {
    E.api('purchase_api.php', { action: 'pr_get', id }).then(r => {
        const p = r.record;
        const html = E.kv([['Date', E.date(p.request_date)], ['Needed by', E.date(p.required_by)], ['Warehouse', E.esc(p.warehouse_name || '—')], ['Requested by', E.esc(p.requested_by || '—')], ['Status', E.badge(p.status)],
                           p.manager_approved_by ? ['Manager approved by', E.esc(p.manager_approved_by)] : null, p.backend_approved_by ? ['Backend approved by', E.esc(p.backend_approved_by)] : null])
            + E.chain(p.purchase_orders.map(o => ['PO ' + o.po_number, 'purchase_orders.php?id=' + o.id]))
            + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Est. rate</th><th class="erp-num">Est. value</th></tr></thead><tbody>` +
              p.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}${i.input_unit ? '<div class="erp-muted">asked: ' + E.qty(i.input_qty, i.input_unit) + '</div>' : ''}</td><td class="erp-num">${E.money(i.estimated_rate, true)}</td><td class="erp-num">${E.money(i.quantity * (i.estimated_rate || 0))}</td></tr>`).join('') +
              `</tbody></table></div>${p.notes ? '<p class="erp-note">' + E.esc(p.notes) + '</p>' : ''}`;
        const act = (a, msg, o) => () => E.confirmAction(msg, '', o).then(note => E.post('purchase_api.php', { action: a, id: p.id, note })).then(x => { E.toast(x.message); load(); openView(p.id); }).catch(() => {});
        E.view(p.pr_number, html, [
            ['draft', 'manager_rejected', 'backend_rejected'].includes(p.status) && { label: 'Edit', icon: 'fa-pen', run: () => openForm(p) },
            // approvals are done only from the Dashboard (Manager → Admin), 1 Oct 2026
            ((PR_PERMS.manager && p.status === 'submitted') || (PR_PERMS.backend && p.status === 'manager_approved')) && { label: 'Approve / reject on your Dashboard', cls: 'adm-btn-primary', icon: 'fa-gauge-high', run: () => location.href = 'index.php' },
            false && PR_PERMS.backend && p.status === 'approved' && { label: 'Create purchase order', cls: 'adm-btn-primary', icon: 'fa-file-signature', run: () => Swal.fire({ title: 'Which supplier?', customClass: { popup: 'erp-modal' },
                html: E.field('Supplier', E.select('cvS', E.options(SUP.filter(s => s.status === 'active'), 'id', s => s.supplier_name, '', 'Choose supplier'))), showCancelButton: true, confirmButtonColor: '#1c5034',
                preConfirm: () => $('#cvS').val() || (Swal.showValidationMessage('Choose a supplier'), false) })
                .then(x => x.isConfirmed ? E.post('purchase_api.php', { action: 'pr_to_po', id: p.id, supplier_id: x.value }) : Promise.reject())
                .then(x => { E.toast(x.message); location.href = 'purchase_orders.php?id=' + x.id; }).catch(() => {}) },
            ['approved', 'converted'].includes(p.status) && { label: 'Purchase flow (quotations → PO → payment → delivery)', cls: 'adm-btn-primary', icon: 'fa-route', run: () => location.href = 'purchase_flow.php?pr_id=' + p.id },
            false && PR_PERMS.backend && p.status === 'approved' && { label: 'Ask for quotations (RFQ)', icon: 'fa-envelope-open-text', run: () => location.href = 'rfqs.php?pr_id=' + p.id },
            !['converted', 'cancelled'].includes(p.status) && { label: 'Cancel', icon: 'fa-ban', run: act('pr_cancel', 'Cancel ' + p.pr_number + '?', { danger: true }) },
        ]);
    }).catch(() => {});
}
JS
);
