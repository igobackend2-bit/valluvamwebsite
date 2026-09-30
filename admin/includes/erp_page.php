<?php
// Shared page shell for the Purchase / Costing / P&L admin pages (added 30 Sep 2026).
// Same markup as every existing admin page (sidebar, topbar, admin.css), plus the
// small scoped erp.css / erp.js used only by these new pages.
require_once __DIR__ . '/check_admin.php';

function erp_page_start(string $title, string $sub, string $actions = '') {
    global $admin_username, $admin_full_name;
    $v = @filemtime(__DIR__ . '/../assets/erp.js') ?: 1;
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="assets/erp.css?v=<?= $v ?>">
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <div class="adm-sub"><?= htmlspecialchars($sub) ?></div>
                </div>
                <div class="erp-top-actions"><?= $actions ?></div>
            </div>
<?php
}

function erp_page_end(string $script = '') {
    $v = @filemtime(__DIR__ . '/../assets/erp.js') ?: 1;
    ?>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/erp.js?v=<?= $v ?>"></script>
    <?php if ($script): ?><script><?= $script ?></script><?php endif; ?>
</body>
</html>
<?php
}
