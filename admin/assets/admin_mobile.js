/* ============================================================================
 * Phone / tablet layout for the admin panel (added 1 Oct 2026).
 * Below 900px the sidebar slides in from a ☰ button instead of covering the
 * page; desktop layout is not changed. Plain JavaScript, no other file needed.
 * ========================================================================== */
(function () {
    'use strict';
    var css = '@media (max-width:900px){' +
        '.adm-sidebar{transform:translateX(-100%);transition:transform .22s ease;z-index:1400;box-shadow:none}' +
        'body.adm-nav-open .adm-sidebar{transform:none;box-shadow:0 0 40px rgba(0,0,0,.35)}' +
        '.adm-main{margin-left:0 !important;padding:66px 14px 40px !important}' +
        '.adm-topbar{gap:10px;margin-bottom:18px}.adm-topbar h1{font-size:20px}.adm-who{font-size:12.5px}' +
        '.am-burger{display:inline-flex !important}' +
        '.am-shade{position:fixed;inset:0;background:rgba(15,25,18,.45);z-index:1350;display:none}body.adm-nav-open .am-shade{display:block}' +
        '}' +
        '.am-burger{display:none;position:fixed;top:12px;left:12px;z-index:1300;width:42px;height:42px;border-radius:10px;border:1px solid var(--adm-line,#e2dccf);background:#fff;color:#1c5034;font-size:20px;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.08)}';
    function start() {
        var side = document.querySelector('.adm-sidebar'); if (!side) return;
        var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'am-burger'; btn.setAttribute('aria-label', 'Open menu'); btn.innerHTML = '&#9776;';
        var shade = document.createElement('div'); shade.className = 'am-shade';
        document.body.appendChild(btn); document.body.appendChild(shade);
        var set = function (v) { document.body.classList.toggle('adm-nav-open', v); btn.setAttribute('aria-expanded', v ? 'true' : 'false'); };
        btn.addEventListener('click', function () { set(!document.body.classList.contains('adm-nav-open')); });
        shade.addEventListener('click', function () { set(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
        side.addEventListener('click', function (e) { if (e.target.closest('a[href]')) set(false); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
