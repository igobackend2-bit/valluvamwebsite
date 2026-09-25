<?php
require_once __DIR__ . '/includes/check_admin.php';

// Super-Admin-only page. Every endpoint this page calls also enforces
// require_permission($pdo,'users.manage') server-side (seeded only to
// role_id 1), but gate the page itself too.
if ((int)($admin_role_id ?? 0) !== 1) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Access denied</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Access denied</h2><p>Only Super Admin can manage admin users and roles.</p><p><a href="dashboard.php">Back to dashboard</a></p></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users & Roles — Valluvam Admin</title>
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
                    <h1>Admin Users & Roles</h1>
                    <div class="adm-sub">Manage admin accounts and per-role permissions</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addUserBtn"><i class="fas fa-plus"></i> New admin user</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Admin users</h2></div>
                <div class="adm-card-body" id="usersTable">
                    <div class="adm-table-wrap"><table class="adm-table"><tbody>
                        <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                    </tbody></table></div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Roles & permissions</h2></div>
                <div class="adm-card-body">
                    <div class="adm-field" style="max-width:280px;">
                        <label>Role</label>
                        <select id="roleSelect" class="adm-select"></select>
                    </div>
                    <div id="permissionsWrap" style="margin-top:12px;">
                        <div class="adm-empty"><i class="fas fa-user-shield"></i><p>Loading roles…</p></div>
                    </div>
                    <div style="margin-top:12px;">
                        <button class="adm-btn adm-btn-primary" id="savePermissionsBtn" style="display:none;">Save permissions</button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let rolesCache = [];
        let permissionsCache = [];
        let matrixCache = {};
        let rolesList = [];

        $(document).ready(function() {
            loadUsers();
            loadRolesPermissions();
            $('#addUserBtn').on('click', function() { editUser(null); });
            $('#roleSelect').on('change', function() { renderPermissionsForRole($(this).val()); });
            $('#savePermissionsBtn').on('click', saveRolePermissions);
        });

        // ---------- Admin users ----------

        function loadUsers() {
            $.ajax({
                url: '../assets/db_query/admin/get_admin_users.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayUsers(data.users);
                    } else {
                        $('#usersTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load admin users.') + '</div>');
                    }
                },
                error: function() {
                    $('#usersTable').html('<div class="adm-error">Could not reach the server while loading admin users.</div>');
                }
            });
        }

        function loadRoles() {
            return $.ajax({ url: '../assets/db_query/admin/get_roles_permissions.php', type: 'GET', dataType: 'json' });
        }

        function displayUsers(users) {
            if (users.length === 0) {
                $('#usersTable').html('<div class="adm-empty"><i class="fas fa-users"></i><p><strong>No admin users yet</strong></p></div>');
                return;
            }

            let rows = '';
            users.forEach(u => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(u.username)}${u.full_name ? '<div class="adm-cell-sub">' + escapeHtml(u.full_name) + '</div>' : ''}</td>
                    <td>${escapeHtml(u.email || '—')}</td>
                    <td>${escapeHtml(u.role_name || '—')}</td>
                    <td>${u.status === 'active' ? '<span class="adm-badge is-green">Active</span>' : '<span class="adm-badge is-neutral">Inactive</span>'}</td>
                    <td>${formatDate(u.last_login_at)}</td>
                    <td>
                        <button class="adm-icon-btn edit-user" data-user='${JSON.stringify(u).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="adm-icon-btn ${u.status === 'active' ? 'is-danger' : ''} toggle-user" data-id="${u.id}" data-status="${u.status}" data-name="${escapeHtml(u.username)}" title="${u.status === 'active' ? 'Deactivate' : 'Activate'}">
                            <i class="fas ${u.status === 'active' ? 'fa-user-slash' : 'fa-user-check'}"></i>
                        </button>
                    </td>
                </tr>`;
            });

            $('#usersTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-user').on('click', function() { editUser($(this).data('user')); });
            $('.toggle-user').on('click', function() {
                toggleUserStatus($(this).data('id'), $(this).data('status'), $(this).data('name'));
            });
        }

        function editUser(user) {
            const roleOptions = rolesList.map(r =>
                `<option value="${r.id}" ${user && Number(user.role_id) === r.id ? 'selected' : (!user && r.id === 3 ? 'selected' : '')}>${escapeHtml(r.name)}</option>`
            ).join('');

            Swal.fire({
                title: user ? `Edit ${user.username}` : 'New admin user',
                html: `
                    <input id="swal-username" class="swal2-input" placeholder="Username" value="${user ? escapeHtml(user.username) : ''}">
                    <input id="swal-fullname" class="swal2-input" placeholder="Full name" value="${user ? escapeHtml(user.full_name || '') : ''}">
                    <input id="swal-email" class="swal2-input" placeholder="Email" value="${user ? escapeHtml(user.email || '') : ''}">
                    <select id="swal-role" class="swal2-input">${roleOptions}</select>
                    <select id="swal-status" class="swal2-input">
                        <option value="active" ${!user || user.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${user && user.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                    <input id="swal-password" type="password" class="swal2-input" placeholder="${user ? 'New password (leave blank to keep current)' : 'Password'}">
                `,
                confirmButtonText: user ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const username = $('#swal-username').val().trim();
                    const roleId = $('#swal-role').val();
                    const password = $('#swal-password').val();
                    if (!username || !roleId) {
                        Swal.showValidationMessage('Username and role are required');
                        return false;
                    }
                    if (!user && !password) {
                        Swal.showValidationMessage('Password is required for a new admin user');
                        return false;
                    }
                    if (password && password.length < 6) {
                        Swal.showValidationMessage('Password must be at least 6 characters');
                        return false;
                    }
                    return {
                        id: user ? user.id : null,
                        username: username,
                        full_name: $('#swal-fullname').val().trim(),
                        email: $('#swal-email').val().trim(),
                        role_id: roleId,
                        status: $('#swal-status').val(),
                        password: password
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveUser(result.value);
            });
        }

        function saveUser(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_admin_user.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadUsers();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The admin user was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function toggleUserStatus(id, status, name) {
            const action = status === 'active' ? 'deactivate' : 'activate';
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} ${name}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: status === 'active' ? '#a8442f' : '#1c5034',
                cancelButtonColor: '#6b6459',
                confirmButtonText: action.charAt(0).toUpperCase() + action.slice(1)
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/toggle_admin_user_status.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Updated', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadUsers();
                            } else {
                                Swal.fire({ title: 'Could not update', text: response.message || 'Status was not changed.', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not update', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    });
                }
            });
        }

        // ---------- Roles & permissions ----------

        function loadRolesPermissions() {
            loadRoles().then(function(data) {
                if (data.status !== 'success') {
                    $('#permissionsWrap').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load roles.') + '</div>');
                    return;
                }
                rolesCache = data.roles;
                rolesList = data.roles;
                permissionsCache = data.permissions;
                matrixCache = data.matrix;

                const $sel = $('#roleSelect');
                $sel.empty();
                rolesCache.forEach(r => $sel.append(`<option value="${r.id}">${escapeHtml(r.name)}</option>`));
                if (rolesCache.length) {
                    renderPermissionsForRole(rolesCache[0].id);
                }
            }).fail(function() {
                $('#permissionsWrap').html('<div class="adm-error">Could not reach the server while loading roles.</div>');
            });
        }

        function renderPermissionsForRole(roleId) {
            roleId = String(roleId);
            const isSuperAdmin = roleId === '1';
            const granted = (matrixCache[roleId] || []).reduce((set, k) => { set[k] = true; return set; }, {});

            let html = '';
            if (isSuperAdmin) {
                html += '<p class="adm-cell-sub" style="margin-bottom:8px;">Super Admin always has every permission — this list can\'t be edited.</p>';
            }
            html += '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:8px;">';
            permissionsCache.forEach(p => {
                const checked = isSuperAdmin || granted[p.perm_key] ? 'checked' : '';
                const disabled = isSuperAdmin ? 'disabled' : '';
                html += `<label style="display:flex; align-items:center; gap:6px; font-size:14px;">
                    <input type="checkbox" class="perm-checkbox" value="${escapeHtml(p.perm_key)}" ${checked} ${disabled}>
                    <span>${escapeHtml(p.perm_key)}</span>
                </label>`;
            });
            html += '</div>';

            $('#permissionsWrap').html(html);
            $('#savePermissionsBtn').toggle(!isSuperAdmin);
        }

        function saveRolePermissions() {
            const roleId = $('#roleSelect').val();
            if (roleId === '1') return;

            const permKeys = [];
            $('.perm-checkbox:checked').each(function() { permKeys.push($(this).val()); });

            $.ajax({
                url: '../assets/db_query/admin/save_role_permissions.php',
                type: 'POST',
                data: { role_id: roleId, perm_keys: permKeys },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Permissions saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadRolesPermissions();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'Permissions were not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
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
