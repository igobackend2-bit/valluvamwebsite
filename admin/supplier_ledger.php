<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Supplier Ledger', 'Purchases, payments, returns and outstanding payable per supplier',
    '<button class="adm-btn adm-btn-ghost" id="csvBtn"><i class="fas fa-file-csv"></i> Export CSV</button>');
?>
<div id="detail"></div>
<section class="adm-card" id="summaryCard">
    <div class="adm-card-head"><h2>All suppliers</h2><input type="text" class="adm-input" id="fQ" placeholder="Search supplier"></div>
    <div class="adm-card-body" id="list"></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ROWS = [];
const COLS = [
    { label: 'Supplier', render: x => `<a class="erp-link" href="?id=${x.id}">${E.esc(x.supplier_name)}</a>${x.company_name ? '<div class="erp-muted">' + E.esc(x.company_name) + '</div>' : ''}`, csv: x => x.supplier_name },
    { label: 'GST', key: 'gst_number', render: x => E.esc(x.gst_number || '—') },
    { label: 'Last purchase', key: 'last_purchase', render: x => E.date(x.last_purchase) },
    { label: 'Invoiced', key: 'total_invoiced', num: true, render: x => E.money(x.total_invoiced) },
    { label: 'Paid', key: 'total_paid', num: true, render: x => E.money(x.total_paid) },
    { label: 'Returns / credit', key: 'returns_credit', num: true, render: x => E.money(x.returns_credit) },
    { label: 'Received, not billed', key: 'received_not_invoiced', num: true, render: x => E.money(x.received_not_invoiced) },
    { label: 'Outstanding', key: 'outstanding', num: true, render: x => `<strong class="${x.outstanding > 0 ? 'erp-neg' : ''}">${E.money(x.outstanding)}</strong>` },
    { label: 'Overdue bills', key: 'overdue_invoices', num: true, render: x => x.overdue_invoices ? `<span class="adm-badge is-red">${x.overdue_invoices}</span>` : '0' },
];
function load() {
    const $l = $('#list'); E.loading($l);
    E.api('erp_reports.php', { report: 'supplier_summary' }, { silent: true }).then(r => { ROWS = r.rows; render(); }).catch(m => E.errorBox($l, m, load));
}
function render() {
    const q = ($('#fQ').val() || '').toLowerCase();
    const rows = ROWS.filter(x => !q || (x.supplier_name + ' ' + (x.company_name || '')).toLowerCase().includes(q));
    const tot = k => rows.reduce((a, x) => a + E.num(x[k]), 0);
    E.table($('#list'), COLS, rows, { empty: 'No suppliers yet', emptyHint: 'Add suppliers in Suppliers.',
        footer: `<td colspan="3"><strong>Total</strong></td><td class="erp-num">${E.money(tot('total_invoiced'))}</td><td class="erp-num">${E.money(tot('total_paid'))}</td><td class="erp-num">${E.money(tot('returns_credit'))}</td>
                 <td class="erp-num">${E.money(tot('received_not_invoiced'))}</td><td class="erp-num"><strong>${E.money(tot('outstanding'))}</strong></td><td></td>` });
}
$('#fQ').on('input', render);
$('#csvBtn').on('click', () => E.csv(ROWS, COLS.map(c => Object.assign({}, c, { csv: c.csv || (x => x[c.key]) })), 'supplier_outstanding.csv'));

