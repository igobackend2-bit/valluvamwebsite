<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Purchase Flow', 'Request → 3 shop quotations → approval → PO → payment proof → transport → DC & bill → unloading check → quality check');
?>
<div id="pqVer" data-v="<?= @filemtime(__DIR__ . '/assets/po_quotes.js') ?: 1 ?>" hidden></div>
<style>
.pf-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin:0 0 14px}
.pf-step{border:1px solid var(--adm-line);border-radius:var(--adm-radius-sm);padding:9px 11px;background:var(--adm-surface);cursor:pointer;border-top:4px solid var(--adm-line)}
.pf-step .n{font-size:11px;color:var(--adm-ink-soft);text-transform:uppercase;letter-spacing:.04em}.pf-step .t{font-weight:700;font-size:13.5px;color:var(--adm-ink)}.pf-step .s{font-size:12px;color:var(--adm-ink-soft);margin-top:2px}
.pf-step.is-done{border-top-color:var(--adm-green)}.pf-step.is-current{border-top-color:#2f6ea8;box-shadow:0 0 0 2px #2f6ea833}.pf-step.is-waiting{border-top-color:var(--adm-amber)}
.pf-step.is-warn{border-top-color:var(--adm-amber);background:var(--adm-amber-soft)}.pf-step.is-blocked{border-top-color:var(--adm-red)}.pf-step.is-todo{opacity:.6}
.pf-card{margin-bottom:14px}.pf-card .adm-card-head h2 .adm-badge{margin-left:8px;vertical-align:middle}
.pf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 14px}
.pf-pay{border:1px dashed var(--adm-green);border-radius:var(--adm-radius-sm);padding:10px 14px;background:var(--adm-green-soft);margin-bottom:10px}
.pf-pay b{display:inline-block;min-width:130px;color:var(--adm-ink-soft);font-weight:500}
.pf-doc{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid var(--adm-line)}.pf-doc:last-child{border-bottom:0}
.pf-doc .lbl{min-width:210px;font-weight:600}.pf-ok{color:var(--adm-green);font-weight:600}.pf-miss{color:var(--adm-red);font-weight:600}
.pf-unl td.bad{background:var(--adm-red-soft)}.pf-unl td.good{background:#e3f1e6}
.pf-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.pf-hide{display:none}
</style>
<?php if (function_exists('role_access_key') && role_access_key((string)($_SESSION['admin_role_name'] ?? '')) === 'accounts'): ?>
<style>/* FIX (2 Oct 2026): Accounts Team sees only the money steps — request, purchase order (bank details) and payment + proof */
#card-quotes,#card-transport,#card-docs,#card-unload,#card-qc,#card-proofs,[data-px],
.pf-step[data-go="quotes"],.pf-step[data-go="transport"],.pf-step[data-go="docs"],.pf-step[data-go="unload"],.pf-step[data-go="qc"]{display:none!important}</style>
<?php endif; ?>
<div id="listView">
    <section class="adm-card">
        <div class="adm-card-head"><h2>Purchases in progress</h2>
            <div class="erp-filters"><input class="adm-input" id="fQ" placeholder="PR / PO no. or supplier"><label class="erp-muted"><input type="checkbox" id="fAll"> show all requests</label></div></div>
        <div class="adm-card-body" id="list"></div>
    </section>
</div>
<div id="detailView" class="pf-hide">
    <div class="pf-actions" style="margin:0 0 12px"><button class="adm-btn adm-btn-ghost" id="backBtn"><i class="fas fa-arrow-left"></i> All purchases</button><span id="dTitle" style="font-weight:700;font-size:18px;align-self:center"></span></div>
    <div class="pf-steps" id="steps"></div>
    <div id="cards"></div>
</div>
<script src="assets/pf_extra.js?v=<?= @filemtime(__DIR__ . '/assets/pf_extra.js') ?: 1 ?>"></script><!-- photos, bill quantity, signature, quality report, all proofs (1 Oct 2026) -->
<?php erp_page_end(<<<'JS'
const E = ERP;
const POQ_LOAD = $.ajax({ url: 'assets/po_quotes.js?v=' + ($('#pqVer').data('v') || 1), dataType: 'script', cache: true }).catch(() => null);
const API = 'purchase_flow_api.php';
const DOC_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
let ITEMS = [], SUP = [], D = null, PR = 0, quoteEd = null;
Promise.all([E.items(), E.suppliers()]).then(([i, s]) => { ITEMS = i; SUP = s; if (E.param('pr_id')) open(+E.param('pr_id')); else loadList(); });
$('#backBtn').on('click', () => { history.replaceState(null, '', 'purchase_flow.php'); $('#detailView').addClass('pf-hide'); $('#listView').removeClass('pf-hide'); loadList(); });
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(loadList, 300); }); $('#fAll').on('change', loadList);

