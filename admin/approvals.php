<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Approvals', 'One inbox for every approval — purchase requests & orders, amendments, bills, payments, returns, stock counts, expenses, journals');
?>
<div class="erp-tabs" id="tabs">
    <button class="erp-tab active" data-tab="open">Waiting for a decision</button>
    <button class="erp-tab" data-tab="history">History</button>
    <button class="erp-tab" data-tab="rules">Approval rules</button>
</div>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card" id="listCard">
    <div class="adm-card-head"><h2 id="title">Waiting for a decision</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fModule"><option value="">All types</option></select>
            <select class="adm-select" id="fStatus" style="display:none"><option value="">All decisions</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="cancelled">Withdrawn</option></select>
            <input type="date" class="adm-input" id="fFrom" title="From"><input type="date" class="adm-input" id="fTo" title="To">
            <input class="adm-input" id="fQ" placeholder="Number, reference, person">
        </div></div>
    <div class="adm-card-body"><div id="list"></div><div id="pager"></div></div>
</section>
<section class="adm-card" id="rulesCard" style="display:none">
    <div class="adm-card-head"><h2>Approval rules</h2><button class="adm-btn adm-btn-primary" id="saveRules"><i class="fas fa-floppy-disk"></i> Save rules</button></div>
    <div class="adm-card-body"><div id="rules"></div>
        <p class="erp-note">Always-on steps (purchase requests / orders, amendments, stock adjustments, stock counts, manual journals) need an approver every time.
        The other rules are optional: switch one on and set the amount from which approval is needed. People holding the approver permission post directly (their approval is recorded);
        everyone else's request waits here with the exact figures they entered.</p></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let TAB = 'open', PAGE = 1, MODULES = [];
