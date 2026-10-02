<?php
// Shared browser helpers for the stock lifecycle pages (added 2 Oct 2026).
// Returned as a string and prepended to each page's inline script (after jQuery + erp.js).
function sf_common_js(): string {
    return <<<'JS'
const SF = (function () {
    const E = ERP, API = 'stock_flow_api.php';
    let META = null;
    const LABEL = { ready_for_loading: 'READY FOR LOADING', loaded: 'LOADED', in_transit: 'IN TRANSIT', delivered: 'DELIVERED', received: 'RECEIVED', qc_pending: 'QC PENDING',
        qc_approved: 'QC APPROVED', qc_hold: 'QC HOLD', qc_failed: 'QC FAILED', stocked: 'STOCKED', cancelled: 'CANCELLED', planned: 'PLANNED', in_progress: 'IN PROGRESS',
        count_completed: 'COUNT COMPLETED', verification_pending: 'VERIFICATION PENDING', approved: 'APPROVED', closed: 'CLOSED', requested: 'REQUESTED', issued: 'ISSUED',
        rejected: 'REJECTED', identified: 'IDENTIFIED', verified: 'VERIFIED', qc_done: 'QC DONE', posted: 'POSTED', entered: 'ENTERED', dispatched: 'DISPATCHED', delivered_: 'DELIVERED',
        prepared: 'PREPARED', checked: 'CHECKED', acknowledged: 'ACKNOWLEDGED', matched: 'MATCHED', shortage: 'SHORTAGE', excess: 'EXCESS', damage_found: 'DAMAGE FOUND',
        pending_verification: 'PENDING VERIFICATION', pass: 'PASS', partial: 'PARTIAL ACCEPTANCE', hold: 'HOLD', fail: 'FAIL', available: 'AVAILABLE', damaged: 'DAMAGED' };
    const TONE = { ready_for_loading: 'neutral', loaded: 'info', in_transit: 'amber', delivered: 'info', received: 'info', qc_pending: 'amber', qc_approved: 'green', qc_hold: 'amber',
        qc_failed: 'red', stocked: 'green', cancelled: 'neutral', planned: 'neutral', in_progress: 'amber', count_completed: 'info', verification_pending: 'amber', approved: 'green', closed: 'neutral',
        requested: 'amber', issued: 'green', rejected: 'red', identified: 'amber', verified: 'info', qc_done: 'info', posted: 'green', entered: 'amber', dispatched: 'info', prepared: 'amber',
        checked: 'info', acknowledged: 'green', matched: 'green', shortage: 'red', excess: 'amber', damage_found: 'red', pending_verification: 'amber', pass: 'green', partial: 'amber', hold: 'amber', fail: 'red' };
    function badge(s) { return s ? `<span class="adm-badge is-${TONE[s] || 'neutral'}">${E.esc(LABEL[s] || String(s).replace(/_/g, ' ').toUpperCase())}</span>` : '—'; }
    function meta() { return META ? Promise.resolve(META) : E.api(API, { action: 'meta' }, { silent: true }).then(r => (META = r)); }
    function can(p) { return !!(META && META.perms && META.perms[p]); }
    function kg(v) { return v === null || v === undefined || v === '' ? '—' : E.qty(Math.round(E.num(v) * 1000) / 1000) + ' kg'; }
    function diff(v, unit) {
        if (v === null || v === undefined || v === '') return '—';
        const n = Math.round(E.num(v) * 1000) / 1000;
        return `<span class="${n < 0 ? 'erp-neg' : (n > 0 ? 'sf-pos' : '')}">${n > 0 ? '+' : ''}${E.qty(n)}${unit ? ' ' + unit : ''}</span>`;
    }
    function pct(v) { return v === null || v === undefined || v === '' ? '—' : diff(v, '%'); }
    function whOptions(sel, placeholder) { return E.options((META && META.warehouses) || [], 'id', w => w.name, sel, placeholder === undefined ? false : placeholder); }
    function v(id) { return $('.swal2-popup #' + id).val(); }
    function chk(id) { return $('.swal2-popup #' + id).is(':checked') ? 1 : 0; }
    function now() { const d = new Date(); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }
    function steps(list, current) {
        const i = list.indexOf(current);
        return '<div class="sf-steps">' + list.map((s, k) => `<span class="sf-step ${k < i ? 'done' : (k === i ? 'now' : '')}">${E.esc(LABEL[s] || s)}</span>`).join('<i class="fas fa-angle-right"></i>') + '</div>';
    }
    // run a chain of API calls one after another; stops at the first error (each step is safe to repeat)
    function chain(list) { return list.reduce((p, f) => p.then(f), Promise.resolve()); }
    const css = `<style>.sf-steps{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:4px 0 12px;font-size:12px}.sf-step{padding:3px 8px;border-radius:12px;background:#eef1ec;color:#6b6459}
        .sf-step.done{background:#dcefe2;color:#1c5034}.sf-step.now{background:#1c5034;color:#fff;font-weight:600}.sf-pos{color:#b8722e;font-weight:600}
        .sf-sec{border:1px solid #e3e0d8;border-radius:10px;padding:12px 14px;margin:12px 0;text-align:left}.sf-sec h3{margin:0 0 8px;font-size:15px}
        .sf-lines input.adm-input,.sf-lines select.adm-select{min-width:78px;padding:6px 8px}.sf-lines td,.sf-lines th{white-space:nowrap}.sf-kpis{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
        .sf-kpis .adm-stat{min-width:120px;cursor:pointer}.sf-note{font-size:12px;color:#6b6459;margin-top:6px}.sf-calc{background:#f6f4ef}</style>`;
    $('head').append(css);
    return { API, meta, can, badge, kg, diff, pct, whOptions, v, chk, now, steps, chain, LABEL };
})();
JS;
}
