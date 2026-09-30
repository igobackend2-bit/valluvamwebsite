<?php
require_once __DIR__ . '/includes/erp_page.php';
erp_page_start('Notifications', 'Alerts from live data: approvals, stock, expiry, payments due, overdue customers, pending receipts / QC / bills',
    '<button class="adm-btn adm-btn-ghost" id="refresh"><i class="fas fa-rotate"></i> Refresh now</button> <button class="adm-btn adm-btn-ghost" id="readAll"><i class="fas fa-check-double"></i> Mark all read</button>');
?>
<div class="erp-tabs" id="tabs"><button class="erp-tab active" data-tab="open">Open</button><button class="erp-tab" data-tab="all">Include resolved</button></div>
<section class="adm-card"><div class="adm-card-body" id="list"></div></section>
<?php erp_page_end(<<<'JS'
const E = ERP;
let ALL = false;
E.tabs($('#tabs'), t => { ALL = t === 'all'; load(); });
function load(refresh) {
    E.loading($('#list'));
    E.api('notifications_api.php', { action: 'list', all: ALL ? 1 : 0, refresh: refresh ? 1 : 0 }, { silent: true }).then(r => {
        E.table($('#list'), [
            { label: '', render: x => `<i class="fas ${x.severity === 'critical' ? 'fa-circle-exclamation erp-neg' : x.severity === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info'}"></i>` },
            { label: 'Alert', render: x => `<strong style="${x.is_read ? 'font-weight:500' : ''}">${E.esc(x.title)}</strong><div class="erp-muted">${E.esc(x.message || '')}</div>` },
            { label: 'Type', render: x => E.badge(x.severity, x.type) },
            { label: 'Since', render: x => E.date(x.first_seen_at) + (x.status === 'resolved' ? '<div class="erp-muted">resolved ' + E.date(x.resolved_at) + '</div>' : '') },
            { label: '', render: x => (x.link ? `<a class="adm-btn adm-btn-primary" href="${E.esc(x.link)}" data-read="${x.id}">Open</a> ` : '') + (!x.is_read && x.status === 'open' ? `<button class="adm-btn adm-btn-ghost" data-mark="${x.id}">Mark read</button>` : '') },
        ], r.rows, { empty: 'All clear — no alerts right now', icon: 'fa-bell-slash' });
    }).catch(m => E.errorBox($('#list'), m, load));
}
$('#list').on('click', '[data-mark]', function () { E.post('notifications_api.php', { action: 'read', id: $(this).data('mark') }, { silent: true }).then(() => load()); });
$('#list').on('click', '[data-read]', function () { E.post('notifications_api.php', { action: 'read', id: $(this).data('read') }, { silent: true }); });
$('#readAll').on('click', () => E.post('notifications_api.php', { action: 'read_all' }).then(() => load()));
$('#refresh').on('click', () => load(true));
load();
JS
);
