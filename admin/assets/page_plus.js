/* ============================================================================
 * Admin page add-ons (added 1 Oct 2026) — loaded on every admin page by the sidebar.
 *   Everywhere : profile chip in the header (instead of "Signed in as …"),
 *                neater Documents lists (one aligned row per file).
 *   assets.php : summary, who is handling which asset, Assign / I'm using it,
 *                "Add an asset I'm using".
 *   waste.php  : summary, product list instead of a product ID, warehouse,
 *                Approve / Reject only for approvers, reported / approved by.
 *   audit_logs : readable list (what changed) + before / after table.
 * Needs page_plus_api.php. Uses the page's own endpoints for every save.
 * ========================================================================== */
(function () {
    'use strict';
    var BASE = '../assets/db_query/admin/';
    var page = (location.pathname.split('/').pop() || 'index.php').toLowerCase();
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); };
    var money = function (v) { return '₹' + Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); };
    var day = function (d) { if (!d) return '—'; var x = new Date(String(d).replace(' ', 'T')); return isNaN(x) ? esc(d) : x.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); };
    var dt = function (d) { if (!d) return '—'; var x = new Date(String(d).replace(' ', 'T')); return isNaN(x) ? esc(d) : x.toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); };
    var css = function (t) { var s = document.createElement('style'); s.textContent = t; document.head.appendChild(s); };
    function get(action, data) {
        var q = Object.keys(data || {}).map(function (k) { return '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('');
        return fetch(BASE + 'page_plus_api.php?action=' + action + q, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) { if (!r || r.status !== 'success') throw (r && r.message) || 'Error'; return r; });
    }
    function post(file, data) {
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]); }).join('&');
        return fetch(BASE + file, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function (r) { return r.json(); }).then(function (r) { if (!r || r.status !== 'success') throw (r && r.message) || 'Not saved'; return r; });
    }
    var kpi = function (v, l, tone, h) { return '<div class="pp-kpi ' + (tone ? 'is-' + tone : '') + '"><div class="v">' + v + '</div><div class="l">' + esc(l) + '</div>' + (h ? '<div class="h">' + esc(h) + '</div>' : '') + '</div>'; };

    css('.pp-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:0 0 16px}' +
        '.pp-kpi{background:var(--adm-surface,#fff);border:1px solid var(--adm-line,#e7e1d4);border-left:4px solid #9aa39b;border-radius:12px;padding:12px 14px}' +
        '.pp-kpi.is-green{border-left-color:#1c5034}.pp-kpi.is-amber{border-left-color:#b8722e;background:#fdf8f0}.pp-kpi.is-red{border-left-color:#a8442f;background:#fcf1ee}.pp-kpi.is-info{border-left-color:#2f6ea8}' +
        '.pp-kpi .v{font-size:22px;font-weight:700;color:var(--adm-ink,#23281f)}.pp-kpi .l{font-size:12.5px;font-weight:600;color:#5d574d}.pp-kpi .h{font-size:11.5px;color:#8b857a}' +
        '.pp-chip{display:inline-flex;align-items:center;gap:6px;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:600;background:#f0ece3;color:#5d574d;white-space:nowrap}' +
        '.pp-chip.is-green{background:#e8f1eb;color:#1c5034}.pp-chip.is-amber{background:#f6ead9;color:#7a4a17}.pp-chip.is-red{background:#fbe9e5;color:#a8442f}.pp-chip.is-info{background:#e6eef7;color:#2f5f8f}' +
        '.pp-muted{color:#8b857a;font-size:12px}.pp-acts{display:flex;gap:6px;flex-wrap:wrap}' +
        /* documents list: one aligned row per file */
        '.erp-docs .erp-docs-row{display:grid !important;grid-template-columns:22px minmax(160px,2fr) auto minmax(110px,1fr) minmax(150px,1.2fr) auto auto auto;gap:6px 10px;align-items:center;padding:8px 6px !important;border-bottom:1px solid var(--adm-line,#efeae0)}' +
        '.erp-docs .erp-docs-row:hover{background:#faf8f3}.erp-docs .erp-docs-row>a.erp-link{overflow-wrap:anywhere;font-weight:600}.erp-docs .erp-docs-row>.erp-muted{font-size:12px}' +
        '.erp-docs .erp-docs-row .adm-icon-btn{width:34px;height:34px}' +
        '@media (max-width:900px){.erp-docs .erp-docs-row{grid-template-columns:22px 1fr auto;}.erp-docs .erp-docs-row>*:nth-child(n+4){grid-column:2 / -1}}' +
        /* profile chip */
        '.pp-prof{position:relative;display:inline-flex}.pp-prof-btn{display:flex;align-items:center;gap:10px;border:1px solid var(--adm-line,#e2dccf);background:#fff;border-radius:30px;padding:4px 14px 4px 4px;cursor:pointer;font:inherit;color:inherit;box-shadow:0 1px 3px rgba(28,80,52,.08)}' +
        '.pp-prof-btn:hover{background:#f7f4ee}.pp-av{width:34px;height:34px;border-radius:50%;background:#1c5034;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;letter-spacing:.02em}' +
        '.pp-pn{display:flex;flex-direction:column;line-height:1.15;text-align:left}.pp-pn b{font-size:13.5px;color:var(--adm-ink,#23281f)}.pp-pn span{font-size:11.5px;color:#8b857a}' +
        '.pp-menu{position:absolute;right:0;top:48px;min-width:230px;background:#fff;border:1px solid var(--adm-line,#e2dccf);border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.15);z-index:1300;display:none;overflow:hidden}' +
        '.pp-menu.is-open{display:block}.pp-menu .hd{padding:12px 14px;border-bottom:1px solid #eee8dc}.pp-menu .hd b{display:block}.pp-menu .hd span{font-size:12px;color:#8b857a}' +
        '.pp-menu a{display:flex;gap:10px;align-items:center;padding:10px 14px;color:var(--adm-ink,#23281f);text-decoration:none;font-size:13.5px}.pp-menu a:hover{background:#f7f4ee}.pp-menu a.out{color:#a8442f;border-top:1px solid #eee8dc}' +
        '@media (max-width:640px){.pp-pn{display:none}.pp-prof-btn{padding:3px}}');

    /* ---------------------------------------------------------------- tidy forms in pop-ups (1 Oct 2026) */
    css('.swal2-popup.pp-pop{padding:22px 26px 20px;border-radius:16px}.swal2-popup.pp-pop .swal2-title{font-size:21px;font-weight:700;color:var(--adm-ink,#23281f);padding:0 0 4px}' +
        '.swal2-popup.pp-pop .swal2-html-container{margin:8px 0 0;overflow:visible}.pp-sub{font-size:13px;color:#8b857a;text-align:center;margin:-2px 0 14px}' +
        '.pp-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 14px;text-align:left}.pp-form .full{grid-column:1/-1}' +
        '.pp-form .sec{grid-column:1/-1;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#1c5034;border-bottom:1px solid #eee8dc;padding:6px 0 4px;margin-top:4px}' +
        '.pp-f{display:flex;flex-direction:column;gap:5px;min-width:0}.pp-f>span{font-size:12.5px;font-weight:600;color:#4b463d}.pp-f>span i{font-style:normal;font-weight:400;color:#8b857a}.pp-f>span b{color:#a8442f}' +
        '.pp-in{width:100%;box-sizing:border-box;height:42px;padding:0 12px;border:1px solid #d8d1c2;border-radius:10px;background:#fff;font:inherit;font-size:14px;color:var(--adm-ink,#23281f);transition:border-color .15s,box-shadow .15s}' +
        'textarea.pp-in{height:auto;min-height:76px;padding:10px 12px;resize:vertical}select.pp-in{padding-right:30px}' +
        '.pp-in:focus{outline:0;border-color:#1c5034;box-shadow:0 0 0 3px rgba(28,80,52,.15)}.pp-in::placeholder{color:#a8a296}' +
        '.pp-pop .swal2-actions{margin-top:20px;gap:8px}.pp-pop .swal2-styled{border-radius:10px;padding:10px 22px;font-weight:600;font-size:14px;margin:0}' +
        '.pp-pop .swal2-validation-message{border-radius:10px;margin:12px 0 0;font-size:13px}' +
        '@media (max-width:560px){.pp-form{grid-template-columns:1fr}.swal2-popup.pp-pop{padding:18px 16px}}');
    var F = function (label, control, opt) { opt = opt || {}; return '<label class="pp-f' + (opt.full ? ' full' : '') + '"><span>' + label + (opt.req ? ' <b>*</b>' : '') + (opt.hint ? ' <i>' + opt.hint + '</i>' : '') + '</span>' + control + '</label>'; };
    var I = function (id, val, attrs) { return '<input id="' + id + '" class="pp-in" value="' + esc(val == null ? '' : val) + '" ' + (attrs || '') + '>'; };
    var SEL = function (id, opts, cur) { return '<select id="' + id + '" class="pp-in">' + opts.map(function (o) { var v = Array.isArray(o) ? o[0] : o, l = Array.isArray(o) ? o[1] : o; return '<option value="' + esc(v) + '"' + (String(v) === String(cur == null ? '' : cur) ? ' selected' : '') + '>' + esc(l) + '</option>'; }).join('') + '</select>'; };
    var TA = function (id, val, ph) { return '<textarea id="' + id + '" class="pp-in" placeholder="' + esc(ph || '') + '">' + esc(val || '') + '</textarea>'; };
    var SEC = function (t) { return '<div class="sec">' + t + '</div>'; };
    var POP = { customClass: { popup: 'pp-pop' }, confirmButtonColor: '#1c5034', cancelButtonColor: '#8b857a', showCancelButton: true, focusConfirm: false };
    var today = function () { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };

    /* ---------------------------------------------------------------- profile chip */
    function profile() {
        var top = document.querySelector('.adm-main .adm-topbar'); if (!top) return;
        get('me').then(function (me) {
            var who = top.querySelector('.adm-who');
            var w = document.createElement('div'); w.className = 'pp-prof';
            w.innerHTML = '<button type="button" class="pp-prof-btn" aria-haspopup="true" aria-label="My profile"><span class="pp-av">' + esc(me.initials || '?') + '</span><span class="pp-pn"><b>' + esc(me.name) + '</b><span>' + esc(me.role) + '</span></span><i class="fas fa-chevron-down" style="font-size:11px;color:#8b857a"></i></button>' +
                '<div class="pp-menu" role="menu"><div class="hd"><b>' + esc(me.name) + '</b><span>' + esc(me.role) + (me.username ? ' · @' + esc(me.username) : '') + '</span>' + (me.email ? '<span style="display:block">' + esc(me.email) + '</span>' : '') + '</div>' +
                '<a href="index.php"><i class="fas fa-gauge-high"></i> My dashboard</a><a href="my_account.php"><i class="fas fa-id-badge"></i> My account & password</a><a class="out" href="logout.php"><i class="fas fa-right-from-bracket"></i> Log out</a></div>';
            if (who) { who.style.display = 'none'; who.parentNode.insertBefore(w, who); } else top.appendChild(w);
            var bell = top.querySelector('.hn-wrap'); if (bell && bell.nextSibling !== w) top.insertBefore(bell, w);
            var m = w.querySelector('.pp-menu');
            w.querySelector('.pp-prof-btn').addEventListener('click', function (e) { e.stopPropagation(); m.classList.toggle('is-open'); });
            document.addEventListener('click', function (e) { if (!w.contains(e.target)) m.classList.remove('is-open'); });
        }).catch(function () {});
    }

    /* ---------------------------------------------------------------- assets */
    function assetsPage() {
        var $ = window.jQuery; if (!$ || typeof window.displayAssets !== 'function') return;
        var S = null, TEAM = [];
        var box = $('<div id="ppAssets"></div>'); $('.adm-main .adm-topbar').after(box);
        function summary() {
            return get('assets_summary').then(function (r) {
                S = r; var t = r.totals || {};
                box.html('<div class="pp-kpis">' + kpi(t.n || 0, 'Assets in use', 'green') + kpi(t.assigned || 0, 'Assigned to a person', 'info') + kpi(t.free || 0, 'Not assigned', +t.free ? 'amber' : '') +
                    kpi(t.maint || 0, 'Under maintenance', +t.maint ? 'amber' : '') + kpi(t.bad || 0, 'Damaged / lost', +t.bad ? 'red' : '') + kpi(money(t.val), 'Current value', '', 'purchase cost ' + money(t.cost)) + (+t.warranty_soon ? kpi(t.warranty_soon, 'Warranty ends in 30 days', 'amber') : '') + '</div>' +
                    '<section class="adm-card"><div class="adm-card-head" style="flex-wrap:wrap;gap:8px"><h2><i class="fas fa-user-check"></i> Who is handling which asset</h2>' + (r.can.create && r.can.edit ? '<button class="adm-btn adm-btn-ghost" id="ppMine"><i class="fas fa-hand"></i> Add an asset I\'m using</button>' : '') + '</div><div class="adm-card-body">' +
                    (r.people.length ? '<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Team member</th><th>Assets</th><th>Value</th><th>Which assets</th></tr></thead><tbody>' + r.people.map(function (p) {
                        return '<tr><td class="adm-cell-title">' + esc(p.person) + '</td><td>' + p.n + '</td><td class="adm-money">' + money(p.val) + '</td><td class="adm-cell-sub">' + esc(p.list) + '</td></tr>'; }).join('') + '</tbody></table></div>' : '<div class="adm-empty"><p>No asset is assigned to anyone yet.</p></div>') +
                    (r.recent.length ? '<h3 style="font-size:14px;margin:16px 0 8px">Latest assignments</h3><div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>When</th><th>Asset</th><th>Given to</th><th>By</th><th>Notes</th></tr></thead><tbody>' + r.recent.map(function (a) {
                        return '<tr><td>' + day(a.assigned_date) + '</td><td><a class="erp-link" href="asset_detail.php?id=' + a.id + '">' + esc(a.asset_id) + '</a> <span class="pp-muted">' + esc(a.asset_name) + '</span></td><td>' + esc(a.assigned_to) + '</td><td>' + esc(a.created_by || '') + '</td><td class="adm-cell-sub">' + esc(a.notes || '') + '</td></tr>'; }).join('') + '</tbody></table></div>' : '') + '</div></section>');
            }).catch(function () {});
        }
        if (true) get('team').then(function (r) { TEAM = r.team || []; }).catch(function () {});
        var orig = window.displayAssets;
        window.displayAssets = function (assets) {
            orig(assets);
            if (!S) return;
            $('#assetsTable tbody tr').each(function (i) {
                var a = assets[i]; if (!a) return;
                var cell = $(this).children('td').last(), btn = '';
                if (S.can.edit && S.can.assign_team) btn += '<button class="adm-btn adm-btn-ghost pp-asg" data-i="' + i + '" title="Give this asset to a team member"><i class="fas fa-user-plus"></i> Assign</button>';
                if (S.can.edit && a.assigned_employee !== S.me) btn += '<button class="adm-btn adm-btn-ghost pp-use" data-i="' + i + '" title="I am using this asset"><i class="fas fa-hand"></i> I\'m using it</button>';
                cell.css({ display: 'flex', gap: '6px', alignItems: 'center', flexWrap: 'wrap' }).append(btn);
            });
            $('#assetsTable').off('click.pp').on('click.pp', '.pp-asg', function () { assign(assets[+$(this).data('i')], null); })
                .on('click.pp', '.pp-use', function () { assign(assets[+$(this).data('i')], S.me); });
        };
        function assign(a, to) {
            var opts = TEAM.map(function (u) { return '<option value="' + esc(u.name) + '">' + esc(u.name) + (u.role ? ' — ' + esc(u.role) : '') + '</option>'; }).join('');
            Swal.fire($.extend({}, POP, { title: to ? 'I\'m using ' + a.asset_id : 'Assign ' + a.asset_id, width: 560, confirmButtonText: to ? 'Yes, it\'s with me' : 'Assign',
                html: '<div class="pp-sub">' + esc(a.asset_name) + ' · ' + esc(a.asset_category) + (a.assigned_employee ? ' · now with ' + esc(a.assigned_employee) : '') + '</div><div class="pp-form">' +
                    (to ? '' : F('Team member', '<select id="ppTo" class="pp-in"><option value="">Choose…</option>' + opts + '</select>', { full: true, req: true })) +
                    F('Date', I('ppDate', today(), 'type="date"'), { full: true }) + F('Notes', TA('ppNotes', '', 'Condition, accessories given with it…'), { full: true }) + '</div>',
                preConfirm: function () {
                    var who = to || $('#ppTo').val(); if (!who) { Swal.showValidationMessage('Choose the team member'); return false; }
                    return post('assign_asset.php', { asset_id: a.id, assigned_to: who, assigned_date: $('#ppDate').val(), notes: $('#ppNotes').val() }).catch(function (m) { Swal.showValidationMessage(esc(m)); return false; });
                } })).then(function (r) { if (r.isConfirmed && r.value) { Swal.fire({ icon: 'success', title: 'Assigned', timer: 1200, showConfirmButton: false }); summary(); window.loadAssets(); } });
        }
        $(document).on('click', '#ppMine', function () {
            Swal.fire($.extend({}, POP, { title: 'Add an asset I\'m using', width: 620, confirmButtonText: 'Add & assign to me',
                html: '<div class="pp-sub">It is added to the asset list and assigned to you.</div><div class="pp-form">' +
                    F('Asset name', I('ppN', '', 'placeholder="e.g. Laptop, Weighing scale"'), { full: true, req: true }) +
                    F('Category', SEL('ppC', ['Electronics', 'Machinery', 'Vehicle', 'Furniture', 'Other'])) + F('Condition', SEL('ppK', [['good', 'Good'], ['new', 'New'], ['fair', 'Fair'], ['damaged', 'Damaged']])) +
                    F('Serial / model number', I('ppS', '', 'placeholder="optional"')) + F('Where it is kept', I('ppL', '', 'placeholder="optional"')) + F('Notes', TA('ppT', '', 'optional'), { full: true }) + '</div>',
                preConfirm: function () {
                    var n = $('#ppN').val().trim(); if (!n) { Swal.showValidationMessage('Enter the asset name'); return false; }
                    return post('save_asset.php', { asset_name: n, asset_category: $('#ppC').val(), serial_number: $('#ppS').val(), location: $('#ppL').val(), asset_condition: $('#ppK').val(), notes: $('#ppT').val(), purchase_cost: 0 })
                        .then(function (r) { return post('assign_asset.php', { asset_id: r.id, assigned_to: S.me, assigned_date: new Date().toISOString().substring(0, 10), notes: 'Added by the user as in use' }); })
                        .catch(function (m) { Swal.showValidationMessage(esc(m)); return false; });
                } })).then(function (r) { if (r.isConfirmed && r.value) { Swal.fire({ icon: 'success', title: 'Added and assigned to you', timer: 1400, showConfirmButton: false }); summary(); window.loadAssets(); } });
        });
        if (typeof window.editAsset === 'function') window.editAsset = function (a) {
            var v = function (k) { return a && a[k] != null ? a[k] : ''; }, d = function (k) { return a && a[k] ? String(a[k]).substring(0, 10) : ''; };
            Swal.fire($.extend({}, POP, { title: a ? 'Edit ' + a.asset_id : 'New asset', width: 760, confirmButtonText: a ? 'Save changes' : 'Create asset',
                html: (a ? '<div class="pp-sub">' + esc(a.asset_name) + (a.assigned_employee ? ' · with ' + esc(a.assigned_employee) : '') + '</div>' : '<div class="pp-sub">Vehicles, machinery, furniture, electronics and other company assets</div>') + '<div class="pp-form">' +
                    SEC('Asset') + F('Asset name', I('fN', v('asset_name'), 'placeholder="e.g. Delivery van, Weighing scale"'), { req: true }) +
                    F('Category', I('fC', v('asset_category') || 'Electronics', 'list="fCl"') + '<datalist id="fCl"><option value="Vehicle"><option value="Machinery"><option value="Furniture"><option value="Electronics"><option value="Other"></datalist>', { req: true }) +
                    F('Type', I('fT', v('asset_type'), 'placeholder="e.g. Delivery van"'), { hint: 'optional' }) + F('Condition', SEL('fK', [['new', 'New'], ['good', 'Good'], ['fair', 'Fair'], ['damaged', 'Damaged'], ['critical', 'Critical']], v('asset_condition') || 'new')) +
                    F('Serial number', I('fS', v('serial_number')), { hint: 'optional' }) + F('Model number', I('fM', v('model_number')), { hint: 'optional' }) +
                    SEC('Purchase & value') + F('Purchase date', I('fPD', d('purchase_date'), 'type="date"')) + F('Purchase cost ₹', I('fPC', a ? v('purchase_cost') : '', 'type="number" min="0" step="0.01" placeholder="0.00"')) +
                    F('Current value ₹', I('fCV', v('current_value'), 'type="number" min="0" step="0.01" placeholder="same as cost"'), { hint: 'optional' }) + F('Document note', I('fDN', v('document_note'), 'placeholder="e.g. invoice in file A-12"'), { hint: 'optional' }) +
                    SEC('Where & who') + F('Location', I('fL', v('location'), 'placeholder="e.g. Main warehouse, office"'), { hint: 'optional' }) + F('Department', I('fD', v('department'), 'placeholder="e.g. Packing, Delivery"'), { hint: 'optional' }) +
                    SEC('Warranty') + F('Warranty start', I('fWS', d('warranty_start_date'), 'type="date"')) + F('Warranty end', I('fWE', d('warranty_end_date'), 'type="date"')) +
                    F('Notes', TA('fNo', v('notes'), 'Anything else about this asset'), { full: true }) + '</div>',
                preConfirm: function () {
                    var name = $('#fN').val().trim(), cat = $('#fC').val().trim();
                    if (!name || !cat) { Swal.showValidationMessage('Asset name and category are required'); return false; }
                    if ($('#fWS').val() && $('#fWE').val() && $('#fWE').val() < $('#fWS').val()) { Swal.showValidationMessage('Warranty end is before the start'); return false; }
                    return { id: a ? a.id : null, asset_name: name, asset_category: cat, asset_type: $('#fT').val().trim(), purchase_date: $('#fPD').val(), purchase_cost: $('#fPC').val() || 0,
                             current_value: $('#fCV').val(), serial_number: $('#fS').val().trim(), model_number: $('#fM').val().trim(), location: $('#fL').val().trim(), department: $('#fD').val().trim(),
                             warranty_start_date: $('#fWS').val(), warranty_end_date: $('#fWE').val(), asset_condition: $('#fK').val(), document_note: $('#fDN').val().trim(), notes: $('#fNo').val().trim(),
                             assigned_employee: a ? (a.assigned_employee || '') : '', supplier_id: a ? (a.supplier_id || '') : '' };   // editing keeps who has it and the supplier
                } })).then(function (r) { if (r.isConfirmed) { window.saveAsset(r.value); setTimeout(summary, 800); } });
        };
        summary().then(function () { window.loadAssets(); });
    }

    /* ---------------------------------------------------------------- waste */
    function wastePage() {
        var $ = window.jQuery; if (!$ || typeof window.displayWaste !== 'function') return;
        var M = null;
        var box = $('<div id="ppWaste"></div>'); $('.adm-main .adm-topbar').after(box);
        var TYPES = { product_waste: 'Product waste', damaged_stock: 'Damaged stock', expired_stock: 'Expired stock', production_waste: 'Production waste', packaging_waste: 'Packaging waste', other: 'Other' };
        var TONE = { reported: 'amber', approved: 'info', processed: 'info', disposed: 'green', cancelled: 'red' };
        var LBL = { reported: 'Waiting for approval', approved: 'Approved', processed: 'Processed', disposed: 'Disposed', cancelled: 'Rejected' };
        function meta() {
            return get('waste_meta').then(function (r) {
                M = r; var s = r.stats || {};
                box.html('<div class="pp-kpis">' + kpi(s.pending || 0, 'Waiting for approval', +s.pending ? 'amber' : 'green', +s.pending ? 'worth ' + money(s.pending_val) : '') + kpi(s.approved_month || 0, 'Approved this month', '', 'value ' + money(s.value_month)) + kpi(s.rejected || 0, 'Rejected') + '</div>' +
                    '<div class="pp-muted" style="margin:-6px 0 14px"><i class="fas fa-circle-info"></i> ' + (r.can.approve ? 'You approve waste: stock goes down only when you approve. Reject if the report is wrong.' : 'Report waste here — the Manager approves it, and only then the stock goes down.') + '</div>');
            });
        }
        window.displayWaste = function (records) {
            if (!records.length) { $('#wasteTable').html('<div class="adm-empty"><i class="fas fa-trash"></i><p><strong>No waste records</strong></p><p>' + (M && M.can.report ? 'Use “Report waste” to add one.' : 'Nothing reported yet.') + '</p></div>'); return; }
            var can = (M && M.can) || {};
            var rows = records.map(function (w) {
                var acts = '';
                if (can.approve && w.status === 'reported') acts = '<button class="adm-btn adm-btn-primary pp-w" data-id="' + w.id + '" data-s="approved" data-c="' + esc(w.waste_id) + '"><i class="fas fa-check"></i> Approve</button><button class="adm-btn adm-btn-ghost pp-w" data-id="' + w.id + '" data-s="cancelled" data-c="' + esc(w.waste_id) + '">Reject</button>';
                else if (can.approve && w.status === 'approved') acts = '<button class="adm-btn adm-btn-ghost pp-w" data-id="' + w.id + '" data-s="processed" data-c="' + esc(w.waste_id) + '">Mark processed</button>';
                else if (can.approve && w.status === 'processed') acts = '<button class="adm-btn adm-btn-ghost pp-w" data-id="' + w.id + '" data-s="disposed" data-c="' + esc(w.waste_id) + '">Mark disposed</button>';
                else if (w.status === 'reported') acts = '<span class="pp-muted">Waiting for the Manager</span>';
                return '<tr><td class="adm-cell-title">' + esc(w.waste_id) + '<div class="adm-cell-sub">' + day(w.date) + '</div></td><td>' + esc(TYPES[w.waste_type] || w.waste_type) + '</td>' +
                    '<td>' + (w.product_name ? esc(w.product_name) : (w.sku ? esc(w.sku) : '<span class="adm-cell-sub">—</span>')) + '</td><td>' + (w.quantity !== null ? esc(w.quantity) + ' ' + esc(w.unit) : '—') + '</td>' +
                    '<td>' + esc(w.reason) + (w.disposal_method ? '<div class="adm-cell-sub">Disposal: ' + esc(w.disposal_method) + '</div>' : '') + '</td><td class="adm-money">' + (w.estimated_value ? money(w.estimated_value) : '—') + '</td>' +
                    '<td><span class="pp-chip is-' + (TONE[w.status] || '') + '">' + esc(LBL[w.status] || w.status) + '</span></td>' +
                    '<td class="adm-cell-sub">Reported by ' + esc(w.created_by || '—') + (w.approved_by ? '<br>' + (w.status === 'cancelled' ? 'Rejected' : 'Approved') + ' by ' + esc(w.approved_by) : '') + '</td><td><div class="pp-acts">' + acts + '</div></td></tr>';
            }).join('');
            $('#wasteTable').html('<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Waste</th><th>Type</th><th>Product / SKU</th><th>Qty</th><th>Reason</th><th>Est. value</th><th>Status</th><th>Who</th><th>Actions</th></tr></thead><tbody>' + rows + '</tbody></table></div>');
        };
        $(document).on('click', '.pp-w', function () {
            var id = $(this).data('id'), st = $(this).data('s'), code = $(this).data('c');
            var t = { approved: ['Approve ' + code + '?', 'Stock of the product goes down now. This cannot be undone.'], cancelled: ['Reject ' + code + '?', 'Stock is not changed.'], processed: ['Mark ' + code + ' as processed?', ''], disposed: ['Mark ' + code + ' as disposed?', ''] }[st];
            Swal.fire({ title: t[0], text: t[1], icon: st === 'cancelled' ? 'warning' : 'question', showCancelButton: true, confirmButtonText: 'Confirm', confirmButtonColor: st === 'cancelled' ? '#a8442f' : '#1c5034',
                preConfirm: function () { return post('approve_waste.php', { id: id, status: st }).catch(function (m) { Swal.showValidationMessage(esc(m)); return false; }); } })
                .then(function (r) { if (r.isConfirmed && r.value) { Swal.fire({ icon: 'success', title: 'Updated', timer: 1100, showConfirmButton: false }); meta(); window.loadWaste(); } });
        });
        // own form replaces the page's one: capture the click before the page's handler (bound in any order)
        var wbtn = document.getElementById('addWasteBtn');
        if (wbtn) wbtn.addEventListener('click', function (e) { e.stopImmediatePropagation(); e.preventDefault(); reportForm(); }, true);
        function reportForm() {
            if (!M) return;
            if (!M.can.report) return Swal.fire({ icon: 'info', title: 'You cannot report waste', text: 'Ask the Manager to give you waste reporting.', confirmButtonColor: '#1c5034' });
            Swal.fire($.extend({}, POP, { title: 'Report waste', width: 680, confirmButtonText: 'Send for approval',
                html: '<div class="pp-sub">The Manager approves it — only then the stock goes down.</div><div class="pp-form">' +
                    F('Product', I('wP', '', 'list="wPl" placeholder="Type to search the product"') + '<datalist id="wPl">' + M.products.map(function (p) { return '<option value="' + esc(p.name) + ' · #' + p.id + '">'; }).join('') + '</datalist>', { full: true, hint: '(leave empty for non-product waste)' }) +
                    F('Type', SEL('wT', Object.keys(TYPES).map(function (k) { return [k, TYPES[k]]; }))) + F('Date', I('wD', today(), 'type="date"')) +
                    F('Quantity', I('wQ', '', 'type="number" min="1" step="1" placeholder="e.g. 2"')) + F('Unit', I('wU', 'pcs')) +
                    F('Warehouse', SEL('wW', M.warehouses.length ? M.warehouses.map(function (w) { return [w.id, w.name]; }) : [[1, 'Main']])) + F('Estimated value ₹', I('wV', '', 'type="number" min="0" step="0.01" placeholder="auto from price"')) +
                    F('Reason', I('wR', '', 'placeholder="e.g. Pack torn while unloading, rats, expired on shelf"'), { full: true, req: true }) +
                    F('Disposal method', I('wM', '', 'placeholder="e.g. Given to cattle feed, destroyed"'), { full: true, hint: 'optional' }) + '</div>',
                didOpen: function () { $('#wP,#wQ').on('change', function () { var $p = $('#wP'); var m = String($p.val()).match(/#(\d+)$/), p = m && M.products.find(function (x) { return String(x.id) === m[1]; }); if (p && !$('#wV').val() && $('#wQ').val()) $('#wV').val((E2(p.price) * E2($('#wQ').val())).toFixed(2)); }); },
                preConfirm: function () {
                    var m = String($('#wP').val()).match(/#(\d+)$/), reason = $('#wR').val().trim();
                    if ($('#wP').val() && !m) { Swal.showValidationMessage('Choose the product from the list'); return false; }
                    if (m && !(+$('#wQ').val() > 0)) { Swal.showValidationMessage('Enter the quantity (whole packs)'); return false; }
                    if (!reason) { Swal.showValidationMessage('Write the reason'); return false; }
                    return post('save_waste_record.php', { date: $('#wD').val(), waste_type: $('#wT').val(), product_id: m ? m[1] : '', sku: m ? 'PRD-' + m[1] : '', quantity: $('#wQ').val(), unit: $('#wU').val() || 'pcs',
                                                          warehouse_id: $('#wW').val(), reason: reason, estimated_value: $('#wV').val(), disposal_method: $('#wM').val() })
                        .catch(function (e) { Swal.showValidationMessage(esc(e)); return false; });
                } })).then(function (r) { if (r.isConfirmed && r.value) { Swal.fire({ icon: 'success', title: (r.value.waste_id || 'Waste') + ' sent for approval', timer: 1500, showConfirmButton: false }); meta(); window.loadWaste(); } });
        }
        var E2 = function (v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; };
        meta().then(function () { window.loadWaste(); }).catch(function () {});
    }

    /* ---------------------------------------------------------------- audit log */
    function auditPage() {
        var $ = window.jQuery; if (!$ || typeof window.displayLogs !== 'function') return;
        var NICE = function (m) { return String(m || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); };
        var LINK = { purchase_requests: 'purchase_flow.php?pr_id=', purchase_orders: 'purchase_orders.php?id=', goods_receipts: 'goods_receipts.php?id=', quality_checks: 'quality_checks.php?id=', suppliers: 'supplier_360.php?id=',
                     assets: 'asset_detail.php?id=', stock_adjustments: 'stock_adjustments.php?id=', purchase_flows: 'purchase_flow.php?pr_id=', purchase_flow_extras: 'purchase_flow.php?pr_id=', purchase_invoices: 'purchase_invoices.php?id=' };
        var TONE = { create: 'green', update: 'info', delete: 'red', approve: 'green', reject: 'red', cancel: 'amber', post: 'green', assign: 'info', status_change: 'amber' };
        var parse = function (s) { if (!s) return null; try { return JSON.parse(s); } catch (e) { return s; } };
        var flat = function (v) { return v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v); };
        function changes(l) {
            var o = parse(l.old_value), n = parse(l.new_value), out = [];
            if (o && n && typeof o === 'object' && typeof n === 'object' && !Array.isArray(o)) Object.keys(Object.assign({}, o, n)).forEach(function (k) { if (flat(o[k]) !== flat(n[k])) out.push([k, o[k], n[k]]); });
            else if (n && typeof n === 'object' && !Array.isArray(n)) Object.keys(n).forEach(function (k) { out.push([k, undefined, n[k]]); });
            return { o: o, n: n, list: out };
        }
        var box = $('<div id="ppAudit"></div>'); $('.adm-main .adm-topbar').after(box);
        get('audit_meta').then(function (r) {
            box.html('<div class="pp-kpis">' + kpi(r.today, 'Actions today', 'green') + kpi(r.week, 'Actions in the last 7 days') + kpi(r.approvals, 'Approvals / rejections (7 days)', 'info') +
                (r.users.length ? '<div class="pp-kpi" style="grid-column:span 2"><div class="l" style="margin-bottom:6px">Most active (7 days)</div>' + r.users.map(function (u) { return '<span class="pp-chip" style="margin:0 6px 6px 0">' + esc(u.username) + ' · ' + u.n + '</span>'; }).join('') + '</div>' : '') + '</div>');
        }).catch(function () {});
        window.displayLogs = function (logs) {
            var seen = {}; logs = logs.filter(function (l) { if (seen[l.id]) return false; seen[l.id] = 1; return true; });   // two loads may finish together
            if (!logs.length) { $('#logsTable').html('<div class="adm-empty"><i class="fas fa-clipboard-list"></i><p><strong>No log entries</strong></p><p>Try adjusting the filters.</p></div>'); return; }
            var rows = logs.map(function (l, i) {
                var c = changes(l), link = LINK[l.module] && l.record_id ? LINK[l.module] + encodeURIComponent(l.record_id) : '';
                var what = c.list.slice(0, 3).map(function (x) { return '<b>' + esc(NICE(x[0])) + '</b>' + (x[1] !== undefined ? ' ' + esc(flat(x[1]).slice(0, 30)) + ' → ' : ': ') + esc(flat(x[2]).slice(0, 40)); }).join(' · ') + (c.list.length > 3 ? ' <span class="pp-muted">+' + (c.list.length - 3) + ' more</span>' : '');
                return '<tr><td style="white-space:nowrap">' + dt(l.created_at) + '</td><td><strong>' + esc(l.username || '—') + '</strong></td><td><span class="pp-chip is-' + (TONE[l.action] || '') + '">' + esc(NICE(l.action)) + '</span></td>' +
                    '<td>' + esc(NICE(l.module)) + '</td><td>' + (link ? '<a class="erp-link" href="' + link + '">#' + esc(l.record_id) + '</a>' : esc(l.record_id ? '#' + l.record_id : '—')) + '</td><td class="adm-cell-sub" style="max-width:420px">' + (what || '—') + '</td>' +
                    '<td><button class="adm-icon-btn pp-log" data-i="' + i + '" title="See before / after"><i class="fas fa-eye"></i></button></td></tr>';
            }).join('');
            $('#logsTable').html('<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>What changed</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>');
            $('#logsTable').off('click.pp').on('click.pp', '.pp-log', function () { window.viewLogDetails(logs[+$(this).data('i')]); });
        };
        window.viewLogDetails = function (l) {
            var c = changes(l);
            var tbl = c.list.length ? '<table style="width:100%;border-collapse:collapse;font-size:13px;text-align:left"><thead><tr style="background:#f7f4ee"><th style="padding:7px">Field</th><th style="padding:7px">Before</th><th style="padding:7px">After</th></tr></thead><tbody>' +
                c.list.map(function (x) { return '<tr><td style="padding:7px;border-top:1px solid #eee;font-weight:600">' + esc(NICE(x[0])) + '</td><td style="padding:7px;border-top:1px solid #eee;color:#a8442f;word-break:break-word">' + esc(x[1] === undefined ? '—' : flat(x[1])) + '</td><td style="padding:7px;border-top:1px solid #eee;color:#1c5034;word-break:break-word">' + esc(flat(x[2])) + '</td></tr>'; }).join('') + '</tbody></table>'
                : '<pre style="text-align:left;background:#f5f5f0;padding:8px;border-radius:6px;max-height:240px;overflow:auto;font-size:12px">' + esc(typeof c.n === 'object' ? JSON.stringify(c.n, null, 2) : flat(c.n)) + '</pre>';
            var link = LINK[l.module] && l.record_id ? '<a class="erp-link" href="' + LINK[l.module] + encodeURIComponent(l.record_id) + '">Open the record</a>' : '';
            Swal.fire({ title: NICE(l.module) + (l.record_id ? ' #' + esc(l.record_id) : ''), width: 760, confirmButtonText: 'Close', confirmButtonColor: '#1c5034',
                html: '<div style="text-align:left"><div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px"><span class="pp-chip">' + esc(l.username || '—') + '</span><span class="pp-chip is-' + (TONE[l.action] || '') + '">' + esc(NICE(l.action)) + '</span><span class="pp-chip">' + dt(l.created_at) + '</span>' + link + '</div>' + tbl + '</div>' });
        };
        window.loadLogs(true);
    }

    function start() {
        profile();
        if (page === 'assets.php') assetsPage();
        if (page === 'waste.php') wastePage();
        if (page === 'audit_logs.php') auditPage();
    }
    if (document.readyState === 'complete') start(); else window.addEventListener('load', start);
})();
