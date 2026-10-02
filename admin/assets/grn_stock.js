/* ============================================================================
 * Stock In — "Received purchases" panel (added 1 Oct 2026).
 * 1. Goods received from a purchase wait here (goods receipt still in draft).
 * 2. Download CSV → fill the warehouse and rack / bin (location_code) → upload.
 * 3. The Admin adds them to stock: the existing goods-receipt posting runs once
 *    (creates the Stock In entry + accounts entries; never twice).
 * 4. "Last purchases added" shows the latest ones that went into stock.
 * Needs grn_stock_api.php + purchase_api.php. jQuery + SweetAlert from the page.
 * ========================================================================== */
(function () {
    'use strict';
    var BASE = '../assets/db_query/admin/';
    var $p = $('#grnPanel'); if (!$p.length) return;
    var D = null;
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); };
    var day = function (d) { if (!d) return '—'; var x = new Date(String(d).replace(' ', 'T')); return isNaN(x) ? esc(d) : x.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); };
    var qty = function (v) { return Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 3 }); };
    $('<style>').text(
        '#grnPanel .gs-tbl{width:100%;border-collapse:collapse;font-size:13px}#grnPanel .gs-tbl th{text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:#5d574d;background:#f7f4ee;padding:8px 10px;white-space:nowrap}' +
        '#grnPanel .gs-tbl td{padding:8px 10px;border-top:1px solid #f0ebe1;vertical-align:middle}#grnPanel .gs-tw{overflow-x:auto;border:1px solid var(--adm-line,#e7e1d4);border-radius:10px}' +
        '#grnPanel .gs-acts{display:flex;gap:6px;flex-wrap:wrap}#grnPanel .gs-h{font-size:14px;font-weight:700;margin:14px 0 8px;color:#23281f}#grnPanel .gs-tag{display:inline-block;border-radius:20px;padding:2px 9px;font-size:12px;font-weight:600;background:#f0ece3;color:#5d574d}' +
        '#grnPanel .gs-ok{background:#e8f1eb;color:#1c5034}#grnPanel .gs-warn{background:#f6ead9;color:#7a4a17}#grnPanel .gs-empty{color:#6b6459;font-size:13px;padding:12px;border:1px dashed #e2dccf;border-radius:10px;text-align:center}' +
        '#grnPanel .gs-help{font-size:12.5px;color:#6b6459;margin:0 0 6px}').appendTo('head');

    function api(file, data, post) {
        return new Promise(function (ok, no) {
            $.ajax({ url: BASE + file, type: post ? 'POST' : 'GET', data: data, dataType: 'json' })
                .done(function (r) { r && r.status === 'success' ? ok(r) : no((r && r.message) || 'Something went wrong.'); })
                .fail(function (x) { no(x.status === 403 ? 'You do not have permission for this.' : 'Could not reach the server.'); });
        });
    }
    function qcTag(s) {
        if (!s) return '<span class="gs-tag">No QC</span>';
        if (s === 'pending') return '<span class="gs-tag gs-warn">QC pending</span>';
        return '<span class="gs-tag gs-ok">QC ' + esc(s.replace('_', ' ')) + '</span>';
    }
    function render() {
        var w = D.waiting, l = D.last;
        $p.html('<section class="adm-card"><div class="adm-card-head" style="flex-wrap:wrap;gap:10px"><h2><i class="fas fa-truck-ramp-box"></i> Received purchases — add to stock</h2></div><div class="adm-card-body">' +
            '<p class="gs-help">Goods received from a purchase order wait here until they are added to stock. Download the CSV, fill <strong>warehouse_id</strong> and <strong>location_code</strong> (rack / bin), then ' +
            (D.can_post ? 'upload it with <strong>Upload CSV &amp; add to stock</strong> (open the CSV in Excel, fill it, save as CSV). The purchase is then marked <strong>Added to inventory — completed</strong>.' : 'send it to the Executive / Admin — they add them to stock.') + '</p>' +   // FIX (2 Oct 2026)
            (w.length ? '<div class="gs-tw"><table class="gs-tbl"><thead><tr><th>Goods receipt</th><th>Supplier</th><th>PO</th><th>Received</th><th>Warehouse</th><th style="text-align:right">Accepted qty</th><th>Quality</th><th></th></tr></thead><tbody>' +
                w.map(function (g) {
                    return '<tr><td><strong>' + esc(g.grn_number) + '</strong><div style="font-size:12px;color:#6b6459">' + g.lines_n + ' item(s)</div></td><td>' + esc(g.supplier_name) + '</td><td>' + esc(g.po_number || '—') + '</td><td>' + day(g.received_date) + '</td><td>' + esc(g.warehouse_name || '—') + '</td>' +
                        '<td style="text-align:right">' + qty(g.accepted) + '</td><td>' + qcTag(g.qc_status) + '</td><td><div class="gs-acts">' +
                        '<a class="adm-btn adm-btn-ghost" href="' + BASE + 'grn_stock_api.php?action=csv&id=' + g.id + '"><i class="fas fa-file-csv"></i> Download CSV</a>' +
                        (D.can_post ? '<button class="adm-btn adm-btn-primary" data-up="' + g.id + '"' + (g.qc_status === 'pending' ? ' disabled title="Finish the quality check first"' : '') + '><i class="fas fa-upload"></i> Upload CSV &amp; add to stock</button>' : '') +
                        '</div></td></tr>';
                }).join('') + '</tbody></table></div>' : '<div class="gs-empty">No received purchases are waiting. ✓</div>') +
            '<div class="gs-h">Last purchases added to stock</div>' +
            (l.length ? '<div class="gs-tw"><table class="gs-tbl"><thead><tr><th>Goods receipt</th><th>Stock In</th><th>Supplier</th><th>PO</th><th>Warehouse</th><th style="text-align:right">Qty added</th><th>Added</th><th></th></tr></thead><tbody>' +
                l.map(function (g) {
                    return '<tr><td><strong>' + esc(g.grn_number) + '</strong></td><td>' + esc(g.stock_in_number || '—') + '</td><td>' + esc(g.supplier_name) + '</td><td>' + esc(g.po_number || '—') + '</td><td>' + esc(g.warehouse_name || '—') + '</td>' +
                        '<td style="text-align:right">' + qty(g.accepted) + '</td><td>' + day(g.posted_at || g.received_date) + (g.posted_by ? ' · ' + esc(g.posted_by) : '') + '</td>' +
                        '<td><a class="adm-btn adm-btn-ghost" href="' + BASE + 'grn_stock_api.php?action=csv&id=' + g.id + '"><i class="fas fa-file-csv"></i> CSV</a></td></tr>';
                }).join('') + '</tbody></table></div>' : '<div class="gs-empty">Nothing added from purchases yet.</div>') +
            '<input type="file" accept=".csv,text/csv" id="gsFile" style="display:none"></div></section>');
    }
    function load() { api('grn_stock_api.php', { action: 'list' }).then(function (r) { D = r; render(); }).catch(function (m) { $p.html(''); console.warn('[received purchases]', m); }); }

    /* CSV → rows (handles quotes, commas and new lines inside quotes) */
    function parseCsv(t) {
        t = t.replace(/^﻿/, ''); var rows = [], row = [], f = '', q = false;
        for (var i = 0; i < t.length; i++) {
            var c = t[i];
            if (q) { if (c === '"') { if (t[i + 1] === '"') { f += '"'; i++; } else q = false; } else f += c; }
            else if (c === '"') q = true; else if (c === ',') { row.push(f); f = ''; }
            else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(f); f = ''; if (row.some(function (x) { return x.trim() !== ''; })) rows.push(row); row = []; }
            else f += c;
        }
        row.push(f); if (row.some(function (x) { return x.trim() !== ''; })) rows.push(row);
        if (!rows.length) return [];
        var head = rows.shift().map(function (h) { return h.trim().toLowerCase(); });
        return rows.map(function (r) { var o = {}; head.forEach(function (h, j) { o[h] = String(r[j] == null ? '' : r[j]).trim().replace(/^'(?=[=+\-@])/, ''); }); return o; });
    }
    var upId = 0;
    $p.on('click', '[data-up]', function () { upId = +$(this).data('up'); $('#gsFile').val('').trigger('click'); });
    $p.on('change', '#gsFile', function () {
        var f = this.files && this.files[0]; if (!f) return;
        var rd = new FileReader();
        rd.onload = function () { check(parseCsv(String(rd.result || ''))); };
        rd.readAsText(f);
    });
    function check(rows) {
        var g = D.waiting.find(function (x) { return +x.id === upId; });
        api('purchase_api.php', { action: 'grn_get', id: upId }).then(function (r) {
            var rec = r.record, byLine = {}, errs = [];
            rec.items.forEach(function (i) { byLine[i.id] = i; });
            rows = rows.filter(function (x) { return x.line_id; });
            if (!rows.length) errs.push('The file has no item lines. Use the CSV downloaded from this page.');
            rows.forEach(function (x) { if (x.grn_number && x.grn_number !== rec.grn_number) errs.push('This file is for ' + x.grn_number + ', not ' + rec.grn_number + '.'); if (!byLine[x.line_id]) errs.push('Line ' + x.line_id + ' is not part of ' + rec.grn_number + '.'); });
            var whs = rows.map(function (x) { return x.warehouse_id; }).filter(Boolean).filter(function (v, i, a) { return a.indexOf(v) === i; });
            if (whs.length > 1) errs.push('Use one warehouse_id for the whole goods receipt (found ' + whs.join(', ') + ').');
            var wh = +(whs[0] || rec.warehouse_id || 0);
            if (!D.warehouses.some(function (w) { return +w.id === wh; })) errs.push('warehouse_id ' + wh + ' does not exist.');
            var codes = {}; D.locations.forEach(function (l) { if (+l.warehouse_id === wh) codes[String(l.code).toUpperCase()] = l; });
            rows.forEach(function (x) { if (x.location_code && !codes[x.location_code.toUpperCase()]) errs.push('Location ' + x.location_code + ' is not in this warehouse — add it in Warehouse Locations first.'); });
            var num = function (v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; };
            rows.forEach(function (x) { var i = byLine[x.line_id]; if (!i) return; if (num(x.rejected_qty, 0) > num(x.received_qty, 0)) errs.push(i.item_name + ': rejected is more than received.'); });
            if (errs.length) { Swal.fire({ title: 'Please fix the CSV', html: '<ul style="text-align:left">' + errs.slice(0, 8).map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>', icon: 'error', confirmButtonColor: '#1c5034' }); return; }
            var whName = (D.warehouses.find(function (w) { return +w.id === wh; }) || {}).name || wh;
            var items = rows.map(function (x) {
                var i = byLine[x.line_id];
                return { po_item_id: i.po_item_id, item_type: i.item_type, item_id: i.item_id, received_qty: num(x.received_qty, +i.received_qty), rejected_qty: num(x.rejected_qty, +i.rejected_qty),
                         rejection_reason: x.rejection_reason || i.rejection_reason || '', batch_number: x.batch_number || i.batch_number || '', lot_number: i.lot_number || '',
                         manufacturing_date: x.manufacturing_date || i.manufacturing_date || '', expiry_date: x.expiry_date || i.expiry_date || '', qc_status: i.qc_status, _name: i.item_name, _loc: x.location_code };
            });
            Swal.fire({ title: 'Add ' + esc(rec.grn_number) + ' to stock?', icon: 'question', width: 720, showCancelButton: true, confirmButtonText: 'Add to stock', confirmButtonColor: '#1c5034',
                html: '<div style="text-align:left;font-size:13.5px">Warehouse: <strong>' + esc(whName) + '</strong>' + (g && g.qc_status && g.qc_status !== 'pending' ? ' · quantities come from the completed quality check' : '') +
                      '<table style="width:100%;margin-top:8px;border-collapse:collapse"><tr style="background:#f7f4ee"><th style="text-align:left;padding:6px">Item</th><th style="text-align:right;padding:6px">Received</th><th style="text-align:right;padding:6px">Rejected</th><th style="text-align:left;padding:6px">Location</th></tr>' +
                      items.map(function (i) { return '<tr><td style="padding:6px;border-top:1px solid #eee">' + esc(i._name) + '</td><td style="text-align:right;padding:6px;border-top:1px solid #eee">' + qty(i.received_qty) + '</td><td style="text-align:right;padding:6px;border-top:1px solid #eee">' + qty(i.rejected_qty) + '</td><td style="padding:6px;border-top:1px solid #eee">' + esc(i._loc || '—') + '</td></tr>'; }).join('') + '</table></div>',
                preConfirm: function () {
                    var send = items.map(function (i) { var o = $.extend({}, i); delete o._name; delete o._loc; return o; });
                    return api('purchase_api.php', { action: 'grn_post', id: rec.id, po_id: rec.po_id || '', supplier_id: rec.supplier_id, received_date: rec.received_date, warehouse_id: wh,
                                                     received_by: rec.received_by || '', supplier_challan_no: rec.supplier_challan_no || '', vehicle_number: rec.vehicle_number || '', notes: rec.notes || '', items: JSON.stringify(send) }, true)
                        .then(function (res) {
                            var locs = items.filter(function (i) { return i._loc; }).map(function (i) { return { item_type: i.item_type, item_id: i.item_id, location_code: i._loc }; });
                            return (locs.length ? api('grn_stock_api.php', { action: 'locate', id: rec.id, lines: JSON.stringify(locs) }, true).then(function () { return res; }, function (m) { res.message += ' (Locations not saved: ' + m + ')'; return res; }) : res);
                        })
                        .catch(function (m) { Swal.showValidationMessage(esc(m)); return false; });
                } }).then(function (res) {
                if (!res.isConfirmed || !res.value) return;
                Swal.fire({ title: 'Added to stock', text: res.value.message || '', icon: 'success', confirmButtonColor: '#1c5034' });
                load(); if (typeof window.loadStockIns === 'function') window.loadStockIns();
            });
        }).catch(function (m) { Swal.fire({ title: 'Could not open the goods receipt', text: m, icon: 'error', confirmButtonColor: '#1c5034' }); });
    }
    load();
})();
