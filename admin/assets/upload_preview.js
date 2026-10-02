/* ============================================================================
 * Preview before upload (added 2 Oct 2026) — every file chosen in the admin
 * (proofs, bills, photos, DC, reports, CSV sheets) is shown first: image /
 * PDF / text preview + name and size. "Use this file" keeps it (the page then
 * uploads it as before); "Choose another" / "Cancel" clears the choice.
 * Works for all pages without touching their code: it runs before the page's
 * own change handlers and passes the event on only after the user confirms.
 * ========================================================================== */
(function () {
    'use strict';
    if (window.__uploadPreview) return; window.__uploadPreview = true;
    var css = '.upv-ov{position:fixed;inset:0;background:rgba(20,24,18,.55);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px}' +
        '.upv-box{background:#fff;border-radius:14px;max-width:880px;width:100%;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);font-family:inherit}' +
        '.upv-h{padding:14px 18px;border-bottom:1px solid #ece6da;font-weight:700;font-size:16px;color:#1c5034;display:flex;justify-content:space-between;gap:10px;align-items:center}' +
        '.upv-b{padding:14px 18px;overflow:auto;flex:1;display:flex;flex-direction:column;gap:14px}' +
        '.upv-f{border:1px solid #ece6da;border-radius:10px;overflow:hidden}.upv-m{padding:8px 12px;font-size:13px;background:#faf8f2;color:#3b3a33;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}' +
        '.upv-f img{display:block;max-width:100%;max-height:60vh;margin:0 auto;background:#f4f1ea}.upv-f iframe{display:block;width:100%;height:60vh;border:0;background:#f4f1ea}' +
        '.upv-f pre{margin:0;padding:10px 12px;max-height:40vh;overflow:auto;font-size:12px;background:#fff;white-space:pre-wrap}.upv-x{padding:22px;text-align:center;color:#6b6459;font-size:13.5px}' +
        '.upv-warn{background:#fbe7e3;color:#a8442f;border-radius:8px;padding:8px 12px;font-size:13px}' +
        '.upv-a{padding:12px 18px;border-top:1px solid #ece6da;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}' +
        '.upv-a button{border-radius:8px;padding:9px 16px;font-weight:600;font-size:14px;cursor:pointer;border:1px solid #d9d2c3;background:#fff;color:#23281f;font-family:inherit}' +
        '.upv-a .upv-ok{background:#1c5034;border-color:#1c5034;color:#fff}';
    var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); };
    var size = function (b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };

    function show(input) {
        var files = [].slice.call(input.files || []), urls = [];
        var ov = document.createElement('div'); ov.className = 'upv-ov';
        var body = files.map(function (f, i) {
            var type = f.type || '', name = f.name.toLowerCase(), big = f.size > 10 * 1048576, prev = '';
            if (/^image\//.test(type)) { var u = URL.createObjectURL(f); urls.push(u); prev = '<img src="' + u + '" alt="preview">'; }
            else if (type === 'application/pdf' || /\.pdf$/.test(name)) { var p = URL.createObjectURL(f); urls.push(p); prev = '<iframe src="' + p + '#toolbar=0" title="PDF preview"></iframe>'; }
            else if (/\.(csv|txt)$/.test(name) || /^text\//.test(type)) prev = '<pre data-upv-text="' + i + '">Reading…</pre>';
            else prev = '<div class="upv-x"><strong>' + esc(f.name) + '</strong><br>No preview for this file type — check the name and size before you continue.</div>';
            return '<div class="upv-f"><div class="upv-m"><strong>' + esc(f.name) + '</strong><span>' + size(f.size) + (type ? ' · ' + esc(type) : '') + '</span></div>' + prev + '</div>' +
                (big ? '<div class="upv-warn">' + esc(f.name) + ' is larger than 10 MB — it may be refused. Choose a smaller file.</div>' : '');
        }).join('');
        ov.innerHTML = '<div class="upv-box" role="dialog" aria-modal="true"><div class="upv-h">Check the file before uploading<span style="font-weight:500;font-size:13px;color:#6b6459">' + files.length + ' file(s)</span></div>' +
            '<div class="upv-b">' + body + '</div><div class="upv-a"><button type="button" data-upv="cancel">Cancel</button><button type="button" data-upv="again">Choose another file</button><button type="button" class="upv-ok" data-upv="ok">Use this file</button></div></div>';
        document.body.appendChild(ov);
        files.forEach(function (f, i) {
            var pre = ov.querySelector('[data-upv-text="' + i + '"]'); if (!pre) return;
            var rd = new FileReader(); rd.onload = function () { var t = String(rd.result || ''); pre.textContent = t.slice(0, 4000) + (t.length > 4000 ? '\n…' : ''); }; rd.readAsText(f.slice(0, 8000));
        });
        var close = function () { urls.forEach(function (u) { URL.revokeObjectURL(u); }); ov.remove(); };
        ov.addEventListener('click', function (e) {
            var b = e.target.closest('[data-upv]'); if (!b) return;
            var a = b.getAttribute('data-upv'); close();
            if (a === 'ok') { input.__upvOk = true; input.dispatchEvent(new Event('change', { bubbles: true })); input.__upvOk = false; return; }
            input.value = '';
            if (a === 'again') setTimeout(function () { input.click(); }, 50);
        });
        var ok = ov.querySelector('[data-upv="ok"]'); if (ok) ok.focus();
    }
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || t.tagName !== 'INPUT' || t.type !== 'file' || t.__upvOk || t.hasAttribute('data-no-preview')) return;
        if (!t.files || !t.files.length) return;
        e.stopImmediatePropagation();
        show(t);
    }, true);
})();
