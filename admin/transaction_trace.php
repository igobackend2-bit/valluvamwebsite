<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Transaction Trace', 'Follow a sale back to its supplier: sale → stock movement → batch → GRN → PO → supplier → bill → transport → landed cost → COGS → gross profit');
?>
<div class="erp-filters" style="margin-bottom:12px"><input class="adm-input" id="fQ" placeholder="Invoice / order / sale number, customer or product" style="min-width:360px"><button class="adm-btn adm-btn-primary" id="goBtn"><i class="fas fa-magnifying-glass"></i> Find</button></div>
<section class="adm-card" id="hits" style="display:none"><div class="adm-card-body" id="hitList"></div></section>
<div id="out"></div>
<?php erp_page_end(<<<'JS'
const E = ERP;
$('#goBtn').on('click', find); $('#fQ').on('keydown', e => { if (e.key === 'Enter') find(); });
const LABEL = { invoice: 'Sales invoice', website_order: 'Website order', manual_sale: 'Manual sale', credit_sale: 'Credit sale', sales_order: 'Sales order' };
function find() {
    E.api('sales_ext_api.php', { action: 'trace_search', q: $('#fQ').val() }).then(r => {
        $('#hits').show(); $('#out').html('');
        $('#hitList').html((r.documents.length ? '<div class="erp-section-title">Sales</div>' + r.documents.map(d => `<div class="erp-docs-row"><a class="erp-link" data-t="${d.type}" data-id="${d.id}">${E.esc(LABEL[d.type])} ${E.esc(d.number)}</a> <span class="erp-muted">${E.date(d.date)} · ${E.esc(d.customer || '')}</span></div>`).join('') : '')
            + (r.products.length ? '<div class="erp-section-title">Products</div>' + r.products.map(p => `<div class="erp-docs-row"><a class="erp-link" data-p="${p.id}">${E.esc(p.product_name)} ${E.esc(p.pack || '')}</a> <span class="erp-muted">stock ${E.qty(p.stock)}</span></div>`).join('') : '')
            || '<div class="adm-empty"><p>Nothing found</p></div>');
    }).catch(() => {});
}
$('#hitList').on('click', '[data-t]', function () { trace($(this).data('t'), $(this).data('id')); });
$('#hitList').on('click', '[data-p]', function () { product($(this).data('p')); });
function step(icon, title, body) { return `<div class="adm-card" style="margin-bottom:10px"><div class="adm-card-body"><div style="display:flex;gap:12px;align-items:flex-start"><i class="fas ${icon}" style="font-size:18px;color:#1c5034;margin-top:3px;width:22px"></i><div style="flex:1"><strong>${title}</strong>${body}</div></div></div></div>`; }
function trace(type, id) {
    $('#hits').hide(); E.loading($('#out'));
    history.replaceState(null, '', `transaction_trace.php?type=${type}&id=${id}`);
    E.api('sales_ext_api.php', { action: 'trace', type, id }).then(r => {
        const d = r.document, t = r.totals;
        let h = `<div class="erp-kpis">${E.kpi('Revenue (ex GST)', E.money(t.revenue), 'is-primary')}${E.kpi('COGS' + (t.cogs_estimated ? ' (part estimated)' : ''), E.money(t.cogs))}${E.kpi('Gross profit', E.money(t.gross_profit), t.gross_profit < 0 ? 'is-amber' : 'is-green')}
            ${E.kpi('Margin', t.margin_pct === null ? '—' : t.margin_pct + '%')}${E.kpi('Collected', E.money(d.paid))}${E.kpi('Outstanding', E.money(t.outstanding), t.outstanding > 0.005 ? 'is-amber' : '')}</div>`;
        h += step('fa-receipt', `${E.esc(LABEL[type])} ${E.esc(d.number)}`, E.kv([['Date', E.date(d.date)], ['Customer', E.esc(d.customer || '—') + ' ' + E.esc(d.mobile || '')], ['Channel', E.esc(d.channel)], ['Total incl. GST', E.money(d.total)], ['GST', E.money(d.tax)], ['Status', E.badge(String(d.status).split(' ')[0], d.status)]])
            + (r.receipts.length ? '<div class="erp-muted">Payments: ' + r.receipts.map(p => `${E.date(p.date)} ${E.money(p.amount)} (${E.esc(p.payment_mode)}) ${E.esc(p.transaction_id)}`).join(' · ') + '</div>' : '')
            + (r.journals.length ? '<div class="erp-muted">Journals: ' + r.journals.map(j => `<a class="erp-link" href="journals.php?id=${j.id}">${E.esc(j.journal_number)}</a> (${E.esc(j.event)})`).join(', ') + '</div>' : ''));
        r.lines.forEach(l => {
            let b = E.kv([['Qty', E.qty(l.quantity)], ['Selling rate', E.money(l.rate)], ['Discount', E.money(l.discount)], ['Revenue', E.money(l.revenue)], ['COGS', E.money(l.cogs)], ['Gross profit', '<strong>' + E.money(l.gross_profit) + '</strong>' + (l.margin_pct !== null ? ' (' + l.margin_pct + '%)' : '')]]);
            if (l.undeducted_qty) b += `<div class="erp-warn">${E.qty(l.undeducted_qty)} billed but not deducted from stock — COGS ${E.money(l.cogs_estimated)} estimated at the current average cost.</div>`;
            l.movements.forEach(m => {
                b += `<div style="border-left:3px solid #d8e7dc;padding:6px 0 6px 12px;margin:8px 0"><div><i class="fas fa-arrow-right-from-bracket"></i> Stock left <strong>${E.esc(m.warehouse || '')}</strong> · ${E.date(m.date)} · ${E.esc(m.reference)} · ${E.qty(m.quantity)} @ avg ${E.money(m.unit_cost)} = <strong>${E.money(m.cogs)}</strong> COGS</div>`;
                if (!m.batches.length) b += '<div class="erp-muted" style="margin-left:18px">No batch recorded for this stock (opening stock or goods received without a batch).</div>';
                m.batches.forEach(bt => {
                    const o = bt.origin;
                    b += `<div style="margin:6px 0 0 18px"><i class="fas fa-barcode"></i> Batch <a class="erp-link" href="batches.php?id=${bt.batch_id}">${E.esc(bt.batch_number || '#' + bt.batch_id)}</a> · ${E.qty(bt.quantity)} · mfg ${E.date(bt.manufacturing_date)} · expiry ${E.date(bt.expiry_date)}</div>`;
                    if (o && o.grn_number) b += `<div style="margin:4px 0 0 36px" class="erp-muted">
                        <div><i class="fas fa-dolly"></i> GRN <a class="erp-link" href="goods_receipts.php?id=${o.grn_id}">${E.esc(o.grn_number)}</a> ${E.date(o.received_date)} · received ${E.qty(o.received_qty)}, accepted ${E.qty(o.accepted_qty)}, rejected ${E.qty(o.rejected_qty)}${o.qc ? ` · QC <a class="erp-link" href="quality_checks.php?id=${o.qc.id}">${E.esc(o.qc.qc_number)}</a> ${E.esc(o.qc.status)} by ${E.esc(o.qc.inspected_by || '')}` : ''}</div>
                        ${o.po_id ? `<div><i class="fas fa-file-signature"></i> PO <a class="erp-link" href="purchase_orders.php?id=${o.po_id}">${E.esc(o.po_number)}</a> ${E.date(o.po_date)} · approved by ${E.esc(o.po_approved_by || '—')}</div>` : ''}
                        <div><i class="fas fa-truck-field"></i> Supplier <a class="erp-link" href="supplier_360.php?id=${o.supplier_id}">${E.esc(o.supplier_name)}</a></div>
                        ${o.pinv_id ? `<div><i class="fas fa-file-invoice-dollar"></i> Bill <a class="erp-link" href="purchase_invoices.php?id=${o.pinv_id}">${E.esc(o.supplier_invoice_no)}</a> · purchase rate ${E.money(o.purchase_rate)} + charges → landed ${E.money(o.landed_unit_cost)}/unit</div>` : `<div><i class="fas fa-file-invoice-dollar"></i> Purchase rate ${E.money(o.purchase_rate)} (no bill yet)</div>`}
                        ${o.payments.length ? '<div><i class="fas fa-money-bill-wave"></i> Paid to supplier: ' + o.payments.map(p => `${E.esc(p.payment_number)} ${E.date(p.payment_date)} ${E.money(p.amount)}`).join(', ') + '</div>' : ''}
                        ${o.shipments.length ? '<div><i class="fas fa-truck"></i> Transport: ' + o.shipments.map(s => `<a class="erp-link" href="shipments.php?id=${s.id}">${E.esc(s.shipment_number)}</a> ${E.esc(s.transport_company || s.transporter_name || '')} ${E.esc(s.vehicle_number || '')} ${s.lr_number ? 'LR ' + E.esc(s.lr_number) : ''} ${E.money(s.total_cost)}`).join(', ') + ` · ${E.money(o.transport_per_unit)}/unit in stock cost</div>` : ''}</div>`;
                    else if (o && o.repack_number) b += `<div style="margin:4px 0 0 36px" class="erp-muted"><i class="fas fa-box"></i> Repacked in <a class="erp-link" href="repacking.php?id=${o.repack_id}">${E.esc(o.repack_number)}</a> from ${E.esc(o.raw_material)} (${E.date(o.repack_date)})</div>`;
                });
                b += '</div>';
            });
            h += step('fa-box-open', E.esc(l.product_name), b);
        });
        h += step('fa-paperclip', 'Documents across the chain', r.documents.length ? r.documents.map(x => `<div class="erp-docs-row"><a class="erp-link" href="${E.BASE}erp_docs.php?action=download&id=${x.id}" target="_blank" rel="noopener">${E.esc(x.original_name)}</a> ${E.badge('none', x.category)} <span class="erp-muted">on ${E.esc(x.entity_label)} · ${E.date(x.created_at)} · ${E.esc(x.uploaded_by || '')}</span></div>`).join('') : '<div class="erp-muted">No documents uploaded on these records.</div>');
        h += step('fa-clipboard-list', 'Who did what (audit log)', r.audit.length ? r.audit.map(a => `<div class="erp-muted">${E.date(a.created_at)} · ${E.esc(a.username || '')} · ${E.esc(a.action)} · ${E.esc(a.module)} #${E.esc(a.record_id)}</div>`).join('') : '<div class="erp-muted">No audit entries.</div>');
        $('#out').html(h);
    }).catch(m => E.errorBox($('#out'), m));
}
function product(pid) {
    $('#hits').hide(); E.loading($('#out'));
    E.api('erp_reports.php', { report: 'item_trace', item_type: 'product', item_id: pid }).then(r => {
        const c = r.costing || {}, p = r.profitability_all_time || {};
        $('#out').html(`<div class="erp-kpis">${E.kpi('Stock now', E.qty(c.closing_qty))}${E.kpi('Average cost', E.money(c.avg_cost))}${E.kpi('Stock value', E.money(c.closing_value), 'is-primary')}${E.kpi('Sold (all time)', E.qty(p.qty_sold || 0))}${E.kpi('Gross profit (all time)', E.money(p.gross_profit || 0))}</div>`
            + step('fa-cart-flatbed', 'Purchases (who supplied it, rate, landed cost)', '<div id="tp"></div>') + step('fa-barcode', 'Batches', '<div id="tb"></div>') + step('fa-arrow-right-arrow-left', 'Stock ledger', '<div id="tl"></div>'));
        E.table($('#tp'), [{ label: 'Date', render: x => E.date(x.received_date) }, { label: 'GRN', render: x => `<a class="erp-link" href="goods_receipts.php?id=${x.grn_id}">${E.esc(x.grn_number)}</a>` }, { label: 'Supplier', render: x => E.esc(x.supplier_name) },
            { label: 'Qty', num: true, render: x => E.qty(x.accepted_qty) }, { label: 'Rate', num: true, render: x => E.money(x.rate) }, { label: 'Landed', num: true, render: x => x.landed_unit ? E.money(x.landed_unit) : '—' }, { label: 'Bill', render: x => E.esc(x.invoice_no || '') }],
            (r.purchases || []).filter(x => x.item_type === 'product' && x.item_id == pid), { empty: 'No goods receipts' });
        E.table($('#tb'), [{ label: 'Batch', render: b => `<a class="erp-link" href="batches.php?id=${b.id}">${E.esc(b.batch_number || '#' + b.id)}</a>` }, { label: 'Received', render: b => E.date(b.received_date) }, { label: 'Expiry', render: b => E.date(b.expiry_date) }, { label: 'Received qty', num: true, render: b => E.qty(b.qty_received) }, { label: 'Left', num: true, render: b => E.qty(b.qty_available_est) }], r.batches, { empty: 'No batches' });
        E.table($('#tl'), [{ label: 'When', render: m => E.date(m.created_at) }, { label: 'Type', render: m => E.esc(m.movement_type) }, { label: 'Reference', render: m => E.esc((m.reference_type || '') + ' ' + (m.reference_number || '')) }, { label: 'Change', num: true, render: m => E.qty(m.quantity) }, { label: 'Stock after', num: true, render: m => E.qty(m.new_stock) }, { label: 'By', render: m => E.esc(m.created_by || '') }], r.ledger, { empty: 'No movements' });
    }).catch(m => E.errorBox($('#out'), m));
}
if (E.param('type') && E.param('id')) trace(E.param('type'), E.param('id'));
else if (E.param('product_id')) product(E.param('product_id'));
JS
);
