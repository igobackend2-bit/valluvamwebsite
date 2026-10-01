/* ============================================================================
 * Header notification bell (added 1 Oct 2026) — on every admin page.
 * Approvals waiting for me, decisions on what I sent, next steps and alerts.
 * Red count = unread; opening the bell marks them read and the count closes.
 * A short chime plays when a new notification arrives. Click → the exact page.
 * Needs header_ntf_api.php. Plain JavaScript (runs before jQuery loads).
 * ========================================================================== */
(function () {
    'use strict';
    var API = '../assets/db_query/admin/header_ntf_api.php', POLL = 30000;
    var items = [], unread = 0, user = 0, open = false, first = true;
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); };
    var store = {
        get: function (k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
    };

    var css = '.hn-wrap{position:relative;display:inline-flex;align-items:center;margin-left:auto}' +
        '.hn-fixed{position:fixed;top:14px;right:18px;z-index:1200}' +
        '.hn-bell{position:relative;width:42px;height:42px;border-radius:50%;border:1px solid var(--adm-line,#e2dccf);background:#fff;color:#1c5034;font-size:18px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(28,80,52,.08)}' +
        '.hn-bell:hover{background:#f4f1ea}.hn-bell.hn-ring i{animation:hnring .9s ease 2}' +
        '@keyframes hnring{0%,100%{transform:rotate(0)}20%{transform:rotate(16deg)}40%{transform:rotate(-14deg)}60%{transform:rotate(9deg)}80%{transform:rotate(-6deg)}}' +
        '.hn-count{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#d23b2a;color:#fff;font-size:11.5px;font-weight:700;display:none;align-items:center;justify-content:center;border:2px solid #fff}' +
        '.hn-panel{position:absolute;top:50px;right:0;width:380px;max-width:calc(100vw - 24px);max-height:70vh;display:none;flex-direction:column;background:#fff;border:1px solid var(--adm-line,#e2dccf);border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.16);z-index:1300;overflow:hidden}' +
        '.hn-panel.is-open{display:flex}.hn-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #eee8dc;font-weight:700;color:#23281f}' +
        '.hn-head button{border:0;background:none;color:#1c5034;font-size:12.5px;cursor:pointer;font-weight:600}' +
        '.hn-list{overflow-y:auto;flex:1}.hn-item{display:flex;gap:10px;padding:10px 14px;border-bottom:1px solid #f1ece2;text-decoration:none;color:#23281f}' +
        '.hn-item:hover{background:#faf8f3}.hn-item.is-new{background:#fdf5ea}.hn-dot{flex:0 0 9px;height:9px;border-radius:50%;margin-top:6px;background:#c9c3b7}' +
        '.hn-item.is-new .hn-dot{background:#d23b2a}.hn-t{font-weight:600;font-size:13.5px}.hn-m{font-size:12.5px;color:#6b6459;margin-top:2px}.hn-d{font-size:11px;color:#9a9488;margin-top:3px}' +
        '.hn-sev-critical .hn-t{color:#a8442f}.hn-empty{padding:22px 14px;color:#6b6459;font-size:13px;text-align:center}' +
        '.hn-foot{padding:9px 14px;text-align:center;border-top:1px solid #eee8dc;font-size:12.5px}.hn-foot a{color:#1c5034;font-weight:600;text-decoration:none}' +
        '.hn-sound{font-size:11.5px;color:#6b6459;cursor:pointer;margin-right:10px;font-weight:400}' +
        '.rd-focus{outline:3px solid #d23b2a;outline-offset:2px;border-radius:8px;background:#fdf5ea}';

    /* ---- sound: a soft two-note chime made in the browser (no file to download) ---- */
    var ctx = null, pending = false;
    function soundOn() { return store.get('hn_sound') !== false; }
    function chime() {
        if (!soundOn()) return;
        try {
            ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
            if (ctx.state === 'suspended') { pending = true; ctx.resume().then(function () { if (ctx.state === 'running' && pending) { pending = false; notes(); } }).catch(function () {}); return; }
            notes();
        } catch (e) {}
    }
    function notes() {
        [[880, 0], [1320, 0.16]].forEach(function (n) {
            var o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + n[1];
            o.type = 'sine'; o.frequency.value = n[0];
            g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.45);
            o.connect(g); g.connect(ctx.destination); o.start(t); o.stop(t + 0.5);
        });
    }
    // browsers allow sound only after the user has clicked the page once — play a waiting chime then
    ['click', 'keydown'].forEach(function (ev) { document.addEventListener(ev, function () { if (pending && ctx) ctx.resume().then(function () { if (pending) { pending = false; notes(); } }).catch(function () {}); }, true); });

    function when(d) {
        if (!d) return '';
        var x = new Date(String(d).replace(' ', 'T')); if (isNaN(x)) return esc(d);
        var m = Math.round((Date.now() - x) / 60000);
        if (m < 1) return 'just now'; if (m < 60) return m + ' min ago'; if (m < 1440) return Math.round(m / 60) + ' h ago';
        return x.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
    }
    function post(data) {
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
        return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body }).then(function (r) { return r.json(); });
    }

    var $wrap, $bell, $count, $panel, $list;
    function build() {
        var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
        $wrap = document.createElement('div'); $wrap.className = 'hn-wrap';
        $wrap.innerHTML = '<button type="button" class="hn-bell" aria-label="Notifications" title="Notifications"><i class="fas fa-bell"></i><span class="hn-count"></span></button>' +
            '<div class="hn-panel" role="dialog" aria-label="Notifications"><div class="hn-head"><span>Notifications</span><span><span class="hn-sound"></span><button type="button" data-all>Mark all read</button></span></div>' +
            '<div class="hn-list"></div><div class="hn-foot"><a href="notifications.php">See all alerts</a> · <a href="approvals.php">All approvals</a></div></div>';
        var top = document.querySelector('.adm-main .adm-topbar');
        if (top) { var who = top.querySelector('.adm-who'); top.appendChild($wrap); if (who) top.insertBefore($wrap, who); }
        else { $wrap.classList.add('hn-fixed'); document.body.appendChild($wrap); }
        $bell = $wrap.querySelector('.hn-bell'); $count = $wrap.querySelector('.hn-count'); $panel = $wrap.querySelector('.hn-panel'); $list = $wrap.querySelector('.hn-list');
        $bell.addEventListener('click', function (e) { e.stopPropagation(); toggle(!open); });
        document.addEventListener('click', function (e) { if (open && !$wrap.contains(e.target)) toggle(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) toggle(false); });
        $wrap.querySelector('[data-all]').addEventListener('click', function (e) { e.stopPropagation(); markAll(); });
        var snd = $wrap.querySelector('.hn-sound');
        var paint = function () { snd.innerHTML = soundOn() ? '<i class="fas fa-volume-high"></i> Sound on' : '<i class="fas fa-volume-xmark"></i> Sound off'; };
        paint(); snd.addEventListener('click', function (e) { e.stopPropagation(); store.set('hn_sound', !soundOn()); paint(); if (soundOn()) chime(); });
        $list.addEventListener('click', function (e) {
            var a = e.target.closest('a.hn-item'); if (!a) return;
            e.preventDefault();
            var id = +a.getAttribute('data-id'), href = a.getAttribute('href');
            post({ action: 'read', id: id }).catch(function () {}).then(function () { location.href = href; });
        });
    }
    function badge() {
        $count.textContent = unread > 99 ? '99+' : unread; $count.style.display = unread > 0 ? 'inline-flex' : 'none';
        var side = document.getElementById('navNtfBadge');   // keep the sidebar "Notifications" count the same
        if (side) { side.textContent = unread; side.style.display = unread > 0 ? 'inline-flex' : 'none'; }
        document.title = document.title.replace(/^\(\d+\+?\) /, '') ; if (unread > 0) document.title = '(' + (unread > 99 ? '99+' : unread) + ') ' + document.title;
    }
    function render(fresh) {
        $list.innerHTML = items.length ? items.map(function (i) {
            var isNew = !i.read || (fresh && fresh[i.id]);
            return '<a class="hn-item hn-sev-' + esc(i.severity) + (isNew ? ' is-new' : '') + '" data-id="' + i.id + '" href="' + esc(i.link) + '"><span class="hn-dot"></span><span>' +
                '<div class="hn-t">' + esc(i.title) + '</div><div class="hn-m">' + esc(i.message) + '</div><div class="hn-d">' + when(i.at) + '</div></span></a>';
        }).join('') : '<div class="hn-empty">No notifications. You are all caught up. ✓</div>';
    }
    var shownNew = null;
    function toggle(v) {
        open = v; $panel.classList.toggle('is-open', v);
        if (v) {
            shownNew = {}; items.forEach(function (i) { if (!i.read) shownNew[i.id] = 1; });
            render(shownNew);
            if (unread > 0) markAll(true);   // seen → the red count closes
        }
    }
    function markAll(keepHighlight) {
        post({ action: 'read_all' }).then(function (r) {
            if (!r || r.status !== 'success') return;
            items.forEach(function (i) { i.read = true; }); unread = 0; badge(); render(keepHighlight ? shownNew : null);
        }).catch(function () {});
    }
    function load() {
        fetch(API + '?action=feed', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || d.status !== 'success') return;
            user = d.user || 0; items = d.items || [];
            var key = 'hn_seen_' + user, seen = store.get(key) || [], isNew = false;
            items.forEach(function (i) { if (!i.read && seen.indexOf(i.id) < 0) isNew = true; });
            store.set(key, items.filter(function (i) { return !i.read; }).map(function (i) { return i.id; }).concat(seen).slice(0, 300));
            if (isNew) { chime(); $bell.classList.remove('hn-ring'); void $bell.offsetWidth; $bell.classList.add('hn-ring'); }
            unread = d.unread || 0; badge();
            if (!open) render(); first = false;
        }).catch(function () {});
    }
    /* ---- dashboard: highlight the task the notification points to ---- */
    function focusTask(tries) {
        var m = location.search.match(/[?&]focus=([^&]+)/); if (!m) return;
        var refs = decodeURIComponent(m[1]).split(','), root = document.getElementById('roleDash'); if (!root) return;
        var hit = null;
        root.querySelectorAll('.rd-task').forEach(function (t) { var txt = t.textContent; if (!hit && refs.some(function (r) { return r && txt.indexOf(r) >= 0; })) hit = t; });
        if (hit) { hit.classList.add('rd-focus'); hit.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
        if (tries > 0) setTimeout(function () { focusTask(tries - 1); }, 500);
    }

    function start() {
        if (!document.querySelector('.adm-shell')) return;
        build(); load(); setInterval(function () { if (!document.hidden) load(); }, POLL);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) load(); });
        focusTask(12);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
