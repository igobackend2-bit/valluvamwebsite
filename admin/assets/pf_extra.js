/* ============================================================================
 * Purchase Flow — extra delivery proofs (added 1 Oct 2026)
 *   Step 6 Loading:   loading photo + quantity written on the shop bill
 *   Step 7 Unloading: unloading photo + who checked the quantity (name + digital signature)
 *   Step 8 Quality:   who checked the quality + report (+ file)
 *   "All proofs" card at the top: every file of this purchase in one place.
 * Adds to the page after each render; the page's own steps are unchanged.
 * Needs pf_extra_api.php (+ purchase_flow_extras_migration.sql) and erp.js (ERP).
 * ========================================================================== */
(function () {
    'use strict';
    function boot() {
        var E = window.ERP, $ = window.jQuery; if (!E || !$) return;
        var cards = document.getElementById('cards'); if (!cards) return;
        var API = 'pf_extra_api.php', X = null, busy = false;
        var esc = E.esc, prId = function () { return +(new URLSearchParams(location.search).get('pr_id') || 0); };
        $('<style>').text(
            '.px-box{border:1px solid var(--adm-line);border-radius:var(--adm-radius-md);padding:12px 14px;margin-top:12px;background:var(--adm-cream)}' +
            '.px-box h4{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--adm-green-dark)}' +
            '.px-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:6px 0}.px-row .lbl{min-width:190px;font-weight:600}' +
            '.px-ok{color:var(--adm-green);font-weight:600}.px-miss{color:#a8442f;font-weight:600}' +
            '.px-thumb{width:88px;height:66px;object-fit:cover;border-radius:8px;border:1px solid var(--adm-line);background:#fff}' +
            '.px-sig{border:1px dashed #9aa39b;border-radius:10px;background:#fff;touch-action:none;width:100%;max-width:420px;height:150px;display:block;cursor:crosshair}' +
            '.px-gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}' +
            '.px-card{border:1px solid var(--adm-line);border-radius:10px;background:#fff;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column}' +
            '.px-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}.px-card .im{height:110px;background:#f3f0e8;display:flex;align-items:center;justify-content:center;font-size:30px;color:#9aa39b}' +
            '.px-card .im img{width:100%;height:100%;object-fit:cover}.px-card .tx{padding:8px 10px;font-size:12.5px}.px-card .tx b{display:block;font-size:13px;color:var(--adm-ink)}' +
            '.px-step{display:inline-block;font-size:11px;font-weight:700;color:#1c5034;background:#e8f1eb;border-radius:20px;padding:1px 8px;margin-bottom:3px}' +
            '.px-tbl td,.px-tbl th{padding:6px 8px}.px-tbl input{max-width:120px}').appendTo('head');

        var dl = function (d) { return E.BASE + 'erp_docs.php?action=download&id=' + d.id; };
        var isImg = function (d) { return /image/.test(d.mime_type || '') || /\.(png|jpe?g|webp)$/i.test(d.original_name || ''); };
        var docView = function (d) { return d ? (isImg(d) ? '<a href="' + dl(d) + '" target="_blank" rel="noopener"><img class="px-thumb" src="' + dl(d) + '" alt=""></a> ' : '') + '<a class="erp-link" href="' + dl(d) + '" target="_blank" rel="noopener">' + esc(d.original_name) + '</a> <span class="erp-muted">' + E.date(d.created_at) + ' · ' + esc(d.uploaded_by || '') + '</span>' : '<span class="px-miss">missing</span>'; };
        function upload(file, category, desc) {
            var ext = String(file.name || '').split('.').pop().toLowerCase();
            if (['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'].indexOf(ext) < 0) return Promise.reject('Allowed files: photo (JPG, PNG, WEBP), PDF or Word.');
            if (file.size > 10 * 1048576) return Promise.reject('Files must be 10 MB or smaller.');
            var fd = new FormData(); fd.append('action', 'upload'); fd.append('entity_type', 'purchase_order'); fd.append('entity_id', X.po.id); fd.append('category', category); fd.append('description', desc); fd.append('file', file);
            return new Promise(function (ok, no) { $.ajax({ url: E.BASE + 'erp_docs.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(function (r) { r && r.status === 'success' ? ok(r.id) : no((r && r.message) || 'Upload failed'); }).fail(function () { no('Upload failed'); }); });
        }
        var save = function (data) { return E.post(API, $.extend({ action: 'save', pr_id: prId() }, data), { silent: true }); };
        var fail = function (m) { if (m) Swal.fire({ icon: 'error', title: 'Not saved', text: String(m), confirmButtonColor: '#1c5034' }); };
        var done = function (r) { E.toast(r.message || 'Saved'); X = null; inject(true); };
        var photoRow = function (part, label, d) {
            return '<div class="px-row"><span class="lbl">' + label + '</span>' + docView(d) + (X.can ? ' <input type="file" accept="image/*,.pdf" capture="environment" data-px-file="' + part + '"><button class="adm-btn adm-btn-ghost" data-px-photo="' + part + '">' + (d ? 'Replace' : 'Attach') + '</button>' : '') + '</div>';
        };

        function loadingBox() {
            var x = X.extras || {}, bill = {}; try { (JSON.parse(x.shop_bill_lines || '[]') || []).forEach(function (l) { bill[l.po_item_id] = l.bill_qty; }); } catch (e) {}
            var rows = X.items.map(function (i) {
                var b = bill[i.id], diff = b !== undefined && Math.abs(E.num(b) - E.num(i.quantity)) > 0.0005;
                return '<tr data-pi="' + i.id + '"><td>' + esc(i.item_name) + '</td><td class="erp-num">' + E.qty(i.quantity, i.unit) + '</td><td>' + (X.can ? '<input class="adm-input" type="number" min="0" step="any" value="' + (b !== undefined ? E.num(b) : '') + '" placeholder="qty">' : (b !== undefined ? E.qty(b) : '—')) + '</td><td>' + (b === undefined ? '<span class="erp-muted">—</span>' : diff ? '<span class="px-miss">differs from the order</span>' : '<span class="px-ok">✓ same as order</span>') + '</td></tr>';
            }).join('');
            return '<div class="px-box" data-px="loading"><h4><i class="fas fa-camera"></i> Loading proof</h4>' + photoRow('loading_photo', 'Loading photo (goods in the vehicle)', X.docs.loading_photo) +
                '<div class="px-row"><span class="lbl">Quantity on the shop bill</span><span class="erp-muted">Type the quantity written on the shop bill for each item.</span></div>' +
                '<div class="adm-table-wrap"><table class="adm-table px-tbl"><thead><tr><th>Item</th><th class="erp-num">Ordered</th><th>On the shop bill</th><th>Check</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
                (X.can ? '<div class="px-row"><span class="lbl">Bill total ₹ (optional)</span><input class="adm-input" id="pxBillTot" type="number" min="0" step="any" style="max-width:160px" value="' + (x.shop_bill_total || '') + '"><button class="adm-btn adm-btn-primary" id="pxBillSave"><i class="fas fa-floppy-disk"></i> Save bill quantities</button></div>' : (x.shop_bill_total ? '<div class="erp-note">Bill total ' + E.money(x.shop_bill_total) + '</div>' : '')) + '</div>';
        }
        function unloadBox() {
            var x = X.extras || {};
            var signed = x.unload_checker_name && X.docs.signature;
            return '<div class="px-box" data-px="unload"><h4><i class="fas fa-signature"></i> Unloading proof & quantity checker</h4>' + photoRow('unloading_photo', 'Unloading photo (at our warehouse)', X.docs.unloading_photo) +
                (signed ? '<div class="px-row"><span class="lbl">Quantity checked by</span><strong>' + esc(x.unload_checker_name) + '</strong> <span class="erp-muted">signed ' + E.date(x.unload_signed_at) + '</span></div><div class="px-row"><span class="lbl">Signature</span><img src="' + dl(X.docs.signature) + '" alt="signature" style="max-width:260px;max-height:110px;border:1px solid var(--adm-line);border-radius:8px;background:#fff"></div>' : '<div class="px-row"><span class="lbl">Quantity checked by</span><span class="px-miss">not signed yet</span></div>') +
                (X.can ? '<div class="px-row"><span class="lbl">' + (signed ? 'Sign again' : 'Checker name *') + '</span><input class="adm-input" id="pxSigName" style="max-width:260px" placeholder="Full name of the person who counted" value="' + esc(signed ? x.unload_checker_name : (X.me || '')) + '"></div>' +
                    '<div class="px-row" style="align-items:flex-start"><span class="lbl">Digital signature *</span><div style="flex:1 1 300px"><canvas class="px-sig" id="pxSig" width="840" height="300"></canvas><div class="px-row"><button class="adm-btn adm-btn-ghost" id="pxSigClear"><i class="fas fa-eraser"></i> Clear</button><button class="adm-btn adm-btn-primary" id="pxSigSave"><i class="fas fa-file-signature"></i> Save name & signature</button><span class="erp-muted">Sign with finger or mouse.</span></div></div></div>' : '') + '</div>';
        }
        function qcBox() {
            var x = X.extras || {}, q = X.qc;
            return '<div class="px-box" data-px="qc"><h4><i class="fas fa-clipboard-check"></i> Quality checked by & report</h4>' +
                (q ? '<div class="px-row"><span class="lbl">Quality check record</span>' + esc(q.qc_number) + ' ' + E.badge(q.status) + (q.inspected_by ? ' · inspected by <strong>' + esc(q.inspected_by) + '</strong>' : '') + (q.notes ? ' · ' + esc(q.notes) : '') + '</div>' : '') +
                (x.qc_inspector_name ? '<div class="px-row"><span class="lbl">Checked by</span><strong>' + esc(x.qc_inspector_name) + '</strong> <span class="erp-muted">' + E.date(x.qc_reported_at) + '</span></div><div class="px-row"><span class="lbl">Report</span><span style="white-space:pre-wrap">' + esc(x.qc_report || '') + '</span></div>' : '<div class="px-row"><span class="lbl">Report</span><span class="px-miss">not written yet</span></div>') +
                (X.docs.qc_report ? '<div class="px-row"><span class="lbl">Report file / photo</span>' + docView(X.docs.qc_report) + '</div>' : '') +
                (X.can ? '<div class="px-row"><span class="lbl">Checked by (name) *</span><input class="adm-input" id="pxQcName" style="max-width:260px" value="' + esc(x.qc_inspector_name || X.me || '') + '"></div>' +
                    '<div class="px-row" style="align-items:flex-start"><span class="lbl">Quality report *</span><textarea class="adm-input" id="pxQcRep" rows="3" style="flex:1 1 300px" placeholder="Colour, smell, moisture, broken pieces, packing, samples checked…">' + esc(x.qc_report || '') + '</textarea></div>' +
                    '<div class="px-row"><span class="lbl">Report file / photo</span><input type="file" id="pxQcFile" accept="image/*,.pdf,.doc,.docx"><button class="adm-btn adm-btn-primary" id="pxQcSave"><i class="fas fa-floppy-disk"></i> Save quality report</button></div>' : '') + '</div>';
        }
        function proofsCard() {
            var p = X.proofs || [];
            return '<section class="adm-card pf-card" id="card-proofs"><div class="adm-card-head"><h2><i class="fas fa-images"></i> All proofs of this purchase <span class="adm-badge is-' + (p.length ? 'green' : 'neutral') + '">' + p.length + '</span></h2></div><div class="adm-card-body">' +
                (p.length ? '<div class="px-gal">' + p.map(function (d) {
                    return '<a class="px-card" href="' + dl(d) + '" target="_blank" rel="noopener"><div class="im">' + (isImg(d) ? '<img src="' + dl(d) + '" alt="" loading="lazy">' : '<i class="fas ' + (/pdf/.test(d.mime_type || '') ? 'fa-file-pdf' : 'fa-file-lines') + '"></i>') + '</div><div class="tx"><span class="px-step">' + esc(d.step) + '</span><b>' + esc(d.label) + '</b><span class="erp-muted">' + E.date(d.created_at) + ' · ' + esc(d.uploaded_by || '') + '</span></div></a>';
                }).join('') + '</div>' : '<div class="erp-note">No proofs attached yet — payment proof, transport receipt, loading / unloading DC, photos, shop bill, signature and quality report appear here.</div>') + '</div></section>';
        }
        function inject(force) {
            if (!prId() || busy) return;
            if (!document.getElementById('card-docs') && !document.getElementById('card-qc')) return;
            if (!force && document.getElementById('card-proofs')) return;
            busy = true;
            (X && !force ? Promise.resolve(X) : E.api(API, { action: 'get', pr_id: prId() }, { silent: true })).then(function (r) {
                X = r; busy = false;
                $('#card-proofs').remove(); $('[data-px]').remove();
                $('#cards').prepend(proofsCard());
                if (!r.installed) { $('#card-proofs .adm-card-body').append('<div class="erp-warn">Run purchase_flow_extras_migration.sql once to switch on photos, bill quantity, signature and quality report.</div>'); return; }
                if (r.po && ['approved', 'partially_received', 'fully_received', 'closed'].indexOf(r.po.status) >= 0) {
                    $('#card-docs .adm-card-body').append(loadingBox());
                    $('#card-unload .adm-card-body').append(unloadBox());
                }
                if (r.qc || (X.extras && X.extras.qc_report) || $('#card-qc .adm-card-body').text().indexOf('Starts after') < 0) $('#card-qc .adm-card-body').append(qcBox());
                bindSig();
            }).catch(function () { busy = false; });
        }
        function bindSig() {
            var c = document.getElementById('pxSig'); if (!c) return;
            var ctx = c.getContext('2d'), drawing = false, inked = false;
            ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1b2a4a';
            var pos = function (e) { var r = c.getBoundingClientRect(); return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height]; };
            c.addEventListener('pointerdown', function (e) { drawing = true; inked = true; c.setPointerCapture(e.pointerId); var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
            c.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); });
            ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (ev) { c.addEventListener(ev, function () { drawing = false; }); });
            $('#pxSigClear').on('click', function () { ctx.clearRect(0, 0, c.width, c.height); inked = false; });
            $('#pxSigSave').on('click', function () {
                var name = $('#pxSigName').val().trim();
                if (!name) return fail('Enter the name of the person who checked the quantity.');
                if (!inked) return fail('Please sign in the box.');
                var $b = $(this).prop('disabled', true);
                // white background so the signature prints clearly
                var o = document.createElement('canvas'); o.width = c.width; o.height = c.height; var oc = o.getContext('2d'); oc.fillStyle = '#fff'; oc.fillRect(0, 0, o.width, o.height); oc.drawImage(c, 0, 0);
                o.toBlob(function (blob) {
                    var file = new File([blob], 'unloading-signature-' + name.replace(/[^a-z0-9]+/gi, '-') + '.png', { type: 'image/png' });
                    upload(file, 'GRN', 'Unloading checker signature — ' + name).then(function (id) { return save({ part: 'unload_sign', name: name, doc_id: id }); }).then(done).catch(function (m) { $b.prop('disabled', false); fail(m); });
                }, 'image/png');
            });
        }
        $(document).on('click', '[data-px-photo]', function () {
            var part = $(this).data('px-photo'), f = $('[data-px-file="' + part + '"]')[0].files[0];
            if (!f) return fail('Choose the photo first (you can take it with the phone camera).');
            var $b = $(this).prop('disabled', true);
            upload(f, 'GRN', part === 'loading_photo' ? 'Loading photo' : 'Unloading photo').then(function (id) { return save({ part: part, doc_id: id }); }).then(done).catch(function (m) { $b.prop('disabled', false); fail(m); });
        });
        $(document).on('click', '#pxBillSave', function () {
            var lines = $('[data-px=loading] tr[data-pi]').map(function () { var v = $(this).find('input').val(); return v === '' ? null : { po_item_id: $(this).data('pi'), bill_qty: v }; }).get().filter(Boolean);
            if (!lines.length) return fail('Type the quantity on the shop bill for at least one item.');
            save({ part: 'bill_qty', lines: JSON.stringify(lines), bill_total: $('#pxBillTot').val() }).then(done).catch(fail);
        });
        $(document).on('click', '#pxQcSave', function () {
            var name = $('#pxQcName').val().trim(), rep = $('#pxQcRep').val().trim(), f = $('#pxQcFile')[0].files[0];
            if (!name || !rep) return fail('Enter who checked the quality and write the report.');
            var $b = $(this).prop('disabled', true);
            (f ? upload(f, 'QC_DOCUMENT', 'Quality check report — ' + name) : Promise.resolve(0)).then(function (id) { return save({ part: 'qc_report', name: name, report: rep, doc_id: id || '' }); }).then(done).catch(function (m) { $b.prop('disabled', false); fail(m); });
        });
        new MutationObserver(function () { if (!document.getElementById('card-proofs') && (document.getElementById('card-docs') || document.getElementById('card-qc'))) inject(true); })
            .observe(cards, { childList: true });
        inject(true);
    }
    if (document.readyState === 'complete') boot(); else window.addEventListener('load', boot);
})();