const STAGE_CLS = { 'Completed': 'green', 'Waiting for payment': 'amber', 'Quotation approval': 'amber', 'PO approval': 'amber', 'Request approval': 'neutral' };
function loadList() {
    const $l = $('#list'); E.loading($l);
    E.api(API, { action: 'list', q: $('#fQ').val(), all: $('#fAll').is(':checked') ? 1 : '' }, { silent: true }).then(r => E.table($l, [
        { label: 'Request', render: x => `<a class="erp-link" data-open="${x.id}">${E.esc(x.pr_number)}</a><div class="erp-muted">${E.date(x.request_date)} · ${E.esc(x.requested_by || '')}</div>` },
        { label: 'Stage', render: x => `<span class="adm-badge is-${STAGE_CLS[x.stage] || 'info'}">${E.esc(x.stage)}</span>` },
        { label: 'Quotations', render: x => E.esc(x.quote_count) + ' / 3' + (x.quote_status && x.quote_status !== 'collecting' ? ' · ' + E.esc(x.quote_status) : '') },
        { label: 'PO', render: x => x.po_number ? E.esc(x.po_number) + ' ' + E.badge(x.po_status) : '—' },
        { label: 'Shop', render: x => E.esc(x.supplier_name || '—') },
        { label: 'Total', num: true, render: x => (x.grand_total ? E.money(x.grand_total) : '—') },
        { label: 'Delivery', render: x => x.delivery_mode ? E.esc(x.delivery_mode) + (x.tracking_number ? ' · ' + E.esc(x.tracking_number) : '') : '—' },
    ], r.rows, { empty: 'No purchase requests in progress', emptyHint: 'Requests appear here once they are submitted.', icon: 'fa-route' })).catch(m => E.errorBox($l, m, loadList));
}
$('#list').on('click', '[data-open]', function () { open(+$(this).data('open')); });

