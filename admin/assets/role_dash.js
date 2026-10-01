/* ============================================================================
 * Role dashboard (added 1 Oct 2026): "My tasks" + KPIs on the Dashboard for
 * Valluvam Team Executive · Valluvam Team Manager · L1 (Sourcing) · Admin ·
 * CEO · Accounts Team. Approvals are done here (Manager and Admin approve only
 * from their dashboard). Needs role_dash_api.php.
 * ========================================================================== */
(function () {
    'use strict';
    const BASE = '../assets/db_query/admin/';
    const $root = $('#roleDash');
    if (!$root.length) return;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const money = v => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const fmt = k => {
        if (k.value === null || k.value === undefined || k.value === '') return '—';
        if (k.fmt === 'money') return money(k.value);
        if (k.fmt === 'pct') return k.value + '%';
        if (k.fmt === 'h') return k.value < 24 ? k.value + ' h' : (Math.round(k.value / 24 * 10) / 10) + ' days';
        if (k.fmt === 'd') return k.value + ' days';
        return Number(k.value).toLocaleString('en-IN');
    };
    const date = d => { if (!d) return ''; const x = new Date(String(d).replace(' ', 'T')); return isNaN(x) ? esc(d) : x.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }); };
    const css = `
#roleDash{margin:4px 0 22px;max-width:1680px}
.rd-sec{background:var(--adm-surface,#fff);border:1px solid var(--adm-line,#e7e1d4);border-radius:16px;padding:20px 22px 22px;margin-bottom:18px;box-shadow:0 1px 3px rgba(28,80,52,.06)}
.rd-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-bottom:14px;margin-bottom:16px;border-bottom:1px solid var(--adm-line,#efeae0)}
.rd-head h2{font-size:19px;font-weight:700;margin:2px 0 0;color:var(--adm-ink,#23281f);letter-spacing:-.01em}
.rd-role{display:inline-block;font-size:11px;font-weight:700;color:#1c5034;background:#e8f1eb;border-radius:20px;padding:2px 10px;text-transform:uppercase;letter-spacing:.06em}
.rd-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.rd-upd{font-size:12px;color:var(--adm-ink-soft,#8b857a)}
.rd-gl{font-size:11.5px;font-weight:700;color:#8b857a;text-transform:uppercase;letter-spacing:.06em;margin:4px 0 8px}
.rd-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:18px}
.rd-kpi{display:flex;flex-direction:column;justify-content:flex-start;min-height:92px;border:1px solid var(--adm-line,#e7e1d4);border-left:4px solid #9aa39b;border-radius:12px;padding:12px 14px;background:#fff}
.rd-kpi.is-green{border-left-color:#1c5034}.rd-kpi.is-amber{border-left-color:#b8722e;background:#fdf8f0}.rd-kpi.is-red{border-left-color:#a8442f;background:#fcf1ee}
.rd-kpi .v{font-size:22px;font-weight:700;line-height:1.2;color:var(--adm-ink,#23281f);font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rd-kpi .l{font-size:12.5px;font-weight:600;color:var(--adm-ink-soft,#5d574d);margin-top:4px;line-height:1.3}.rd-kpi .h{font-size:11.5px;color:#8b857a;margin-top:2px;line-height:1.3}
.rd-block{margin-top:4px}.rd-bh{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 0 8px}
.rd-bh h3{font-size:15px;font-weight:700;margin:0;color:var(--adm-ink,#23281f);display:flex;align-items:center;gap:8px}
.rd-n{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 7px;border-radius:10px;background:#f0ece3;color:#5d574d;font-size:11.5px;font-weight:700}
.rd-n.is-hot{background:#d23b2a;color:#fff}.rd-more{font-size:12.5px;font-weight:600;color:#1c5034;text-decoration:none;white-space:nowrap}.rd-more:hover{text-decoration:underline}
.rd-tasks{border:1px solid var(--adm-line,#e7e1d4);border-radius:12px;overflow:hidden;margin-bottom:18px}
.rd-task{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:12px 14px;border-top:1px solid var(--adm-line,#efeae0);background:#fff}
.rd-task:first-child{border-top:0}.rd-task:hover{background:#fcfbf8}
.rd-task .t{font-weight:600;color:var(--adm-ink,#23281f)}.rd-task .s{font-size:12.5px;color:var(--adm-ink-soft,#6b6459);margin-top:2px}
.rd-task .a{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.rd-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--adm-line,#d8d1c2);background:#fff;border-radius:8px;padding:7px 13px;font-size:13px;font-weight:600;cursor:pointer;color:var(--adm-ink,#23281f);text-decoration:none;line-height:1.2}
.rd-btn:hover{background:#f6f3ec}.rd-btn.p{background:#1c5034;border-color:#1c5034;color:#fff}.rd-btn.p:hover{background:#163f29}.rd-btn:disabled{opacity:.6}
.rd-stage{font-size:11.5px;font-weight:600;background:#f6ead9;color:#7a4a17;border-radius:20px;padding:3px 10px;white-space:nowrap}
.rd-amt{font-weight:700;white-space:nowrap;font-variant-numeric:tabular-nums}
.rd-empty{color:var(--adm-ink-soft,#6b6459);font-size:13px;padding:14px;border:1px dashed var(--adm-line,#e2dccf);border-radius:12px;background:#fcfbf8;text-align:center}
.rd-hide{display:none !important}
.rd-lists{display:grid;grid-template-columns:minmax(0,1fr);gap:18px}
@media (min-width:1760px){.rd-lists{grid-template-columns:minmax(0,3fr) minmax(0,2fr)}.rd-lists .rd-wide{grid-column:1 / -1}}
.rd-tw{overflow-x:auto;border:1px solid var(--adm-line,#e7e1d4);border-radius:12px;background:#fff}
.rd-tbl{width:100%;border-collapse:collapse;font-size:13px}.rd-wide .rd-tbl{min-width:820px}
.rd-tbl th{background:#f7f4ee;text-align:left;padding:9px 12px;font-size:11.5px;font-weight:700;color:#5d574d;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;border-bottom:1px solid var(--adm-line,#e7e1d4)}
.rd-tbl td{padding:9px 12px;border-top:1px solid var(--adm-line,#f0ebe1);vertical-align:middle;color:var(--adm-ink,#23281f)}
.rd-tbl tbody tr:first-child td{border-top:0}.rd-tbl tbody tr:nth-child(even) td{background:#fcfbf8}
.rd-tbl th.num,.rd-tbl td.num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.rd-tbl td.nw{white-space:nowrap}.rd-tbl td.txt{min-width:220px}.rd-tbl td.muted{color:#6b6459}
.rd-tbl tr[data-href]{cursor:pointer}.rd-tbl tr[data-href]:hover td{background:#f3efe6}
.rd-badge{display:inline-block;background:#e8f1eb;color:#1c5034;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;white-space:nowrap}
.rd-pill{display:inline-block;border-radius:20px;padding:2px 9px;font-size:12px;font-weight:600;white-space:nowrap;background:#f0ece3;color:#5d574d}
.rd-st-approved,.rd-st-completed{background:#e8f1eb;color:#1c5034}.rd-st-rejected,.rd-st-cancelled{background:#fbe9e5;color:#a8442f}
.rd-neg{color:#a8442f;font-weight:600}.rd-pos{color:#1c5034;font-weight:600}
@media (max-width:640px){.rd-tbl{min-width:640px}.rd-head{flex-direction:column}.rd-sec{padding:16px 14px}.rd-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.rd-kpi{min-height:0;padding:10px 11px}.rd-kpi .v{font-size:18px}.rd-head h2{font-size:17px}}`;
    $('<style>').text(css).appendTo('head');

    function api(file, data, post) {
        return new Promise((ok, no) => $.ajax({ url: BASE + file, type: post ? 'POST' : 'GET', data, dataType: 'json' })
            .done(r => (r && r.status === 'success' ? ok(r) : no((r && r.message) || 'Something went wrong.'))).fail(x => no(x.status === 403 ? 'You do not have permission for this.' : 'Could not reach the server.')));
    }
    const cell = (c, v) => {
        if (c.f === 'money') return `<td class="num">${v === null || v === undefined || v === '' ? '—' : money(v)}</td>`;
        if (c.f === 'signed') return `<td class="num ${v < 0 ? 'rd-neg' : 'rd-pos'}">${v < 0 ? '− ' : '+ '}${money(Math.abs(v))}</td>`;
        if (c.f === 'n') return `<td class="num">${Number(v || 0).toLocaleString('en-IN')}</td>`;
        if (c.f === 'date') return `<td class="nw muted">${date(v)}</td>`;
        if (c.f === 'badge') return `<td class="nw"><span class="rd-badge">${esc(v)}</span></td>`;
        if (c.f === 'status') return `<td class="nw"><span class="rd-pill rd-st-${esc(String(v || '').toLowerCase())}">${esc(v)}</span></td>`;
        return `<td${/^[A-Z]{2,5}-\d{4}-\d+$/.test(String(v || '')) ? ' class="nw"' : (String(v || '').length > 40 ? ' class="txt"' : '')}>${esc(v === null || v === undefined || v === '' ? '—' : v)}</td>`;   // document numbers stay on one line
    };
    const isNum = c => ['money', 'signed', 'n'].includes(c.f);
    const ORDER = ['waiting', 'pipeline', 'history', 'txns'], WIDE = ['history', 'txns'];
    const lists = s => !(s.lists || []).length ? '' : `<div class="rd-lists">${s.lists.slice().sort((x, y) => ORDER.indexOf(x.key) - ORDER.indexOf(y.key)).map(l => `<div class="rd-block rd-list ${WIDE.includes(l.key) ? 'rd-wide' : ''}" data-list="${esc(l.key)}">
        <div class="rd-bh"><h3>${esc(l.title)} <span class="rd-n ${l.key === 'waiting' && l.rows.length ? 'is-hot' : ''}">${l.rows.length}</span></h3>${l.more ? `<a class="rd-more" href="${esc(l.more)}">View all →</a>` : ''}</div>
        ${l.rows.length ? `<div class="rd-tw"><table class="rd-tbl"><thead><tr>${l.cols.map(c => `<th class="${isNum(c) ? 'num' : ''}">${esc(c.l)}</th>`).join('')}</tr></thead><tbody>${l.rows.map(r => `<tr${r.link ? ` data-href="${esc(r.link)}" title="Open"` : ''}>${l.cols.map(c => cell(c, r[c.k])).join('')}</tr>`).join('')}</tbody></table></div>`
        : `<div class="rd-empty">${esc(l.empty || 'Nothing to show.')}</div>`}</div>`).join('')}</div>`;
    const kpis = s => {
        const groups = [...new Set(s.kpis.map(k => k.group || ''))];
        const card = k => `<div class="rd-kpi ${k.tone ? 'is-' + k.tone : ''}"><div class="v" title="${esc(fmt(k))}">${fmt(k)}</div><div class="l">${esc(k.label)}</div>${k.hint ? `<div class="h">${esc(k.hint)}</div>` : ''}</div>`;
        return groups.map(g => `${g ? `<div class="rd-gl">${esc(g)}</div>` : ''}<div class="rd-kpis">${s.kpis.filter(k => (k.group || '') === g).map(card).join('')}</div>`).join('');
    };
    const tasks = s => `<div class="rd-block"><div class="rd-bh"><h3>My tasks <span class="rd-n ${s.tasks.length ? 'is-hot' : ''}">${s.tasks.length}</span></h3></div>
        ${s.tasks.length ? `<div class="rd-tasks">${s.tasks.map((t, i) => `<div class="rd-task" data-sec="${s.key}" data-i="${i}"><div><div class="t">${esc(t.title)}</div><div class="s">${esc(t.sub || '')}${t.date ? ' · ' + date(t.date) : ''}</div></div>
            <div class="a">${t.amount ? `<span class="rd-amt">${money(t.amount)}</span>` : ''}<span class="rd-stage">${esc(t.stage)}</span>
            ${(t.actions || []).map((a, j) => `<button class="rd-btn ${a.primary ? 'p' : ''}" data-act="${j}">${esc(a.label)}</button>`).join('')}
            <a class="rd-btn" href="${esc(t.link)}">Open</a></div></div>`).join('')}</div>` : '<div class="rd-empty" style="margin-bottom:18px">Nothing waiting for you. ✓</div>'}</div>`;
    $root.on('click', 'tr[data-href]', function () { location.href = $(this).data('href'); });
    $root.on('click', '[data-reload]', function () { $(this).prop('disabled', true); load(); });
    function load() {
        api('role_dash_api.php', { action: 'get' }).then(r => {
            if (r.limited) $root.nextAll().addClass('rd-hide');   // page-limited roles see only their own dashboard
            if (!r.sections.length) { $root.html(r.note ? `<div class="rd-sec"><div class="rd-empty">${esc(r.note)}</div></div>` : ''); return; }
            const upd = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
            $root.html(r.sections.map(s => `<section class="rd-sec" data-sec="${s.key}"><div class="rd-head"><div><span class="rd-role">${esc(s.role)}</span><h2>${esc(s.title)}</h2></div>
                <div class="rd-tools"><span class="rd-upd">Updated ${upd}</span><button type="button" class="rd-btn" data-reload>↻ Refresh</button>${s.new ? `<a class="rd-btn p" href="${esc(s.new)}">+ New purchase request</a>` : ''}</div></div>
                ${kpis(s)}${tasks(s)}${lists(s)}</section>`).join(''));
            $root.data('sections', r.sections);
        }).catch(m => $root.html(`<div class="rd-sec"><div class="rd-empty">${esc(m)}</div></div>`));
    }
    $root.on('click', '[data-act]', function () {
        const $t = $(this).closest('.rd-task'), sec = ($root.data('sections') || []).find(s => s.key === $t.data('sec'));
        const task = sec.tasks[+$t.data('i')], a = task.actions[+$(this).data('act')];
        const call = () => {
            if (a.kind.startsWith('pr_')) return api('purchase_api.php', { action: a.kind, id: task.id, note: a._why || '' }, true);
            if (a.kind === 'apr_approve') return api('approvals_api.php', { action: 'approve', id: task.approval_id, remarks: a._why || '' }, true);
            if (a.kind === 'apr_reject') return api('approvals_api.php', { action: 'reject', id: task.approval_id, remarks: a._why || '' }, true);
            if (a.kind === 'po_approve') return api('purchase_api.php', { action: 'po_approve', id: task.po_id }, true);
            if (a.kind === 'po_reject') return api('procurement_api.php', { action: 'po_reject', id: task.po_id, reason: a._why || '' }, true);
            if (a.kind === 'waste_approve') return api('approve_waste.php', { id: task.waste_id, status: 'approved' }, true).then(r => (r.message ? r : Object.assign(r, { message: 'Waste approved — stock reduced.' })));
            if (a.kind === 'waste_reject') return api('approve_waste.php', { id: task.waste_id, status: 'cancelled', reason: a._why || '' }, true).then(r => (r.message ? r : Object.assign(r, { message: 'Waste record rejected.' })));
            return Promise.reject('Unknown action');
        };
        Swal.fire({ title: a.label + '?', text: task.title, icon: a.reason ? 'warning' : 'question', input: 'text', inputPlaceholder: a.reason ? 'Reason (required)' : 'Remarks (optional)',
                    showCancelButton: true, confirmButtonText: a.label, confirmButtonColor: a.reason ? '#a8442f' : '#1c5034',
                    preConfirm: v => { if (a.reason && !String(v || '').trim()) { Swal.showValidationMessage('Give the reason'); return false; } a._why = v || ''; return call().catch(m => { Swal.showValidationMessage(esc(m)); return false; }); } })
            .then(res => { if (res.isConfirmed && res.value) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.value.message || 'Done', showConfirmButton: false, timer: 3200 }); load(); } });
    });
    load();
})();
