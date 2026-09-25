<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account — Valluvam Admin</title>
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
                    <h1>My Account</h1>
                    <div class="adm-sub">Signed in as <?php echo htmlspecialchars($admin_full_name); ?> (<?php echo htmlspecialchars($admin_username); ?>) — <?php echo htmlspecialchars($admin_role_name); ?></div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Change password</h2></div>
                <div class="adm-card-body">
                    <p class="adm-cell-sub" style="margin-bottom:12px;">If you're logging in for the first time with a temporary password, change it now.</p>
                    <div style="display:flex; flex-direction:column; gap:10px; max-width:360px;">
                        <div class="adm-field">
                            <label>Current password</label>
                            <input type="password" id="oldPassword" class="adm-input">
                        </div>
                        <div class="adm-field">
                            <label>New password</label>
                            <input type="password" id="newPassword" class="adm-input">
                        </div>
                        <div class="adm-field">
                            <label>Confirm new password</label>
                            <input type="password" id="confirmPassword" class="adm-input">
                        </div>
                        <button class="adm-btn adm-btn-primary" id="changePasswordBtn"><i class="fas fa-key"></i> Change password</button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#changePasswordBtn').on('click', changePassword);
        });

        function changePassword() {
            const oldPassword = $('#oldPassword').val();
            const newPassword = $('#newPassword').val();
            const confirmPassword = $('#confirmPassword').val();

            if (!oldPassword || !newPassword) {
                Swal.fire({ title: 'Missing fields', text: 'Current and new password are both required.', icon: 'warning', confirmButtonColor: '#1c5034' });
                return;
            }
            if (newPassword.length < 6) {
                Swal.fire({ title: 'Password too short', text: 'New password must be at least 6 characters.', icon: 'warning', confirmButtonColor: '#1c5034' });
                return;
            }
            if (newPassword !== confirmPassword) {
                Swal.fire({ title: 'Passwords do not match', text: 'New password and confirmation must match.', icon: 'warning', confirmButtonColor: '#1c5034' });
                return;
            }

            $.ajax({
                url: '../assets/db_query/admin/change_own_password.php',
                type: 'POST',
                data: { old_password: oldPassword, new_password: newPassword },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#oldPassword').val('');
                        $('#newPassword').val('');
                        $('#confirmPassword').val('');
                        Swal.fire({ title: 'Password changed', icon: 'success', confirmButtonColor: '#1c5034', timer: 1500, showConfirmButton: false });
                    } else {
                        Swal.fire({ title: 'Could not change password', text: response.message || 'Please try again.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not change password', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }
    </script>
</body>
</html>
