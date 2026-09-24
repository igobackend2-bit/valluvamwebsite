<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin login — Valluvam</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="adm-login-page">
    <main class="adm-login-card">
        <div class="adm-login-head">
            <img src="../images/logo.png" alt="Valluvam logo">
            <h1>Admin login</h1>
            <p>Sign in to manage orders and products</p>
        </div>

        <div class="adm-error adm-login-error" id="loginError" role="alert"></div>

        <form id="adminLoginForm" novalidate>
            <div class="adm-field">
                <label for="username">Username</label>
                <input type="text" class="adm-input" id="username" name="username" required autocomplete="username">
            </div>
            <div class="adm-field">
                <label for="password">Password</label>
                <input type="password" class="adm-input" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%; justify-content:center; padding:11px;">
                <i class="fas fa-arrow-right-to-bracket"></i> Sign in
            </button>
        </form>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function showError(message) {
            const $err = $('#loginError');
            $err.text(message).show();
        }

        $(document).ready(function() {
            $('#adminLoginForm').on('submit', function(e) {
                e.preventDefault();
                $('#loginError').hide();

                const username = $('#username').val().trim();
                const password = $('#password').val();

                if (!username || !password) {
                    showError('Enter both a username and a password.');
                    return;
                }

                const $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true).css('opacity', 0.7);

                $.ajax({
                    url: '../assets/db_query/admin/login.php',
                    type: 'POST',
                    data: { username: username, password: password },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            window.location.href = 'index.php';
                        } else {
                            showError(response.message || 'Incorrect username or password.');
                            $btn.prop('disabled', false).css('opacity', 1);
                        }
                    },
                    error: function() {
                        showError('Could not reach the server. Please try again.');
                        $btn.prop('disabled', false).css('opacity', 1);
                    }
                });
            });
        });
    </script>
</body>
</html>
