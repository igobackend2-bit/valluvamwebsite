<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('RFQ & Quotations', 'Ask suppliers for prices, record their quotations, compare landed cost and award purchase orders',
    '<button class="adm-btn adm-btn-primary" id="newBtn"><i class="fas fa-plus"></i> New RFQ</button>');
?>
<!-- FIX (2 Oct 2026): approved purchase requests that still need shop quotations — click to enter them -->
<section class="adm-card" id="needQCard">
    <div class="adm-card-head"><h2>Requests waiting for your quotations <span class="adm-badge is-neutral" id="needQN">0</span></h2><span class="erp-muted">Approved purchase requests — click one to enter the 3 shop quotations</span></div>
    <div class="adm-card-body" id="needQ"></div>
</section>
<style>#needQCard .adm-card-head{flex-wrap:wrap;gap:6px 16px}#needQCard .adm-card-head h2 .adm-badge{margin-left:8px;vertical-align:middle}
.nq-list{display:grid;gap:12px}
.nq-row{display:grid;grid-template-columns:minmax(180px,1.1fr) minmax(240px,2.4fr) minmax(130px,.8fr) auto;gap:14px 20px;align-items:center;padding:14px 18px;border:1px solid var(--adm-line);border-left:4px solid #2f6ea8;border-radius:12px;background:var(--adm-surface);cursor:pointer;transition:box-shadow .15s,background .15s}
.nq-row:hover{background:#fbfaf6;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.nq-row.is-wait{border-left-color:var(--adm-amber)}.nq-row.is-bad{border-left-color:var(--adm-red)}
.nq-ref strong{font-size:15px;color:var(--adm-green-dark)}.nq-ref .erp-muted{display:block;margin-top:3px}
.nq-items{font-size:13px;line-height:1.5;color:var(--adm-ink)}.nq-items .erp-muted{font-size:12px}
.nq-q{font-size:12.5px;color:var(--adm-ink-soft)}.nq-q b{display:block;font-size:14px;color:var(--adm-ink)}
.nq-bar{height:5px;border-radius:4px;background:#ece8e1;margin-top:6px;overflow:hidden}.nq-bar span{display:block;height:100%;background:#2f6ea8}
@media (max-width:860px){.nq-row{grid-template-columns:1fr}.nq-row .adm-btn{justify-self:start}}</style>
<section class="adm-card">
    <div class="adm-card-head"><h2>Requests for quotation</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fStatus"><option value="">All status</option><option value="draft">Draft</option><option value="sent">Sent</option><option value="quoted">Quoted</option><option value="awarded">Awarded</option><option value="closed">Closed</option><option value="cancelled">Cancelled</option></select>
            <input type="date" class="adm-input" id="fFrom"><input type="date" class="adm-input" id="fTo">
            <input class="adm-input" id="fQ" placeholder="RFQ no. or notes">
        </div></div>
    <div class="adm-card-body" id="list"></div>
</section>
<!-- every set of shop quotations compared — lowest price / fastest delivery highlighted (1 Oct 2026) -->
<section class="adm-card" id="cmpCard">
    <div class="adm-card-head"><h2>Shop quotations compared</h2><span class="erp-muted">From the purchase flow — click a request to see its 3 shops side by side</span></div>
    <div class="adm-card-body" id="cmpList"></div>
</section>
<style>/* FIX (2 Oct 2026): professional, aligned layout for "Shop quotations compared" (view only) */
#cmpCard .pq-grp{border-radius:12px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
#cmpCard .pq-grp-h{padding:14px 18px;font-size:14.5px}
#cmpCard .pq-grp-h > span:first-child strong{font-size:15.5px;color:var(--adm-green-dark)}
#cmpCard .pq-grp-m{font-size:13px;gap:8px}
#cmpCard .pq-grp-m .fa-chevron-down{margin-left:4px;transition:transform .2s}
#cmpCard .pq-grp-b{padding:4px 18px 18px;border-top:1px solid var(--adm-line)}
#cmpCard .pq-grp-b > .erp-note{margin:10px 0 0}
#cmpCard .erp-section-title{margin:14px 0 10px}
#cmpCard .pq-wrap{padding:16px;border-radius:12px}
#cmpCard .pq-sum{grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:14px}
#cmpCard .pq-sum-card{padding:12px 16px;border-radius:10px}
#cmpCard .pq-sum-v{font-size:16px;margin:2px 0}
#cmpCard .pq-scroll{border:1px solid var(--adm-line);border-radius:10px;background:var(--adm-surface)}
#cmpCard .pq-tbl{border:0;border-radius:0;font-size:13.5px;min-width:720px}
#cmpCard .pq-tbl col.pq-c0{width:230px}
#cmpCard .pq-tbl th,#cmpCard .pq-tbl td{padding:10px 14px;line-height:1.4;text-align:center}
#cmpCard .pq-tbl th:first-child,#cmpCard .pq-tbl td.pq-lbl,#cmpCard .pq-tbl .pq-sec td{text-align:left}
#cmpCard .pq-tbl td + td,#cmpCard .pq-tbl th + th{border-left:1px solid var(--adm-line)}
#cmpCard .pq-tbl thead th{padding:13px 14px;font-size:14px;font-weight:700;vertical-align:middle}
#cmpCard .pq-tbl thead th .adm-badge{margin-left:6px;vertical-align:middle}
#cmpCard .pq-tbl tbody tr:not(.pq-sec):nth-child(even) td{background:#fcfbf8}
#cmpCard .pq-tbl tbody tr:not(.pq-sec):hover td{background:#f6f3ec}
#cmpCard .pq-tbl td.pq-lbl{font-size:13px;color:var(--adm-ink-soft);font-weight:500}
#cmpCard .pq-tbl td.pq-lbl strong{color:var(--adm-ink);font-weight:600}
#cmpCard .pq-tbl .pq-sec td{padding:9px 14px;font-size:12px;letter-spacing:.06em;border-left:0;border-top:1px solid var(--adm-line);box-shadow:inset 4px 0 0 var(--adm-green)}
#cmpCard .pq-tbl td.pq-best{background:#e3f1e6 !important;box-shadow:inset 0 0 0 2px #9fd0ab;font-weight:600;color:var(--adm-green-dark)}
#cmpCard .pq-tbl td .pq-unm{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
#cmpCard .pq-tbl td .erp-muted{font-size:12px}
#cmpCard .pq-tbl .pq-flags{justify-content:center}
#cmpCard .pq-tbl td.pq-lbl,#cmpCard .pq-tbl thead th:first-child{position:sticky;left:0;z-index:1;background:var(--adm-surface)}
#cmpCard .pq-tbl tbody tr:not(.pq-sec):nth-child(even) td.pq-lbl{background:#fcfbf8}
#cmpCard .pq-tbl thead th:first-child{background:var(--adm-green-soft)}
#cmpCard .pq-wrap > .erp-note{margin-top:10px;text-align:right}
#cmpCard .adm-card-head{flex-wrap:wrap;gap:6px 16px}
@media (max-width:760px){#cmpCard .pq-tbl col.pq-c0{width:150px}#cmpCard .pq-grp-h{padding:12px}#cmpCard .pq-grp-b{padding:4px 12px 12px}#cmpCard .pq-wrap{padding:10px}}
</style>
<script>window.addEventListener('load', function () { if (!window.ERP || !window.jQuery) return; jQuery.ajax({ url: 'assets/po_quotes.js?v=<?= @filemtime(__DIR__ . '/assets/po_quotes.js') ?: 1 ?>', dataType: 'script', cache: true }).then(function () { if (window.POQ && POQ.compareAll) POQ.compareAll(jQuery('#cmpList')); }); });</script>
<?php erp_page_end(<<<'JS'
const E = ERP;
let SUP = [], WH = [], ITEMS = [];
Promise.all([E.suppliers(), E.warehouses(), E.items()]).then(([s, w, i]) => {
    SUP = s; WH = w; ITEMS = i; load();
    if (E.param('id')) openView(E.param('id'));
    if (E.param('pr_id')) E.api('procurement_api.php', { action: 'rfq_from_pr', pr_id: E.param('pr_id') }).then(r => openForm({ pr_id: r.pr.id, pr_number: r.pr.pr_number, warehouse_id: r.pr.warehouse_id, due_date: r.pr.required_by, items: r.items })).catch(() => {});
    if (E.param('quote')) E.api('procurement_api.php', { action: 'quo_get', id: E.param('quote') }).then(r => r.record.rfq_id ? openView(r.record.rfq_id) : viewQuote(r.record)).catch(() => {});
});
$('#fStatus,#fFrom,#fTo').on('change', load);
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(load, 300); });
$('#newBtn').on('click', () => openForm(null));
function load() {
    E.loading($('#list'));
    E.api('procurement_api.php', { action: 'rfq_list', status: $('#fStatus').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val() }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'RFQ', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.rfq_number)}</a>` },
        { label: 'Date', render: x => E.date(x.rfq_date) }, { label: 'Reply by', render: x => E.date(x.due_date) },
        { label: 'Items', num: true, render: x => x.item_count }, { label: 'Suppliers', num: true, render: x => x.supplier_count }, { label: 'Quotations', num: true, render: x => x.quote_count },
        { label: 'Status', render: x => E.badge(x.status) },
    ], r.rows, { empty: 'No RFQs yet', emptyHint: 'Create one, or open an approved purchase request and choose "Ask for quotations".', icon: 'fa-envelope-open-text' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });

function openForm(r) {
    r = r || {};
    const chosen = (r.suppliers || []).map(s => String(s.supplier_id));
    const html = `<div class="erp-grid">
        ${E.field('RFQ date *', E.input('rDate', r.rfq_date || E.today(), 'type="date"'))}
        ${E.field('Reply by', E.input('rDue', r.due_date || '', 'type="date"'))}
        ${E.field('Deliver to', E.select('rWh', E.options(WH, 'id', w => w.name, r.warehouse_id || 1, false)))}
        ${r.pr_number ? E.field('From request', `<div class="adm-input" style="background:#faf8f2">${E.esc(r.pr_number)}</div>`) : ''}
        ${E.field('Terms for suppliers', E.textarea('rTerms', r.terms || 'Please quote rate per unit, GST %, delivery days, payment terms and freight.'), 'span-2')}
        ${E.field('Internal notes', E.textarea('rNotes', r.notes || ''), 'span-2')}
      </div>
      <div class="erp-section-title">Items (rate column = target / last price, optional)</div><div id="rLines"></div>
      <div class="erp-section-title">Ask these suppliers</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px;max-height:180px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:8px;">
        ${SUP.filter(s => s.status === 'active').map(s => `<label style="font-size:13px"><input type="checkbox" class="rSup" value="${s.id}" ${chosen.includes(String(s.id)) ? 'checked' : ''}> ${E.esc(s.supplier_name)}</label>`).join('')}</div>`;
    let ed;
    E.form(r.id ? 'Edit ' + r.rfq_number : 'New RFQ', html, () => {
        const items = ed.get().map(i => ({ item_type: i.item_type, item_id: i.item_id, quantity: i.quantity, target_rate: i.rate || '' }));
        if (!items.length) return Promise.reject('Add at least one item');
        return E.post('procurement_api.php', { action: 'rfq_save', id: r.id || '', pr_id: r.pr_id || '', rfq_date: $('#rDate').val(), due_date: $('#rDue').val(), warehouse_id: $('#rWh').val(),
                                               terms: $('#rTerms').val(), notes: $('#rNotes').val(), items, supplier_ids: $('.rSup:checked').map(function () { return this.value; }).get() }, { silent: true })
            .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); });
    }, { didOpen: () => { ed = E.lineEditor($('#rLines'), { items: ITEMS, columns: ['item', 'qty', 'rate'], lines: (r.items || []).map(i => ({ item_type: i.item_type, item_id: i.item_id, quantity: i.quantity, rate: i.target_rate })) }); } });
}

function openView(id) {
    E.api('procurement_api.php', { action: 'rfq_get', id }).then(res => {
        const r = res.record;
        const open = ['draft', 'sent', 'quoted'].includes(r.status);
        const qBySup = {}; r.quotations.forEach(q => { if (q.status !== 'cancelled') qBySup[q.supplier_id] = q; });
        const html = E.kv([['Date', E.date(r.rfq_date)], ['Reply by', E.date(r.due_date)], ['Deliver to', E.esc(r.warehouse_name || '—')], ['Status', E.badge(r.status)], r.pr_number ? ['From request', `<a class="erp-link" href="purchase_requests.php?id=${r.pr_id}">${E.esc(r.pr_number)}</a>`] : null])
            + E.chain(r.purchase_orders.map(p => ['PO ' + p.po_number + ' · ' + p.supplier_name + ' (' + p.status + ')', 'purchase_orders.php?id=' + p.id]))
            + '<div class="erp-section-title">Items</div><div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Target rate</th><th>Specs</th></tr></thead><tbody>'
            + r.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${i.target_rate ? E.money(i.target_rate) : '—'}</td><td>${E.esc(i.specs || '')}</td></tr>`).join('') + '</tbody></table></div>'
            + '<div class="erp-section-title">Suppliers & quotations</div><div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Supplier</th><th>Status</th><th>Quotation</th><th class="erp-num">Total</th><th>Valid until</th><th>Delivery</th><th></th></tr></thead><tbody>'
            + r.suppliers.map(s => { const q = qBySup[s.supplier_id];
                return `<tr><td>${E.esc(s.supplier_name)}<div class="erp-muted">${E.esc(s.mobile || '')} ${E.esc(s.email || '')}</div></td><td>${E.badge(s.status)}</td>
                  <td>${q ? `<a class="erp-link" data-q="${q.id}">${E.esc(q.quote_number)}</a> ${E.badge(q.status)}` : '—'}</td><td class="erp-num">${q ? E.money(q.grand_total) : ''}</td>
                  <td>${q ? E.date(q.valid_until) : ''}</td><td>${q && q.delivery_days ? q.delivery_days + ' days' : ''}</td>
                  <td>${open && (!q || q.status === 'received') ? `<button class="adm-btn adm-btn-ghost" data-addq="${s.supplier_id}" data-qid="${q ? q.id : ''}">${q ? 'Edit quotation' : 'Enter quotation'}</button>` : ''}</td></tr>`; }).join('')
            + '</tbody></table></div><div id="vCompare"></div><div id="vDocs"></div>';
        E.view(r.rfq_number, html, [
            open && { label: 'Edit', icon: 'fa-pen', run: () => openForm(r) },
            ['draft'].includes(r.status) && { label: 'Mark as sent', cls: 'adm-btn-primary', icon: 'fa-paper-plane', run: () => E.post('procurement_api.php', { action: 'rfq_send', id: r.id }).then(x => { E.toast(x.message); load(); openView(r.id); }) },
            { label: 'Print RFQ', icon: 'fa-print', run: () => window.open('print_erp.php?type=rfq&id=' + r.id, '_blank') },
            open && r.quotations.length && { label: 'Compare & award', cls: 'adm-btn-primary', icon: 'fa-scale-balanced', run: () => compare(r.id) },
            open && { label: 'Cancel RFQ', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + r.rfq_number + '?', '', { danger: true, reason: 'Reason' }).then(reason => E.post('procurement_api.php', { action: 'rfq_cancel', id: r.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
            r.status === 'awarded' && { label: 'Close RFQ', icon: 'fa-lock', run: () => E.post('procurement_api.php', { action: 'rfq_close', id: r.id }).then(x => { E.toast(x.message); load(); }) },
        ], { didOpen: (p) => {
            E.docs($('#vDocs'), 'rfq', r.id, 'QUOTATION');
            $(p).on('click', '[data-addq]', function () { quoteForm(r, $(this).data('addq'), $(this).data('qid')); });
            $(p).on('click', '[data-q]', function () { E.api('procurement_api.php', { action: 'quo_get', id: $(this).data('q') }).then(x => viewQuote(x.record)); });
        } });
    }).catch(() => {});
}

function quoteForm(rfq, supplierId, quoteId) {
    const go = q => {
        q = q || {};
        const byItem = {}; (q.items || []).forEach(i => byItem[i.rfq_item_id] = i);
        const sup = SUP.find(s => String(s.id) === String(supplierId));
        const html = `<div class="erp-grid">
            ${E.field('Supplier', `<div class="adm-input" style="background:#faf8f2">${E.esc(sup ? sup.supplier_name : '')}</div>`)}
            ${E.field('Supplier quote ref.', E.input('qRef', q.supplier_quote_ref || ''))}
            ${E.field('Quote date *', E.input('qDate', q.quote_date || E.today(), 'type="date"'))}
            ${E.field('Valid until', E.input('qValid', q.valid_until || '', 'type="date"'))}
            ${E.field('Delivery (days)', E.input('qDays', q.delivery_days || '', 'type="number" min="0"'))}
            ${E.field('Freight ₹ (their charge)', E.input('qFreight', q.freight_amount || 0, 'type="number" min="0" step="any"'))}
            ${E.field('Payment terms', E.input('qPay', q.payment_terms || ''))}
            ${E.field('Freight terms', E.input('qFt', q.freight_terms || ''))}
            ${E.field('Notes', E.textarea('qNotes', q.notes || ''), 'span-all')}</div>
          <div class="erp-section-title">Quoted rates (leave rate empty for items not quoted)</div>
          <div class="adm-table-wrap"><table class="erp-lines"><thead><tr><th>Item</th><th>Qty</th><th>Rate ₹</th><th>Discount ₹</th><th>GST %</th><th>Remarks</th></tr></thead><tbody>
          ${rfq.items.map(i => { const x = byItem[i.id] || {}; return `<tr data-ri="${i.id}" data-type="${i.item_type}" data-item="${i.item_id}"><td>${E.esc(i.item_name)}</td>
            <td class="w-num"><input class="adm-input" type="number" step="any" min="0" data-f="qty" value="${E.esc(x.quantity || i.quantity)}"></td>
            <td class="w-num"><input class="adm-input" type="number" step="any" min="0" data-f="rate" value="${E.esc(x.rate || '')}"></td>
            <td class="w-num"><input class="adm-input" type="number" step="any" min="0" data-f="disc" value="${E.esc(x.discount_amount || 0)}"></td>
            <td class="w-sm"><input class="adm-input" type="number" step="any" min="0" data-f="tax" value="${E.esc(x.tax_percent || 0)}"></td>
            <td><input class="adm-input" data-f="rem" value="${E.esc(x.remarks || '')}"></td></tr>`; }).join('')}</tbody></table></div>`;
        E.form((q.quote_number || 'New quotation') + ' — ' + rfq.rfq_number, html, () => {
            const items = [];
            $('.swal2-popup tr[data-ri]').each(function () { const $r = $(this); if ($r.find('[data-f=rate]').val() === '') return;
                items.push({ rfq_item_id: $r.data('ri'), item_type: $r.data('type'), item_id: $r.data('item'), quantity: $r.find('[data-f=qty]').val(), rate: $r.find('[data-f=rate]').val(),
                             discount_amount: $r.find('[data-f=disc]').val(), tax_percent: $r.find('[data-f=tax]').val(), remarks: $r.find('[data-f=rem]').val() }); });
            if (!items.length) return Promise.reject('Enter at least one rate');
            return E.post('procurement_api.php', { action: 'quo_save', id: q.id || '', rfq_id: rfq.id, supplier_id: supplierId, supplier_quote_ref: $('#qRef').val(), quote_date: $('#qDate').val(), valid_until: $('#qValid').val(),
                                                   delivery_days: $('#qDays').val(), freight_amount: $('#qFreight').val(), payment_terms: $('#qPay').val(), freight_terms: $('#qFt').val(), notes: $('#qNotes').val(), items }, { silent: true })
                .then(x => { E.toast(x.message); load(); setTimeout(() => openView(rfq.id), 300); });
        });
    };
    quoteId ? E.api('procurement_api.php', { action: 'quo_get', id: quoteId }).then(x => go(x.record)) : go(null);
}
function viewQuote(q) {
    const html = E.kv([['Supplier', E.esc(q.supplier_name)], ['RFQ', q.rfq_number ? `<a class="erp-link" href="rfqs.php?id=${q.rfq_id}">${E.esc(q.rfq_number)}</a>` : '—'], ['Their ref.', E.esc(q.supplier_quote_ref || '—')],
                       ['Date', E.date(q.quote_date)], ['Valid until', E.date(q.valid_until)], ['Delivery', q.delivery_days ? q.delivery_days + ' days' : '—'], ['Status', E.badge(q.status)], ['Payment terms', E.esc(q.payment_terms || '—')]])
        + '<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Rate</th><th class="erp-num">Discount</th><th class="erp-num">GST</th><th class="erp-num">Total</th><th>Awarded</th></tr></thead><tbody>'
        + q.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.money(i.discount_amount)}</td><td class="erp-num">${E.num(i.tax_percent)}%</td><td class="erp-num">${E.money(i.line_total)}</td><td>${i.awarded == 1 ? E.badge('awarded') : ''}</td></tr>`).join('')
        + `</tbody></table></div><div class="erp-totals"><span>Freight <strong>${E.money(q.freight_amount)}</strong></span><span>Total <strong>${E.money(q.grand_total)}</strong></span></div><div id="qDocs"></div>`;
    E.view(q.quote_number, html, [
        q.status === 'received' && { label: 'Reject', icon: 'fa-xmark', run: () => E.confirmAction('Reject ' + q.quote_number + '?', '', { reason: 'Reason', danger: true }).then(reason => E.post('procurement_api.php', { action: 'quo_reject', id: q.id, reason })).then(x => { E.toast(x.message); load(); }).catch(() => {}) },
    ], { didOpen: () => E.docs($('#qDocs'), 'quotation', q.id, 'QUOTATION') });
}

function compare(id) {
    E.api('procurement_api.php', { action: 'rfq_compare', id }).then(c => {
        const qs = c.quotations;
        const head = '<tr><th>Item</th>' + qs.map(q => `<th class="erp-num">${E.esc(q.supplier_name)}<div class="erp-muted">${E.esc(q.quote_number)}${q.expired ? ' · EXPIRED' : ''}${q.delivery_days ? ' · ' + q.delivery_days + 'd' : ''}</div></th>`).join('') + '</tr>';
        const body = c.items.map(i => {
            const best = c.best[i.id];
            return `<tr><td>${E.esc(i.item_name)}<div class="erp-muted">${E.qty(i.quantity, i.unit)}</div></td>` + qs.map(q => {
                const l = q.lines[i.id];
                if (!l) return '<td class="erp-num erp-muted">not quoted</td>';
                const isBest = best && best.quotation_id === q.id;
                return `<td class="erp-num" style="${isBest ? 'background:#eaf5ee;' : ''}"><label style="cursor:pointer"><input type="radio" name="aw_${i.id}" value="${q.id}" ${isBest ? 'checked' : ''} ${q.expired ? 'disabled' : ''}>
                    ${E.money(l.landed_unit)}</label><div class="erp-muted">rate ${E.money(l.rate)} · GST ${E.num(l.tax_percent)}%${l.discount ? ' · disc ' + E.money(l.discount) : ''}</div>${isBest ? '<div class="erp-pos erp-muted">lowest landed</div>' : ''}</td>`;
            }).join('') + '</tr>';
        }).join('');
        const foot = '<tr><td><strong>Quotation total</strong></td>' + qs.map(q => `<td class="erp-num"><strong>${E.money(q.grand_total)}</strong>${c.cheapest_complete === q.id ? '<div class="erp-pos erp-muted">cheapest complete quote</div>' : ''}</td>`).join('') + '</tr>';
        const html = `<p class="erp-note">Landed unit = (rate − discount) + share of the supplier's freight, before GST (GST is claimable). Choose one supplier per item — the lowest landed cost is pre-selected.</p>
            <div class="adm-table-wrap"><table class="adm-table"><thead>${head}</thead><tbody>${body}</tbody><tfoot>${foot}</tfoot></table></div>`;
        E.form('Compare quotations — ' + c.rfq.rfq_number, html, () => {
            const awards = c.items.map(i => ({ rfq_item_id: i.id, quotation_id: $(`input[name=aw_${i.id}]:checked`).val() })).filter(a => a.quotation_id);
            if (!awards.length) return Promise.reject('Choose a supplier for at least one item');
            return E.post('procurement_api.php', { action: 'rfq_award', id: c.rfq.id, awards }, { silent: true }).then(x => { E.toast(x.message); load(); setTimeout(() => openView(c.rfq.id), 300); });
        }, { confirmText: 'Create purchase order(s)', width: 1100 });
    }).catch(() => {});
}
// FIX (2 Oct 2026): requests that still need shop quotations (from the purchase flow); click → enter quotations
function loadNeedQ() {
    const $b = $('#needQ'); E.loading($b);
    E.api('purchase_flow_api.php', { action: 'list' }, { silent: true }).then(r => {
        const rows = (r.rows || []).filter(x => x.pr_status === 'approved' && x.quote_status !== 'approved');
        $('#needQN').text(rows.length).toggleClass('is-amber', rows.length > 0).toggleClass('is-neutral', !rows.length);
        if (!rows.length) { $b.html('<div class="adm-empty"><i class="fas fa-circle-check"></i><p><strong>No request is waiting for quotations</strong></p><p>Approved purchase requests appear here for the 3 shop quotations.</p></div>'); return; }
        const st = x => x.quote_status === 'submitted' ? ['is-wait', 'Sent — waiting for Manager / Admin approval', 'View quotations'] : x.quote_status === 'rejected' ? ['is-bad', 'Rejected — change and send again', 'Fix quotations'] : ['', 'Quotations to collect', 'Provide quotation'];
        $b.html('<div class="nq-list">' + rows.map(x => { const s = st(x), n = Math.min(3, +x.quote_count || 0);
            return `<div class="nq-row ${s[0]}" data-pr="${x.id}"><div class="nq-ref"><strong>${E.esc(x.pr_number)}</strong><span class="erp-muted">${E.date(x.request_date)}${x.required_by ? ' · needed by ' + E.date(x.required_by) : ''} · ${E.esc(x.requested_by || '')}</span></div>
                <div class="nq-items" data-items="${x.id}"><span class="erp-muted">Loading items…</span></div>
                <div class="nq-q"><b>${n} of 3</b>quotations · ${E.esc(s[1])}<div class="nq-bar"><span style="width:${Math.round(n / 3 * 100)}%"></span></div></div>
                <a class="adm-btn ${s[0] ? 'adm-btn-ghost' : 'adm-btn-primary'}" href="purchase_flow.php?pr_id=${x.id}#card-quotes"><i class="fas fa-pen-to-square"></i> ${E.esc(s[2])}</a></div>`; }).join('') + '</div>');
        rows.slice(0, 25).forEach(x => E.api('purchase_flow_api.php', { action: 'get', pr_id: x.id }, { silent: true }).then(d => {
            const it = (d.pr && d.pr.items) || [];
            $(`[data-items="${x.id}"]`).html(it.length ? it.slice(0, 4).map(i => `${E.esc(i.item_name)} <span class="erp-muted">× ${E.qty(i.quantity, i.unit)}</span>`).join('<br>') + (it.length > 4 ? `<br><span class="erp-muted">+ ${it.length - 4} more item(s)</span>` : '') : '<span class="erp-muted">No items</span>');
        }).catch(() => $(`[data-items="${x.id}"]`).html('<span class="erp-muted">—</span>')));
    }).catch(m => E.errorBox($b, m, loadNeedQ));
}
$('#needQ').on('click', '.nq-row', function (e) { if ($(e.target).closest('a').length) return; location.href = 'purchase_flow.php?pr_id=' + $(this).data('pr') + '#card-quotes'; });
loadNeedQ();
JS
);
