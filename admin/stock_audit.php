<?php
// Monthly physical stock audit on top of the existing stock count (added 2 Oct 2026)
require_once __DIR__ . '/includes/erp_page.php';
require_once __DIR__ . '/includes/stock_flow_common.php';
erp_page_start('Monthly Stock Audit', 'Plan → count (quantity + weight + machine weight) → digital checks + proof → verification (approval) → closed. Differences are calculated, never typed.',
    '<button class="adm-btn adm-btn-primary" id="newBtn" style="display:none"><i class="fas fa-plus"></i> Plan audit</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="list">Audits</button><button class="erp-tab" data-tab="report">Audit report</button></div>
<section class="adm-card" id="secList"><div class="adm-card-head"><h2>Audits</h2></div><div class="adm-card-body" id="list"></div></section>
<section class="adm-card" id="secRep" style="display:none"><div class="adm-card-head"><h2>Audit report</h2>
    <div class="erp-filters"><input type="month" class="adm-input" id="rMonth"><select class="adm-select" id="rWh"><option value="">All locations</option></select><button class="adm-btn adm-btn-ghost" id="rCsv"><i class="fas fa-file-csv"></i> CSV</button></div></div>
    <div class="sf-kpis" id="rKpis" style="padding:12px 16px 0"></div><div class="adm-card-body" id="rep"></div></section>
<?php erp_page_end(sf_common_js() . <<<'JS'
const E = ERP;
const FLOW = ['planned', 'in_progress', 'count_completed', 'verification_pending', 'approved', 'closed'];
const CHECKS = [['chk_physical_count', 'Physical count completed'], ['chk_weight', 'Weight checked'], ['chk_batch', 'Batch checked'], ['chk_expiry', 'Expiry checked'], ['chk_damage', 'Damage checked'], ['chk_location', 'Location checked']];
let REP = [];
$('#rMonth').val(E.today().slice(0, 7));
SF.meta().then(() => { if (SF.can('stockflow.audit')) $('#newBtn').show(); $('#rWh').append(SF.whOptions('', false)); load(); if (E.param('id')) openView(E.param('id')); }).catch(m => E.errorBox($('#list'), m));
E.tabs($('#tabs'), t => { $('#secList').toggle(t === 'list'); $('#secRep').toggle(t === 'report'); if (t === 'report') report(); });
$('#rMonth,#rWh').on('change', report);
const done = (id, m) => { E.toast(m || 'Saved'); load(); openView(id); };
const fail = m => { if (m !== 'cancelled') E.alertError(m); };
function load() {
    E.loading($('#list'));
    E.api(SF.API, { action: 'aud_list' }, { silent: true }).then(r => E.table($('#list'), [
        { label: 'Audit', render: x => `<a class="erp-link" data-view="${x.id}">${E.esc(x.audit_number)}</a>` }, { label: 'Month', render: x => E.esc(x.audit_month) }, { label: 'Location', render: x => E.esc(x.warehouse_name) },
        { label: 'Auditor', render: x => E.esc(x.auditor_name) + ' <span class="erp-muted">' + E.esc(x.auditor_employee_id) + '</span>' }, { label: 'Date', render: x => E.date(x.audit_date) },
        { label: 'Time', render: x => (x.start_time || '').slice(0, 5) + (x.end_time ? ' – ' + x.end_time.slice(0, 5) : '') }, { label: 'Items', num: true, render: x => x.lines_total }, { label: 'With difference', num: true, render: x => x.lines_diff },
        { label: 'Proofs', num: true, render: x => x.proofs }, { label: 'Stock count', render: x => E.esc(x.count_number || '') + ' ' + E.badge(x.count_status) }, { label: 'Status', render: x => SF.badge(x.status) },
    ], r.rows, { empty: 'No audits yet', emptyHint: 'Plan the monthly audit for each location.', icon: 'fa-clipboard-check' })).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-view]', function () { openView($(this).data('view')); });
$('#newBtn').on('click', () => E.form('Plan monthly audit', `<div class="erp-grid">${E.field('Location *', E.select('aW', SF.whOptions(1)))}${E.field('Month *', E.input('aM', E.today().slice(0, 7), 'type="month"'))}
    ${E.field('Audit date *', E.input('aD', E.today(), 'type="date"'))}${E.field('Auditor name *', E.input('aN', ''))}${E.field('Auditor employee ID *', E.input('aE', ''))}
    ${E.field('Only category (optional)', E.input('aC', ''))}${E.field('Include items with zero system stock', '<label><input type="checkbox" id="aZ"> yes</label>')}${E.field('Remarks', E.input('aR', ''), 'span-all')}</div>
    <p class="sf-note">System quantities are frozen now (existing stock count). Count, weigh, confirm digitally and attach proof; differences post only after verification (approval).</p>`,
    () => E.post('warehouse_api.php', { action: 'cnt_create', warehouse_id: SF.v('aW'), count_date: SF.v('aD'), category: SF.v('aC'), include_zero: SF.chk('aZ'), notes: 'Monthly audit ' + SF.v('aM') }, { silent: true })
        .then(c => E.post(SF.API, { action: 'aud_plan', stock_count_id: c.id, audit_month: SF.v('aM'), auditor_name: SF.v('aN'), auditor_employee_id: SF.v('aE'), audit_date: SF.v('aD'), remarks: SF.v('aR') }, { silent: true }))
        .then(x => { E.toast(x.message); load(); setTimeout(() => openView(x.id), 300); }), { width: 900, confirmText: 'Plan' }));

function openView(id) {
    E.api(SF.API, { action: 'aud_get', id }).then(r => {
        const a = r.record, st = a.status, edit = st === 'in_progress' && SF.can('stockflow.audit') && ['draft', 'rejected'].includes(a.count_status);
        let html = SF.steps(FLOW, st) + E.kv([['Location', E.esc(a.warehouse_name)], ['Month', E.esc(a.audit_month)], ['Auditor', E.esc(a.auditor_name + ' (' + a.auditor_employee_id + ')')], ['Date', E.date(a.audit_date)],
            ['Start', (a.start_time || '—').slice(0, 5)], ['End', (a.end_time || '—').slice(0, 5)], ['Stock count', `<a class="erp-link" href="stock_counts.php?id=${a.stock_count_id}">${E.esc(a.count_number)}</a> ${E.badge(a.count_status)}`], ['Status', SF.badge(st)],
            a.confirmed_by ? ['Digitally confirmed', E.esc(a.confirmed_by) + ' · ' + E.date(a.confirmed_at) + ' ' + String(a.confirmed_at).slice(11, 16)] : null, a.closed_by ? ['Closed', E.esc(a.closed_by) + ' · ' + E.date(a.closed_at)] : null]);
        html += `<div class="adm-table-wrap"><table class="erp-lines sf-lines"><thead><tr><th>Item</th><th>Batch</th><th>Expiry</th><th>System qty</th><th>Physical qty</th><th>Qty diff</th><th>Diff %</th><th>System wt</th><th>Physical wt</th><th>Machine wt</th><th>Wt diff</th><th>Damage qty</th><th>Reason / remarks</th></tr></thead><tbody>` +
            a.lines.map(l => `<tr data-l="${l.id}" data-ci="${l.count_item_id}" data-sys="${l.system_qty}" data-uw="${l.unit_weight_kg ?? ''}"><td>${E.esc(l.item_name)}</td>
                <td>${edit ? `<input class="adm-input" data-f="b" value="${E.esc(l.batch_number || '')}">` : E.esc(l.batch_number || '—')}</td><td>${edit ? `<input class="adm-input" type="date" data-f="e" value="${l.expiry_date || ''}">` : E.date(l.expiry_date)}</td>
                <td class="erp-num">${E.qty(l.system_qty)}</td><td>${edit ? `<input class="adm-input" type="number" min="0" step="any" data-f="q" value="${l.counted_qty ?? ''}">` : (l.physical_qty === null ? '—' : E.qty(l.physical_qty))}</td>
                <td class="erp-num sf-calc" data-f="qd">${SF.diff(l.qty_diff)}</td><td class="erp-num sf-calc">${SF.pct(l.diff_pct)}</td><td class="erp-num">${SF.kg(l.system_weight)}</td>
                <td>${edit ? `<input class="adm-input" type="number" min="0" step="any" data-f="pw" value="${l.physical_weight ?? ''}">` : SF.kg(l.physical_weight)}</td><td>${edit ? `<input class="adm-input" type="number" min="0" step="any" data-f="mw" value="${l.machine_weight ?? ''}">` : SF.kg(l.machine_weight)}</td>
                <td class="erp-num sf-calc">${SF.diff(l.weight_diff)}</td><td>${edit ? `<input class="adm-input" type="number" min="0" step="any" data-f="dq" value="${+l.damage_qty || ''}">` : E.qty(l.damage_qty)}</td>
                <td>${edit ? `<input class="adm-input" data-f="r" value="${E.esc(l.count_reason || l.remarks || '')}">` : E.esc(l.count_reason || l.remarks || '')}</td></tr>`).join('') + '</tbody></table></div>';
        html += `<div class="sf-sec"><h3><i class="fas fa-square-check"></i> Digital checking</h3><div class="erp-grid">${CHECKS.map(c => `<label><input type="checkbox" id="${c[0]}" ${+a[c[0]] ? 'checked' : ''} ${edit ? '' : 'disabled'}> ${c[1]}</label>`).join('')}</div>` +
            (edit ? `<div class="erp-grid">${E.field('End time', E.input('aEnd', (a.end_time || SF.now()).slice(0, 5), 'type="time"'))}${E.field('Remarks', E.input('aRem', a.confirm_remarks || ''))}</div>
                <label><input type="checkbox" id="aConfirm"> I confirm this audit was physically counted and checked by me (digital confirmation, recorded with my login and time)</label>` : (a.confirm_remarks ? `<p>${E.esc(a.confirm_remarks)}</p>` : '')) +
            '<p class="sf-note">Attach weight machine photos, physical stock / rack photos, the counting sheet and the signed document below (uploaded by / date / time / type are kept).</p></div>' + signoffHtml(a) + '<div id="vDocs"></div>';
        const lines = () => $('.swal2-popup tr[data-l]').map(function () { const $r = $(this); return { id: $r.data('l'), physical_weight: $r.find('[data-f=pw]').val(), machine_weight: $r.find('[data-f=mw]').val(), damage_qty: $r.find('[data-f=dq]').val(), batch_number: $r.find('[data-f=b]').val(), expiry_date: $r.find('[data-f=e]').val(), remarks: $r.find('[data-f=r]').val() }; }).get();
        const counts = () => $('.swal2-popup tr[data-l]').map(function () { const $r = $(this); return { id: $r.data('ci'), counted_qty: $r.find('[data-f=q]').val(), reason: $r.find('[data-f=r]').val() }; }).get();
        const save = complete => () => E.post('warehouse_api.php', { action: 'cnt_save', id: a.stock_count_id, items: counts() }, { silent: true })
            .then(() => E.post(SF.API, Object.assign({ action: 'aud_save', id: a.id, complete: complete ? 1 : 0, confirm: SF.chk('aConfirm'), end_time: SF.v('aEnd'), confirm_remarks: SF.v('aRem'), lines: lines() },
                Object.fromEntries(CHECKS.map(c => [c[0], SF.chk(c[0])]))), { silent: true })).then(x => done(a.id, x.message)).catch(fail);
        const acts = [];
        if (st === 'planned' && SF.can('stockflow.audit')) acts.push({ label: 'Start audit', cls: 'adm-btn-primary', icon: 'fa-play', run: () => E.post(SF.API, { action: 'aud_start', id: a.id, start_time: SF.now() }, { silent: true }).then(x => done(a.id, x.message)).catch(fail) });
        if (edit) acts.push({ label: 'Save', icon: 'fa-floppy-disk', run: save(false) }, { label: 'Count completed + confirm', cls: 'adm-btn-primary', icon: 'fa-signature', run: save(true) });
        if (st === 'count_completed' && SF.can('stockflow.audit')) acts.push({ label: 'Submit for verification', cls: 'adm-btn-primary', icon: 'fa-paper-plane', run: () => E.post('warehouse_api.php', { action: 'cnt_submit', id: a.stock_count_id, items: [] }, { silent: true }).then(x => done(a.id, x.message)).catch(fail) });
        if (st === 'verification_pending') acts.push({ label: 'Open in Approvals', icon: 'fa-stamp', run: () => location.href = 'approvals.php?module=stock_count' });
        if (st === 'approved' && SF.can('stockflow.audit_approve')) acts.push({ label: 'Close audit', cls: 'adm-btn-primary', icon: 'fa-lock', run: () => E.post(SF.API, { action: 'aud_close', id: a.id }, { silent: true }).then(x => done(a.id, x.message)).catch(fail) });
        acts.push({ label: 'Report', icon: 'fa-table', run: () => { Swal.close(); $('#tabs [data-tab=report]').trigger('click'); report(a.id); } });
        if (['planned', 'in_progress', 'count_completed'].includes(st) && SF.can('stockflow.audit')) acts.push({ label: 'Cancel', icon: 'fa-ban', run: () => E.confirmAction('Cancel ' + a.audit_number + '?', '', { danger: true, reason: 'Reason' }).then(reason => E.post(SF.API, { action: 'aud_cancel', id: a.id, reason }, { silent: true })).then(x => done(a.id, x.message)).catch(fail) });
        E.view(a.audit_number, html, acts, { width: 1300, didOpen: p => {
            E.docs($('#vDocs'), 'stock_audit', a.id, 'COUNTING_SHEET');
            bindSignoff(p, a);   // FIX (2 Oct 2026)
            $(p).on('input', '[data-f=q]', function () { const $r = $(this).closest('tr'); $r.find('[data-f=qd]').html(this.value === '' ? '—' : SF.diff(E.num(this.value) - E.num($r.data('sys')))); });
        } });
    }).catch(() => {});
}
// FIX (2 Oct 2026): monthly audit — (external) auditor quality check, report, name and digital signature
const QCR = { satisfactory: 'Satisfactory', needs_improvement: 'Needs improvement', unsatisfactory: 'Unsatisfactory' };
function signoffHtml(a) {
    const s = a.signoff, canSign = a.signoff_installed && (SF.can('stockflow.audit') || SF.can('stockflow.audit_approve')) && ['count_completed', 'verification_pending', 'approved'].includes(a.status);
    const dl = id => `${E.BASE}erp_docs.php?action=download&id=${id}`;
    let h = '<div class="sf-sec"><h3><i class="fas fa-user-check"></i> Auditor quality check, report & signature</h3>';
    if (!a.signoff_installed) return h + '<p class="sf-note">Run sf_audit_signoff_migration.sql once in HeidiSQL to switch on the auditor sign-off.</p></div>';
    if (s) h += E.kv([['Auditor', E.esc(s.auditor_name) + (s.auditor_designation ? ' · ' + E.esc(s.auditor_designation) : '')], ['Type', s.auditor_type === 'external' ? 'External auditor' : 'Internal'], ['Firm / organisation', E.esc(s.auditor_org || '—')],
            ['Phone', E.esc(s.auditor_phone || '—')], ['Quality result', SF.badge(s.qc_result)], ['Signed', E.date(s.signed_at) + ' ' + String(s.signed_at).slice(11, 16) + ' · entered by ' + E.esc(s.entered_by)]])
        + `<div class="erp-grid" style="font-size:13.5px;margin-top:10px"><div><strong>Quality check findings</strong><p style="white-space:pre-wrap">${E.esc(s.qc_findings)}</p></div>${s.stock_findings ? `<div><strong>Stock audit findings</strong><p style="white-space:pre-wrap">${E.esc(s.stock_findings)}</p></div>` : ''}${s.recommendations ? `<div><strong>Recommendations</strong><p style="white-space:pre-wrap">${E.esc(s.recommendations)}</p></div>` : ''}</div>`
        + `<div class="erp-grid" style="font-size:13.5px"><div><strong>Signature</strong><br><img src="${dl(s.signature_doc_id)}" alt="signature" style="max-width:300px;max-height:120px;border:1px solid var(--adm-line);border-radius:8px;background:#fff"></div>${s.report_doc_id ? `<div><strong>Report file</strong><br><a class="erp-link" target="_blank" rel="noopener" href="${dl(s.report_doc_id)}"><i class="fas fa-paperclip"></i> ${E.esc(s.report_name || 'Report')}</a></div>` : ''}</div>`;
    else h += `<p class="sf-note">${canSign ? 'Once a month an external auditor checks the stock and the quality. Enter the auditor details and findings, attach the report and let the auditor sign below.' : 'Not signed yet — the auditor signs after the count is completed.'}</p>`;
    if (canSign) h += `<details ${s ? '' : 'open'}><summary style="cursor:pointer;font-weight:600;margin:8px 0">${s ? 'Update the sign-off' : 'Auditor sign-off'}</summary><div class="erp-grid">
        ${E.field('Auditor type *', E.select('soType', `<option value="external" ${!s || s.auditor_type === 'external' ? 'selected' : ''}>External auditor</option><option value="internal" ${s && s.auditor_type === 'internal' ? 'selected' : ''}>Internal</option>`))}
        ${E.field('Auditor name *', E.input('soName', s ? s.auditor_name : a.auditor_name))}${E.field('Firm / organisation *', E.input('soOrg', s ? s.auditor_org || '' : ''))}
        ${E.field('Designation', E.input('soDes', s ? s.auditor_designation || '' : ''))}${E.field('Phone', E.input('soPh', s ? s.auditor_phone || '' : '', 'inputmode="numeric"'))}
        ${E.field('Quality result *', E.select('soRes', '<option value="">Choose…</option>' + Object.entries(QCR).map(([k, v]) => `<option value="${k}" ${s && s.qc_result === k ? 'selected' : ''}>${v}</option>`).join('')))}
        ${E.field('Quality check findings *', `<textarea class="adm-input" id="soQc" rows="3" placeholder="Colour, smell, moisture, insects, expiry, packing, storage, hygiene…">${E.esc(s ? s.qc_findings : '')}</textarea>`, 'span-all')}
        ${E.field('Stock audit findings', `<textarea class="adm-input" id="soSt" rows="2" placeholder="Shortages, excess, damage, wrong location…">${E.esc(s ? s.stock_findings || '' : '')}</textarea>`, 'span-all')}
        ${E.field('Recommendations', `<textarea class="adm-input" id="soRec" rows="2">${E.esc(s ? s.recommendations || '' : '')}</textarea>`, 'span-all')}
        ${E.field('Report file (PDF / photo)', '<input type="file" class="adm-input" id="soFile" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">')}</div>
        <div><strong>Auditor signature *</strong><canvas id="soSig" width="840" height="260" style="display:block;width:100%;max-width:520px;height:160px;border:1px dashed var(--adm-line);border-radius:8px;background:#fff;touch-action:none;margin:6px 0"></canvas>
        <button type="button" class="adm-btn adm-btn-ghost" id="soClear"><i class="fas fa-eraser"></i> Clear</button> <button type="button" class="adm-btn adm-btn-primary" id="soSave"><i class="fas fa-signature"></i> Save signed report</button></div></details>`;
    return h + '</div>';
}
function bindSignoff(p, a) {
    const cv = $(p).find('#soSig')[0]; if (!cv) return;
    const ctx = cv.getContext('2d'); let drawing = false, drawn = false;
    ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.strokeStyle = '#1f1d1a';
    const pos = e => { const r = cv.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return [(t.clientX - r.left) * cv.width / r.width, (t.clientY - r.top) * cv.height / r.height]; };
    const start = e => { drawing = true; const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); e.preventDefault(); };
    const move = e => { if (!drawing) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); drawn = true; e.preventDefault(); };
    const end = () => { drawing = false; };
    cv.addEventListener('mousedown', start); cv.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    cv.addEventListener('touchstart', start, { passive: false }); cv.addEventListener('touchmove', move, { passive: false }); cv.addEventListener('touchend', end);
    $(p).on('click', '#soClear', () => { ctx.clearRect(0, 0, cv.width, cv.height); drawn = false; });
    const up = (blob, name, cat, desc) => { const fd = new FormData(); fd.append('action', 'upload'); fd.append('entity_type', 'stock_audit'); fd.append('entity_id', a.id); fd.append('category', cat); fd.append('description', desc); fd.append('file', blob, name);
        return new Promise((ok, no) => $.ajax({ url: E.BASE + 'erp_docs.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' }).done(r => r && r.status === 'success' ? ok(r.id) : no((r && r.message) || 'Upload failed')).fail(() => no('Upload failed'))); };
    $(p).on('click', '#soSave', function () {
        if (!SF.v('soName')) return fail('Enter the auditor name.');
        if (SF.v('soType') === 'external' && !SF.v('soOrg')) return fail('Enter the auditor firm / organisation.');
        if (!SF.v('soRes')) return fail('Choose the quality result.');
        if (!String($('#soQc').val() || '').trim()) return fail('Enter the quality check findings.');
        if (!drawn) return fail('The auditor must sign in the box.');
        const $b = $(this).prop('disabled', true), file = $('#soFile')[0].files[0];
        if (file && file.size > 10 * 1048576) { $b.prop('disabled', false); return fail('Files must be 10 MB or smaller.'); }
        let sigId;
        new Promise(ok => cv.toBlob(ok, 'image/png')).then(b => up(b, 'auditor-signature.png', 'SIGNED_DOCUMENT', 'Auditor signature — ' + SF.v('soName')))
            .then(id => { sigId = id; return file ? up(file, file.name, 'QC_DOCUMENT', 'Audit / quality report — ' + SF.v('soName')) : null; })
            .then(repId => E.post(SF.API, { action: 'aud_signoff', id: a.id, auditor_type: SF.v('soType'), auditor_name: SF.v('soName'), auditor_org: SF.v('soOrg'), auditor_designation: SF.v('soDes'), auditor_phone: SF.v('soPh'),
                qc_result: SF.v('soRes'), qc_findings: $('#soQc').val(), stock_findings: $('#soSt').val(), recommendations: $('#soRec').val(), signature_doc_id: sigId, report_doc_id: repId || '' }, { silent: true }))
            .then(x => done(a.id, x.message)).catch(m => { $b.prop('disabled', false); fail(m); });
    });
}
function report(auditId) {
    E.loading($('#rep'));
    E.api(SF.API, { action: 'aud_report', month: auditId ? '' : $('#rMonth').val(), warehouse_id: $('#rWh').val(), audit_id: auditId || '' }, { silent: true }).then(r => {
        REP = r.rows; const s = r.summary;
        $('#rKpis').html([['Matched', s.matched, 'is-green'], ['Shortage', s.shortage, 'is-red'], ['Excess', s.excess, 'is-amber'], ['Damage found', s.damage_found, 'is-red'], ['Pending verification', s.pending_verification, 'is-amber']].map(k => E.kpi(k[0], k[1], k[2])).join(''));
        E.table($('#rep'), [{ label: 'Audit', render: x => `<a class="erp-link" href="stock_audit.php?id=${x.audit_id}">${E.esc(x.audit_number)}</a>` }, { label: 'Location', render: x => E.esc(x.location) }, { label: 'Product', render: x => E.esc(x.item_name) },
            { label: 'Batch', render: x => E.esc(x.batch_number || '—') }, { label: 'System qty', num: true, render: x => E.qty(x.system_qty) }, { label: 'Physical qty', num: true, render: x => x.physical_qty === null ? '—' : E.qty(x.physical_qty) },
            { label: 'Difference', num: true, render: x => SF.diff(x.qty_diff) }, { label: 'System wt', num: true, render: x => SF.kg(x.system_weight) }, { label: 'Physical wt', num: true, render: x => SF.kg(x.physical_weight) },
            { label: 'Weight diff', num: true, render: x => SF.diff(x.weight_diff) }, { label: 'Auditor', render: x => E.esc(x.auditor_name) }, { label: 'Audit date', render: x => E.date(x.audit_date) },
            { label: 'Line', render: x => SF.badge(x.line_status) }, { label: 'Status', render: x => SF.badge(x.status_label) }, { label: 'Proof', num: true, render: x => x.proofs }, { label: 'Remarks', render: x => E.esc(x.remarks || '') }],
            r.rows, { empty: 'No audit lines for this selection', icon: 'fa-table' });
    }).catch(m => E.errorBox($('#rep'), m, report));
}
$('#rCsv').on('click', () => E.csv(REP, ['audit_number', 'location', 'item_name', 'batch_number', 'system_qty', 'physical_qty', 'qty_diff', 'system_weight', 'physical_weight', 'machine_weight', 'weight_diff', 'diff_pct', 'auditor_name', 'audit_date', 'line_status', 'status_label', 'proofs', 'remarks']
    .map(k => ({ label: k.replace(/_/g, ' '), key: k })), 'stock_audit_report.csv'));
JS
);