E.tabs($('#tabs'), t => { TAB = t; PAGE = 1; show(); });
$('#fModule,#fStatus,#fFrom,#fTo').on('change', () => { PAGE = 1; load(); });
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(() => { PAGE = 1; load(); }, 300); });
if (E.param('module')) setTimeout(() => $('#fModule').val(E.param('module')), 0);
function show() {
    $('#rulesCard').toggle(TAB === 'rules'); $('#listCard,#kpis').toggle(TAB !== 'rules'); $('#fStatus').toggle(TAB === 'history');
    $('#title').text(TAB === 'open' ? 'Waiting for a decision' : 'Decision history');
    TAB === 'rules' ? loadRules() : load();
}
function load() {
    const $l = $('#list'); E.loading($l);
    E.api('approvals_api.php', { action: 'list', status: TAB === 'open' ? 'open' : ($('#fStatus').val() || ''), module: $('#fModule').val() || E.param('module') || '', date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val(), page: PAGE, per_page: 50 }, { silent: true }).then(r => {
        if (!MODULES.length) { MODULES = r.modules; $('#fModule').append(r.modules.map(m => `<option value="${m.module}">${E.esc(m.label)}</option>`).join('')); if (E.param('module')) $('#fModule').val(E.param('module')); }
        $('#kpis').html(r.open_by_module.map(m => E.kpi((MODULES.find(x => x.module === m.module) || {}).label || m.module, m.n, 'is-amber')).join('') || E.kpi('Waiting for approval', 0, 'is-green'));
        let rows = TAB === 'history' ? r.rows.filter(x => !['submitted', 'under_review'].includes(x.status)) : r.rows;
        E.table($l, [
            { label: 'Request', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.request_number)}</a>` },
            { label: 'Type', render: x => E.esc(x.label || x.module) },
            { label: 'Document', render: x => x.link ? `<a class="erp-link" href="${E.esc(x.link)}">${E.esc(x.reference || 'open')}</a>` : E.esc(x.reference || '—') },
            { label: 'What', render: x => E.esc(x.summary || '') },
            { label: 'Amount', num: true, render: x => x.amount === null ? '—' : E.money(x.amount) },
            { label: 'Submitted', render: x => `${E.esc(x.submitted_by || '')}<div class="erp-muted">${E.date(x.submitted_at)}</div>` },
            { label: 'Status', render: x => E.badge(x.status) + (x.execution_error ? `<div class="erp-neg erp-muted">${E.esc(x.execution_error)}</div>` : '') + (x.decided_by ? `<div class="erp-muted">${E.esc(x.decided_by)} · ${E.date(x.decided_at)}</div>` : '') },
            { label: '', render: x => x.can_decide ? `<button class="adm-btn adm-btn-primary" data-ok="${x.id}"><i class="fas fa-check"></i> Approve</button> <button class="adm-btn adm-btn-ghost" data-no="${x.id}">Reject</button>` : '' },
        ], rows, { empty: TAB === 'open' ? 'Nothing is waiting for approval' : 'No decisions yet', icon: 'fa-circle-check' });
        E.pager($('#pager'), r.total, PAGE, 50, p => { PAGE = p; load(); });
    }).catch(m => E.errorBox($l, m, load));
}
function decide(id, approve) {
    E.confirmAction(approve ? 'Approve this request?' : 'Reject this request?', approve ? 'It is posted now, exactly as it was submitted.' : 'The requester sees your reason.', { reason: approve ? 'Remarks (optional)' : 'Reason for rejecting', optional: approve, danger: !approve })
        .then(rem => E.post('approvals_api.php', { action: approve ? 'approve' : 'reject', id, remarks: rem }))
        .then(r => { E.toast(r.message); load(); }).catch(() => load());
}
$('#list').on('click', '[data-ok]', function () { decide($(this).data('ok'), true); });
$('#list').on('click', '[data-no]', function () { decide($(this).data('no'), false); });
$('#list').on('click', '[data-view]', function () { view($(this).data('view')); });
function reqTable(req) {
    const keys = Object.keys(req).filter(k => !['action', 'id'].includes(k));
    return '<table class="adm-table"><tbody>' + keys.map(k => {
        let v = req[k];
        if (Array.isArray(v)) v = '<table class="erp-lines"><thead><tr>' + Object.keys(v[0] || {}).map(h => `<th>${E.esc(h.replace(/_/g, ' '))}</th>`).join('') + '</tr></thead><tbody>' +
            v.map(o => '<tr>' + Object.keys(v[0] || {}).map(h => `<td>${E.esc(typeof o[h] === 'object' ? JSON.stringify(o[h]) : o[h])}</td>`).join('') + '</tr>').join('') + '</tbody></table>';
        else if (k === 'proof_doc_id' && +v) v = `<a class="erp-link" href="../assets/db_query/admin/erp_docs.php?action=download&id=${+v}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> View proof</a>`;   // FIX (3 Oct 2026): see the proof before approving
        else v = E.esc(v);
        return `<tr><td class="erp-muted" style="width:170px">${E.esc(k === 'proof_doc_id' ? 'proof' : k.replace(/_/g, ' '))}</td><td>${v}</td></tr>`;
    }).join('') + '</tbody></table>';
}
function view(id) {
    E.api('approvals_api.php', { action: 'get', id }).then(r => {
        const x = r.record;
        const html = E.kv([['Type', E.esc(x.label || x.module)], ['Document', x.link ? `<a class="erp-link" href="${E.esc(x.link)}">${E.esc(x.reference || 'open')}</a>` : E.esc(x.reference || '—')],
                           ['Amount', x.amount === null ? '—' : E.money(x.amount)], ['Status', E.badge(x.status)], ['Submitted by', E.esc(x.submitted_by || '') + ' · ' + E.date(x.submitted_at)],
                           x.decided_by ? ['Decided by', E.esc(x.decided_by) + ' · ' + E.date(x.decided_at)] : null, x.remarks ? ['Remarks', E.esc(x.remarks)] : null])
            + `<p>${E.esc(x.summary || '')}</p>` + (x.execution_error ? `<div class="erp-warn">Posting did not complete: ${E.esc(x.execution_error)}</div>` : '')
            + (Object.keys(x.request || {}).length > 2 ? '<div class="erp-section-title">Request as submitted</div>' + reqTable(x.request) : '')
            + '<div class="erp-section-title">History</div>' + (x.actions.map(a => `<div class="erp-muted">${E.date(a.created_at)} · <strong>${E.esc(a.action)}</strong> · ${E.esc(a.by_user || '')}${a.remarks ? ' — ' + E.esc(a.remarks) : ''}</div>`).join('') || '—');
        E.view(x.request_number, html, [
            x.can_decide && { label: 'Approve', cls: 'adm-btn-primary', icon: 'fa-check', run: () => decide(x.id, true) },
            x.can_decide && { label: 'Reject', icon: 'fa-xmark', run: () => decide(x.id, false) },
            x.can_decide && x.status === 'submitted' && { label: 'Take for review', icon: 'fa-eye', run: () => E.post('approvals_api.php', { action: 'review', id: x.id }).then(m => { E.toast(m.message); load(); view(x.id); }) },
            ['submitted', 'under_review'].includes(x.status) && { label: 'Withdraw', icon: 'fa-rotate-left', run: () => E.confirmAction('Withdraw this request?', '', { reason: 'Reason (optional)', optional: true }).then(rem => E.post('approvals_api.php', { action: 'cancel', id: x.id, remarks: rem })).then(m => { E.toast(m.message); load(); }).catch(() => {}) },
        ]);
    }).catch(() => {});
}
function loadRules() {
    E.api('approvals_api.php', { action: 'policies' }).then(r => {
        E.table($('#rules'), [
            { label: 'Step', render: x => E.esc(x.label) },
            { label: 'Approval', render: x => x.inherent == 1 ? E.badge('active', 'Always') : `<label><input type="checkbox" data-mod="${x.module}" data-f="en" ${x.enabled == 1 ? 'checked' : ''} ${r.can_manage ? '' : 'disabled'}> Required</label>` },
            { label: 'From amount ₹', render: x => x.inherent == 1 ? '—' : `<input class="adm-input" type="number" min="0" step="any" data-mod="${x.module}" data-f="min" value="${E.num(x.min_amount)}" style="width:130px" ${r.can_manage ? '' : 'disabled'}>` },
            { label: 'Approver permission', render: x => `<code>${E.esc(x.approver_perm)}</code>` },
            { label: 'Changed', render: x => x.updated_by ? E.esc(x.updated_by) + ' · ' + E.date(x.updated_at) : '—' },
        ], r.rows);
        $('#saveRules').toggle(!!r.can_manage);
    });
}
$('#saveRules').on('click', () => {
    const rules = [];
    $('#rules [data-f=en]').each(function () { const m = $(this).data('mod'); rules.push({ module: m, enabled: this.checked ? 1 : 0, min_amount: $(`#rules [data-f=min][data-mod="${m}"]`).val() }); });
    E.post('approvals_api.php', { action: 'policies_save', rules }).then(r => { E.toast(r.message); loadRules(); });
});
show();
JS
);
