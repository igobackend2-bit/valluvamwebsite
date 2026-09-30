/* ==========================================================================
   Shared UI helpers for the Purchase / Costing / P&L admin pages (30 Sep 2026)
   jQuery + SweetAlert2, same as the rest of the Valluvam admin.
   Money math shown in the browser is a PREVIEW only — the server recalculates
   every total (erp_line() in erp_helper.php) and stores its own figures.
   ========================================================================== */
window.ERP = (function ($) {
    const BASE = '../assets/db_query/admin/';
    const cache = {};

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); }
    function num(v) { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return isFinite(n) ? n : 0; }
    function r2(v) { return Math.round((num(v) + Number.EPSILON) * 100) / 100; }
    function money(v, dash) { if ((v === null || v === undefined || v === '') && dash) return '—'; const n = num(v); return (n < 0 ? '-₹' : '₹') + Math.abs(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function qty(v, unit) { const n = num(v); const s = Number.isInteger(n) ? String(n) : n.toLocaleString('en-IN', { maximumFractionDigits: 3 }); return unit ? s + ' ' + esc(unit) : s; }
    function date(s) { if (!s) return '—'; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? esc(s) : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); }
    function today() { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function monthStart() { return today().slice(0, 8) + '01'; }

    const STATUS = {
        draft: 'neutral', submitted: 'amber', pending_approval: 'amber', approved: 'info', rejected: 'red', converted: 'green', cancelled: 'red', closed: 'neutral',
        partially_received: 'amber', fully_received: 'green', posted: 'green', pending: 'amber', completed: 'green',
        unpaid: 'red', partially_paid: 'amber', paid: 'green', overdue: 'red', credit_note: 'info', refund: 'info', replacement: 'neutral',
        passed: 'green', failed: 'red', partial: 'amber', active: 'green', inactive: 'neutral', expired: 'red', expiring: 'amber', ok: 'green', none: 'neutral',
        sent: 'info', quoted: 'amber', awarded: 'green', received: 'green', accepted: 'green', partially_accepted: 'amber', in_transit: 'amber', arrived: 'green', planned: 'neutral',
        partially_passed: 'amber', under_review: 'amber', issued: 'info', adjusted: 'green', refunded: 'green', reversed: 'neutral', open: 'amber', critical: 'red', warning: 'amber', info: 'info',
        matched: 'green', unmatched: 'amber', ignored: 'neutral', empty: 'neutral', critical_: 'red', picked: 'info', packed: 'info', dispatched: 'amber', delivered: 'green', returned: 'red'
    };
    function badge(s, label) { if (!s) return '—'; return `<span class="adm-badge is-${STATUS[s] || 'neutral'}">${esc(label || String(s).replace(/_/g, ' '))}</span>`; }

    function toast(msg, icon) { Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: msg, showConfirmButton: false, timer: 2600 }); }
    function alertError(msg) { return Swal.fire({ title: 'Could not complete', text: msg || 'Please try again.', icon: 'error', confirmButtonColor: '#1c5034' }); }

    /** GET or POST to an endpoint. Arrays/objects in params are JSON-encoded. Resolves with the JSON, rejects with a message. */
    function api(file, params, opts) {
        opts = opts || {};
        const data = {};
        Object.keys(params || {}).forEach(k => { const v = params[k]; data[k] = (v !== null && typeof v === 'object' && !(v instanceof File)) ? JSON.stringify(v) : v; });
        return new Promise((resolve, reject) => {
            $.ajax({ url: BASE + file, type: opts.post ? 'POST' : 'GET', data: data, dataType: 'json' })
                .done(res => {
                    if (res && res.status === 'success') return resolve(res);
                    const m = (res && res.message) || 'The server returned an error.';
                    if (!opts.silent) alertError(m);
                    reject(m);
                })
                .fail(xhr => {
                    const m = xhr.status === 403 ? 'You do not have permission to do this.' : 'Could not reach the server. Check your connection and try again.';
                    if (!opts.silent) alertError(m);
                    reject(m);
                });
        });
    }
    const post = (file, params, opts) => api(file, params, Object.assign({ post: true }, opts || {}));

    function loading($el) { $el.html('<div class="adm-table-wrap"><table class="adm-table"><tbody>' + '<tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>'.repeat(3) + '</tbody></table></div>'); }
    function errorBox($el, msg, retry) {
        $el.html(`<div class="adm-error">${esc(msg)} ${retry ? '<button class="adm-btn adm-btn-ghost erp-retry" style="margin-left:8px;">Retry</button>' : ''}</div>`);
        if (retry) $el.find('.erp-retry').on('click', retry);
    }

    /** cols: [{key,label,render(row),num:true,cls}] */
    function table($el, cols, rows, opts) {
        opts = opts || {};
        if (!rows || !rows.length) {
            $el.html(`<div class="adm-empty"><i class="fas ${opts.icon || 'fa-inbox'}"></i><p><strong>${esc(opts.empty || 'Nothing here yet')}</strong></p>${opts.emptyHint ? '<p>' + esc(opts.emptyHint) + '</p>' : ''}</div>`);
            return;
        }
        const head = cols.map(c => `<th class="${c.num ? 'erp-num' : ''}">${esc(c.label)}</th>`).join('');
        const body = rows.map((r, i) => '<tr data-i="' + i + '">' + cols.map(c => {
            const v = c.render ? c.render(r, i) : esc(r[c.key] ?? '—');
            return `<td class="${c.num ? 'erp-num' : ''} ${c.cls || ''}">${v}</td>`;
        }).join('') + '</tr>').join('');
        const foot = opts.footer ? `<tfoot><tr>${opts.footer}</tr></tfoot>` : '';
        $el.html(`<div class="adm-table-wrap"><table class="adm-table"><thead><tr>${head}</tr></thead><tbody>${body}</tbody>${foot}</table></div>`);
        $el.data('rows', rows);
    }

    function csv(rows, cols, filename) {
        const q = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const lines = [cols.map(c => q(c.label)).join(',')].concat(rows.map(r => cols.map(c => q(c.csv ? c.csv(r) : r[c.key])).join(',')));
        const blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    }

    // ---------------- master data (cached)
    function suppliers() { return cache.sup ? Promise.resolve(cache.sup) : api('get_suppliers.php', {}, { silent: true }).then(r => (cache.sup = r.suppliers || [])).catch(() => []); }
    function warehouses() { return cache.wh ? Promise.resolve(cache.wh) : api('get_warehouses.php', {}, { silent: true }).then(r => (cache.wh = r.warehouses || [])).catch(() => [{ id: 1, name: 'Main Warehouse' }]); }
    function items(type) {
        const k = 'items' + (type || '');
        return cache[k] ? Promise.resolve(cache[k]) : api('inventory_ops_api.php', { action: 'items', type: type || '' }, { silent: true }).then(r => (cache[k] = r.items || [])).catch(() => []);
    }
    function options(list, valKey, labelFn, selected, placeholder) {
        return (placeholder !== false ? `<option value="">${esc(placeholder || 'Select…')}</option>` : '') +
            list.map(o => `<option value="${esc(o[valKey])}" ${String(o[valKey]) === String(selected ?? '') ? 'selected' : ''}>${esc(labelFn(o))}</option>`).join('');
    }
    function itemOptions(list, selType, selId) {
        const grp = (t, label) => {
            const g = list.filter(i => i.item_type === t);
            return g.length ? `<optgroup label="${label}">` + g.map(i => `<option value="${t}:${i.item_id}" data-unit="${esc(i.unit)}" ${t === selType && String(i.item_id) === String(selId) ? 'selected' : ''}>${esc(i.label)}${i.category ? ' — ' + esc(i.category) : ''} · stock ${qty(i.stock)}</option>`).join('') + '</optgroup>' : '';
        };
        return '<option value="">Select item…</option>' + grp('product', 'Product packs (sold on the website)') + grp('raw_material', 'Bulk raw materials (kg / L)');
    }
    function field(label, input, cls) { return `<div class="adm-field ${cls || ''}"><label>${esc(label)}</label>${input}</div>`; }
    function input(id, value, attrs) { return `<input class="adm-input" id="${id}" value="${esc(value ?? '')}" ${attrs || ''}>`; }
    function select(id, opts, attrs) { return `<select class="adm-select" id="${id}" ${attrs || ''}>${opts}</select>`; }
    function textarea(id, value) { return `<textarea class="adm-input" id="${id}">${esc(value ?? '')}</textarea>`; }

    /**
     * Line-item editor.  cfg.columns subset of: item, qty, rate, discount, tax, total
     * cfg.fixedItems: lines that can't change item (from PO/GRN) — pass rows with item_type/item_id/item_name.
     * Returns { get(), recalc() }.
     */
    function lineEditor($el, cfg) {
        const cols = cfg.columns || ['item', 'qty', 'rate', 'discount', 'tax', 'total'];
        const list = cfg.items || [];
        const head = { item: 'Item', qty: 'Qty', rate: 'Rate ₹', discount: 'Discount ₹', tax: 'GST %', total: 'Line total' };
        function rowHtml(l) {
            l = l || {};
            const cells = cols.map(c => {
                if (c === 'item') return `<td style="min-width:240px">${select('', itemOptions(list, l.item_type, l.item_id), 'data-f="item"')}</td>`;
                if (c === 'total') return '<td class="erp-num" data-f="total">₹0.00</td>';
                const v = { qty: l.quantity, rate: l.rate, discount: l.discount_amount, tax: l.tax_percent }[c];
                return `<td class="${c === 'tax' ? 'w-sm' : 'w-num'}"><input class="adm-input" type="number" min="0" step="any" data-f="${c}" value="${esc(v ?? (c === 'qty' ? '' : 0))}"></td>`;
            }).join('');
            return `<tr>${cells}<td><button type="button" class="adm-icon-btn is-danger" data-f="del" title="Remove line"><i class="fas fa-xmark"></i></button></td></tr>`;
        }
        $el.html(`<div class="adm-table-wrap"><table class="erp-lines"><thead><tr>${cols.map(c => `<th>${head[c]}</th>`).join('')}<th></th></tr></thead><tbody></tbody></table></div>
                  <button type="button" class="adm-btn adm-btn-ghost" data-f="add" style="margin-top:8px;"><i class="fas fa-plus"></i> Add line</button>
                  <div class="erp-totals" data-f="totals"></div>`);
        const $tb = $el.find('tbody');
        (cfg.lines && cfg.lines.length ? cfg.lines : [{}]).forEach(l => $tb.append(rowHtml(l)));
        function recalc() {
            if (!cols.includes('rate')) { $el.find('[data-f=totals]').empty(); return; }   // quantity-only lists (transfers, counts)
            let gross = 0, disc = 0, tax = 0;
            $tb.find('tr').each(function () {
                const $r = $(this);
                const q = num($r.find('[data-f=qty]').val()), rt = num($r.find('[data-f=rate]').val());
                const d = Math.min(num($r.find('[data-f=discount]').val()), r2(q * rt)), t = num($r.find('[data-f=tax]').val());
                const net = r2(r2(q * rt) - d), tx = r2(net * t / 100);
                gross += r2(q * rt); disc += d; tax += tx;
                $r.find('[data-f=total]').text(money(net + tx));
            });
            const extra = cfg.extra ? cfg.extra() : 0;
            $el.find('[data-f=totals]').html(`<span>Subtotal <strong>${money(gross)}</strong></span>` + (cols.includes('discount') ? `<span>Discount <strong>${money(disc)}</strong></span>` : '') +
                (cols.includes('tax') ? `<span>GST <strong>${money(tax)}</strong></span>` : '') + (extra ? `<span>Charges <strong>${money(extra)}</strong></span>` : '') +
                `<span>Total <strong>${money(gross - disc + tax + extra)}</strong></span>`);
        }
        $el.on('input change', 'input, select', recalc);
        $el.on('click', '[data-f=add]', () => { $tb.append(rowHtml({})); recalc(); });
        $el.on('click', '[data-f=del]', function () { $(this).closest('tr').remove(); if (!$tb.find('tr').length) $tb.append(rowHtml({})); recalc(); });
        recalc();
        return {
            recalc,
            get() {
                const out = [];
                $tb.find('tr').each(function () {
                    const $r = $(this);
                    const it = String($r.find('[data-f=item]').val() || '');
                    if (!it) return;
                    const [type, id] = it.split(':');
                    out.push({ item_type: type, item_id: id, quantity: $r.find('[data-f=qty]').val(), rate: $r.find('[data-f=rate]').val() ?? 0,
                               estimated_rate: $r.find('[data-f=rate]').val() ?? '', discount_amount: $r.find('[data-f=discount]').val() ?? 0, tax_percent: $r.find('[data-f=tax]').val() ?? 0 });
                });
                return out;
            }
        };
    }

    /** Opens a wide modal form. onSave(): returns a Promise (reject/throw to keep the modal open). */
    function form(title, html, onSave, opts) {
        opts = opts || {};
        return Swal.fire({
            title, html, width: opts.width || 980, customClass: { popup: 'erp-modal' }, showCancelButton: true, focusConfirm: false,
            confirmButtonText: opts.confirmText || 'Save', confirmButtonColor: '#1c5034', cancelButtonColor: '#6b6459',
            showDenyButton: !!opts.denyText, denyButtonText: opts.denyText, denyButtonColor: '#b8722e',
            didOpen: opts.didOpen, allowOutsideClick: false,
            preConfirm: () => onSave('confirm').catch(m => { Swal.showValidationMessage(esc(m || 'Please check the form')); return false; }),
            preDeny: opts.denyText ? () => onSave('deny').catch(m => { Swal.showValidationMessage(esc(m || 'Please check the form')); return false; }) : undefined
        });
    }
    /** Read-only detail modal with action buttons: actions [{label, cls, icon, run: () => Promise}] */
    function view(title, html, actions, opts) {
        opts = opts || {};
        const btns = (actions || []).filter(Boolean).map((a, i) => `<button type="button" class="adm-btn ${a.cls || 'adm-btn-ghost'}" data-act="${i}">${a.icon ? '<i class="fas ' + a.icon + '"></i> ' : ''}${esc(a.label)}</button>`).join('');
        return Swal.fire({
            title, html: html + (btns ? `<div class="erp-actions">${btns}</div>` : ''), width: opts.width || 1000, customClass: { popup: 'erp-modal' },
            showConfirmButton: true, confirmButtonText: 'Close', confirmButtonColor: '#1c5034',
            didOpen: (popup) => {
                $(popup).on('click', '[data-act]', function () { const a = actions.filter(Boolean)[+$(this).data('act')]; if (a) a.run(); });
                if (opts.didOpen) opts.didOpen(popup);
            }
        });
    }
    function confirmAction(title, text, opts) {
        opts = opts || {};
        return Swal.fire({ title, text, icon: opts.icon || 'question', showCancelButton: true, confirmButtonText: opts.confirmText || 'Yes',
                           confirmButtonColor: opts.danger ? '#a8442f' : '#1c5034', cancelButtonColor: '#6b6459',
                           input: opts.reason ? 'text' : undefined, inputPlaceholder: opts.reason || undefined,
                           inputValidator: opts.reason && !opts.optional ? (v => !v || !v.trim() ? 'A reason is required' : undefined) : undefined })
            .then(r => r.isConfirmed ? (r.value === true ? '' : (r.value || '')) : Promise.reject('cancelled'));
    }

    /**
     * Documents panel for a saved record (v2, 1 Oct 2026): category, description, upload from this computer,
     * view / download, new version, archive / restore, version history, and documents of LINKED records
     * (e.g. the supplier bill shows on its PO, GRN and payment). defaultCat preselects the category.
     */
    function docs($el, entityType, entityId, defaultCat) {
        if (!entityId && entityType !== 'company') { $el.html('<div class="erp-docs erp-muted">Save the record first, then attach documents.</div>'); return; }
        let replaces = null, showArchived = false, CATS = {};
        $el.html(`<div class="erp-docs"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
              <strong style="font-size:13px;"><i class="fas fa-paperclip"></i> Documents</strong>
              <label class="erp-muted" style="cursor:pointer;"><input type="checkbox" data-f="arch"> show archived</label></div>
            <div data-f="list" class="erp-muted">Loading…</div><div data-f="rel"></div>
            <div class="erp-docs-upload" data-f="upbox"><select class="adm-select" data-f="cat" style="max-width:220px;"></select>
              <input type="file" data-f="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.docx,.doc,.csv" class="adm-input" style="max-width:250px;">
              <input class="adm-input" data-f="desc" placeholder="Description (optional)" style="max-width:220px;">
              <button type="button" class="adm-btn adm-btn-primary" data-f="up"><i class="fas fa-upload"></i> Upload</button></div>
            <div class="erp-note"><span data-f="hint">PDF, JPG, PNG, WEBP, Excel, Word or CSV · up to 10 MB · stored privately (only logged-in admins can open them)</span>
              <span data-f="msg" style="margin-left:8px;font-weight:600;"></span></div></div>`);
        // inline status (a SweetAlert toast would close the record window this panel lives in)
        const say = (m, bad) => $el.find('[data-f=msg]').css('color', bad ? '#a8442f' : '#1c5034').text(m);
        const row = (d, rel) => `<div class="erp-docs-row"><i class="fas ${/image/.test(d.mime_type) ? 'fa-file-image' : /pdf/.test(d.mime_type) ? 'fa-file-pdf' : /sheet|excel|csv/.test(d.mime_type) ? 'fa-file-excel' : 'fa-file'}"></i>
                <a class="erp-link" href="${BASE}erp_docs.php?action=download&id=${d.id}" target="_blank" rel="noopener">${esc(d.original_name)}</a>
                ${badge(d.status === 'archived' ? 'cancelled' : 'none', (CATS[d.category] || d.category || 'Other') + (d.version > 1 ? ' · v' + d.version : ''))}
                ${d.description ? '<span class="erp-muted">' + esc(d.description) + '</span>' : ''}
                <span class="erp-muted">${date(d.created_at)} · ${esc(d.uploaded_by || '')}${rel ? ' · on <a class="erp-link" href="' + esc(d.from_link) + '">' + esc(d.from_label) + '</a>' : ''}</span>
                <a class="adm-icon-btn" href="${BASE}erp_docs.php?action=download&id=${d.id}&dl=1" title="Download"><i class="fas fa-download"></i></a>
                ${!rel && d.status !== 'archived' ? `<button type="button" class="adm-icon-btn" data-ver="${d.id}" title="Upload a new version"><i class="fas fa-code-branch"></i></button>
                   <button type="button" class="adm-icon-btn is-danger" data-arch="${d.id}" title="Archive (kept, can be restored)"><i class="fas fa-box-archive"></i></button>` : ''}
                ${!rel && d.status === 'archived' ? `<button type="button" class="adm-icon-btn" data-rest="${d.id}" title="Restore"><i class="fas fa-rotate-left"></i></button><span class="erp-muted">${esc(d.archive_reason || '')}</span>` : ''}
                ${!rel && d.version > 1 ? `<button type="button" class="adm-icon-btn" data-hist="${d.id}" title="Version history"><i class="fas fa-clock-rotate-left"></i></button>` : ''}</div>
                <div data-histbox="${d.id}"></div>`;
        const load = () => api('erp_docs.php', { action: 'list', entity_type: entityType, entity_id: entityId || 0, archived: showArchived ? 1 : 0 }, { silent: true }).then(r => {
            CATS = r.categories || {};
            const $c = $el.find('[data-f=cat]');
            if (!$c.children().length) $c.html(Object.keys(CATS).map(k => `<option value="${k}" ${k === (defaultCat || 'OTHER') ? 'selected' : ''}>${esc(CATS[k])}</option>`).join(''));
            if (!r.can_upload) $el.find('[data-f=upbox]').hide();
            $el.find('[data-f=list]').html(r.rows.length ? r.rows.map(d => row(d, false)).join('') : '<span class="erp-muted">No documents attached yet.</span>');
            $el.find('[data-f=rel]').html(r.related.length ? '<div class="erp-muted" style="margin-top:8px;font-weight:700;">From linked records</div>' + r.related.map(d => row(d, true)).join('') : '');
        }).catch(m => $el.find('[data-f=list]').text(m));
        $el.off('.erpdocs');
        $el.on('change.erpdocs', '[data-f=arch]', function () { showArchived = this.checked; load(); });
        $el.on('click.erpdocs', '[data-ver]', function () { replaces = $(this).data('ver'); say('Choose the new file, then Upload (replaces the selected document; the old version is kept).'); $el.find('[data-f=file]').trigger('click'); });
        $el.on('click.erpdocs', '[data-f=up]', () => {
            const f = $el.find('[data-f=file]')[0].files[0];
            if (!f) return say('Choose a file first', true);
            if (f.size > 50 * 1024 * 1024) return say('That file is too large.', true);
            const fd = new FormData(); fd.append('action', 'upload'); fd.append('entity_type', entityType); fd.append('entity_id', entityId || 0);
            fd.append('category', $el.find('[data-f=cat]').val()); fd.append('description', $el.find('[data-f=desc]').val()); if (replaces) fd.append('replaces_id', replaces); fd.append('file', f);
            const $b = $el.find('[data-f=up]').prop('disabled', true).text('Uploading…');
            $.ajax({ url: BASE + 'erp_docs.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(r => { if (r.status === 'success') { say(r.message + ' ✓'); replaces = null; $el.find('[data-f=file]').val(''); $el.find('[data-f=desc]').val(''); load(); } else say(r.message, true); })
                .fail(x => say(x.status === 413 ? 'The file is too large for the server.' : 'Upload failed — check the file and your connection.', true))
                .always(() => $b.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload'));
        });
        $el.on('click.erpdocs', '[data-arch]', function () {
            const id = $(this).data('arch');
            post('erp_docs.php', { action: 'archive', id, reason: 'Archived from ' + entityType }, { silent: true }).then(r => { say(r.message); load(); }).catch(m => say(m, true));
        });
        $el.on('click.erpdocs', '[data-rest]', function () { post('erp_docs.php', { action: 'restore', id: $(this).data('rest') }, { silent: true }).then(r => { say(r.message); load(); }).catch(m => say(m, true)); });
        $el.on('click.erpdocs', '[data-hist]', function () {
            const id = $(this).data('hist'), $box = $el.find(`[data-histbox="${id}"]`);
            if ($box.html()) return $box.html('');
            api('erp_docs.php', { action: 'history', id }, { silent: true }).then(r => $box.html('<div style="margin-left:24px">' + r.versions.map(v => `<div class="erp-muted">v${v.version} · <a class="erp-link" href="${BASE}erp_docs.php?action=download&id=${v.id}" target="_blank" rel="noopener">${esc(v.original_name)}</a> · ${date(v.created_at)} · ${esc(v.uploaded_by || '')} · ${esc(v.status)}</div>`).join('') + '</div>')).catch(m => say(m, true));
        });
        load();
    }

    /** Server-side paging bar. onPage(page) is called with the new page number. */
    function pager($el, total, page, per, onPage) {
        const pages = Math.max(1, Math.ceil(total / per));
        if (pages <= 1) { $el.html(total ? `<div class="erp-muted" style="margin-top:8px;">${total} record(s)</div>` : ''); return; }
        $el.html(`<div class="erp-filters" style="justify-content:flex-end;margin-top:10px;"><span class="erp-muted">${total} records · page ${page} of ${pages}</span>
            <button class="adm-btn adm-btn-ghost" data-pg="${page - 1}" ${page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>
            <button class="adm-btn adm-btn-ghost" data-pg="${page + 1}" ${page >= pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button></div>`);
        $el.off('.pg').on('click.pg', '[data-pg]', function () { onPage(+$(this).data('pg')); });
    }
    /** Tabs: <div class="erp-tabs"> with .erp-tab[data-tab]. */
    function tabs($el, onChange, initial) {
        $el.on('click', '.erp-tab', function () { $el.find('.erp-tab').removeClass('active'); $(this).addClass('active'); onChange($(this).data('tab')); });
        if (initial) { $el.find('.erp-tab').removeClass('active').filter(`[data-tab="${initial}"]`).addClass('active'); }
    }
    function kpi(label, value, cls, link) { const h = `<div class="adm-stat ${cls || 'is-neutral'}"><h3>${value}</h3><p>${esc(label)}</p></div>`; return link ? `<a href="${link}">${h}</a>` : h; }
    function itemLabel(x) { return esc(x.item_name || x.name || ('#' + (x.item_id || x.id))); }

    function chain(links) { return '<div class="erp-chain">' + links.filter(Boolean).map(l => `<a href="${l[1]}">${esc(l[0])}</a>`).join('') + '</div>'; }
    function kv(pairs) { return '<div class="erp-detail-head">' + pairs.filter(Boolean).map(p => `<div><span>${esc(p[0])}</span>${p[1]}</div>`).join('') + '</div>'; }
    function param(name) { return new URLSearchParams(location.search).get(name); }

    return { esc, num, r2, money, qty, date, today, monthStart, badge, toast, alertError, api, post, loading, errorBox, table, csv,
             suppliers, warehouses, items, options, itemOptions, field, input, select, textarea, lineEditor, form, view, confirmAction, docs, chain, kv, param,
             pager, tabs, kpi, itemLabel, BASE };
})(jQuery);