function open(prId) {
    PR = prId; history.replaceState(null, '', 'purchase_flow.php?pr_id=' + prId);
    $('#listView').addClass('pf-hide'); $('#detailView').removeClass('pf-hide'); E.loading($('#cards'));
    E.api(API, { action: 'get', pr_id: prId }).then(r => { D = r; render(); }).catch(m => E.errorBox($('#cards'), m, () => open(prId)));
}
const reload = msg => { if (msg) E.toast(msg); open(PR); };
const fail = m => { if (m) Swal.fire({ icon: 'error', title: 'Not saved', text: String(m), customClass: { popup: 'erp-modal' }, confirmButtonColor: '#1c5034' }); };
function upload(entityType, entityId, category, file, description) {
    const ext = String(file.name).split('.').pop().toLowerCase();
    if (!DOC_EXT.includes(ext)) return Promise.reject('Allowed files: PDF, JPG, PNG, WEBP, Word, Excel, CSV.');
    if (file.size > 10 * 1048576) return Promise.reject('Files must be 10 MB or smaller.');
    const fd = new FormData(); fd.append('action', 'upload'); fd.append('entity_type', entityType); fd.append('entity_id', entityId); fd.append('category', category);
    fd.append('description', description || ''); fd.append('file', file);
    return new Promise((ok, no) => $.ajax({ url: E.BASE + 'erp_docs.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
        .done(r => (r && r.status === 'success' ? ok(r.id) : no((r && r.message) || 'Upload failed'))).fail(() => no('Upload failed')));
}
const docLink = d => (d ? `<a class="erp-link" href="${E.BASE}erp_docs.php?action=download&id=${d.id}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> ${E.esc(d.original_name)}</a> <span class="erp-muted">${E.date(d.created_at)} · ${E.esc(d.uploaded_by || '')}</span>` : '');
const card = (key, n, title, state, body) => `<section class="adm-card pf-card" id="card-${key}"><div class="adm-card-head"><h2>${n}. ${E.esc(title)}${stateBadge(state)}</h2></div><div class="adm-card-body">${body}</div></section>`;
function stateBadge(s) { const m = { done: ['green', 'Done'], current: ['info', 'To do now'], waiting: ['amber', 'Waiting'], warn: ['amber', 'Check'], blocked: ['red', 'Stopped'], todo: ['neutral', 'Later'] }[s] || ['neutral', s]; return `<span class="adm-badge is-${m[0]}">${m[1]}</span>`; }

function render() {
    const { pr, flow, po, steps, can } = D, f = flow || {};
    $('#dTitle').html(`${E.esc(pr.pr_number)} ${po ? '· ' + E.esc(po.po_number) : ''}`);
    $('#steps').html(steps.map((s, i) => `<div class="pf-step is-${s.state}" data-go="${s.key}"><div class="n">Step ${i + 1}</div><div class="t">${E.esc(s.label)}</div><div class="s">${E.esc(s.note || '')}</div></div>`).join(''));
    const st = k => steps.find(s => s.key === k).state;
    const poOk = po && ['approved', 'partially_received', 'fully_received', 'closed'].includes(po.status);
    let h = '';
    // 1. request
    h += card('request', 1, 'Purchase request', st('request'), E.kv([['Status', E.badge(pr.status)], ['Requested by', E.esc(pr.requested_by || '—')], ['Needed by', E.date(pr.required_by)], ['Warehouse', E.esc(pr.warehouse_name || '—')],
            pr.manager_approved_by ? ['Manager approved', E.esc(pr.manager_approved_by)] : null, pr.backend_approved_by ? ['Backend approved', E.esc(pr.backend_approved_by)] : null])
        + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Packs / qty</th><th>Asked as</th></tr></thead><tbody>${pr.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td>${i.input_unit ? E.qty(i.input_qty, i.input_unit) : '—'}</td></tr>`).join('')}</tbody></table></div>
           <div class="pf-actions"><a class="adm-btn adm-btn-ghost" href="purchase_requests.php?id=${pr.id}"><i class="fas fa-up-right-from-square"></i> Open request</a></div>`);
    // 2. quotations
    h += card('quotes', 2, 'Shop quotations (compare 3 shops)', st('quotes'), quotesBody());
    // 3. PO
    h += card('po', 3, 'Purchase order (with bank details) → approval', st('po'), poBody());
    // 4. payment
    h += card('payment', 4, 'Payment + payment proof', st('payment'), poOk ? payBody() : '<div class="erp-note">After the purchase order is approved it waits here for payment.</div>');
    // 5. transport
    h += card('transport', 5, 'Transport — internal vehicle or courier', st('transport'), poOk ? transportBody() : '<div class="erp-note">Available once the PO is approved.</div>');
    // 6. docs
    h += card('docs', 6, 'Loading DC + shop bill', st('docs'), poOk ? docsBody() : '<div class="erp-note">Available once the PO is approved.</div>');
    // 7. unloading
    h += card('unload', 7, 'Unloading — check the quantity', st('unload'), poOk ? unloadBody() : '<div class="erp-note">Available once the PO is approved.</div>');
    // 8. QC
    h += card('qc', 8, 'Quality check', st('qc'), qcBody());
    $('#cards').html(h);
    bindQuotes(); bindPay(); bindTransport(); bindDocs(); bindUnload(); bindQc();
}
$('#steps').on('click', '[data-go]', function () { const el = document.getElementById('card-' + $(this).data('go')); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); });

// ---------------------------------------------------------------- 2. quotations
function quotesBody() {
    const { pr, flow, can, approval, quotes } = D, f = flow || {};
    if (!['approved', 'converted'].includes(pr.status)) return '<div class="erp-note">Shop quotations are collected after the request is approved by the Manager and Backend.</div>';
    const editable = can.quotes && pr.status === 'approved' && !['submitted', 'approved'].includes(f.quote_status || 'collecting');
    let h = '';
    if (f.quote_status === 'rejected') h += `<div class="erp-warn">Rejected by ${E.esc(f.quote_decided_by || '')}${f.quote_remarks ? ': ' + E.esc(f.quote_remarks) : ''}. Change the quotations or the chosen shop and send again.</div>`;
    if (editable) {
        h += `<p class="erp-note" style="margin:0 0 8px">Enter 3 shops (or import their Excel / CSV), click <strong>Use this quotation</strong> on the shop to buy from, then send for approval. Lowest price and fastest delivery are highlighted.</p>
              <div id="qEd"></div>
              <div class="erp-grid" style="margin-top:10px">${E.field('Reason if fewer than 3 shops', E.input('qWhy', f.fewer_quotes_reason || '', 'placeholder="Only needed when you have fewer than 3 quotations"'), 'span-2')}</div>
              <div class="pf-actions"><button class="adm-btn adm-btn-ghost" id="qSave"><i class="fas fa-floppy-disk"></i> Save quotations</button><button class="adm-btn adm-btn-primary" id="qSend"><i class="fas fa-paper-plane"></i> Send chosen shop for approval</button></div>`;
        return h;
    }
    h += '<div id="qView"></div>';
    if (f.quote_status === 'submitted') {
        h += `<div class="erp-warn">Waiting for approval${approval ? ' — ' + E.esc(approval.request_number) + ' sent by ' + E.esc(approval.submitted_by || '') + ' ' + E.date(approval.submitted_at) : ''}.${approval && approval.execution_error ? '<br><strong>Last try failed:</strong> ' + E.esc(approval.execution_error) : ''}</div>`;
        h += can.approve ? '<div class="pf-actions"><a class="adm-btn adm-btn-primary" href="index.php"><i class="fas fa-gauge-high"></i> Approve / reject on your Dashboard</a></div>' : '<div class="erp-note">The Admin approves the shop from the Admin dashboard.</div>';
    }
    if (f.quote_status === 'approved') h += `<div class="erp-note">Approved by ${E.esc(f.quote_decided_by || '')} · ${E.date(f.quote_decided_at)}${f.quote_remarks ? ' — ' + E.esc(f.quote_remarks) : ''}</div>`;
    if (!quotes.length) h += '<div class="erp-note">No quotations entered.</div>';
    return h;
}
function bindQuotes() {
    const { flow, quotes, pr } = D, f = flow || {};
    POQ_LOAD.then(() => {
        if (!window.POQ) return;
        if ($('#qEd').length) {
            quoteEd = POQ.mount($('#qEd'), { poId: 0, items: ITEMS, suppliers: SUP, loader: () => Promise.resolve({ installed: true, quotes }),
                lines: () => pr.items.map(i => ({ item_type: i.item_type, item_id: i.item_id, quantity: i.quantity })), linesLabel: 'Take items from the request' });
            quoteEd.ready.then(() => { if (!quotes.length) $('#qEd [data-f=fromPo]').trigger('click'); });
        }
        if ($('#qView').length) POQ.show($('#qView'), quotes, { title: 'Quotations compared' });
    });
    const send = submit => {
        if (!quoteEd) return;
        const c = quoteEd.collect();
        return quoteEd.check().then(() => E.post(API, { action: submit ? 'quotes_submit' : 'quotes_save', pr_id: PR, selected_slot: c.selected_slot, quotes: JSON.stringify(c.quotes), fewer_reason: $('#qWhy').val() }, { silent: true }))
            .then(r => quoteEd.uploadFiles('purchase_request', PR).then(p => reload(r.message + (p.length ? ' Note: ' + p.join('; ') : ''))))
            .catch(fail);
    };
    $('#qSave').on('click', () => send(false));
    $('#qSend').on('click', () => send(true));
    $('#qApprove').on('click', () => E.confirmAction('Approve the chosen shop?', 'A purchase order is created from this quotation and sent for PO approval.', { reason: 'Remarks (optional)', optional: true })
        .then(rem => E.post('approvals_api.php', { action: 'approve', id: D.approval.id, remarks: rem || '' }, { silent: true })).then(r => reload(r.message)).catch(m => m && fail(m)));
    $('#qReject').on('click', () => E.confirmAction('Reject these quotations?', 'They go back for changes.', { danger: true, reason: 'Reason' })
        .then(rem => E.post('approvals_api.php', { action: 'reject', id: D.approval.id, remarks: rem }, { silent: true })).then(r => reload(r.message)).catch(m => m && fail(m)));
    $('#poRetry').on('click', () => E.post(API, { action: 'quotes_retry_po', pr_id: PR }, { silent: true }).then(r => reload(r.message)).catch(fail));
}

// ---------------------------------------------------------------- 3. PO
function payTo() {
    const s = D.supplier; if (!s) return '';
    const row = (k, v) => (v ? `<div><b>${k}</b> ${E.esc(v)}</div>` : '');
    const bank = row('Account holder', s.account_holder_name) + row('Bank', s.bank_name) + row('Account number', s.bank_account_number) + row('IFSC', s.bank_ifsc) + row('UPI', s.upi_id) + (s.bank_details ? row('Bank details', s.bank_details) : '');
    return `<div class="pf-pay"><div style="font-weight:700;margin-bottom:4px"><i class="fas fa-building-columns"></i> Pay to: ${E.esc(s.supplier_name)}</div>${row('Contact', [s.owner_name, s.mobile].filter(Boolean).join(' · '))}${row('GSTIN', s.gst_number)}${bank || '<div class="pf-miss">No bank / UPI details saved for this shop — add them in Suppliers before paying.</div>'}${row('Payment terms', s.payment_terms)}</div>`;
}
function poBody() {
    const { po, flow, can } = D, f = flow || {};
    if (!po) {
        if (f.quote_status === 'approved') return `<div class="erp-warn">The shop is approved but the purchase order was not created.</div>${can.approve ? '<div class="pf-actions"><button class="adm-btn adm-btn-primary" id="poRetry"><i class="fas fa-file-signature"></i> Create the purchase order now</button></div>' : ''}`;
        return '<div class="erp-note">Created automatically from the approved shop quotation (supplier, items, rates, freight and bank details).</div>';
    }
    return E.kv([['PO', `<a class="erp-link" href="purchase_orders.php?id=${po.id}">${E.esc(po.po_number)}</a>`], ['Status', E.badge(po.status)], ['Shop', E.esc(po.supplier_name)], ['Total', E.money(po.grand_total)],
                 ['Expected delivery', E.date(po.expected_delivery_date)], po.approved_by ? ['Approved by', E.esc(po.approved_by) + ' · ' + E.date(po.approved_at)] : null])
        + `<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Item</th><th class="erp-num">Qty</th><th class="erp-num">Rate</th><th class="erp-num">GST</th><th class="erp-num">Total</th></tr></thead><tbody>${po.items.map(i => `<tr><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td><td class="erp-num">${E.money(i.rate)}</td><td class="erp-num">${E.num(i.tax_percent)}%</td><td class="erp-num">${E.money(i.line_total)}</td></tr>`).join('')}</tbody></table></div>`
        + payTo()
        + `<div class="pf-actions"><a class="adm-btn adm-btn-ghost" href="purchase_orders.php?id=${po.id}"><i class="fas fa-up-right-from-square"></i> Open PO</a><button class="adm-btn adm-btn-ghost" onclick="window.open('print_erp.php?type=po&id=${po.id}','_blank')"><i class="fas fa-print"></i> Print / PDF</button>
           ${po.status === 'pending_approval' ? (can.approve ? '<a class="adm-btn adm-btn-primary" href="index.php"><i class="fas fa-gauge-high"></i> Approve PO on your Dashboard</a>' : '<span class="erp-note">Waiting for PO approval (Admin / CEO dashboard).</span>') : ''}</div>`;
}

// ---------------------------------------------------------------- 4. payment
function payBody() {
    const { po, payment, docs, flow, can } = D;
    let h = payTo();
    if (payment) {
        h += E.kv([['Payment', `<a class="erp-link" href="purchase_payments.php">${E.esc(payment.payment_number)}</a>`], ['Paid', E.money(payment.amount)], ['Date', E.date(payment.payment_date)], ['Mode', E.esc(payment.payment_mode)], ['Reference / UTR', E.esc(payment.reference_number || '—')], ['Status', E.badge(payment.status)]]);
        h += `<div class="pf-doc"><span class="lbl">Payment proof</span>${docs.payment_proof ? '<span class="pf-ok">✓</span> ' + docLink(docs.payment_proof) : '<span class="pf-miss">missing</span> <input type="file" id="payProof2" accept=".pdf,.jpg,.jpeg,.png,.webp"> <button class="adm-btn adm-btn-primary" id="payProofBtn">Attach proof</button>'}</div>`;
        if (E.num(payment.amount) + 0.005 < E.num(po.grand_total)) h += `<div class="erp-note">Balance ${E.money(E.num(po.grand_total) - E.num(payment.amount))} — record further payments in Purchase Payments.</div>`;
        return h;
    }
    if (!can.pay) return h + '<div class="erp-note">Waiting for payment by the Accounts Team.</div>';
    h += `<div class="erp-warn">Waiting for payment of ${E.money(po.grand_total)}.</div>
      <div class="pf-grid">${E.field('Amount ₹ *', E.input('pAmt', E.num(po.grand_total), 'type="number" min="0" step="any"'))}${E.field('Payment date *', E.input('pDate', E.today(), 'type="date"'))}
        ${E.field('Mode *', E.select('pMode', ['bank_transfer', 'upi', 'cheque', 'cash', 'card', 'other'].map(m => `<option value="${m}">${m.replace('_', ' ')}</option>`).join('')))}
        ${E.field('Reference / UTR / cheque no.', E.input('pRef', ''))}${E.field('Payment proof * (screenshot / receipt)', '<input type="file" class="adm-input" id="pProof" accept=".pdf,.jpg,.jpeg,.png,.webp">')}</div>
      <div class="pf-actions"><button class="adm-btn adm-btn-primary" id="payBtn"><i class="fas fa-indian-rupee-sign"></i> Mark as paid</button></div>`;
    return h;
}
function bindPay() {
    const { po, payment } = D || {};
    $('#payBtn').on('click', function () {
        const file = $('#pProof')[0].files[0];
        if (!file) return fail('Attach the payment proof (screenshot or receipt).');
        if (['bank_transfer', 'upi', 'cheque'].includes($('#pMode').val()) && !$('#pRef').val().trim()) return fail('Enter the reference / UTR / cheque number.');
        const $b = $(this).prop('disabled', true); let docId;
        upload('purchase_order', po.id, 'SUPPLIER_PAYMENT_PROOF', file, 'Payment proof')
            .then(id => { docId = id; return E.post('purchase_api.php', { action: 'pay_save', supplier_id: po.supplier_id, amount: $('#pAmt').val(), payment_date: $('#pDate').val(), payment_mode: $('#pMode').val(),
                                                                       reference_number: $('#pRef').val(), notes: 'For ' + po.po_number }, { silent: true }); })
            .then(r => r.id ? E.post(API, { action: 'pay_link', pr_id: PR, payment_id: r.id, proof_doc_id: docId }, { silent: true }).then(() => reload(r.message))
                            : E.post(API, { action: 'doc_link', pr_id: PR, slot: 'payment_proof', doc_id: docId }, { silent: true }).then(() => reload(r.message)))
            .catch(m => { $b.prop('disabled', false); fail(m); });
    });
    $('#payProofBtn').on('click', () => {
        const file = $('#payProof2')[0].files[0]; if (!file) return fail('Choose the proof file.');
        upload('purchase_order', po.id, 'SUPPLIER_PAYMENT_PROOF', file, 'Payment proof ' + (payment ? payment.payment_number : ''))
            .then(id => E.post(API, { action: 'pay_link', pr_id: PR, proof_doc_id: id }, { silent: true })).then(r => reload(r.message)).catch(fail);
    });
    $('#poApprove').on('click', () => E.confirmAction('Approve ' + po.po_number + '?', 'It then waits for payment.').then(() => E.post('purchase_api.php', { action: 'po_approve', id: po.id }, { silent: true })).then(r => reload(r.message)).catch(m => m && fail(m)));
}

// ---------------------------------------------------------------- 5. transport
function transportBody() {
    const { flow, couriers, docs, can } = D, f = flow || {};
    const mode = f.delivery_mode || 'courier';
    const cs = couriers.find(c => String(c.id) === String(f.courier_service_id));
    const trackUrl = cs && cs.tracking_url && f.tracking_number ? cs.tracking_url.replace('{n}', encodeURIComponent(f.tracking_number)) : (cs && cs.tracking_url) || '';
    let h = '';
    if (f.delivery_mode) h += E.kv([['Delivery', f.delivery_mode === 'courier' ? 'Courier' : 'Internal (own vehicle)'], f.courier_name ? ['Courier', E.esc(f.courier_name)] : null,
        f.tracking_number ? ['Tracking no.', E.esc(f.tracking_number) + (trackUrl ? ` <a class="erp-link" href="${E.esc(trackUrl)}" target="_blank" rel="noopener">track</a>` : '')] : null,
        ['Driver / delivery person', E.esc([f.driver_name, f.driver_phone].filter(Boolean).join(' · ') || '—')], f.vehicle_number ? ['Vehicle', E.esc(f.vehicle_number)] : null,
        ['Dispatched', E.date(f.dispatch_date)], f.shipment_id ? ['Transport record', `<a class="erp-link" href="shipments.php?id=${f.shipment_id}">open</a>`] : null]);
    if (!can.transport) return h || '<div class="erp-note">Not entered yet.</div>';
    h += `<div class="pf-grid" style="margin-top:8px">
        ${E.field('Delivery type *', E.select('tMode', `<option value="courier" ${mode === 'courier' ? 'selected' : ''}>Courier</option><option value="internal" ${mode === 'internal' ? 'selected' : ''}>Internal (own vehicle / our driver)</option>`))}
        <div class="adm-field t-c"><label>Courier service *</label>${E.select('tCs', '<option value="">Choose courier…</option>' + couriers.map(c => `<option value="${c.id}" ${String(c.id) === String(f.courier_service_id) ? 'selected' : ''}>${E.esc(c.name)}</option>`).join(''))}</div>
        <div class="adm-field t-c t-other"><label>Courier name *</label>${E.input('tCname', f.courier_name || '')}</div>
        <div class="adm-field t-c"><label>Tracking / AWB no. *</label>${E.input('tTrack', f.tracking_number || '')}</div>
        ${E.field('Driver / delivery person name', E.input('tDriver', f.driver_name || ''))}${E.field('Driver phone', E.input('tPhone', f.driver_phone || '', 'inputmode="numeric" maxlength="13"'))}
        <div class="adm-field t-i"><label>Vehicle number</label>${E.input('tVeh', f.vehicle_number || '', 'style="text-transform:uppercase"')}</div>
        ${E.field('Dispatch date', E.input('tDate', f.dispatch_date || E.today(), 'type="date"'))}
        <div class="adm-field t-c"><label>Courier receipt / proof ${docs.courier_proof ? '(attached)' : '*'}</label><input type="file" class="adm-input" id="tProof" accept=".pdf,.jpg,.jpeg,.png,.webp"></div></div>
      ${docs.courier_proof ? '<div class="pf-doc"><span class="lbl">Courier proof</span><span class="pf-ok">✓</span> ' + docLink(docs.courier_proof) + '</div>' : ''}
      <div class="pf-actions"><button class="adm-btn adm-btn-primary" id="tSave"><i class="fas fa-truck"></i> Save transport details</button></div>`;
    return h;
}
function bindTransport() {
    const sync = () => { const c = $('#tMode').val() === 'courier'; $('.t-c').toggle(c); $('.t-i').toggle(!c); const other = /^other/i.test($('#tCs option:selected').text()); $('.t-other').toggle(c && other); };
    $('#tMode,#tCs').on('change', sync); sync();
    $('#tSave').on('click', function () {
        const courier = $('#tMode').val() === 'courier', file = $('#tProof')[0] && $('#tProof')[0].files[0];
        if (courier && !file && !D.docs.courier_proof) return fail('Attach the courier receipt / proof.');
        const $b = $(this).prop('disabled', true);
        E.post(API, { action: 'transport_save', pr_id: PR, delivery_mode: $('#tMode').val(), courier_service_id: $('#tCs').val(), courier_name: $('#tCname').val(), tracking_number: $('#tTrack').val(),
                      driver_name: $('#tDriver').val(), driver_phone: $('#tPhone').val(), vehicle_number: $('#tVeh').val(), dispatch_date: $('#tDate').val() }, { silent: true })
            .then(r => (courier && file ? upload('purchase_order', D.po.id, 'TRANSPORT_RECEIPT', file, 'Courier proof ' + $('#tTrack').val())
                .then(id => E.post(API, { action: 'doc_link', pr_id: PR, slot: 'courier_proof', doc_id: id }, { silent: true })) : Promise.resolve()).then(() => reload(r.message)))
            .catch(m => { $b.prop('disabled', false); fail(m); });
    });
}

// ---------------------------------------------------------------- 6. documents
function docRow(slot, label, d, extra) {
    return `<div class="pf-doc"><span class="lbl">${label}</span>${d ? '<span class="pf-ok">✓</span> ' + docLink(d) : '<span class="pf-miss">missing</span>'}
      ${D.can.transport && D.can.docs ? `${extra || ''}<input type="file" data-slot="${slot}" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"><button class="adm-btn adm-btn-ghost" data-up="${slot}">${d ? 'Replace' : 'Attach'}</button>` : ''}</div>`;
}
function docsBody() {
    const { docs, flow } = D, f = flow || {};
    return docRow('loading_dc', 'Loading DC (from the shop)', docs.loading_dc)
        + docRow('shop_bill', 'Shop bill' + (f.shop_bill_type ? ` (${E.esc(f.shop_bill_type)}${f.shop_bill_number ? ' · ' + E.esc(f.shop_bill_number) : ''})` : ''), docs.shop_bill,
                 `<select class="adm-select" id="billType" style="max-width:190px"><option value="">Bill type…</option><option value="handwritten" ${f.shop_bill_type === 'handwritten' ? 'selected' : ''}>Handwritten bill</option><option value="system" ${f.shop_bill_type === 'system' ? 'selected' : ''}>System / printed bill</option></select><input class="adm-input" id="billNo" placeholder="Bill no." value="${E.esc(f.shop_bill_number || '')}" style="max-width:140px">`)
        + '<div class="erp-note">Files are kept with the purchase order (Documents) and only logged-in admins can open them.</div>';
}
function bindDocs() {
    $('#cards').off('click.pfdoc').on('click.pfdoc', '[data-up]', function () {
        const slot = $(this).data('up'), file = $(`input[data-slot="${slot}"]`)[0].files[0];
        if (!file) return fail('Choose the file first.');
        if (slot === 'shop_bill' && !$('#billType').val()) return fail('Choose handwritten or system bill.');
        const cat = { loading_dc: 'GRN', unloading_dc: 'GRN', shop_bill: 'SUPPLIER_INVOICE' }[slot];
        const desc = { loading_dc: 'Loading DC', unloading_dc: 'Unloading DC', shop_bill: 'Shop bill (' + $('#billType').val() + ')' }[slot];
        const $b = $(this).prop('disabled', true);
        upload('purchase_order', D.po.id, cat, file, desc).then(id => E.post(API, { action: 'doc_link', pr_id: PR, slot, doc_id: id, bill_type: $('#billType').val(), bill_number: $('#billNo').val() }, { silent: true }))
            .then(r => reload(r.message)).catch(m => { $b.prop('disabled', false); fail(m); });
    });
}

// ---------------------------------------------------------------- 7. unloading
function unloadBody() {
    const { po, flow, docs, grn, can } = D, f = flow || {};
    const saved = f.unload_check ? JSON.parse(f.unload_check) : null;
    const byId = {}; (saved ? saved.lines : []).forEach(l => { byId[l.po_item_id] = l; });
    let h = docRow('unloading_dc', 'Unloading DC (signed at our warehouse)', docs.unloading_dc);
    const locked = !!grn;
    h += `<div class="adm-table-wrap" style="margin-top:8px"><table class="adm-table pf-unl"><thead><tr><th>Item</th><th class="erp-num">Ordered</th><th class="erp-num">Received</th><th class="erp-num">Damaged</th><th>Check</th></tr></thead><tbody>
      ${po.items.map(i => { const s = byId[i.id], rec = s ? s.received : E.num(i.quantity) - E.num(i.received_qty), dmg = s ? s.damaged : 0;
          return `<tr data-pi="${i.id}" data-ord="${E.num(i.quantity)}"><td>${E.esc(i.item_name)}</td><td class="erp-num">${E.qty(i.quantity, i.unit)}</td>
            <td class="w-num">${locked ? E.qty(rec) : `<input class="adm-input" type="number" min="0" step="any" data-f="rec" value="${rec}">`}</td>
            <td class="w-num">${locked ? E.qty(dmg) : `<input class="adm-input" type="number" min="0" step="any" data-f="dmg" value="${dmg}">`}</td><td data-f="chk"></td></tr>`; }).join('')}</tbody></table></div>`;
    h += E.field('What was short / extra / damaged', locked ? `<div>${E.esc((saved && saved.remarks) || '—')}</div>` : E.input('uRem', (saved && saved.remarks) || ''));
    if (saved) h += `<div class="erp-note">Checked by ${E.esc(f.unload_checked_by || '')} · ${E.date(f.unload_checked_at)}</div>`;
    if (grn) h += `<div class="pf-actions"><a class="adm-btn adm-btn-ghost" href="goods_receipts.php?id=${grn.id}"><i class="fas fa-dolly"></i> Goods receipt ${E.esc(grn.grn_number)} (${E.esc(grn.status)})</a></div>`;
    else if (can.receive) h += `<div class="pf-actions"><button class="adm-btn adm-btn-primary" id="uSave"><i class="fas fa-clipboard-check"></i> Save check & create goods receipt</button></div><div class="erp-note">The goods receipt is created as a draft — stock is added only after the quality check and posting.</div>`;
    return h;
}
function bindUnload() {
    const mark = () => $('.pf-unl tr[data-pi]').each(function () {
        const $r = $(this), ord = E.num($r.data('ord'));
        const rec = $r.find('[data-f=rec]').length ? E.num($r.find('[data-f=rec]').val()) : E.num($r.find('td').eq(2).text());
        const dmg = $r.find('[data-f=dmg]').length ? E.num($r.find('[data-f=dmg]').val()) : E.num($r.find('td').eq(3).text());
        const ok = Math.abs(rec - ord) < 0.0005 && dmg === 0;
        $r.find('[data-f=chk]').html(ok ? '<span class="pf-ok">✓ correct</span>' : `<span class="pf-miss">${rec < ord ? 'short ' + E.qty(ord - rec) : rec > ord ? 'extra ' + E.qty(rec - ord) : ''}${dmg > 0 ? ' damaged ' + E.qty(dmg) : ''}</span>`)
            .removeClass('good bad').addClass(ok ? 'good' : 'bad');
    });
    $('.pf-unl').on('input', 'input', mark); mark();
    $('#uSave').on('click', function () {
        if (!D.docs.unloading_dc) return fail('Attach the unloading DC first.');
        const lines = $('.pf-unl tr[data-pi]').map(function () { const $r = $(this); return { po_item_id: $r.data('pi'), received_qty: $r.find('[data-f=rec]').val(), damaged_qty: $r.find('[data-f=dmg]').val() }; }).get();
        const $b = $(this).prop('disabled', true);
        E.post(API, { action: 'unload_save', pr_id: PR, lines: JSON.stringify(lines), remarks: $('#uRem').val() }, { silent: true })
            .then(() => E.post('purchase_api.php', { action: 'grn_save', po_id: D.po.id, received_date: E.today(), warehouse_id: D.po.warehouse_id, vehicle_number: (D.flow || {}).vehicle_number || '',
                    supplier_challan_no: (D.flow || {}).shop_bill_number || '', notes: 'From purchase flow ' + D.pr.pr_number + ($('#uRem').val() ? ' — ' + $('#uRem').val() : ''),
                    items: JSON.stringify(lines.filter(l => E.num(l.received_qty) > 0).map(l => ({ po_item_id: l.po_item_id, received_qty: l.received_qty, rejected_qty: l.damaged_qty || 0, rejection_reason: E.num(l.damaged_qty) > 0 ? 'Damaged at unloading' : '' }))) }, { silent: true }))
            .then(g => E.post(API, { action: 'grn_link', pr_id: PR, grn_id: g.id }, { silent: true }).then(() => reload(g.message)))
            .catch(m => { $b.prop('disabled', false); fail(m); });
    });
}

// ---------------------------------------------------------------- 8. QC
function qcBody() {
    const { grn, qc, can } = D;
    if (!grn) return '<div class="erp-note">Starts after the unloading check creates the goods receipt.</div>';
    if (!qc) return `<div class="erp-note">Check the quality of the received goods before they go into stock.</div>${can.qc ? '<div class="pf-actions"><button class="adm-btn adm-btn-primary" id="qcStart"><i class="fas fa-microscope"></i> Start quality check</button></div>' : ''}`;
    const done = ['passed', 'partially_passed', 'rejected'].includes(qc.status);
    return E.kv([['Quality check', `<a class="erp-link" href="quality_checks.php?id=${qc.id}">${E.esc(qc.qc_number)}</a>`], ['Status', E.badge(qc.status)], qc.completed_at ? ['Completed', E.date(qc.completed_at)] : null, ['Goods receipt', E.esc(grn.grn_number) + ' · ' + E.esc(grn.status)]])
        + `<div class="pf-actions"><a class="adm-btn ${done ? 'adm-btn-ghost' : 'adm-btn-primary'}" href="quality_checks.php?id=${qc.id}"><i class="fas fa-microscope"></i> ${done ? 'View' : 'Do'} the quality check</a>
           ${done && grn.status === 'draft' ? `<a class="adm-btn adm-btn-primary" href="goods_receipts.php?id=${grn.id}"><i class="fas fa-boxes-stacked"></i> Post goods receipt (add to stock)</a>` : ''}</div>`
        + (done && grn.status === 'posted' ? '<div class="erp-note pf-ok" style="font-size:14px">✓ Purchase completed — goods are in stock. Record the shop bill in Purchase Invoices for accounts.</div>' : '');
}
function bindQc() {
    $('#qcStart').on('click', () => E.post('procurement_api.php', { action: 'qc_create', grn_id: D.grn.id }, { silent: true })
        .then(r => E.post(API, { action: 'qc_link', pr_id: PR, qc_id: r.id }, { silent: true }).then(() => reload(r.message))).catch(fail));
}
JS
);
