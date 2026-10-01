/* ============================================================================
 * Competitor quotations on purchase orders (added 1 Oct 2026)
 *   - up to 3 supplier quotations side by side: contact, bank / UPI, delivery,
 *     payment terms, freight, validity and item prices
 *   - highlights: lowest price, fastest delivery, lowest rate per item,
 *     missing bank details, expired quotes
 *   - import from an Excel / CSV file (auto-fills everything it can read);
 *     PDF / Word / image quotations are attached to the PO as documents
 *   - "Use this quotation" fills the PO supplier and item lines
 * Used only by purchase_orders.php. Needs po_quotes_api.php.
 * ========================================================================== */
(function () {
    'use strict';
    const E = window.ERP;
    const MAX = 3;
    const API = 'po_quotes_api.php';
    const XLSX_URL = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
    const SHEET_EXT = ['xlsx', 'xls', 'csv', 'ods'];
    const ATTACH_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'csv'];

    const SUPPLIER_FIELDS = [
        ['supplier_name', 'Supplier / shop name *', 'text'], ['contact_person', 'Contact person / owner', 'text'], ['mobile', 'Contact number', 'tel'],
        ['email', 'E-mail', 'email'], ['gst_number', 'GSTIN', 'text'],
    ];
    const BANK_FIELDS = [
        ['account_holder_name', 'Account holder', 'text'], ['bank_name', 'Bank name', 'text'], ['bank_account_number', 'Account number', 'text'],
        ['bank_ifsc', 'IFSC', 'text'], ['upi_id', 'UPI ID', 'text'], ['payment_terms', 'Payment terms', 'text'],
    ];
    const TERM_FIELDS = [
        ['delivery_days', 'Delivery (days)', 'number'], ['freight', 'Freight / transport ₹', 'number'], ['valid_till', 'Quote valid till', 'date'], ['notes', 'Notes', 'text'],
    ];
    const HEAD_FIELDS = SUPPLIER_FIELDS.concat(BANK_FIELDS, TERM_FIELDS).map(f => f[0]);

    // header names understood when importing (lower-case, punctuation removed)
    const SYN = {
        supplier_name: ['supplier', 'supplier name', 'vendor', 'vendor name', 'shop', 'shop name', 'company', 'company name', 'competitor', 'competitor name', 'party', 'party name', 'dealer', 'dealer name', 'firm', 'firm name', 'quoted by'],
        contact_person: ['contact person', 'contact name', 'owner', 'owner name', 'person', 'shop owner', 'proprietor', 'contact person name'],
        mobile: ['mobile', 'mobile number', 'mobile no', 'phone', 'phone number', 'phone no', 'contact number', 'contact no', 'contact', 'whatsapp', 'whatsapp number', 'cell', 'tel', 'telephone'],
        email: ['email', 'e mail', 'email id', 'mail', 'mail id'],
        gst_number: ['gstin', 'gst number', 'gst no', 'gstin number', 'gstin no', 'gst in'],
        account_holder_name: ['account holder', 'account holder name', 'account name', 'beneficiary', 'beneficiary name', 'ac holder', 'a c holder'],
        bank_name: ['bank', 'bank name'],
        bank_account_number: ['account number', 'account no', 'a c no', 'a c number', 'ac no', 'acc no', 'bank account', 'bank account number', 'bank account no', 'account'],
        bank_ifsc: ['ifsc', 'ifsc code', 'ifsc no'],
        upi_id: ['upi', 'upi id', 'gpay', 'phonepe', 'upi number'],
        delivery_days: ['delivery days', 'delivery', 'delivery time', 'lead time', 'lead time days', 'days', 'delivery in days', 'delivery period', 'delivery within', 'supply days'],
        payment_terms: ['payment terms', 'payment', 'terms', 'credit days', 'credit', 'payment term'],
        freight: ['freight', 'transport', 'transport charges', 'delivery charges', 'shipping', 'freight charges', 'transportation', 'loading charges'],
        valid_till: ['valid till', 'validity', 'valid until', 'quote valid till', 'valid upto', 'valid up to', 'quotation valid till', 'offer valid till'],
        notes: ['notes', 'remarks', 'remark', 'comments', 'comment'],
        item_name: ['item', 'item name', 'product', 'product name', 'description', 'material', 'particulars', 'item description', 'goods', 'commodity'],
        quantity: ['qty', 'quantity', 'order qty', 'required qty', 'qty required', 'nos'],
        unit: ['unit', 'uom', 'units'],
        rate: ['rate', 'price', 'unit price', 'price per unit', 'rate per unit', 'unit rate', 'cost', 'unit cost', 'rate per kg', 'price per kg', 'rate rs', 'price rs', 'rate inr', 'basic rate', 'offer price', 'quoted price', 'quoted rate'],
        tax_percent: ['gst %', 'gst%', 'gst rate', 'gst percent', 'tax', 'tax %', 'tax rate', 'gst rate %', 'igst %', 'gst percentage', 'gst'],
        amount: ['amount', 'total', 'line total', 'value', 'total amount'],
    };
    const LOOKUP = {};
    Object.keys(SYN).forEach(k => SYN[k].forEach(s => { LOOKUP[normHead(s)] = k; }));

    function normHead(s) { return String(s ?? '').toLowerCase().replace(/₹|\brs\.?\b|\binr\b/g, ' rs ').replace(/[^a-z0-9%]+/g, ' ').replace(/\s+/g, ' ').trim(); }
    function headKey(s) {
        const h = normHead(s);
        if (!h) return null;
        if (LOOKUP[h]) return LOOKUP[h];
        const bare = h.replace(/\s*(rs|%)\s*/g, ' ').replace(/\s+/g, ' ').trim();
        if (LOOKUP[bare]) return LOOKUP[bare];
        if (/%/.test(h) && /gst|tax/.test(h)) return 'tax_percent';
        return null;
    }
    function normName(s) { return String(s ?? '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
    function toNum(v) {
        if (typeof v === 'number') return isFinite(v) ? v : '';
        const s = String(v ?? '').replace(/₹|rs\.?|inr|,|\s|%/gi, '');
        if (s === '') return '';
        const n = parseFloat(s);
        return isFinite(n) ? n : '';
    }
    function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function toDate(v) {
        if (v instanceof Date && !isNaN(v)) return ymd(v);
        const s = String(v ?? '').trim();
        if (!s) return '';
        let m = s.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
        if (m) return m[1] + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0');
        m = s.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})$/);   // Indian dd-mm-yyyy
        if (m) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return y + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0'); }
        return '';
    }
    function toDays(v) {
        if (v instanceof Date && !isNaN(v)) { const d = Math.round((v - new Date(E.today())) / 86400000); return d >= 0 ? d : ''; }
        const s = String(v ?? '').toLowerCase().trim();
        if (!s) return '';
        if (/immediate|same day|today|ready stock|in stock/.test(s)) return 0;
        if (/tomorrow|next day/.test(s)) return 1;
        const n = parseFloat(s.replace(/[^0-9.]/g, ''));
        if (!isFinite(n)) return '';
        if (/week/.test(s)) return Math.round(n * 7);
        if (/month/.test(s)) return Math.round(n * 30);
        if (/hour|hr/.test(s)) return Math.ceil(n / 24);
        return Math.round(n);
    }
    function cleanText(v, key) {
        if (v === null || v === undefined) return '';
        if (v instanceof Date) return ymd(v);
        let s = typeof v === 'number' ? (Number.isInteger(v) ? String(v) : String(v)) : String(v).trim();
        if (key === 'mobile') s = s.replace(/[^0-9+]/g, '');
        if (key === 'bank_account_number') s = s.replace(/\s+/g, '');
        if (key === 'bank_ifsc' || key === 'gst_number') s = s.replace(/\s+/g, '').toUpperCase();
        return s;
    }
    const GSTIN = /^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/;
    const IFSC = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    const SKIP_ROW = /^(sub ?total|total|grand total|net total|gst|cgst|sgst|igst|tax|round ?off|discount|amount in words|terms.*|note.*)$/;
    const FREIGHT_ROW = /^(freight|transport|transport charges|delivery charges|shipping|loading|loading charges|freight charges)$/;

    // ------------------------------------------------------------ item matching
    function matchItem(name, items) {
        const n = normName(name);
        if (!n) return null;
        const scored = items.map(i => {
            const l = normName(i.label);
            const lc = normName(i.label + ' ' + (i.category || ''));
            let s = 0;
            if (l === n) s = 100;
            else if (l.replace(/ /g, '') === n.replace(/ /g, '')) s = 95;
            else if (l.startsWith(n) || n.startsWith(l)) s = 80;
            else if (l.includes(n) || n.includes(l)) s = 70;
            else {
                const a = n.split(' '), b = new Set(lc.split(' '));
                const hit = a.filter(w => w.length > 1 && b.has(w)).length;
                s = a.length ? Math.round(60 * hit / Math.max(a.length, lc.split(' ').length / 1.5)) : 0;
            }
            return { i, s };
        }).filter(x => x.s >= 45).sort((x, y) => y.s - x.s || x.i.label.length - y.i.label.length);
        if (!scored.length) return null;
        if (scored.length > 1 && scored[0].s < 80 && scored[1].s === scored[0].s) return null;   // ambiguous
        return scored[0].i;
    }

    // ------------------------------------------------------------ file import
    let xlsxLoading = null;
    function loadXlsx() {
        if (window.XLSX) return Promise.resolve(window.XLSX);
        if (!xlsxLoading) xlsxLoading = new Promise((ok, no) => {
            const s = document.createElement('script'); s.src = XLSX_URL; s.async = true;
            s.onload = () => window.XLSX ? ok(window.XLSX) : no('Could not load the Excel reader.');
            s.onerror = () => { xlsxLoading = null; no('Could not load the Excel reader. Check the internet connection and try again.'); };
            document.head.appendChild(s);
        });
        return xlsxLoading;
    }
    /** Reads every sheet and returns { quotes: [...], warnings: [...] } */
    function parseWorkbook(XLSX, wb, fileName, items) {
        const bySup = new Map(), warnings = [];
        const baseName = String(fileName || '').replace(/\.[^.]+$/, '');
        function quoteFor(name) {
            const k = normName(name) || '_';
            if (!bySup.has(k)) bySup.set(k, { supplier_name: String(name || '').trim(), items: [] });
            return bySup.get(k);
        }
        function setHead(q, key, val) {
            if (val === '' || val === null || val === undefined) return;
            if (q[key] !== undefined && q[key] !== '') return;
            if (key === 'delivery_days') { const d = toDays(val); if (d !== '') q[key] = d; return; }
            if (key === 'freight') { const f = toNum(val); if (f !== '') q[key] = f; return; }
            if (key === 'valid_till') { const d = toDate(val); if (d) q[key] = d; return; }
            if (key === 'bank_account_number' && typeof val === 'number' && val > 999999999999999) warnings.push('An account number looks too long for Excel and may be cut off — please check it.');
            const t = cleanText(val, key);
            if (t) q[key] = t;
        }
        function addItem(q, name, qty, unit, rate, tax) {
            const nm = String(name || '').trim();
            if (!nm) return;
            const low = normName(nm);
            if (FREIGHT_ROW.test(low)) { const f = toNum(rate) || toNum(qty); if (f !== '' && !q.freight) q.freight = f; return; }
            if (SKIP_ROW.test(low)) return;
            const r = toNum(rate);
            if (r === '' && toNum(qty) === '') return;
            const m = matchItem(nm, items);
            q.items.push({ item_type: m ? m.item_type : '', item_id: m ? m.item_id : '', item_name: m ? m.label : nm, source_name: nm,
                           quantity: toNum(qty) === '' ? '' : toNum(qty), unit: String(unit || (m ? m.unit : '') || ''), rate: r === '' ? '' : r, tax_percent: toNum(tax) === '' ? '' : toNum(tax) });
        }
        wb.SheetNames.forEach(sheetName => {
            const ws = wb.Sheets[sheetName];
            const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: true, blankrows: false });
            if (!rows.length) return;
            // header row = first row that names an item column and a price / quantity column
            let hi = -1, cols = [];
            for (let r = 0; r < Math.min(rows.length, 40); r++) {
                const keys = rows[r].map(headKey);
                if (keys.includes('item_name') && (keys.includes('rate') || keys.includes('quantity') || keys.includes('amount'))) { hi = r; cols = keys; break; }
                if (keys.includes('supplier_name') && keys.filter(Boolean).length >= 3 && !keys.includes('item_name')) { hi = r; cols = keys; break; }
            }
            // key : value lines above the table (e.g. "Supplier name | ABC Traders")
            const meta = {};
            rows.slice(0, hi < 0 ? rows.length : hi).forEach(row => {
                const cells = row.map(c => (c instanceof Date ? c : String(c ?? '').trim())).filter(c => c !== '');
                if (!cells.length) return;
                let k = cells[0], v = cells[1];
                if (typeof k === 'string' && k.includes(':') && (v === undefined || v === '')) { const p = k.split(':'); k = p.shift(); v = p.join(':').trim(); }
                const key = headKey(k);
                if (key && HEAD_FIELDS.includes(key) && v !== undefined && v !== '') meta[key] = v;
            });
            const sheetLabel = /^sheet\s*\d*$/i.test(sheetName) || wb.SheetNames.length === 1 ? '' : sheetName;
            if (hi < 0) {   // only supplier details on this sheet
                if (meta.supplier_name || sheetLabel) { const q = quoteFor(meta.supplier_name || sheetLabel); Object.keys(meta).forEach(k => setHead(q, k, meta[k])); }
                return;
            }
            const head = rows[hi];
            const idx = k => cols.indexOf(k);
            const iItem = idx('item_name'), iQty = idx('quantity'), iUnit = idx('unit'), iRate = idx('rate'), iTax = idx('tax_percent'), iAmt = idx('amount'), iSup = idx('supplier_name');
            const body = rows.slice(hi + 1);
            if (iSup >= 0) {   // long format: one row per supplier + item
                let cur = null;
                body.forEach(row => {
                    const s = String(row[iSup] ?? '').trim();
                    if (s) cur = quoteFor(s);
                    if (!cur) return;
                    cols.forEach((k, c) => { if (k && HEAD_FIELDS.includes(k) && k !== 'supplier_name') setHead(cur, k, row[c]); });
                    if (iItem >= 0) {
                        let rate = iRate >= 0 ? row[iRate] : '';
                        if (toNum(rate) === '' && iAmt >= 0 && toNum(row[iAmt]) !== '' && toNum(row[iQty]) > 0) rate = toNum(row[iAmt]) / toNum(row[iQty]);
                        addItem(cur, row[iItem], iQty >= 0 ? row[iQty] : '', iUnit >= 0 ? row[iUnit] : '', rate, iTax >= 0 ? row[iTax] : '');
                    }
                });
                return;
            }
            // comparison format: Item | Qty | Supplier A | Supplier B | ... (unknown numeric columns = suppliers)
            const supCols = head.map((h, c) => ({ h: String(h ?? '').trim(), c })).filter(x => x.h && !cols[x.c] &&
                body.filter(r => toNum(r[x.c]) !== '').length >= Math.max(1, Math.ceil(body.filter(r => String(r[iItem] ?? '').trim()).length / 3)));
            if (iRate < 0 && supCols.length) {
                supCols.forEach(sc => {
                    const q = quoteFor(sc.h);
                    body.forEach(row => {
                        const label = normName(row[iItem]);
                        const k = headKey(row[iItem]);
                        if (k && HEAD_FIELDS.includes(k)) { setHead(q, k, row[sc.c]); return; }   // "Delivery days | 3 | 5 | 2" rows
                        if (label) addItem(q, row[iItem], iQty >= 0 ? row[iQty] : '', iUnit >= 0 ? row[iUnit] : '', row[sc.c], iTax >= 0 ? row[iTax] : '');
                    });
                });
                return;
            }
            // one supplier per sheet
            const q = quoteFor(meta.supplier_name || sheetLabel || baseName);
            Object.keys(meta).forEach(k => setHead(q, k, meta[k]));
            body.forEach(row => {
                cols.forEach((k, c) => { if (k && HEAD_FIELDS.includes(k)) setHead(q, k, row[c]); });
                let rate = iRate >= 0 ? row[iRate] : '';
                if (toNum(rate) === '' && iAmt >= 0 && toNum(row[iAmt]) !== '' && toNum(row[iQty]) > 0) rate = toNum(row[iAmt]) / toNum(row[iQty]);
                addItem(q, row[iItem], iQty >= 0 ? row[iQty] : '', iUnit >= 0 ? row[iUnit] : '', rate, iTax >= 0 ? row[iTax] : '');
            });
        });
        const quotes = [...bySup.values()].filter(q => q.supplier_name || q.items.length);
        quotes.forEach(q => {
            if (q.gst_number && !GSTIN.test(q.gst_number)) { warnings.push(`${q.supplier_name}: GSTIN "${q.gst_number}" does not look valid — please check.`); }
            if (q.bank_ifsc && !IFSC.test(q.bank_ifsc)) { warnings.push(`${q.supplier_name}: IFSC "${q.bank_ifsc}" does not look valid — please check.`); }
            const un = q.items.filter(i => !i.item_id).length;
            if (un) warnings.push(`${q.supplier_name || 'A quotation'}: ${un} item(s) did not match a product — choose them in the list.`);
        });
        return { quotes, warnings };
    }

    // ------------------------------------------------------------ analysis (highlights)
    function lineAmount(l) { const q = E.num(l.quantity), r = E.num(l.rate), t = E.num(l.tax_percent); const net = Math.round(q * r * 100) / 100; return { net, tax: Math.round(net * t) / 100 }; }
    function analyse(quotes, rowKeys) {
        const out = quotes.map((q, i) => {
            const priced = (q.items || []).filter(l => E.num(l.rate) > 0 && E.num(l.quantity) > 0);
            const sub = priced.reduce((a, l) => a + lineAmount(l).net, 0), tax = priced.reduce((a, l) => a + lineAmount(l).tax, 0);
            const keys = new Set(priced.map(itemKey));
            return { i, q, active: !!(q.supplier_name || '').trim() && priced.length > 0, sub, tax, freight: E.num(q.freight), total: sub + tax + E.num(q.freight),
                     covers: rowKeys.filter(k => keys.has(k)).length, days: q.delivery_days === '' || q.delivery_days === null || q.delivery_days === undefined ? null : E.num(q.delivery_days) };
        });
        const act = out.filter(o => o.active);
        const full = act.filter(o => o.covers === rowKeys.length);
        const pool = full.length ? full : act;
        const lowest = pool.length ? pool.reduce((a, b) => (b.total < a.total ? b : a)) : null;
        const highest = pool.length ? pool.reduce((a, b) => (b.total > a.total ? b : a)) : null;
        const withDays = act.filter(o => o.days !== null);
        const fastest = withDays.length ? withDays.reduce((a, b) => (b.days < a.days ? b : a)) : null;
        const tieLow = lowest ? pool.filter(o => Math.abs(o.total - lowest.total) < 0.005).length > 1 : false;
        const tieFast = fastest ? withDays.filter(o => o.days === fastest.days).length > 1 : false;
        // lowest rate per item (incl. GST)
        const bestRate = {};
        rowKeys.forEach(k => {
            let best = null, n = 0;
            act.forEach(o => { const l = (o.q.items || []).find(x => itemKey(x) === k && E.num(x.rate) > 0); if (l) { n++; const v = E.num(l.rate) * (1 + E.num(l.tax_percent) / 100); if (best === null || v < best.v - 0.00001) best = { v, i: o.i }; else if (Math.abs(v - best.v) < 0.00001) best.tie = true; } });
            if (best && n > 1) bestRate[k] = best;   // only when 2+ suppliers priced the item
        });
        return { rows: out, lowest: act.length > 1 ? lowest : null, highest, fastest: act.length > 1 ? fastest : null, tieLow, tieFast, bestRate,
                 saving: lowest && highest && lowest !== highest ? highest.total - lowest.total : 0, partial: act.length > full.length && full.length > 0, noFull: act.length > 1 && !full.length };
    }
    function flags(q, o, a) {
        const f = [];
        if (a.lowest && a.lowest.i === o.i) f.push(['green', a.tieLow ? 'Lowest price (tie)' : 'Lowest price']);
        if (a.fastest && a.fastest.i === o.i) f.push(['info', a.tieFast ? 'Fastest delivery (tie)' : 'Fastest delivery']);
        if (a.lowest && a.fastest && a.lowest.i === o.i && a.fastest.i === o.i && !a.tieLow && !a.tieFast) f.unshift(['green', '★ Best choice']);
        if (o.active && !q.bank_account_number && !q.upi_id) f.push(['amber', 'Bank / UPI missing']);
        if (o.active && q.bank_account_number && !q.bank_ifsc) f.push(['amber', 'IFSC missing']);
        if (q.valid_till && q.valid_till < E.today()) f.push(['red', 'Quote expired']);
        if (o.active && !q.mobile) f.push(['amber', 'No contact number']);
        return f.map(x => `<span class="adm-badge is-${x[0]}">${E.esc(x[1])}</span>`).join(' ');
    }
    function itemKey(l) { return l.item_type && l.item_id ? l.item_type + ':' + l.item_id : 'txt:' + normName(l.source_name || l.item_name); }
    function summaryHtml(a, selectedSlot) {
        if (!a.rows.some(o => o.active)) return '<div class="erp-note">Enter supplier names and item prices (or import an Excel file) to compare.</div>';
        const name = o => E.esc(o.q.supplier_name);
        const parts = [];
        if (a.lowest) parts.push(`<div class="pq-sum-card is-green"><div class="pq-sum-k">Lowest price</div><div class="pq-sum-v">${name(a.lowest)}</div><div class="pq-sum-s">${E.money(a.lowest.total)}${a.saving > 0.004 ? ' · saves ' + E.money(a.saving) + ' vs highest' : ''}</div></div>`);
        if (a.fastest) parts.push(`<div class="pq-sum-card is-info"><div class="pq-sum-k">Fastest delivery</div><div class="pq-sum-v">${name(a.fastest)}</div><div class="pq-sum-s">${a.fastest.days} day(s)</div></div>`);
        const sel = a.rows.find(o => o.i + 1 === selectedSlot && o.active);
        if (sel) {
            const diff = a.lowest && a.lowest.i !== sel.i ? sel.total - a.lowest.total : 0;
            parts.push(`<div class="pq-sum-card ${diff > 0.004 ? 'is-amber' : 'is-green'}"><div class="pq-sum-k">Chosen for this PO</div><div class="pq-sum-v">${name(sel)}</div><div class="pq-sum-s">${E.money(sel.total)}${diff > 0.004 ? ' · ' + E.money(diff) + ' more than lowest' : ''}</div></div>`);
        }
        let h = `<div class="pq-sum">${parts.join('')}</div>`;
        if (a.noFull) h += '<div class="erp-warn">No quotation covers every item, so totals are not directly comparable — compare the item rates (green = lowest).</div>';
        else if (a.partial) h += '<div class="erp-note">"Lowest price" compares only quotations that price every item.</div>';
        return h;
    }

    // ------------------------------------------------------------ styles
    function css() {
        if (document.getElementById('pq-style')) return;
        const s = document.createElement('style'); s.id = 'pq-style';
        s.textContent = `
.pq-wrap{border:1px solid var(--adm-line);border-radius:var(--adm-radius-md);padding:12px;background:var(--adm-cream);margin:6px 0 4px;text-align:left}
.pq-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.pq-bar .erp-note{margin:0;flex:1 1 260px}
.pq-tbl{width:100%;table-layout:fixed;min-width:760px;border-collapse:separate;border-spacing:0;font-size:13px;background:var(--adm-surface);border:1px solid var(--adm-line);border-radius:var(--adm-radius-sm);overflow:hidden}
.pq-tbl th,.pq-tbl td{padding:5px 6px;border-bottom:1px solid var(--adm-line);vertical-align:middle}
.pq-tbl thead th{background:var(--adm-green-soft);color:var(--adm-green-dark);font-weight:600;text-align:left}
.pq-tbl td.pq-lbl{color:var(--adm-ink-soft);font-size:12.5px;overflow-wrap:anywhere}
.pq-tbl col.pq-c0{width:210px}
.pq-qty{display:flex;gap:6px;align-items:center;margin-top:4px}.pq-qty input{width:90px !important;flex:0 0 90px}
.pq-tbl .pq-sec td{background:var(--adm-cream);font-weight:600;color:var(--adm-green-dark);font-size:12px;text-transform:uppercase;letter-spacing:.03em}
.pq-tbl input,.pq-tbl select{width:100%;min-width:0;padding:5px 7px;font-size:13px;height:auto}
.pq-rate{display:flex;gap:4px}.pq-rate input:first-child{flex:1 1 auto}.pq-rate input:last-child{flex:0 0 58px}
.pq-best{background:#e3f1e6 !important;box-shadow:inset 3px 0 0 var(--adm-green)}
.pq-col-low{background:#f1f8f2}
.pq-tot td{font-weight:600}
.pq-flags{display:flex;flex-wrap:wrap;gap:4px}
.pq-sum{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin-bottom:10px}
.pq-sum-card{border-radius:var(--adm-radius-sm);padding:9px 12px;background:var(--adm-surface);border:1px solid var(--adm-line);border-left:4px solid var(--adm-ink-soft)}
.pq-sum-card.is-green{border-left-color:var(--adm-green)}.pq-sum-card.is-info{border-left-color:#2f6ea8}.pq-sum-card.is-amber{border-left-color:var(--adm-amber)}
.pq-sum-k{font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--adm-ink-soft)}
.pq-sum-v{font-weight:700;font-size:15px;color:var(--adm-ink)}.pq-sum-s{font-size:12.5px;color:var(--adm-ink-soft)}
.pq-unm{font-size:11.5px;color:#7a4a17}
.pq-scroll{overflow-x:auto}
.pq-use.is-on{background:var(--adm-green);color:#fff}
.pq-files{font-size:12.5px;color:var(--adm-ink-soft);margin-bottom:8px}
.pq-msg{background:var(--adm-green-soft);color:var(--adm-green-dark);border-radius:var(--adm-radius-sm);padding:9px 13px;font-size:13px;margin-bottom:10px}
.pq-msg.is-ok{border-left:4px solid var(--adm-green)}
@media (max-width:760px){.pq-tbl col.pq-c0{width:150px}}`;
        document.head.appendChild(s);
    }

    // ------------------------------------------------------------ editor (inside the PO form)
    function mount($el, ctx) {
        css();
        const items = ctx.items || [];
        const S = { quotes: [], selected: 0, rows: [], files: [], installed: true, loaded: [], msg: '' };
        // never use Swal / toast here: it would close the purchase-order form
        const say = (m, kind) => { S.msg = m ? `<div class="${kind === 'ok' ? 'pq-msg is-ok' : kind === 'info' ? 'pq-msg' : 'erp-warn'}">${m}</div>` : ''; $el.find('[data-f=msg]').html(S.msg); };
        for (let i = 0; i < MAX; i++) S.quotes.push({ items: [] });
        const supByName = n => (ctx.suppliers || []).find(s => String(s.supplier_name).trim().toLowerCase() === String(n || '').trim().toLowerCase());
        const label = k => { const [t, id] = k.split(':'); const it = items.find(x => x.item_type === t && String(x.item_id) === id); return it ? it.label : null; };

        $el.html('<div class="pq-wrap"><div class="erp-muted">Loading quotations…</div></div>');
        const ready = (ctx.loader ? ctx.loader() : E.api(API, { action: 'get', po_id: ctx.poId || 0 }, { silent: true })).then(r => {
            S.installed = r.installed !== false;
            (r.quotes || []).forEach(q => {
                const i = Math.min(MAX, Math.max(1, +q.slot)) - 1;
                S.quotes[i] = Object.assign({}, q, { delivery_days: q.delivery_days ?? '', items: (q.items || []).map(l => Object.assign({}, l, { item_type: l.item_type || '', item_id: l.item_id || '' })) });
                if (+q.is_selected) S.selected = i + 1;
            });
            S.loaded = r.quotes || [];
            syncRows(true);
            render();
        }).catch(() => { S.installed = false; render(); });

        function syncRows(fromPo) {
            const keys = [];
            const add = (k, name, qty, unit) => { let row = S.rows.find(r => r.key === k); if (!row) { row = { key: k, name, qty: qty === '' ? '' : qty, unit: unit || '' }; S.rows.push(row); } else if ((row.qty === '' || row.qty === undefined) && qty !== '') row.qty = qty; keys.push(k); };
            if (fromPo && ctx.lines) (ctx.lines() || []).forEach(l => { if (l.item_type && l.item_id) add(l.item_type + ':' + l.item_id, label(l.item_type + ':' + l.item_id), E.num(l.quantity) || '', ''); });
            S.quotes.forEach(q => (q.items || []).forEach(l => add(itemKey(l), l.item_name, E.num(l.quantity) || '', l.unit)));
            S.rows.forEach(r => { r.name = label(r.key) || r.name; });
        }
        function cell(q, i, f) {
            const v = q[f[0]] ?? '';
            const extra = f[0] === 'supplier_name' ? `list="pqSup${i}"` : f[2] === 'number' ? 'min="0" step="any"' : f[0] === 'bank_ifsc' ? 'maxlength="11" style="text-transform:uppercase"' : f[0] === 'gst_number' ? 'maxlength="15" style="text-transform:uppercase"' : '';
            return `<td><input class="adm-input" type="${f[2]}" data-q="${i}" data-k="${f[0]}" value="${E.esc(v)}" ${extra}></td>`;
        }
        function render() {
            if (!S.installed) { $el.html('<div class="pq-wrap"><div class="erp-warn">Competitor quotations are not switched on yet. Run <strong>po_competitor_quotes_migration.sql</strong> once in HeidiSQL.</div></div>'); return; }
            const a = analyse(S.quotes, S.rows.map(r => r.key));
            const heads = S.quotes.map((q, i) => `<th>Quotation ${i + 1}</th>`).join('');
            const sec = t => `<tr class="pq-sec"><td colspan="${MAX + 1}">${t}</td></tr>`;
            const frow = f => `<tr><td class="pq-lbl">${E.esc(f[1])}</td>${S.quotes.map((q, i) => cell(q, i, f)).join('')}</tr>`;
            const optItems = sel => '<option value="">Choose product…</option>' + items.map(it => `<option value="${it.item_type}:${it.item_id}" ${sel === it.item_type + ':' + it.item_id ? 'selected' : ''}>${E.esc(it.label)}</option>`).join('');
            const itemRows = S.rows.map((r, ri) => {
                const unmatched = r.key.startsWith('txt:');
                const nameCell = unmatched ? `<div class="pq-unm"><i class="fas fa-triangle-exclamation"></i> "${E.esc(r.name)}" — not matched</div><select class="adm-select" data-map="${ri}">${optItems('')}</select>` : `<strong>${E.esc(r.name || r.key)}</strong>`;
                const cells = S.quotes.map((q, i) => {
                    const l = (q.items || []).find(x => itemKey(x) === r.key) || {};
                    const best = a.bestRate[r.key] && a.bestRate[r.key].i === i && E.num(l.rate) > 0;
                    return `<td class="${best ? 'pq-best' : ''}"><div class="pq-rate"><input class="adm-input" type="number" min="0" step="any" placeholder="Rate ₹" data-r="${ri}" data-q="${i}" data-f="rate" value="${E.esc(l.rate ?? '')}" title="Rate per unit, before GST"><input class="adm-input" type="number" min="0" step="any" placeholder="GST%" data-r="${ri}" data-q="${i}" data-f="tax_percent" value="${E.esc(l.tax_percent ?? '')}" title="GST %"></div>${best ? '<div class="pq-unm" style="color:var(--adm-green)">lowest rate' + (a.bestRate[r.key].tie ? ' (tie)' : '') + '</div>' : ''}</td>`;
                }).join('');
                return `<tr><td class="pq-lbl">${nameCell}<div class="pq-qty">Qty <input class="adm-input" type="number" min="0" step="any" data-rq="${ri}" value="${E.esc(r.qty)}"> ${E.esc(r.unit || '')} <button type="button" class="adm-icon-btn is-danger" data-rdel="${ri}" title="Remove item"><i class="fas fa-xmark"></i></button></div></td>${cells}</tr>`;
            }).join('') || `<tr><td colspan="${MAX + 1}" class="erp-muted">No items yet — add PO lines below and click “Take items from PO lines”, or import a file.</td></tr>`;
            const tot = (lbl, fn, cls) => `<tr class="pq-tot"><td class="pq-lbl">${lbl}</td>${a.rows.map(o => `<td class="${typeof cls === 'function' ? cls(o) : ''}">${o.active ? fn(o) : '<span class="erp-muted">—</span>'}</td>`).join('')}</tr>`;
            const lowCls = o => (a.lowest && a.lowest.i === o.i ? 'pq-best' : '');
            const fastCls = o => (a.fastest && a.fastest.i === o.i ? 'pq-best' : '');
            const lists = S.quotes.map((q, i) => `<datalist id="pqSup${i}">${(ctx.suppliers || []).map(s => `<option value="${E.esc(s.supplier_name)}">`).join('')}</datalist>`).join('');
            $el.html(`<div class="pq-wrap">
              <div class="pq-bar">
                <label class="adm-btn adm-btn-primary" style="cursor:pointer"><i class="fas fa-file-import"></i> Import Excel / CSV / quotation file<input type="file" data-f="file" accept=".xlsx,.xls,.csv,.ods,.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" multiple hidden></label>
                <button type="button" class="adm-btn adm-btn-ghost" data-f="tpl"><i class="fas fa-download"></i> Download Excel template</button>
                <button type="button" class="adm-btn adm-btn-ghost" data-f="fromPo"><i class="fas fa-list"></i> ${E.esc(ctx.linesLabel || 'Take items from PO lines')}</button>
                <button type="button" class="adm-btn adm-btn-ghost" data-f="clear"><i class="fas fa-eraser"></i> Clear</button>
                <p class="erp-note">Excel / CSV fills suppliers, contacts, bank details, delivery and prices automatically. PDF / Word / photos are attached to the PO when you save.</p>
              </div>
              ${S.files.length ? `<div class="pq-files"><i class="fas fa-paperclip"></i> Will be attached to the PO as quotation documents: ${S.files.map(f => E.esc(f.name)).join(', ')}</div>` : ''}
              <div data-f="msg">${S.msg}</div>
              <div data-f="sum">${summaryHtml(a, S.selected)}</div>
              <div class="pq-scroll"><table class="pq-tbl"><colgroup><col class="pq-c0">${S.quotes.map(() => '<col>').join('')}</colgroup><thead><tr><th></th>${heads}</tr></thead><tbody>
                ${sec('Supplier')}${SUPPLIER_FIELDS.map(frow).join('')}
                ${sec('Bank & payment')}${BANK_FIELDS.map(frow).join('')}
                ${sec('Delivery & terms')}${TERM_FIELDS.map(frow).join('')}
                ${sec('Item prices (rate per unit before GST · GST %)')}${itemRows}
                ${sec('Comparison')}
                ${tot('Items priced', o => `${o.covers} of ${S.rows.length}`)}
                ${tot('Subtotal', o => E.money(o.sub))}${tot('GST', o => E.money(o.tax))}${tot('Freight', o => E.money(o.freight))}
                ${tot('Grand total', o => `<strong>${E.money(o.total)}</strong>`, lowCls)}
                ${tot('Delivery', o => (o.days === null ? '<span class="erp-muted">not given</span>' : o.days + ' day(s)'), fastCls)}
                <tr><td class="pq-lbl">Highlights</td>${a.rows.map(o => `<td><div class="pq-flags">${flags(o.q, o, a)}</div></td>`).join('')}</tr>
                <tr><td class="pq-lbl"></td>${a.rows.map(o => `<td>${o.active ? `<button type="button" class="adm-btn adm-btn-ghost pq-use ${S.selected === o.i + 1 ? 'is-on' : ''}" data-use="${o.i}"><i class="fas ${S.selected === o.i + 1 ? 'fa-circle-check' : 'fa-hand-pointer'}"></i> ${S.selected === o.i + 1 ? 'Chosen for this PO' : 'Use this quotation'}</button>` : ''}</td>`).join('')}</tr>
              </tbody></table></div>${lists}</div>`);
        }
        function refreshSummary() {
            // re-render keeps focus problems away by rendering only on change/blur, not on every key
            const act = document.activeElement, id = act && act.dataset ? JSON.stringify(act.dataset) : null, pos = act && act.selectionStart;
            render();
            if (id) { const d = JSON.parse(id); const sel = Object.keys(d).map(k => `[data-${k.replace(/[A-Z]/g, m => '-' + m.toLowerCase())}="${d[k]}"]`).join(''); const el = sel && $el.find(sel)[0]; if (el) { el.focus(); try { if (pos !== null && pos !== undefined) el.setSelectionRange(pos, pos); } catch (e) { /* number inputs */ } } }
        }
        function setLine(qi, row, f, v) {
            const q = S.quotes[qi]; q.items = q.items || [];
            let l = q.items.find(x => itemKey(x) === row.key);
            if (!l) {
                const [t, id] = row.key.startsWith('txt:') ? ['', ''] : row.key.split(':');
                l = { item_type: t, item_id: id, item_name: row.name, source_name: row.name, quantity: row.qty, unit: row.unit, rate: '', tax_percent: '' };
                q.items.push(l);
            }
            l[f] = v; l.quantity = row.qty;
        }
        function fillFromSupplier(i, name) {
            const s = supByName(name), q = S.quotes[i];
            if (!s) { q.supplier_id = ''; return; }
            q.supplier_id = s.id;
            const map = { contact_person: s.owner_name || s.company_name, mobile: s.mobile, email: s.email, gst_number: s.gst_number, account_holder_name: s.account_holder_name,
                          bank_name: s.bank_name, bank_account_number: s.bank_account_number, bank_ifsc: s.bank_ifsc, upi_id: s.upi_id, payment_terms: s.payment_terms };
            Object.keys(map).forEach(k => { if (!q[k] && map[k]) q[k] = map[k]; });
        }
        function importQuotes(list) {
            let placed = 0;
            list.forEach(nq => {
                let i = S.quotes.findIndex(q => normName(q.supplier_name) && normName(q.supplier_name) === normName(nq.supplier_name));
                if (i < 0) i = S.quotes.findIndex(q => !normName(q.supplier_name) && !(q.items || []).some(l => E.num(l.rate) > 0));
                if (i < 0) return;
                const cur = S.quotes[i];
                HEAD_FIELDS.forEach(k => { if (nq[k] !== undefined && nq[k] !== '') cur[k] = nq[k]; });
                cur.source_file = nq.source_file || cur.source_file;
                cur.items = cur.items || [];
                nq.items.forEach(l => { const ex = cur.items.find(x => itemKey(x) === itemKey(l)); if (ex) Object.assign(ex, l); else cur.items.push(l); });
                fillFromSupplier(i, cur.supplier_name);
                placed++;
            });
            return { placed, skipped: list.length - placed };
        }

        // ---- events
        $el.on('change', 'input[data-k]', function () {
            const i = +this.dataset.q, k = this.dataset.k;
            let v = this.value.trim();
            if (k === 'bank_ifsc' || k === 'gst_number') v = v.toUpperCase();
            S.quotes[i][k] = v;
            if (k === 'supplier_name') fillFromSupplier(i, v);
            refreshSummary();
        });
        $el.on('change', 'input[data-r]', function () { const row = S.rows[+this.dataset.r]; setLine(+this.dataset.q, row, this.dataset.f, this.value); refreshSummary(); });
        $el.on('change', 'input[data-rq]', function () { const row = S.rows[+this.dataset.rq]; row.qty = this.value; S.quotes.forEach(q => (q.items || []).forEach(l => { if (itemKey(l) === row.key) l.quantity = row.qty; })); refreshSummary(); });
        $el.on('change', 'select[data-map]', function () {
            const row = S.rows[+this.dataset.map], v = this.value; if (!v) return;
            const [t, id] = v.split(':'), it = items.find(x => x.item_type === t && String(x.item_id) === id);
            const old = row.key;
            if (S.rows.some(r => r.key === v)) { say('That product is already in the list.'); this.value = ''; return; }
            S.quotes.forEach(q => (q.items || []).forEach(l => { if (itemKey(l) === old) { l.item_type = t; l.item_id = id; l.item_name = it.label; } }));
            row.key = v; row.name = it.label; row.unit = row.unit || it.unit;
            render();
        });
        $el.on('click', '[data-rdel]', function () { const row = S.rows[+this.dataset.rdel]; S.quotes.forEach(q => { q.items = (q.items || []).filter(l => itemKey(l) !== row.key); }); S.rows = S.rows.filter(r => r !== row); render(); });
        $el.on('click', '[data-f=fromPo]', () => { const before = S.rows.length; syncRows(true); render(); say(S.rows.length > before ? (S.rows.length - before) + ' item(s) added from the PO lines.' : (ctx.lines && ctx.lines().length ? 'All PO lines are already in the comparison.' : 'Add item lines to the PO first (Items section below).'), S.rows.length > before ? 'ok' : 'info'); });
        let clearArmed = 0;
        $el.on('click', '[data-f=clear]', function () {
            if (Date.now() - clearArmed > 4000) { clearArmed = Date.now(); $(this).html('<i class="fas fa-eraser"></i> Click again to clear'); return; }
            clearArmed = 0; S.quotes = []; for (let i = 0; i < MAX; i++) S.quotes.push({ items: [] }); S.rows = []; S.selected = 0; S.files = []; S.msg = '';
            render(); say('Cleared. Nothing is deleted until you save the PO.', 'info');
        });
        $el.on('click', '[data-use]', function () {
            const i = +this.dataset.use, q = S.quotes[i];
            S.selected = i + 1; render();
            if (ctx.onUse) ctx.onUse(Object.assign({}, q, { items: (q.items || []).filter(l => E.num(l.rate) > 0).map(l => Object.assign({}, l, { quantity: l.quantity || (S.rows.find(r => r.key === itemKey(l)) || {}).qty })) }));
        });
        $el.on('click', '[data-f=tpl]', () => loadXlsx().then(X => {
            const head = ['Supplier name', 'Contact person', 'Mobile', 'Email', 'GSTIN', 'Account holder', 'Bank name', 'Account number', 'IFSC', 'UPI ID', 'Delivery days', 'Payment terms', 'Freight', 'Valid till', 'Item', 'Qty', 'Unit', 'Rate', 'GST %', 'Notes'];
            const lines = (ctx.lines ? ctx.lines() : []).filter(l => l.item_type && l.item_id);
            const aoa = [head];
            for (let s = 0; s < MAX; s++) (lines.length ? lines : [{}]).forEach(l => { const r = new Array(head.length).fill(''); r[14] = l.item_id ? label(l.item_type + ':' + l.item_id) : ''; r[15] = l.item_id ? E.num(l.quantity) || '' : ''; aoa.push(r); });
            const ws = X.utils.aoa_to_sheet(aoa);
            ws['!cols'] = head.map(h => ({ wch: Math.max(12, h.length + 2) }));
            for (let r = 1; r < aoa.length; r++) ['C', 'H'].forEach(c => { const a = c + (r + 1); ws[a] = ws[a] || { t: 's', v: '' }; ws[a].z = '@'; });
            const how = X.utils.aoa_to_sheet([['How to fill this template'], [''], ['One row per supplier per item. Write the supplier details on the first row of each supplier (they can be left blank on the next rows).'],
                ['Up to 3 suppliers are read. Rate = price per unit before GST. GST % = 0, 5, 12, 18 …'], ['Delivery days = number of days to deliver (e.g. 3). Valid till = dd-mm-yyyy.'],
                ['Type Mobile and Account number as text (they are already formatted as text) so Excel does not cut them.'], ['Item names should match your product names; anything that does not match can be chosen after import.'],
                [''], ['Other layouts also work: one supplier per sheet (sheet name = supplier), or a comparison sheet with columns Item | Qty | Supplier A | Supplier B | Supplier C.']]);
            how['!cols'] = [{ wch: 120 }];
            const wb = X.utils.book_new(); X.utils.book_append_sheet(wb, ws, 'Quotations'); X.utils.book_append_sheet(wb, how, 'How to fill');
            X.writeFile(wb, 'competitor_quotation_template.xlsx');
        }).catch(m => say(E.esc(String(m)))));
        $el.on('change', 'input[data-f=file]', function () {
            const files = [...this.files]; this.value = '';
            if (!files.length) return;
            const bad = files.filter(f => !ATTACH_EXT.includes(f.name.split('.').pop().toLowerCase()));
            if (bad.length) { say('Not supported: ' + E.esc(bad.map(f => f.name).join(', ')) + '. Use Excel, CSV, PDF, Word or a photo.'); return; }
            const big = files.filter(f => f.size > 10 * 1048576);
            if (big.length) { say('Files must be 10 MB or smaller: ' + E.esc(big.map(f => f.name).join(', '))); return; }
            files.forEach(f => { if (!S.files.some(x => x.name === f.name && x.size === f.size)) S.files.push(f); });
            const sheets = files.filter(f => SHEET_EXT.includes(f.name.split('.').pop().toLowerCase()));
            if (!sheets.length) { render(); say('The file will be attached to the PO when you save. PDF / Word / photos cannot be read automatically — please type the details and prices.', 'info'); return; }
            loadXlsx().then(X => Promise.all(sheets.map(f => f.arrayBuffer().then(buf => {
                const wb = X.read(new Uint8Array(buf), { type: 'array', cellDates: true, raw: false });
                const res = parseWorkbook(X, wb, f.name, items);
                res.quotes.forEach(q => { q.source_file = f.name; });
                return res;
            })))).then(results => {
                const quotes = [].concat(...results.map(r => r.quotes)), warnings = [].concat(...results.map(r => r.warnings));
                if (!quotes.length) { render(); say('Nothing found to import. The file needs a header row with at least an <strong>Item</strong> column and a <strong>Rate / Price</strong> column — use “Download Excel template” for the expected layout.'); return; }
                const res = importQuotes(quotes);
                syncRows(false); render();
                const msgs = [`Imported ${res.placed} quotation(s) from ${E.esc(sheets.map(f => f.name).join(', '))} — check the comparison below.`];
                if (res.skipped) msgs.push(`${res.skipped} more supplier(s) were in the file, but only ${MAX} can be compared — clear one column and import again.`);
                say(msgs.concat(warnings.map(E.esc)).join('<br>'), warnings.length || res.skipped ? 'warn' : 'ok');
            }).catch(e => { say('Could not read the file: ' + E.esc(e && e.message ? e.message : e)); });
        });

        return {
            ready,
            /** Validation before saving the PO (resolves or rejects with a message). */
            check() {
                if (!S.installed) return Promise.resolve();
                for (let i = 0; i < MAX; i++) {
                    const q = S.quotes[i], has = (q.items || []).some(l => E.num(l.rate) > 0);
                    if (has && !String(q.supplier_name || '').trim()) return Promise.reject(`Quotation ${i + 1}: enter the supplier name`);
                    if (q.gst_number && !GSTIN.test(q.gst_number)) return Promise.reject(`${q.supplier_name || 'Quotation ' + (i + 1)}: GSTIN is not valid (or leave it blank)`);
                    if (q.bank_ifsc && !IFSC.test(q.bank_ifsc)) return Promise.reject(`${q.supplier_name || 'Quotation ' + (i + 1)}: IFSC is not valid (or leave it blank)`);
                }
                return Promise.resolve();
            },
            /** The quotations as they are in the form (empty columns left out). */
            collect() {
                const quotes = S.quotes.map((q, i) => Object.assign({}, q, { slot: i + 1, items: (q.items || []).map(l => ({ item_type: l.item_type, item_id: l.item_id, item_name: l.item_name, quantity: l.quantity, unit: l.unit, rate: l.rate, tax_percent: l.tax_percent })) }))
                    .filter(q => String(q.supplier_name || '').trim() || q.items.some(l => E.num(l.rate) > 0));
                return { quotes, selected_slot: S.selected, loaded: S.loaded.length };
            },
            /** Attaches the imported / attached quotation files to a saved record. Resolves to a list of problems. */
            uploadFiles(entityType, entityId) {
                const jobs = S.files.map(f => {
                    const fd = new FormData(); fd.append('action', 'upload'); fd.append('entity_type', entityType); fd.append('entity_id', entityId); fd.append('category', 'QUOTATION');
                    fd.append('description', 'Shop quotation'); fd.append('file', f);
                    return $.ajax({ url: E.BASE + 'erp_docs.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                        .then(r => (r && r.status === 'success' ? null : f.name + ' not attached: ' + ((r && r.message) || 'error')), () => f.name + ' not attached');
                });
                return Promise.all(jobs).then(res => { S.files = []; render(); return res.filter(Boolean); });
            },
            /** Saves the quotations with the PO and attaches the files. Never blocks the PO. Resolves to a list of problems (empty = all fine). */
            save(poId) {
                if (!S.installed || !poId) return Promise.resolve([]);
                const { quotes } = this.collect();
                const jobs = [];
                if (quotes.length || S.loaded.length) {
                    jobs.push(E.post(API, { action: 'save', po_id: poId, selected_slot: S.selected, quotes: JSON.stringify(quotes) }, { silent: true })
                        .then(() => null, m => 'quotations not saved: ' + m));
                }
                jobs.push(this.uploadFiles('purchase_order', poId));
                return Promise.all(jobs).then(res => [].concat(...res.map(x => x || [])).filter(Boolean));
            },
        };
    }

    // ------------------------------------------------------------ read-only view (PO detail)
    function view($el, po) {
        css();
        E.api(API, { action: 'get', po_id: po.id }, { silent: true }).then(r => show($el, r.quotes || [], { po })).catch(() => $el.empty());
    }
    /** Read-only comparison of saved quotations. opts.po = purchase order (optional), opts.title */
    function show($el, quotesIn, opts) {
        css(); opts = opts || {};
        const po = opts.po || { supplier_id: null, supplier_name: '' };
        {
            const quotes = (quotesIn || []).map(q => Object.assign({}, q, { delivery_days: q.delivery_days ?? '' }));
            if (!quotes.length) { $el.empty(); return; }
            const rows = [];
            quotes.forEach(q => (q.items || []).forEach(l => { const k = itemKey(l); if (!rows.some(x => x.key === k)) rows.push({ key: k, name: l.item_name, qty: l.quantity, unit: l.unit }); }));
            const a = analyse(quotes, rows.map(x => x.key));
            const sel = quotes.findIndex(q => +q.is_selected);
            // the PO's own supplier vs the quotations
            const poQ = quotes.findIndex(q => (q.supplier_id && String(q.supplier_id) === String(po.supplier_id)) || normName(q.supplier_name) === normName(po.supplier_name));
            let warn = '';
            if (a.lowest && poQ >= 0 && poQ !== a.lowest.i) warn = `<div class="erp-warn">This PO is placed with <strong>${E.esc(po.supplier_name)}</strong>, which quoted ${E.money(a.rows[poQ].total)} — <strong>${E.money(a.rows[poQ].total - a.lowest.total)} more</strong> than the lowest quotation (${E.esc(a.lowest.q.supplier_name)}).</div>`;
            else if (quotes.length && poQ < 0 && opts.po) warn = `<div class="erp-note">The PO supplier (${E.esc(po.supplier_name)}) is not one of the saved quotations.</div>`;
            const td = (fn, cls) => a.rows.map(o => `<td class="${cls ? cls(o) : ''}">${fn(o.q, o)}</td>`).join('');
            const line = (lbl, fn, cls) => `<tr><td class="pq-lbl">${lbl}</td>${td(fn, cls)}</tr>`;
            const t = v => (v ? E.esc(v) : '<span class="erp-muted">—</span>');
            const itemTr = rows.map(x => `<tr><td class="pq-lbl"><strong>${E.esc(x.name)}</strong><div class="erp-muted">Qty ${E.qty(x.qty, x.unit)}</div></td>${a.rows.map(o => {
                const l = (o.q.items || []).find(y => itemKey(y) === x.key);
                const best = l && a.bestRate[x.key] && a.bestRate[x.key].i === o.i;
                return `<td class="${best ? 'pq-best' : ''}">${l ? E.money(l.rate) + ' <span class="erp-muted">+ ' + E.num(l.tax_percent) + '% GST</span>' + (best ? '<div class="pq-unm" style="color:var(--adm-green)">lowest rate</div>' : '') : '<span class="erp-muted">not quoted</span>'}</td>`;
            }).join('')}</tr>`).join('');
            $el.html(`<div class="erp-section-title">${E.esc(opts.title || 'Competitor quotations')}</div><div class="pq-wrap">${summaryHtml(a, sel + 1)}${warn}
              <div class="pq-scroll"><table class="pq-tbl"><colgroup><col class="pq-c0">${quotes.map(() => '<col>').join('')}</colgroup><thead><tr><th></th>${quotes.map((q, i) => `<th>${E.esc(q.supplier_name)}${i === sel ? ' <span class="adm-badge is-green">Chosen</span>' : ''}</th>`).join('')}</tr></thead><tbody>
              <tr class="pq-sec"><td colspan="${quotes.length + 1}">Supplier</td></tr>
              ${line('Contact person', q => t(q.contact_person))}${line('Contact number', q => (q.mobile ? `<a class="erp-link" href="tel:${E.esc(q.mobile)}">${E.esc(q.mobile)}</a>` : t('')))}${line('E-mail', q => t(q.email))}${line('GSTIN', q => t(q.gst_number))}
              <tr class="pq-sec"><td colspan="${quotes.length + 1}">Bank & payment</td></tr>
              ${line('Account holder', q => t(q.account_holder_name))}${line('Bank', q => t(q.bank_name))}${line('Account number', q => t(q.bank_account_number))}${line('IFSC', q => t(q.bank_ifsc))}${line('UPI ID', q => t(q.upi_id))}${line('Payment terms', q => t(q.payment_terms))}
              <tr class="pq-sec"><td colspan="${quotes.length + 1}">Items</td></tr>${itemTr}
              <tr class="pq-sec"><td colspan="${quotes.length + 1}">Comparison</td></tr>
              ${line('Subtotal', (q, o) => E.money(o.sub))}${line('GST', (q, o) => E.money(o.tax))}${line('Freight', (q, o) => E.money(o.freight))}
              ${line('Grand total', (q, o) => '<strong>' + E.money(o.total) + '</strong>', o => (a.lowest && a.lowest.i === o.i ? 'pq-best' : ''))}
              ${line('Delivery', (q, o) => (o.days === null ? t('') : o.days + ' day(s)'), o => (a.fastest && a.fastest.i === o.i ? 'pq-best' : ''))}
              ${line('Valid till', q => (q.valid_till ? E.date(q.valid_till) : t('')))}${line('Notes', q => t(q.notes))}
              ${line('Highlights', (q, o) => `<div class="pq-flags">${flags(q, o, a)}</div>`)}
              </tbody></table></div><div class="erp-note">Saved by ${E.esc(quotes[0].created_by || '')} · ${E.date(quotes[0].updated_at || quotes[0].created_at)}${quotes.some(q => q.source_file) ? ' · imported from ' + [...new Set(quotes.map(q => q.source_file).filter(Boolean))].map(E.esc).join(', ') : ''}</div></div>`);
        }
    }

    window.POQ = { mount, view, show, _parse: parseWorkbook, _analyse: analyse };
})();
