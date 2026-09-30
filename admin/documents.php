<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Documents', 'Every uploaded bill, receipt, GRN, QC photo, payment proof… with the transaction it belongs to',
    '<button class="adm-btn adm-btn-primary" id="upBtn"><i class="fas fa-upload"></i> Upload document</button>');
?>
<div class="erp-kpis" id="kpis"></div>
<section class="adm-card">
    <div class="adm-card-head"><h2>All documents</h2>
        <div class="erp-filters">
            <select class="adm-select" id="fEntity"><option value="">All records</option></select>
            <select class="adm-select" id="fCat"><option value="">All categories</option></select>
            <select class="adm-select" id="fStatus"><option value="active">Active</option><option value="archived">Archived</option><option value="">All</option></select>
            <input type="date" class="adm-input" id="fFrom" title="Uploaded from"><input type="date" class="adm-input" id="fTo" title="Uploaded to">
            <input class="adm-input" id="fQ" placeholder="File name, description, uploader">
        </div></div>
    <div class="adm-card-body"><div id="list"></div><div id="pager"></div></div>
</section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let META = null, PAGE = 1;
E.api('erp_docs.php', { action: 'meta' }).then(m => {
    META = m;
    $('#fEntity').append(m.entity_types.map(t => `<option value="${t.key}">${E.esc(t.label)}</option>`).join(''));
    $('#fCat').append(Object.keys(m.categories).map(k => `<option value="${k}">${E.esc(m.categories[k])}</option>`).join(''));
    if (!m.can_upload) $('#upBtn').hide();
    if (E.param('entity')) $('#fEntity').val(E.param('entity'));
    load();
});
$('#fEntity,#fCat,#fStatus,#fFrom,#fTo').on('change', () => { PAGE = 1; load(); });
let tq; $('#fQ').on('input', () => { clearTimeout(tq); tq = setTimeout(() => { PAGE = 1; load(); }, 300); });
function size(b) { b = +b || 0; return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
function load() {
    E.loading($('#list'));
    E.api('erp_docs.php', { action: 'all', entity_type: $('#fEntity').val(), category: $('#fCat').val(), status: $('#fStatus').val(), date_from: $('#fFrom').val(), date_to: $('#fTo').val(), q: $('#fQ').val(), page: PAGE, per_page: 50 }, { silent: true }).then(r => {
        $('#kpis').html(E.kpi('Documents found', r.total, 'is-primary') + E.kpi('Storage used', r.storage_mb + ' MB'));
        E.table($('#list'), [
            { label: 'File', render: d => `<a class="erp-link" href="${E.BASE}erp_docs.php?action=download&id=${d.id}" target="_blank" rel="noopener">${E.esc(d.original_name)}</a><div class="erp-muted">${E.esc(d.description || '')}</div>` },
            { label: 'Category', render: d => E.badge(d.status === 'archived' ? 'cancelled' : 'none', (META.categories[d.category] || d.category) + (d.version > 1 ? ' · v' + d.version : '')) },
            { label: 'Belongs to', render: d => d.entity_link ? `<a class="erp-link" href="${E.esc(d.entity_link)}">${E.esc(d.entity_label)}</a>` : E.esc(d.entity_label) },
            { label: 'Size', num: true, render: d => size(d.file_size) },
            { label: 'Uploaded', render: d => E.date(d.created_at) + `<div class="erp-muted">${E.esc(d.uploaded_by || '')}</div>` },
            { label: '', render: d => `<a class="adm-icon-btn" href="${E.BASE}erp_docs.php?action=download&id=${d.id}&dl=1" title="Download"><i class="fas fa-download"></i></a>` +
                (d.status === 'archived' ? (META.can_archive ? ` <button class="adm-icon-btn" data-rest="${d.id}" title="Restore"><i class="fas fa-rotate-left"></i></button>` : '') + `<div class="erp-muted">${E.esc(d.archive_reason || '')}</div>`
                                         : ` <button class="adm-icon-btn is-danger" data-arch="${d.id}" title="Archive"><i class="fas fa-box-archive"></i></button>`) },
        ], r.rows, { empty: 'No documents found', icon: 'fa-folder-open' });
        E.pager($('#pager'), r.total, PAGE, 50, p => { PAGE = p; load(); });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-arch]', function () {
    const id = $(this).data('arch');
    E.confirmAction('Archive this document?', 'The file is kept and can be restored.', { reason: 'Reason', danger: true }).then(reason => E.post('erp_docs.php', { action: 'archive', id, reason })).then(r => { E.toast(r.message); load(); }).catch(() => {});
});
$('#list').on('click', '[data-rest]', function () { E.post('erp_docs.php', { action: 'restore', id: $(this).data('rest') }).then(r => { E.toast(r.message); load(); }); });
$('#upBtn').on('click', () => {
    const html = `<div class="erp-grid">
        ${E.field('Belongs to *', E.select('uEnt', META.entity_types.map(t => `<option value="${t.key}">${E.esc(t.label)}</option>`).join('')))}
        ${E.field('Document number / name *', E.input('uNum', '', 'placeholder="e.g. PO-2026-000012, invoice no., supplier name"'))}
        ${E.field('Category *', E.select('uCat', Object.keys(META.categories).map(k => `<option value="${k}">${E.esc(META.categories[k])}</option>`).join('')))}
        ${E.field('Description', E.input('uDesc', ''), 'span-2')}
        ${E.field('File from this computer *', '<input type="file" class="adm-input" id="uFile" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.docx,.doc,.csv">', 'span-all')}
      </div><p class="erp-note">PDF, JPG, PNG, WEBP, Excel, Word or CSV · up to ${META.max_mb} MB. For "Company / general" leave the number empty.</p>`;
    E.form('Upload document', html, () => new Promise((resolve, reject) => {
        const f = $('#uFile')[0].files[0];
        if (!f) return reject('Choose a file');
        const fd = new FormData();
        fd.append('action', 'upload'); fd.append('entity_type', $('#uEnt').val()); fd.append('entity_number', $('#uNum').val()); fd.append('category', $('#uCat').val()); fd.append('description', $('#uDesc').val()); fd.append('file', f);
        $.ajax({ url: E.BASE + 'erp_docs.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
            .done(r => r.status === 'success' ? (E.toast(r.message), load(), resolve()) : reject(r.message)).fail(() => reject('Upload failed — check the file size and your connection.'));
    }), { confirmText: 'Upload', width: 760, didOpen: () => $('#uEnt').val('company').on('change', function () { $('#uNum').prop('disabled', this.value === 'company'); }).trigger('change') });
});
JS
);