function detail(id) {
    const $d = $('#detail').html('<section class="adm-card"><div class="adm-card-body" id="dBody"></div></section>');
    E.loading($('#dBody'));
    E.api('erp_reports.php', { report: 'supplier_ledger', supplier_id: id }).then(r => {
        const s = r.supplier, t = r.totals;
        const kpi = (label, v, cls) => `<div class="adm-stat ${cls || 'is-neutral'}"><h3>${v}</h3><p>${label}</p></div>`;
        const led = r.entries.map(e => `<tr><td>${E.date(e.date)}</td><td>${E.esc(e.type)}</td><td><a class="erp-link" href="${e.link[0]}?id=${e.link[1]}">${E.esc(e.ref)}</a> ${e.ref2 ? '<span class="erp-muted">' + E.esc(e.ref2) + '</span>' : ''}</td>
            <td class="erp-num">${e.credit ? E.money(e.credit) : ''}</td><td class="erp-num">${e.debit ? E.money(e.debit) : ''}</td><td class="erp-num"><strong>${E.money(e.balance)}</strong></td></tr>`).join('');
        const ph = r.price_history.slice(0, 50).map(p => `<tr><td>${E.date(p.received_date)}</td><td>${E.esc(p.item_name)}</td><td class="erp-num">${E.qty(p.accepted_qty, p.unit)}</td><td class="erp-num">${E.money(p.rate)}</td>
            <td class="erp-num">${E.money(p.landed_unit, true)}</td><td><a class="erp-link" href="goods_receipts.php?id=${p.grn_id}">${E.esc(p.grn_number)}</a></td><td>${E.esc(p.invoice_no || '—')}</td><td>${E.esc(p.batch_number || '—')}</td></tr>`).join('');
        $('#dBody').html(`<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;"><div><h2 style="margin:0 0 4px;font-size:20px;">${E.esc(s.supplier_name)}</h2>
              <div class="erp-muted">${E.esc([s.company_name, s.mobile, s.email, s.gst_number ? 'GST ' + s.gst_number : '', s.payment_terms].filter(Boolean).join(' · '))}</div>
              ${s.address ? '<div class="erp-muted">' + E.esc(s.address) + '</div>' : ''}${[s.owner_name && 'Owner: ' + E.esc(s.owner_name), s.account_holder_name && 'Account holder: ' + E.esc(s.account_holder_name), s.bank_name && 'Bank: ' + E.esc(s.bank_name), s.bank_account_number && 'A/c: ' + E.esc(s.bank_account_number), s.bank_ifsc && 'IFSC: ' + E.esc(s.bank_ifsc), s.upi_id && 'UPI: ' + E.esc(s.upi_id), s.bank_details && E.esc(s.bank_details)].filter(Boolean).map(x => '<div class="erp-muted">' + x + '</div>').join('')}</div>
              <div class="erp-top-actions"><a class="adm-btn adm-btn-primary" href="purchase_payments.php"><i class="fas fa-money-bill-wave"></i> Record payment</a><a class="adm-btn adm-btn-ghost" href="supplier_ledger.php">All suppliers</a></div></div>
            <div class="erp-kpis" style="margin-top:16px;">${kpi('Total purchases (billed)', E.money(t.total_invoiced))}${kpi('Total paid', E.money(t.total_paid), 'is-green')}${kpi('Returns / credit notes', E.money(t.returns_credit))}
              ${kpi('Received, not yet billed', E.money(t.received_not_invoiced), 'is-amber')}${kpi('Current payable', E.money(t.outstanding), t.outstanding > 0 ? 'is-amber' : 'is-green')}</div>
            <div class="erp-section-title">Ledger</div>${led ? `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Date</th><th>Entry</th><th>Reference</th><th class="erp-num">Bill (payable +)</th><th class="erp-num">Paid / credit (−)</th><th class="erp-num">Balance</th></tr></thead><tbody>${led}</tbody></table></div>` : '<span class="erp-muted">No bills or payments yet.</span>'}
            <div class="erp-section-title">Purchase orders &amp; receipts</div>
            <div class="erp-chain">${r.purchase_orders.map(o => `<a href="purchase_orders.php?id=${o.id}">${E.esc(o.po_number)} · ${E.esc(o.status.replace(/_/g, ' '))}</a>`).join('') || '<span class="erp-muted">No purchase orders.</span>'}</div>
            <div class="erp-chain">${r.grns.map(g => `<a href="goods_receipts.php?id=${g.id}">${E.esc(g.grn_number)} · ${E.date(g.received_date)}</a>`).join('') || '<span class="erp-muted">No goods receipts.</span>'}</div>
            <div class="erp-section-title">Price history</div>${ph ? `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Date</th><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Rate</th><th class="erp-num">Landed</th><th>GRN</th><th>Bill</th><th>Batch</th></tr></thead><tbody>${ph}</tbody></table></div>` : '<span class="erp-muted">No purchases yet.</span>'}
            <div class="erp-section-title">Documents (all records of this supplier)</div>
            ${r.documents.map(d => `<div class="erp-docs-row"><i class="fas fa-file"></i><a class="erp-link" target="_blank" href="../assets/db_query/admin/erp_docs.php?action=download&id=${d.id}">${E.esc(d.original_name)}</a>${E.badge('none', d.doc_type.replace(/_/g, ' '))}<span class="erp-muted">${E.esc(d.entity_type.replace(/_/g, ' '))} · ${E.date(d.created_at)}</span></div>`).join('') || '<span class="erp-muted">No documents.</span>'}
            <div id="supDocs"></div>`);
        E.docs($('#supDocs'), 'supplier', s.id);
    }).catch(m => E.errorBox($('#dBody'), m));
}
load();
if (E.param('id')) detail(E.param('id'));
JS
);
