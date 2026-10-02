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
<style>/* FIX (2 Oct 2026): live stage, "Other" location, tick-box item picker */
.prs{display:flex;flex-direction:column;gap:4px;min-width:220px;max-width:340px}
.prs-pill{display:inline-flex;align-items:flex-start;gap:6px;padding:4px 10px;border-radius:12px;font-size:12.5px;font-weight:600;line-height:1.35;white-space:normal}
.prs-pill i{margin-top:2px}
.prs-wait{background:#fff4e0;color:#8a5a12}.prs-ok{background:#e6f0fa;color:#1d4f80}.prs-done{background:#e3f3e8;color:#1c5034}.prs-bad{background:#fbe7e3;color:#a8442f}.prs-muted{background:#efede9;color:#6b6459}
.prs-bar{height:4px;border-radius:3px;background:#ece8e1;overflow:hidden}.prs-bar span{display:block;height:100%;background:#1c5034}
.prs-meta{font-size:11.5px;color:#6b6459}
.erp-detail-head .prs span{display:inline;text-transform:none;letter-spacing:normal;font-weight:inherit;color:inherit;font-size:inherit}.erp-detail-head .prs .prs-pill{display:inline-flex;font-size:12.5px;font-weight:600}.erp-detail-head .prs .prs-meta{display:block;font-size:11.5px;color:#6b6459;font-weight:400}.erp-detail-head .prs .prs-bar span{display:block}
#qWOther{display:none;margin-top:8px;gap:8px;flex-wrap:wrap}#qWOther.on{display:flex}#qWOther .adm-input{flex:1 1 180px}
.prp{border:1px solid #e4dfd6;border-radius:10px;padding:10px;margin:6px 0 12px;background:#fbfaf7;text-align:left}
.prp-head{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px}.prp-head .adm-input{flex:1 1 220px}
.prp-list{max-height:260px;overflow:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:2px 12px}
.prp-grp{grid-column:1/-1;font-size:12px;font-weight:700;color:#6b6459;text-transform:uppercase;letter-spacing:.03em;margin:8px 0 2px}
.prp-list label{display:flex;gap:8px;align-items:flex-start;padding:5px 6px;border-radius:6px;cursor:pointer;font-size:13px;line-height:1.3;margin:0;font-weight:400}
.prp-list label:hover{background:#f0ece4}.prp-list input{margin-top:2px;flex:none;width:16px;height:16px;accent-color:#1c5034}
.prp-list small{color:#8a8378;display:block}.prp-count{font-size:12.5px;color:#1c5034;font-weight:600}
</style>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [], WH = [], ITEMS = [], PR_PERMS = { manager: false, backend: false };
Promise.all([E.suppliers(), E.api('purchase_api.php', { action: 'pr_warehouses' }, { silent: true }).then(r => r.warehouses || []).catch(() => E.warehouses()), E.items(), E.api('purchase_api.php', { action: 'pr_permissions' }, { silent: true })]).then(([s, w, i, p]) => { SUP = s; WH = w; ITEMS = i; PR_PERMS = p; load(); if (E.param('id')) openView(E.param('id')); });
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
        { label: 'Status', render: x => stageHtml(x) },
    ], r.rows, { empty: 'No purchase requests', icon: 'fa-clipboard-list' })).catch(m => E.errorBox($l, m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
// FIX (2 Oct 2026): exact live stage — Manager → Admin → quotation → PO → PO approval → Accounts payment → paid → loaded → unloaded (warehouse) → QC → inventory
function stageHtml(x) {
    const g = x.stage; if (!g) return E.badge(x.status);
    const ic = { wait: 'fa-hourglass-half', ok: 'fa-truck-fast', done: 'fa-circle-check', bad: 'fa-circle-xmark', muted: 'fa-file-pen' }[g.tone] || 'fa-circle-info';
    const pct = Math.round(Math.max(0, Math.min(g.of, g.step)) / g.of * 100);
    return `<div class="prs"><span class="prs-pill prs-${E.esc(g.tone)}"><i class="fas ${ic}"></i><span>${E.esc(g.label)}</span></span>` +
        (g.step > 0 ? `<div class="prs-bar"><span style="width:${pct}%"></span></div>` : '') +
        `<span class="prs-meta">${g.step > 0 ? 'Step ' + g.step + ' of ' + g.of : ''}${g.who && g.tone !== 'done' ? (g.step > 0 ? ' · ' : '') + 'With: ' + E.esc(g.who) : ''}</span></div>`;
}
// FIX (2 Oct 2026): tick-box item picker — each ticked item becomes a request line (qty / unit / rate stay editable)
function pickerHtml() {
    const grp = (t, label) => { const g = ITEMS.filter(i => i.item_type === t); return g.length ? `<div class="prp-grp">${label}</div>` + g.map(i => `<label data-s="${E.esc((i.label + ' ' + (i.category || '')).toLowerCase())}"><input type="checkbox" value="${t}:${i.item_id}"><span>${E.esc(i.label)}<small>${i.category ? E.esc(i.category) + ' · ' : ''}stock ${E.qty(i.stock)}</small></span></label>`).join('') : ''; };
    return `<div class="prp"><div class="prp-head"><input type="text" class="adm-input" id="prpQ" placeholder="Search products to tick…"><span class="prp-count" id="prpN"></span></div>
        <div class="prp-list" id="prpL">${grp('product', 'Product packs') + grp('raw_material', 'Bulk raw materials (kg / L)') || '<p class="erp-muted">No items found.</p>'}</div></div>`;
}
function wirePicker($p, ed) {
    const $tb = () => $p.find('#qLines tbody');
    const rowsOf = v => $tb().find('tr').filter(function () { return String($(this).find('[data-f=item]').val() || '') === v; });
    const sync = () => { const used = new Set($tb().find('[data-f=item]').map(function () { return String($(this).val() || ''); }).get().filter(Boolean));
        $p.find('#prpL input').each(function () { this.checked = used.has(this.value); }); $p.find('#prpN').text(used.size ? used.size + ' item(s) ticked' : ''); };
    $p.on('change', '#prpL input', function () {
        const v = this.value;
        if (this.checked) {
            if (!rowsOf(v).length) {
                let $r = $tb().find('tr').filter(function () { return !$(this).find('[data-f=item]').val(); }).first();
                if (!$r.length) { $p.find('#qLines [data-f=add]').trigger('click'); $r = $tb().find('tr').last(); }
                $r.find('[data-f=item]').val(v).trigger('change'); $r.find('[data-f=qty]').trigger('focus');
            }
        } else rowsOf(v).each(function () { $(this).find('[data-f=del]').trigger('click'); });
        sync();
    });
    $p.on('input', '#prpQ', function () { const q = this.value.toLowerCase().trim(); $p.find('#prpL label').each(function () { $(this).toggle(!q || $(this).data('s').includes(q)); }); });
    $p.on('change click', '#qLines', () => setTimeout(sync, 0));
    sync();
}

function openForm(pr) {
    let ed;
    const html = `<div class="erp-grid">
        ${E.field('Request date *', E.input('qD', pr.request_date || E.today(), 'type="date"'))}
        ${E.field('Needed by', E.input('qN', pr.required_by || '', 'type="date"'))}
        ${E.field('Warehouse / location', E.select('qW', E.options(WH, 'id', w => w.name, pr.warehouse_id || 1, false) + '<option value="__other">Other — type a new location…</option>') +
            '<div id="qWOther"><input class="adm-input" id="qWName" maxlength="100" placeholder="New location name *"><input class="adm-input" id="qWLoc" maxlength="150" placeholder="Address / area (optional)"></div>')}
        ${E.field('Requested by', E.input('qB', pr.requested_by || '', 'placeholder="Name of the person who needs the items" maxlength="100"'))}
        ${E.field('Notes', E.textarea('qNotes', pr.notes || ''), 'span-all')}
      </div><div class="erp-section-title">Items needed — tick the items, then enter quantity</div>${pickerHtml()}<div id="qLines"></div>`;
    // FIX (2 Oct 2026): "Other" location is saved first (as a warehouse), then the request uses it; ticked lines need a quantity
    const whId = () => $('#qW').val() !== '__other' ? Promise.resolve($('#qW').val()) : (String($('#qWName').val() || '').trim().length < 2 ? Promise.reject('Type the new location name (Other)')
        : E.post('purchase_api.php', { action: 'pr_add_location', name: $('#qWName').val(), location: $('#qWLoc').val() }, { silent: true }).then(x => {
            if (!WH.some(w => String(w.id) === String(x.id))) WH.push({ id: x.id, name: x.name });
            $('#qW option[value="__other"]').before(`<option value="${E.esc(x.id)}">${E.esc(x.name)}</option>`); $('#qW').val(String(x.id)).trigger('change'); return x.id; }));
    const noQty = () => $('#qLines tbody tr').filter(function () { return $(this).find('[data-f=item]').val() && !(parseFloat($(this).find('[data-f=qty]').val()) > 0); })
        .map(function () { return $(this).find('[data-f=item] option:selected').text().split(' · ')[0]; }).get();
    E.form(E.brand(pr.id ? 'Edit purchase request' : 'New purchase request', pr.id ? pr.pr_number : 'Draft'), html, btn => (noQty().length ? Promise.reject('Enter the quantity for: ' + noQty().join(', ')) : whId()).then(wid => E.post('purchase_api.php', { action: btn === 'deny' ? 'pr_save' : 'pr_submit', id: pr.id || '',
        request_date: $('#qD').val(), required_by: $('#qN').val(), warehouse_id: wid, requested_by: $('#qB').val(), notes: $('#qNotes').val(), items: ed.get() }, { silent: true }))
        .then(r => { E.toast(r.message); load(); }),
      { confirmText: 'Submit for approval', denyText: 'Save draft', didOpen: () => { ed = E.lineEditor($('#qLines'), { items: ITEMS, columns: ['item', 'qty', 'uom', 'rate'],
          lines: (pr.items || []).map(i => Object.assign({}, i, { rate: i.estimated_rate })) });
          const $p = $(Swal.getPopup()).addClass('erp-doc'); wirePicker($p, ed);
          $p.on('change', '#qW', function () { const o = this.value === '__other'; $p.find('#qWOther').toggleClass('on', o); if (o) $p.find('#qWName').trigger('focus'); }); }, width: 1100 });
}

function openView(id) {
    E.api('purchase_api.php', { action: 'pr_get', id }).then(r => {
        const p = r.record;
        const html = E.kv([['Date', E.date(p.request_date)], ['Needed by', E.date(p.required_by)], ['Warehouse', E.esc(p.warehouse_name || '—')], ['Requested by', E.esc(p.requested_by || '—')], ['Status', E.badge(p.status)], p.stage ? ['Current stage', stageHtml(p)] : null,
                           p.manager_approved_by ? ['Manager approved by', E.esc(p.manager_approved_by)] : null, p.backend_approved_by ? ['Backend approved by', E.esc(p.backend_approved_by)] : null])
            + E.chain(p.purchase_orders.map(o => ['PO ' + o.po_number, 'purchase_orders.php?id=' + o.id]))
            + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Est. rate</th><th class="erp-num">Est. value</th></tr></thead><tbody>` +
              p.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}${i.input_unit ? '<div class="erp-muted">asked: ' + E.qty(i.input_qty, i.input_unit) + '</div>' : ''}</td><td class="erp-num">${E.money(i.estimated_rate, true)}</td><td class="erp-num">${E.money(i.quantity * (i.estimated_rate || 0))}</td></tr>`).join('') +
              `</tbody></table></div>${p.notes ? '<p class="erp-note">' + E.esc(p.notes) + '</p>' : ''}`;
        const act = (a, msg, o) => () => E.confirmAction(msg, '', o).then(note => E.post('purchase_api.php', { action: a, id: p.id, note })).then(x => { E.toast(x.message); load(); openView(p.id); }).catch(() => {});
        E.view(E.brand('Purchase request', p.pr_number), html, [   // FIX (2 Oct 2026): Valluvam letterhead
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
        ], { width: 1100, didOpen: pop => $(pop).addClass('erp-doc') });
    }).catch(() => {});
}
JS
);
