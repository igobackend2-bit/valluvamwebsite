<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Dashboard', 'What needs attention in purchasing, receiving and payables', '');
?>
<div class="erp-kpis" id="kpis"></div>
<div id="warn"></div>
<section class="adm-card"><div class="adm-card-head"><h2>Purchase orders waiting</h2><a class="adm-btn adm-btn-ghost" href="purchase_orders.php?new=1"><i class="fas fa-plus"></i> New PO</a></div><div class="adm-card-body" id="pos"></div></section>
<section class="adm-card" style="margin-top:18px;"><div class="adm-card-head"><h2>Goods received but not billed</h2></div><div class="adm-card-body" id="unbilled"></div></section>
<section class="adm-card" style="margin-top:18px;"><div class="adm-card-head"><h2>Unpaid vendor bills</h2></div><div class="adm-card-body" id="bills"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
const kpi = (href, icon, label, v, cls) => `<a href="${href}"><div class="adm-stat ${cls}"><div class="adm-stat-icon"><i class="fas ${icon}"></i></div><h3>${v}</h3><p>${label}</p></div></a>`;
Promise.all([E.api('erp_reports.php', { report: 'pending' }, { silent: true }), E.api('erp_reports.php', { report: 'supplier_summary' }, { silent: true }),
             E.api('erp_reports.php', { report: 'purchases', date_from: E.monthStart(), date_to: E.today(), group_by: 'month' }, { silent: true })])
.then(([p, s, pu]) => {
    const payable = s.rows.reduce((a, x) => a + E.num(x.outstanding), 0);
    const overdue = s.rows.reduce((a, x) => a + E.num(x.overdue_invoices), 0);
    $('#kpis').html(kpi('purchase_history.php', 'fa-cart-flatbed', 'Purchased this month (received)', E.money(pu.total_landed), 'is-primary') +
        kpi('supplier_ledger.php', 'fa-scale-unbalanced', 'Total payable to suppliers', E.money(payable), 'is-amber') +
        kpi('purchase_invoices.php?status=overdue', 'fa-triangle-exclamation', 'Overdue bills', overdue, overdue ? 'is-amber' : 'is-green') +
        kpi('purchase_orders.php', 'fa-file-signature', 'Open / pending POs', p.purchase_orders.length, 'is-neutral') +
        kpi('goods_receipts.php?status=draft', 'fa-dolly', 'Draft GRNs (not posted)', p.draft_grns.length, p.draft_grns.length ? 'is-amber' : 'is-neutral') +
        kpi('purchase_requests.php', 'fa-clipboard-list', 'Requests to act on', p.pending_requests.length, 'is-neutral') +
        kpi('stock_adjustments.php', 'fa-scale-balanced', 'Adjustments to approve', p.pending_adjustments, p.pending_adjustments ? 'is-amber' : 'is-neutral'));
    E.table($('#pos'), [
        { label: 'PO', render: x => `<a class="erp-link" href="purchase_orders.php?id=${x.id}">${E.esc(x.po_number)}</a>` },
        { label: 'Supplier', render: x => E.esc(x.supplier_name) }, { label: 'Date', render: x => E.date(x.po_date) },
        { label: 'Expected', render: x => { const late = x.expected_delivery_date && x.expected_delivery_date < E.today() && ['approved', 'partially_received'].includes(x.status); return (late ? '<span class="erp-neg">' : '') + E.date(x.expected_delivery_date) + (late ? ' (late)</span>' : ''); } },
        { label: 'Pending qty', num: true, render: x => E.qty(x.pending_qty) }, { label: 'Value', num: true, render: x => E.money(x.grand_total) }, { label: 'Status', render: x => E.badge(x.status) },
    ], p.purchase_orders, { empty: 'Nothing waiting', icon: 'fa-circle-check' });
    E.table($('#unbilled'), [
        { label: 'GRN', render: x => `<a class="erp-link" href="goods_receipts.php?id=${x.id}">${E.esc(x.grn_number)}</a>` }, { label: 'Supplier', render: x => E.esc(x.supplier_name) },
        { label: 'Received', render: x => E.date(x.received_date) }, { label: '', render: x => `<a class="adm-btn adm-btn-ghost" href="purchase_invoices.php?grn_id=${x.id}">Enter bill</a>` },
    ], p.unbilled_grns, { empty: 'Every receipt has a bill', icon: 'fa-circle-check' });
}).catch(m => E.errorBox($('#kpis'), m));
E.api('purchase_api.php', { action: 'pinv_list' }, { silent: true }).then(r => E.table($('#bills'), [
    { label: 'Bill', render: x => `<a class="erp-link" href="purchase_invoices.php?id=${x.id}">${E.esc(x.supplier_invoice_no)}</a>` }, { label: 'Supplier', render: x => E.esc(x.supplier_name) },
    { label: 'Due', render: x => E.date(x.due_date) }, { label: 'Balance', num: true, render: x => `<strong>${E.money(x.balance)}</strong>` }, { label: 'Status', render: x => E.badge(x.payment_status) },
], r.rows.filter(x => x.status === 'posted' && x.balance > 0).sort((a, b) => String(a.due_date || '9').localeCompare(String(b.due_date || '9'))), { empty: 'No unpaid bills', icon: 'fa-circle-check' }))
 .catch(m => E.errorBox($('#bills'), m));
JS
);
