<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Valluvam Admin</title>
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
                    <h1>Settings</h1>
                    <div class="adm-sub">Store-wide settings</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-body" id="settingsForm">
                    <div class="adm-table-wrap">
                        <table class="adm-table"><tbody>
                            <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                        </tbody></table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() { loadSettings(); });

        function loadSettings() {
            $.ajax({
                url: '../assets/db_query/admin/get_settings.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displaySettings(data.settings);
                    } else {
                        $('#settingsForm').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load settings.') + '</div>');
                    }
                },
                error: function() {
                    $('#settingsForm').html('<div class="adm-error">Could not reach the server while loading settings.</div>');
                }
            });
        }

        function displaySettings(settings) {
            if (settings.length === 0) {
                $('#settingsForm').html('<div class="adm-empty"><i class="fas fa-gear"></i><p><strong>No settings found</strong></p></div>');
                return;
            }

            let rows = '';
            settings.forEach(s => {
                rows += `<div class="adm-field" style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">${escapeHtml(prettyLabel(s.setting_key))}</label>
                    <div class="adm-cell-sub" style="margin-bottom:6px;">${escapeHtml(s.description || '')}</div>
                    <div style="display:flex;gap:8px;">
                        <input type="text" class="adm-input setting-input" data-key="${escapeHtml(s.setting_key)}" value="${escapeHtml(s.setting_value || '')}" style="flex:1;">
                        <button class="adm-btn adm-btn-ghost save-setting" data-key="${escapeHtml(s.setting_key)}">Save</button>
                    </div>
                </div>`;
            });

            $('#settingsForm').html(rows);

            $('.save-setting').on('click', function() {
                const key = $(this).data('key');
                const value = $(`.setting-input[data-key="${key}"]`).val();
                saveSetting(key, value);
            });
        }

        function prettyLabel(key) {
            return key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        }

        function saveSetting(key, value) {
            $.ajax({
                url: '../assets/db_query/admin/update_setting.php',
                type: 'POST',
                data: { key: key, value: value },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1000, showConfirmButton: false });
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
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
    </script>
</body>
</html>
