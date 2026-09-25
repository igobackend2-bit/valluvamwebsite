<?php
require_once __DIR__ . '/includes/check_admin.php';

// Server-side gate, in addition to require_permission($pdo,'audit_logs.view')
// inside get_audit_logs.php: this page itself only renders for roles that
// hold audit_logs.view (Super Admin and Manager per the seeded data —
// Staff was never granted this key, so it naturally locks them out).
require_once __DIR__ . '/../assets/db_query/admin/auth_helper.php';
require_once __DIR__ . '/../assets/db_query/admin/../config.php';

if ((int)($admin_role_id ?? 0) !== 1) {
    $hasAccess = false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM admin_role_permissions WHERE role_id = ? AND perm_key = 'audit_logs.view'");
        $stmt->execute([$admin_role_id]);
        $hasAccess = (bool)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        $hasAccess = false;
    }
    if (!$hasAccess) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access denied</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Access denied</h2><p>You do not have permission to view the audit log.</p><p><a href="dashboard.php">Back to dashboard</a></p></body></html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Audit Log</h1>
                    <div class="adm-sub">Read-only history of admin actions — nothing here can be edited or deleted</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Filters</h2></div>
                <div class="adm-card-body">
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                        <div class="adm-field">
                            <label>Module</label>
                            <select id="filterModule" class="adm-select">
                                <option value="">All modules</option>
                            </select>
                        </div>
                        <div class="adm-field">
                            <label>Username</label>
                            <input type="text" id="filterUsername" class="adm-input" placeholder="Username">
                        </div>
                        <div class="adm-field">
                            <label>Action</label>
                            <select id="filterAction" class="adm-select">
                                <option value="">All actions</option>
                                <option value="create">Create</option>
                                <option value="update">Update</option>
                                <option value="delete">Delete</option>
                                <option value="approve">Approve</option>
                                <option value="cancel">Cancel</option>
                            </select>
                        </div>
                        <div class="adm-field">
                            <label>From</label>
                            <input type="date" id="filterFrom" class="adm-input">
                        </div>
                        <div class="adm-field">
                            <label>To</label>
                            <input type="date" id="filterTo" class="adm-input">
                        </div>
                        <button class="adm-btn adm-btn-primary" id="applyFiltersBtn"><i class="fas fa-filter"></i> Apply</button>
                        <button class="adm-btn adm-btn-ghost" id="clearFiltersBtn">Clear</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Log entries</h2></div>
                <div class="adm-card-body" id="logsTable">
                    <div class="adm-table-wrap"><table class="adm-table"><tbody>
                        <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                    </tbody></table></div>
                </div>
                <div class="adm-card-body" style="text-align:center;">
                    <button class="adm-btn adm-btn-ghost" id="loadMoreBtn" style="display:none;">Load more</button>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentOffset = 0;
        let allLogs = [];

        $(document).ready(function() {
            loadLogs(true);
            $('#applyFiltersBtn').on('click', function() { loadLogs(true); });
            $('#clearFiltersBtn').on('click', function() {
                $('#filterModule').val('');
                $('#filterUsername').val('');
                $('#filterAction').val('');
                $('#filterFrom').val('');
                $('#filterTo').val('');
                loadLogs(true);
            });
            $('#loadMoreBtn').on('click', function() { loadLogs(false); });
        });

        function loadLogs(reset) {
            if (reset) {
                currentOffset = 0;
                allLogs = [];
            }
            $.ajax({
                url: '../assets/db_query/admin/get_audit_logs.php',
                type: 'GET',
                data: {
                    module: $('#filterModule').val() || '',
                    username: $('#filterUsername').val() || '',
                    action: $('#filterAction').val() || '',
                    date_from: $('#filterFrom').val() || '',
                    date_to: $('#filterTo').val() || '',
                    offset: currentOffset
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        populateModules(data.modules);
                        allLogs = allLogs.concat(data.logs);
                        currentOffset += data.logs.length;
                        displayLogs(allLogs);
                        $('#loadMoreBtn').toggle(!!data.has_more);
                    } else {
                        $('#logsTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load audit logs.') + '</div>');
                    }
                },
                error: function() {
                    $('#logsTable').html('<div class="adm-error">Could not reach the server while loading audit logs.</div>');
                }
            });
        }

        function populateModules(modules) {
            const $sel = $('#filterModule');
            const current = $sel.val();
            if ($sel.data('populated')) return;
            modules.forEach(m => {
                $sel.append(`<option value="${escapeHtml(m)}">${escapeHtml(m)}</option>`);
            });
            $sel.data('populated', true);
            $sel.val(current);
        }

        function displayLogs(logs) {
            if (logs.length === 0) {
                $('#logsTable').html('<div class="adm-empty"><i class="fas fa-clipboard-list"></i><p><strong>No log entries</strong></p><p>Try adjusting the filters.</p></div>');
                return;
            }

            let rows = '';
            logs.forEach(l => {
                const actionBadge = badgeForAction(l.action);
                rows += `<tr>
                    <td>${formatDate(l.created_at)}</td>
                    <td>${escapeHtml(l.username || '—')}</td>
                    <td>${actionBadge}</td>
                    <td>${escapeHtml(l.module)}</td>
                    <td>${escapeHtml(l.record_id || '—')}</td>
                    <td><button class="adm-icon-btn view-log" data-log='${JSON.stringify(l).replace(/'/g, "&#39;")}' title="View details"><i class="fas fa-eye"></i></button></td>
                </tr>`;
            });

            $('#logsTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Details</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.view-log').on('click', function() { viewLogDetails($(this).data('log')); });
        }

        function badgeForAction(action) {
            const map = {
                create: 'is-green', update: 'is-info', delete: 'is-danger',
                approve: 'is-green', cancel: 'is-amber'
            };
            const cls = map[action] || 'is-neutral';
            return `<span class="adm-badge ${cls}">${escapeHtml(action)}</span>`;
        }

        function viewLogDetails(log) {
            let oldPretty = prettyJson(log.old_value);
            let newPretty = prettyJson(log.new_value);
            Swal.fire({
                title: `${escapeHtml(log.module)} #${escapeHtml(log.record_id || '')}`,
                html: `
                    <div style="text-align:left;">
                        <p><strong>User:</strong> ${escapeHtml(log.username || '—')} &nbsp; <strong>Action:</strong> ${escapeHtml(log.action)} &nbsp; <strong>When:</strong> ${formatDate(log.created_at)}</p>
                        <p><strong>Old value</strong></p>
                        <pre style="max-height:160px; overflow:auto; background:#f5f5f0; padding:8px; border-radius:6px; text-align:left; font-size:12px;">${escapeHtml(oldPretty)}</pre>
                        <p><strong>New value</strong></p>
                        <pre style="max-height:160px; overflow:auto; background:#f5f5f0; padding:8px; border-radius:6px; text-align:left; font-size:12px;">${escapeHtml(newPretty)}</pre>
                    </div>
                `,
                width: 620,
                confirmButtonText: 'Close',
                confirmButtonColor: '#1c5034'
            });
        }

        function prettyJson(str) {
            if (!str) return '—';
            try {
                return JSON.stringify(JSON.parse(str), null, 2);
            } catch (e) {
                return str;
            }
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString.replace(' ', 'T'));
            return date.toLocaleDateString('en-IN') + ' · ' + date.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
        }
    </script>
</body>
</html>
