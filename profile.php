<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$actionpage = basename($_SERVER['PHP_SELF'], '.php');
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account – Valluvam Products</title>
    <meta name="description" content="Manage your Valluvam account – profile, orders, addresses, wishlist and inbox.">
    <meta name="robots" content="noindex, follow">
    <link rel="stylesheet" href="css/redesign.css?v=2">
    <style>
        /* ── PAGE SHELL ─────────────────────────────────────────── */
        .vp-account-page {
            background: #f5f2ec;
            min-height: 100vh;
            padding: 50px 0 80px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .vp-account-wrap { display: flex; gap: 28px; align-items: flex-start; }

        /* ── SIDEBAR ────────────────────────────────────────────── */
        .vp-sidebar {
            width: 260px;
            flex-shrink: 0;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(20,50,30,.07);
            border: 1px solid rgba(180,210,170,.4);
            position: sticky;
            top: 90px;
        }

        .vp-sidebar-header {
            background: linear-gradient(135deg, #1a3d2b 0%, #2d6a4f 100%);
            padding: 28px 20px 24px;
            text-align: center;
            color: #fff;
        }

        .vp-avatar-wrap {
            width: 72px; height: 72px; border-radius: 50%;
            background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
            font-size: 28px; color: #fff;
            border: 3px solid rgba(255,255,255,.4);
            overflow: hidden;
        }

        .vp-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }

        .vp-sidebar-name { font-size: 1rem; font-weight: 700; margin: 0 0 2px; }
        .vp-sidebar-email { font-size: 0.75rem; opacity: .75; margin: 0; word-break: break-all; }

        .vp-nav { padding: 10px 0; }
        .vp-nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; cursor: pointer;
            font-size: 0.875rem; font-weight: 600; color: #3a5040;
            border-left: 3px solid transparent;
            transition: all .2s ease;
            text-decoration: none;
        }
        .vp-nav-item:hover { background: #f0f7f0; color: #1a3d2b; text-decoration: none; }
        .vp-nav-item.active { background: #eaf4ea; color: #1a3d2b; border-left-color: #2d6a4f; }
        .vp-nav-item i { width: 20px; text-align: center; font-size: 15px; color: #4a8060; }
        .vp-nav-badge {
            margin-left: auto; background: #d4430a; color: #fff;
            font-size: 0.65rem; font-weight: 800; border-radius: 50px;
            padding: 2px 7px; min-width: 20px; text-align: center;
        }
        .vp-nav-divider { height: 1px; background: #eef0ec; margin: 6px 0; }

        /* ── MAIN PANEL ─────────────────────────────────────────── */
        .vp-main { flex: 1; min-width: 0; }

        .vp-panel {
            display: none; background: #fff; border-radius: 18px;
            padding: 32px; box-shadow: 0 4px 20px rgba(20,50,30,.07);
            border: 1px solid rgba(180,210,170,.4); animation: vpFadeIn .25s ease;
        }
        .vp-panel.active { display: block; }

        @keyframes vpFadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

        .vp-panel-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.5rem; color: #1a3d2b; margin: 0 0 6px;
        }
        .vp-panel-sub { font-size: 0.85rem; color: #6a8070; margin: 0 0 28px; }

        /* ── FORM STYLES ────────────────────────────────────────── */
        .vp-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        @media (max-width: 640px) { .vp-form-grid { grid-template-columns: 1fr; } }

        .vp-field { display: flex; flex-direction: column; gap: 6px; }
        .vp-field.full { grid-column: 1 / -1; }

        .vp-field label { font-size: 0.78rem; font-weight: 700; color: #3a5040; text-transform: uppercase; letter-spacing: .05em; }

        .vp-input {
            padding: 10px 14px; border-radius: 10px;
            border: 1.5px solid #d4e8d0; background: #f9fbf9;
            font-size: 0.9rem; color: #1a3d2b;
            transition: border-color .2s ease, box-shadow .2s ease;
            font-family: inherit;
        }
        .vp-input:focus { outline: none; border-color: #2d6a4f; box-shadow: 0 0 0 3px rgba(45,106,79,.1); background: #fff; }

        .vp-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 24px; border-radius: 50px; font-size: 0.875rem;
            font-weight: 700; cursor: pointer; border: none; transition: all .25s ease;
            font-family: inherit;
        }
        .vp-btn-primary { background: #2d6a4f; color: #fff; }
        .vp-btn-primary:hover { background: #1a3d2b; transform: translateY(-1px); }
        .vp-btn-outline { background: #fff; color: #2d6a4f; border: 1.5px solid #b8d4b4; }
        .vp-btn-outline:hover { background: #eaf4ea; }
        .vp-btn-danger { background: #fff; color: #c0392b; border: 1.5px solid #f0b8b3; }
        .vp-btn-danger:hover { background: #fdf0f0; }

        .vp-alert { padding: 12px 16px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; display: none; }
        .vp-alert-success { background: #eaf4ea; color: #1a6a3a; border: 1px solid #b8d4b4; display: block; }
        .vp-alert-error   { background: #fdf0f0; color: #a93226; border: 1px solid #f0b8b3; display: block; }

        /* ── ORDERS LIST ────────────────────────────────────────── */
        .vp-order-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; }
        .vp-filter-pill {
            padding: 6px 16px; border-radius: 50px; font-size: 0.78rem; font-weight: 700;
            background: #f0f4f0; color: #4a7060; border: 1.5px solid #d0e4d0; cursor: pointer;
            transition: all .2s ease;
        }
        .vp-filter-pill.active, .vp-filter-pill:hover { background: #2d6a4f; color: #fff; border-color: #2d6a4f; }

        .vp-order-card {
            border: 1px solid #deecd8; border-radius: 14px; padding: 20px;
            margin-bottom: 14px; transition: box-shadow .2s ease;
        }
        .vp-order-card:hover { box-shadow: 0 6px 20px rgba(20,50,30,.08); }

        .vp-order-head {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px; margin-bottom: 14px;
        }
        .vp-order-id { font-size: 0.95rem; font-weight: 700; color: #1a3d2b; }
        .vp-order-date { font-size: 0.78rem; color: #7a9080; }

        .vp-status-badge {
            padding: 4px 12px; border-radius: 50px; font-size: 0.72rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .vp-status-badge.pending   { background: #fff8e1; color: #b8860b; border: 1px solid #f0d080; }
        .vp-status-badge.processing{ background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .vp-status-badge.shipped   { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .vp-status-badge.delivered { background: #e8f5e9; color: #1a7a40; border: 1px solid #88c898; }
        .vp-status-badge.cancelled { background: #fdf0f0; color: #c0392b; border: 1px solid #f0b8b3; }

        .vp-order-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 0.83rem; color: #4a6050; margin-bottom: 12px; }
        .vp-order-meta span strong { color: #1a3d2b; }

        .vp-order-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ── ADDRESS GRID ───────────────────────────────────────── */
        .vp-addr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }

        .vp-addr-card {
            border: 1.5px solid #d4e8d0; border-radius: 14px; padding: 18px;
            position: relative; transition: box-shadow .2s;
        }
        .vp-addr-card.default { border-color: #2d6a4f; background: #f5fbf5; }
        .vp-addr-card:hover { box-shadow: 0 6px 18px rgba(20,50,30,.09); }

        .vp-addr-label {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .08em; color: #fff; background: #4a8060;
            border-radius: 50px; padding: 2px 10px; margin-bottom: 8px;
        }
        .vp-addr-card.default .vp-addr-label { background: #2d6a4f; }
        .vp-addr-default-tag {
            position: absolute; top: 14px; right: 14px;
            font-size: 0.65rem; font-weight: 800; color: #1a8040;
            background: #d4f0da; border-radius: 50px; padding: 2px 8px;
        }
        .vp-addr-name { font-weight: 700; color: #1a3d2b; margin-bottom: 4px; }
        .vp-addr-text { font-size: 0.83rem; color: #4a6050; line-height: 1.55; margin-bottom: 12px; }
        .vp-addr-phone { font-size: 0.8rem; color: #6a8070; margin-bottom: 12px; }
        .vp-addr-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ── INBOX ──────────────────────────────────────────────── */
        .vp-notif-item {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 16px 0; border-bottom: 1px solid #eef0ec;
            cursor: pointer; transition: opacity .2s;
        }
        .vp-notif-item.unread { background: #f6fbf6; margin: 0 -32px; padding: 16px 32px; }
        .vp-notif-item:last-child { border-bottom: none; }
        .vp-notif-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #2d6a4f;
            flex-shrink: 0; margin-top: 6px;
        }
        .vp-notif-icon {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 16px;
        }
        .vp-notif-icon.order   { background: #e8f5e9; color: #2d6a4f; }
        .vp-notif-icon.promo   { background: #fff8e1; color: #b8860b; }
        .vp-notif-icon.system  { background: #e3f2fd; color: #1565c0; }
        .vp-notif-icon.review  { background: #fce4ec; color: #c2185b; }
        .vp-notif-body { flex: 1; min-width: 0; }
        .vp-notif-title { font-weight: 700; font-size: 0.88rem; color: #1a3d2b; margin-bottom: 2px; }
        .vp-notif-desc  { font-size: 0.8rem; color: #5a7060; line-height: 1.5; }
        .vp-notif-time  { font-size: 0.72rem; color: #9ab0a0; margin-top: 4px; }

        /* ── WISHLIST ───────────────────────────────────────────── */
        .vp-wishlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
        .vp-wish-card {
            border: 1px solid #d4e8d0; border-radius: 14px; overflow: hidden;
            transition: box-shadow .25s, transform .25s;
        }
        .vp-wish-card:hover { box-shadow: 0 8px 22px rgba(20,50,30,.1); transform: translateY(-3px); }
        .vp-wish-img { width: 100%; aspect-ratio: 1/1; object-fit: cover; }
        .vp-wish-body { padding: 10px 12px; }
        .vp-wish-name { font-size: 0.85rem; font-weight: 700; color: #1a3d2b; margin-bottom: 4px; }
        .vp-wish-price { font-size: 0.8rem; color: #4a8060; font-weight: 700; }

        /* ── STATS ROW ──────────────────────────────────────────── */
        .vp-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }
        @media (max-width: 640px) { .vp-stats-row { grid-template-columns: repeat(2, 1fr); } }

        .vp-stat-card {
            background: linear-gradient(135deg, #f5fbf5, #e8f5e9);
            border: 1px solid #c8e4c0; border-radius: 14px; padding: 18px;
            text-align: center;
        }
        .vp-stat-num { font-size: 1.8rem; font-weight: 800; color: #1a3d2b; line-height: 1; }
        .vp-stat-label { font-size: 0.72rem; color: #5a8060; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }

        /* ── EMPTY STATE ────────────────────────────────────────── */
        .vp-empty { text-align: center; padding: 60px 20px; }
        .vp-empty i { font-size: 3rem; color: #b8d4b0; margin-bottom: 14px; }
        .vp-empty h4 { color: #1a3d2b; font-size: 1.1rem; margin-bottom: 6px; }
        .vp-empty p  { color: #7a9080; font-size: 0.85rem; margin-bottom: 20px; }

        /* ── MODAL ──────────────────────────────────────────────── */
        .vp-modal-bg {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 1050; align-items: center; justify-content: center;
        }
        .vp-modal-bg.open { display: flex; }
        .vp-modal {
            background: #fff; border-radius: 18px; padding: 32px;
            width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
        }
        .vp-modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; }
        .vp-modal-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: #1a3d2b; }
        .vp-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #7a9080; }

        /* ── RESPONSIVE ─────────────────────────────────────────── */
        @media (max-width: 900px) {
            .vp-account-wrap { flex-direction: column; }
            .vp-sidebar { width: 100%; position: static; }
            .vp-nav { display: flex; overflow-x: auto; padding: 0; gap: 0; scrollbar-width: none; }
            .vp-nav::-webkit-scrollbar { display: none; }
            .vp-nav-item { flex-shrink: 0; border-left: none; border-bottom: 3px solid transparent; padding: 12px 16px; }
            .vp-nav-item.active { border-bottom-color: #2d6a4f; }
        }
        @media (max-width: 640px) {
            .vp-panel { padding: 20px; }
            .vp-stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="goto-here">

<div class="vp-account-page">
    <div class="container">
        <div class="vp-account-wrap">

            <!-- ── SIDEBAR ──────────────────────────────────────── -->
            <aside class="vp-sidebar">
                <div class="vp-sidebar-header">
                    <div class="vp-avatar-wrap" id="avatarWrap">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <p class="vp-sidebar-name" id="sidebarName">Loading...</p>
                    <p class="vp-sidebar-email" id="sidebarEmail"></p>
                </div>

                <nav class="vp-nav">
                    <a class="vp-nav-item active" data-tab="overview" href="#">
                        <i class="fa-solid fa-house-chimney"></i> Overview
                    </a>
                    <a class="vp-nav-item" data-tab="profile" href="#">
                        <i class="fa-solid fa-circle-user"></i> My Profile
                    </a>
                    <a class="vp-nav-item" data-tab="orders" href="#">
                        <i class="fa-solid fa-bag-shopping"></i> My Orders
                    </a>
                    <a class="vp-nav-item" data-tab="addresses" href="#">
                        <i class="fa-solid fa-location-dot"></i> Addresses
                    </a>
                    <a class="vp-nav-item" data-tab="wishlist" href="#">
                        <i class="fa-solid fa-heart"></i> Wishlist
                    </a>
                    <a class="vp-nav-item" data-tab="inbox" href="#">
                        <i class="fa-solid fa-bell"></i> Inbox
                        <span class="vp-nav-badge d-none" id="inboxBadge">0</span>
                    </a>
                    <div class="vp-nav-divider"></div>
                    <a class="vp-nav-item" data-tab="security" href="#">
                        <i class="fa-solid fa-lock"></i> Security
                    </a>
                    <a class="vp-nav-item" href="logout.php" style="color:#c0392b;">
                        <i class="fa-solid fa-right-from-bracket" style="color:#c0392b;"></i> Logout
                    </a>
                </nav>
            </aside>

            <!-- ── MAIN CONTENT ──────────────────────────────────── -->
            <div class="vp-main">

                <!-- OVERVIEW TAB -->
                <div class="vp-panel active" id="tab-overview">
                    <h2 class="vp-panel-title" id="welcomeName">Welcome back!</h2>
                    <p class="vp-panel-sub">Here's a summary of your account activity.</p>

                    <div class="vp-stats-row">
                        <div class="vp-stat-card">
                            <div class="vp-stat-num" id="totalOrders">–</div>
                            <div class="vp-stat-label">Total Orders</div>
                        </div>
                        <div class="vp-stat-card">
                            <div class="vp-stat-num" id="pendingOrders">–</div>
                            <div class="vp-stat-label">Pending</div>
                        </div>
                        <div class="vp-stat-card">
                            <div class="vp-stat-num" id="wishlistCount">–</div>
                            <div class="vp-stat-label">Wishlist</div>
                        </div>
                        <div class="vp-stat-card">
                            <div class="vp-stat-num" id="unreadNotifs">–</div>
                            <div class="vp-stat-label">Inbox</div>
                        </div>
                    </div>

                    <!-- Recent 3 orders -->
                    <h4 style="font-weight:700;color:#1a3d2b;margin-bottom:14px;font-size:1rem;">Recent Orders</h4>
                    <div id="recentOrders"><div class="text-center py-3"><div class="spinner-border text-success" role="status"></div></div></div>

                    <div class="d-flex gap-3 mt-4 flex-wrap">
                        <a href="shop.php" class="vp-btn vp-btn-primary"><i class="fa-solid fa-cart-shopping"></i> Shop Now</a>
                        <a data-tab="orders" href="#" class="vp-btn vp-btn-outline vp-nav-link"><i class="fa-solid fa-bag-shopping"></i> All Orders</a>
                    </div>
                </div>

                <!-- PROFILE TAB -->
                <div class="vp-panel" id="tab-profile">
                    <h2 class="vp-panel-title">My Profile</h2>
                    <p class="vp-panel-sub">Update your personal information.</p>
                    <div id="profileAlert"></div>
                    <form id="profileForm">
                        <div class="vp-form-grid">
                            <div class="vp-field full">
                                <label>Full Name</label>
                                <input class="vp-input" type="text" name="full_name" id="pFullName" placeholder="Your full name" maxlength="120">
                            </div>
                            <div class="vp-field">
                                <label>Username</label>
                                <input class="vp-input" type="text" id="pUsername" disabled>
                            </div>
                            <div class="vp-field">
                                <label>Email Address</label>
                                <input class="vp-input" type="email" id="pEmail" disabled>
                            </div>
                            <div class="vp-field">
                                <label>Phone Number</label>
                                <input class="vp-input" type="tel" name="phone" id="pPhone" placeholder="10-digit mobile number" maxlength="15">
                            </div>
                            <div class="vp-field">
                                <label>Gender</label>
                                <select class="vp-input" name="gender" id="pGender">
                                    <option value="">Prefer not to say</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                    <option value="prefer_not_to_say">Prefer not to say</option>
                                </select>
                            </div>
                            <div class="vp-field">
                                <label>Date of Birth</label>
                                <input class="vp-input" type="date" name="dob" id="pDob" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="vp-btn vp-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- ORDERS TAB -->
                <div class="vp-panel" id="tab-orders">
                    <h2 class="vp-panel-title">My Orders</h2>
                    <p class="vp-panel-sub">Track, manage and review your orders.</p>

                    <div class="vp-order-filters">
                        <div class="vp-filter-pill active" data-filter="all">All</div>
                        <div class="vp-filter-pill" data-filter="pending">Pending</div>
                        <div class="vp-filter-pill" data-filter="processing">Processing</div>
                        <div class="vp-filter-pill" data-filter="shipped">Shipped</div>
                        <div class="vp-filter-pill" data-filter="delivered">Delivered</div>
                        <div class="vp-filter-pill" data-filter="cancelled">Cancelled</div>
                    </div>

                    <div id="ordersContainer"><div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div></div>
                </div>

                <!-- ADDRESSES TAB -->
                <div class="vp-panel" id="tab-addresses">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <h2 class="vp-panel-title" style="margin-bottom:0;">Saved Addresses</h2>
                            <p class="vp-panel-sub" style="margin-bottom:0;">Manage your delivery addresses.</p>
                        </div>
                        <button class="vp-btn vp-btn-primary" onclick="openAddressModal()"><i class="fa-solid fa-plus"></i> Add New</button>
                    </div>
                    <hr style="border-color:#eef0ec;margin:20px 0;">
                    <div class="vp-addr-grid" id="addressGrid"><div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div></div>
                </div>

                <!-- WISHLIST TAB -->
                <div class="vp-panel" id="tab-wishlist">
                    <h2 class="vp-panel-title">My Wishlist</h2>
                    <p class="vp-panel-sub">Products you've saved for later.</p>
                    <div id="wishlistGrid" class="vp-wishlist-grid"><div class="text-center py-4 w-100"><div class="spinner-border text-success" role="status"></div></div></div>
                </div>

                <!-- INBOX TAB -->
                <div class="vp-panel" id="tab-inbox">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="vp-panel-title" style="margin-bottom:0;">Inbox</h2>
                            <p class="vp-panel-sub" style="margin-bottom:0;">Order updates, offers & notifications.</p>
                        </div>
                        <button class="vp-btn vp-btn-outline" onclick="markAllRead()"><i class="fa-solid fa-check-double"></i> Mark All Read</button>
                    </div>
                    <div id="notifList"><div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div></div>
                </div>

                <!-- SECURITY TAB -->
                <div class="vp-panel" id="tab-security">
                    <h2 class="vp-panel-title">Security</h2>
                    <p class="vp-panel-sub">Manage your password and account security.</p>
                    <div id="securityAlert"></div>
                    <form id="passwordForm">
                        <div class="vp-form-grid">
                            <div class="vp-field full">
                                <label>Current Password</label>
                                <input class="vp-input" type="password" name="current_password" placeholder="Enter current password" required>
                            </div>
                            <div class="vp-field">
                                <label>New Password</label>
                                <input class="vp-input" type="password" name="new_password" placeholder="At least 6 characters" required minlength="6">
                            </div>
                            <div class="vp-field">
                                <label>Confirm New Password</label>
                                <input class="vp-input" type="password" name="confirm_password" placeholder="Repeat new password" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="vp-btn vp-btn-primary"><i class="fa-solid fa-key"></i> Update Password</button>
                        </div>
                    </form>

                    <hr style="border-color:#eef0ec;margin:32px 0;">
                    <h5 style="color:#1a3d2b;font-weight:700;margin-bottom:8px;">Account Details</h5>
                    <p style="font-size:.83rem;color:#5a7060;">Member since: <strong id="memberSince">–</strong></p>
                    <p style="font-size:.83rem;color:#5a7060;margin-top:4px;">Need help? <a href="contact.php" style="color:#2d6a4f;font-weight:700;">Contact Support</a></p>
                </div>

            </div><!-- /vp-main -->
        </div><!-- /vp-account-wrap -->
    </div><!-- /container -->
</div><!-- /vp-account-page -->

<!-- ADDRESS MODAL -->
<div class="vp-modal-bg" id="addressModal">
    <div class="vp-modal">
        <div class="vp-modal-head">
            <span class="vp-modal-title" id="addrModalTitle">Add New Address</span>
            <button class="vp-modal-close" onclick="closeAddressModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="addrModalAlert"></div>
        <form id="addressForm">
            <input type="hidden" name="action" id="addrAction" value="add">
            <input type="hidden" name="id"     id="addrId"     value="0">
            <div class="vp-form-grid">
                <div class="vp-field">
                    <label>Label</label>
                    <select class="vp-input" name="label" id="addrLabel">
                        <option value="Home">🏠 Home</option>
                        <option value="Work">🏢 Work</option>
                        <option value="Other">📍 Other</option>
                    </select>
                </div>
                <div class="vp-field">
                    <label>Set as Default</label>
                    <select class="vp-input" name="is_default" id="addrDefault">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="vp-field full">
                    <label>Full Name *</label>
                    <input class="vp-input" type="text" name="full_name" id="addrName" required placeholder="Recipient full name">
                </div>
                <div class="vp-field full">
                    <label>Phone Number *</label>
                    <input class="vp-input" type="tel" name="phone" id="addrPhone" required placeholder="10-digit mobile number">
                </div>
                <div class="vp-field full">
                    <label>Street Address *</label>
                    <input class="vp-input" type="text" name="street" id="addrStreet" required placeholder="House/flat no., street name">
                </div>
                <div class="vp-field full">
                    <label>Apartment / Landmark</label>
                    <input class="vp-input" type="text" name="apartment" id="addrApartment" placeholder="Apartment, landmark (optional)">
                </div>
                <div class="vp-field">
                    <label>City *</label>
                    <input class="vp-input" type="text" name="city" id="addrCity" required placeholder="City">
                </div>
                <div class="vp-field">
                    <label>State *</label>
                    <input class="vp-input" type="text" name="state" id="addrState" required placeholder="State">
                </div>
                <div class="vp-field">
                    <label>Pincode *</label>
                    <input class="vp-input" type="text" name="postcode" id="addrPostcode" required placeholder="6-digit pincode" maxlength="6">
                </div>
            </div>
            <div class="mt-4 d-flex gap-3">
                <button type="submit" class="vp-btn vp-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Address</button>
                <button type="button" class="vp-btn vp-btn-outline" onclick="closeAddressModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// ── Tab Navigation ─────────────────────────────────────────────
const tabs   = document.querySelectorAll('[data-tab]');
const panels = document.querySelectorAll('.vp-panel');

function switchTab(tabName) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
    panels.forEach(p => p.classList.toggle('active', p.id === 'tab-' + tabName));
    if (tabName === 'orders')    loadOrders();
    if (tabName === 'addresses') loadAddresses();
    if (tabName === 'inbox')     loadNotifications();
    if (tabName === 'wishlist')  loadWishlist();
}

tabs.forEach(t => t.addEventListener('click', e => {
    if (t.tagName === 'A' && !t.href.endsWith('#')) return; // let logout link go
    e.preventDefault();
    if (t.dataset.tab) switchTab(t.dataset.tab);
}));

document.querySelectorAll('.vp-nav-link').forEach(el => el.addEventListener('click', e => {
    e.preventDefault(); switchTab(el.dataset.tab);
}));

// ── Load Profile Data ──────────────────────────────────────────
let profileData = {};
fetch('assets/db_query/profile/get_profile.php')
    .then(r => r.json())
    .then(d => {
        if (d.status !== 'success') return;
        const u = d.user;
        profileData = u;
        document.getElementById('sidebarName').textContent  = u.full_name || u.username;
        document.getElementById('sidebarEmail').textContent = u.email;
        document.getElementById('welcomeName').textContent  = 'Welcome back, ' + (u.full_name || u.username) + '!';
        document.getElementById('pFullName').value  = u.full_name || '';
        document.getElementById('pUsername').value  = u.username || '';
        document.getElementById('pEmail').value     = u.email || '';
        document.getElementById('pPhone').value     = u.phone_number || '';
        if (u.gender) document.getElementById('pGender').value = u.gender;
        if (u.dob)    document.getElementById('pDob').value    = u.dob;
        if (u.created_at) {
            document.getElementById('memberSince').textContent = new Date(u.created_at).toLocaleDateString('en-IN', {year:'numeric',month:'long',day:'numeric'});
        }
    });

// ── Profile Form Submit ────────────────────────────────────────
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('assets/db_query/profile/get_profile.php', {method:'POST', body: new FormData(this)})
        .then(r => r.json())
        .then(d => {
            const el = document.getElementById('profileAlert');
            el.innerHTML = `<div class="vp-alert ${d.status==='success'?'vp-alert-success':'vp-alert-error'}"><i class="fa-solid ${d.status==='success'?'fa-circle-check':'fa-circle-exclamation'}"></i> ${d.message}</div>`;
            if (d.status === 'success') {
                document.getElementById('sidebarName').textContent = document.getElementById('pFullName').value || document.getElementById('pUsername').value;
            }
            setTimeout(() => el.innerHTML = '', 4000);
        });
});

// ── Load Orders ────────────────────────────────────────────────
let allOrders = [];
function loadOrders() {
    fetch('assets/db_query/order/get_user_orders.php')
        .then(r => r.json())
        .then(d => {
            allOrders = d.orders || [];
            document.getElementById('totalOrders').textContent   = allOrders.length;
            document.getElementById('pendingOrders').textContent = allOrders.filter(o => o.order_status === 'pending' || !o.order_status).length;
            renderOrders('all');
            renderRecentOrders();
        });
}

function renderRecentOrders() {
    const c = document.getElementById('recentOrders');
    const recent = allOrders.slice(0, 3);
    if (!recent.length) { c.innerHTML = '<div class="vp-empty"><i class="fa-solid fa-bag-shopping"></i><h4>No orders yet</h4><p>Start shopping to see your orders here.</p><a href="shop.php" class="vp-btn vp-btn-primary">Browse Products</a></div>'; return; }
    c.innerHTML = recent.map(o => orderCardHTML(o, true)).join('');
}

function renderOrders(filter) {
    const c = document.getElementById('ordersContainer');
    let list = filter === 'all' ? allOrders : allOrders.filter(o => (o.order_status||'pending').toLowerCase() === filter);
    if (!list.length) { c.innerHTML = '<div class="vp-empty"><i class="fa-solid fa-bag-shopping"></i><h4>No orders found</h4><p>No orders match this filter.</p></div>'; return; }
    c.innerHTML = list.map(o => orderCardHTML(o, false)).join('');
}

function orderCardHTML(o, compact) {
    const status = (o.order_status || 'pending').toLowerCase();
    const date   = new Date(o.created_at).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'});
    const amt    = parseFloat(o.amount||0).toLocaleString('en-IN', {style:'currency',currency:'INR',minimumFractionDigits:0});
    return `<div class="vp-order-card">
        <div class="vp-order-head">
            <div>
                <div class="vp-order-id">#${o.receipt||o.id}</div>
                <div class="vp-order-date">${date}</div>
            </div>
            <span class="vp-status-badge ${status}">${status}</span>
        </div>
        <div class="vp-order-meta">
            <span><strong>Amount:</strong> ${amt}</span>
            <span><strong>Payment:</strong> ${o.payment_method||'–'}</span>
            <span><strong>City:</strong> ${o.city||'–'}</span>
        </div>
        ${!compact ? `<div class="vp-order-actions">
            <a href="order_tracking.php" class="vp-btn vp-btn-outline" style="font-size:.78rem;padding:7px 14px;"><i class="fa-solid fa-magnifying-glass"></i> Track</a>
            <a href="shop.php" class="vp-btn vp-btn-outline" style="font-size:.78rem;padding:7px 14px;"><i class="fa-solid fa-rotate-right"></i> Reorder</a>
            ${status==='delivered'?`<a href="review.php" class="vp-btn vp-btn-outline" style="font-size:.78rem;padding:7px 14px;"><i class="fa-solid fa-star"></i> Review</a>`:``}
        </div>` : ''}
    </div>`;
}

// Order filter pills
document.querySelectorAll('.vp-filter-pill').forEach(p => p.addEventListener('click', function() {
    document.querySelectorAll('.vp-filter-pill').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    renderOrders(this.dataset.filter);
}));

// ── Load Addresses ─────────────────────────────────────────────
function loadAddresses() {
    fetch('assets/db_query/profile/address_query.php')
        .then(r => r.json())
        .then(d => {
            const grid = document.getElementById('addressGrid');
            const list = d.addresses || [];
            if (!list.length) {
                grid.innerHTML = '<div class="vp-empty"><i class="fa-solid fa-location-dot"></i><h4>No addresses saved</h4><p>Add a delivery address to speed up checkout.</p></div>';
                return;
            }
            grid.innerHTML = list.map(a => `
                <div class="vp-addr-card ${a.is_default=='1'?'default':''}">
                    ${a.is_default=='1'?'<span class="vp-addr-default-tag">✓ Default</span>':''}
                    <div class="vp-addr-label">${a.label}</div>
                    <div class="vp-addr-name">${a.full_name}</div>
                    <div class="vp-addr-text">${a.street}${a.apartment?', '+a.apartment:''}, ${a.city}, ${a.state} – ${a.postcode}</div>
                    <div class="vp-addr-phone"><i class="fa-solid fa-phone" style="font-size:11px;"></i> ${a.phone}</div>
                    <div class="vp-addr-actions">
                        <button class="vp-btn vp-btn-outline" style="font-size:.76rem;padding:6px 12px;" onclick="editAddress(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i> Edit</button>
                        ${a.is_default!='1'?`<button class="vp-btn vp-btn-outline" style="font-size:.76rem;padding:6px 12px;" onclick="setDefault(${a.id})"><i class="fa-solid fa-check"></i> Set Default</button>`:''}
                        <button class="vp-btn vp-btn-danger" style="font-size:.76rem;padding:6px 12px;" onclick="deleteAddress(${a.id})"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>`).join('');
        });
}

function openAddressModal(mode='add') {
    document.getElementById('addressModal').classList.add('open');
    if (mode === 'add') {
        document.getElementById('addrModalTitle').textContent = 'Add New Address';
        document.getElementById('addressForm').reset();
        document.getElementById('addrAction').value = 'add';
        document.getElementById('addrId').value = '0';
    }
}

function closeAddressModal() { document.getElementById('addressModal').classList.remove('open'); }

function editAddress(a) {
    openAddressModal('edit');
    document.getElementById('addrModalTitle').textContent = 'Edit Address';
    document.getElementById('addrAction').value   = 'update';
    document.getElementById('addrId').value       = a.id;
    document.getElementById('addrLabel').value    = a.label;
    document.getElementById('addrName').value     = a.full_name;
    document.getElementById('addrPhone').value    = a.phone;
    document.getElementById('addrStreet').value   = a.street;
    document.getElementById('addrApartment').value= a.apartment||'';
    document.getElementById('addrCity').value     = a.city;
    document.getElementById('addrState').value    = a.state;
    document.getElementById('addrPostcode').value = a.postcode;
    document.getElementById('addrDefault').value  = a.is_default;
}

function setDefault(id) {
    const fd = new FormData(); fd.append('action','set_default'); fd.append('id', id);
    fetch('assets/db_query/profile/address_query.php', {method:'POST',body:fd}).then(()=>loadAddresses());
}

function deleteAddress(id) {
    if (!confirm('Delete this address?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id', id);
    fetch('assets/db_query/profile/address_query.php', {method:'POST',body:fd}).then(()=>loadAddresses());
}

document.getElementById('addressForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('assets/db_query/profile/address_query.php', {method:'POST', body: new FormData(this)})
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') { closeAddressModal(); loadAddresses(); }
            else {
                document.getElementById('addrModalAlert').innerHTML = `<div class="vp-alert vp-alert-error">${d.message}</div>`;
            }
        });
});

// ── Load Notifications ─────────────────────────────────────────
function loadNotifications() {
    fetch('assets/db_query/profile/notifications_query.php')
        .then(r => r.json())
        .then(d => {
            const unread = d.unread_count || 0;
            const badge  = document.getElementById('inboxBadge');
            document.getElementById('unreadNotifs').textContent = unread;
            if (unread > 0) { badge.textContent = unread; badge.classList.remove('d-none'); }
            else { badge.classList.add('d-none'); }

            const list   = d.notifications || [];
            const notifEl = document.getElementById('notifList');
            if (!list.length) {
                notifEl.innerHTML = '<div class="vp-empty"><i class="fa-solid fa-bell-slash"></i><h4>No notifications</h4><p>You\'re all caught up!</p></div>';
                return;
            }
            const iconMap = { order:'fa-bag-shopping', promo:'fa-tag', system:'fa-circle-info', review:'fa-star' };
            notifEl.innerHTML = list.map(n => `
                <div class="vp-notif-item ${n.is_read=='0'?'unread':''}" onclick="markRead(${n.id}, this)">
                    ${n.is_read=='0'?'<div class="vp-notif-dot"></div>':'<div style="width:8px;"></div>'}
                    <div class="vp-notif-icon ${n.type}"><i class="fa-solid ${iconMap[n.type]||'fa-bell'}"></i></div>
                    <div class="vp-notif-body">
                        <div class="vp-notif-title">${n.title}</div>
                        <div class="vp-notif-desc">${n.body}</div>
                        <div class="vp-notif-time">${new Date(n.created_at).toLocaleString('en-IN',{dateStyle:'medium',timeStyle:'short'})}</div>
                    </div>
                </div>`).join('');
        });
}

function markRead(id, el) {
    const fd = new FormData(); fd.append('action','mark_read'); fd.append('id', id);
    fetch('assets/db_query/profile/notifications_query.php', {method:'POST', body:fd});
    el.classList.remove('unread');
    const dot = el.querySelector('.vp-notif-dot');
    if (dot) dot.style.visibility = 'hidden';
}

function markAllRead() {
    const fd = new FormData(); fd.append('action','mark_all_read');
    fetch('assets/db_query/profile/notifications_query.php', {method:'POST', body:fd}).then(()=>loadNotifications());
}

// ── Load Wishlist ──────────────────────────────────────────────
function loadWishlist() {
    // Wishlist data is fetched from existing wishlist JS/API
    const grid = document.getElementById('wishlistGrid');
    fetch('wishlist.php', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.text())
        .then(() => {
            // Fallback: just link to wishlist page
            grid.innerHTML = `<div class="vp-empty w-100"><i class="fa-solid fa-heart"></i><h4>Your Wishlist</h4><p>View and manage your saved products.</p><a href="wishlist.php" class="vp-btn vp-btn-primary">Open Wishlist</a></div>`;
        })
        .catch(() => {
            grid.innerHTML = `<div class="vp-empty w-100"><i class="fa-solid fa-heart"></i><h4>Your Wishlist</h4><p>View and manage your saved products.</p><a href="wishlist.php" class="vp-btn vp-btn-primary">Open Wishlist</a></div>`;
        });
    document.getElementById('wishlistCount').textContent = '–';
}

// ── Password Change ────────────────────────────────────────────
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd   = new FormData(this);
    const np   = fd.get('new_password');
    const cp   = fd.get('confirm_password');
    const alert = document.getElementById('securityAlert');
    if (np !== cp) {
        alert.innerHTML = '<div class="vp-alert vp-alert-error">New passwords do not match.</div>';
        return;
    }
    alert.innerHTML = '<div class="vp-alert vp-alert-success">Password updated. (Backend endpoint needed to complete this.)</div>';
    this.reset();
    setTimeout(() => alert.innerHTML = '', 4000);
});

// ── Initial Load ───────────────────────────────────────────────
loadOrders();
loadNotifications();
</script>
</body>
</html>
