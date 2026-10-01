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
#roleDash{margin:6px 0 18px}
.rd-sec{background:var(--adm-surface,#fff);border:1px solid var(--adm-line,#e7e1d4);border-radius:14px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 2px rgba(28,80,52,.06)}
.rd-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.rd-head h2{font-size:17px;margin:0;color:var(--adm-ink,#23281f)}.rd-role{font-size:12px;color:var(--adm-ink-soft,#6b6459);text-transform:uppercase;letter-spacing:.04em}
.rd-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin-bottom:12px}
.rd-kpi{border:1px solid var(--adm-line,#e7e1d4);border-left:4px solid #9aa39b;border-radius:10px;padding:8px 11px;background:#fff}
.rd-kpi.is-green{border-left-color:#1c5034}.rd-kpi.is-amber{border-left-color:#b8722e;background:#fdf7ee}.rd-kpi.is-red{border-left-color:#a8442f;background:#fbefec}
.rd-kpi .v{font-size:20px;font-weight:700;color:var(--adm-ink,#23281f)}.rd-kpi .l{font-size:12px;color:var(--adm-ink-soft,#6b6459)}.rd-kpi .h{font-size:11px;color:#8b857a}
.rd-task{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:9px 0;border-top:1px solid var(--adm-line,#e7e1d4)}
.rd-task .t{font-weight:600;color:var(--adm-ink,#23281f)}.rd-task .s{font-size:12.5px;color:var(--adm-ink-soft,#6b6459)}
.rd-task .a{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.rd-btn{border:1px solid var(--adm-line,#d8d1c2);background:#fff;border-radius:8px;padding:6px 12px;font-size:13px;cursor:pointer;color:var(--adm-ink,#23281f);text-decoration:none}
.rd-btn.p{background:#1c5034;border-color:#1c5034;color:#fff}.rd-btn:disabled{opacity:.6}
.rd-stage{font-size:11.5px;background:#f6ead9;color:#7a4a17;border-radius:20px;padding:2px 9px;white-space:nowrap}
.rd-amt{font-weight:600;white-space:nowrap}.rd-empty{color:var(--adm-ink-soft,#6b6459);font-size:13px;padding:6px 0}
.rd-hide{display:none !important}`;
    $('<style>').text(css).appendTo('head');

    function api(file, data, post) {
        return new Promise((ok, no) => $.ajax({ url: BASE + file, type: post ? 'POST' : 'GET', data, dataType: 'json' })
            .done(r => (r && r.status === 'success' ? ok(r) : no((r && r.message) || 'Something went wrong.'))).fail(x => no(x.status === 403 ? 'You do not have permission for this.' : 'Could not reach the server.')));
    }
    function load() {
        api('role_dash_api.php', { action: 'get' }).then(r => {
            if (r.limited) $root.nextAll().addClass('rd-hide');   // page-limited roles see only their own dashboard
            if (!r.sections.length) { $root.html(r.note ? `<div class="rd-sec"><div class="rd-empty">${esc(r.note)}</div></div>` : ''); return; }
            $root.html(r.sections.map(s => `<section class="rd-sec" data-sec="${s.key}"><div class="rd-head"><div><div class="rd-role">${esc(s.role)}</div><h2>${esc(s.title)}</h2></div>
                ${s.new ? `<a class="rd-btn p" href="${esc(s.new)}">+ New purchase request</a>` : ''}</div>
                <div class="rd-kpis">${s.kpis.map(k => `<div class="rd-kpi ${k.tone ? 'is-' + k.tone : ''}"><div class="v">${fmt(k)}</div><div class="l">${esc(k.label)}</div>${k.hint ? `<div class="h">${esc(k.hint)}</div>` : ''}</div>`).join('')}</div>
                ${s.tasks.length ? s.tasks.map((t, i) => `<div class="rd-task" data-sec="${s.key}" data-i="${i}"><div><div class="t">${esc(t.title)}</div><div class="s">${esc(t.sub || '')}${t.date ? ' · ' + date(t.date) : ''}</div></div>
                    <div class="a">${t.amount ? `<span class="rd-amt">${money(t.amount)}</span>` : ''}<span class="rd-stage">${esc(t.stage)}</span>
                    ${(t.actions || []).map((a, j) => `<button class="rd-btn ${a.primary ? 'p' : ''}" data-act="${j}">${esc(a.label)}</button>`).join('')}
                    <a class="rd-btn" href="${esc(t.link)}">Open</a></div></div>`).join('') : '<div class="rd-empty">Nothing waiting for you. ✓</div>'}</section>`).join(''));
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
            return Promise.reject('Unknown action');
        };
        Swal.fire({ title: a.label + '?', text: task.title, icon: a.reason ? 'warning' : 'question', input: 'text', inputPlaceholder: a.reason ? 'Reason (required)' : 'Remarks (optional)',
                    showCancelButton: true, confirmButtonText: a.label, confirmButtonColor: a.reason ? '#a8442f' : '#1c5034',
                    preConfirm: v => { if (a.reason && !String(v || '').trim()) { Swal.showValidationMessage('Give the reason'); return false; } a._why = v || ''; return call().catch(m => { Swal.showValidationMessage(esc(m)); return false; }); } })
            .then(res => { if (res.isConfirmed && res.value) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.value.message || 'Done', showConfirmButton: false, timer: 3200 }); load(); } });
    });
    load();
})();
