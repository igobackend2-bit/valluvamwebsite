<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$status = isset($_SESSION['status']) ? $_SESSION['status'] : 0;
$user_name = isset($_SESSION['username']) ? $_SESSION['username'] : '';

?>



<!DOCTYPE html>
<html lang="en">

<head>
  <!-- FIX: moved here from above <!DOCTYPE html> (it forced browsers into quirks mode) -->
  <script>
  const userStatus = <?php echo $status; ?>;
  const currentPage = "<?php echo basename($_SERVER['PHP_SELF']); ?>";
</script>
  <?php
    // HOTFIX: every CSS/JS/image reference on this site is written as a
    // relative path (e.g. "css/style.css", "assets/js/..."), which only
    // resolved correctly because every page used to live directly at the
    // site root. Now that productdetail.php can be reached via the new
    // "/{category}/{slug}" pretty URL (e.g. /dryfruits/dry-strawberry),
    // those same relative paths resolve against that extra path segment
    // instead and 404.
    //
    // Fix: only on productdetail.php, pin relative-URL resolution back to
    // the real site root with a <base> tag. Scoped to this one page only
    // (computed here directly from the executing script, not from a
    // page-supplied variable, so it doesn't depend on every page consistently
    // setting one) - a global/unconditional <base> would also change how
    // same-page anchor links ("#faq" etc.) resolve on every OTHER page
    // (e.g. "/rice" + href="#faq" would then navigate to "/#faq" on the
    // homepage instead of scrolling on the rice page), which we don't want.
    if (basename($_SERVER['PHP_SELF'], '.php') === 'productdetail') {
      echo '<base href="/">' . "\n";
    }
  ?>
  <meta charset="utf-8">
  <!-- FIX: the #ftco-loader div sits at the END of every page, so it covered the already-painted page with a white screen (white flash on every click). -->
  <style>#ftco-loader{display:none !important;}</style>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- ===== De-duplicated <head> (was ~140 lines requesting css/style.css
       5x, Google Fonts 5x, 5 different Font Awesome versions, and BOTH
       Bootstrap 4 and 5 from CDN - a real load-time cost repeated on every
       page, and the two Bootstrap versions could silently fight over the
       same utility classes. css/style.css already bundles Bootstrap 4.2.1
       (see its own header comment) to match the local js/bootstrap.min.js
       (v4.2.1) and the site's `data-toggle` markup, so no CDN Bootstrap is
       needed at all. One copy of everything else below. ===== -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;1,400;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=account_circle" />
  <meta property="og:image" content="images/logo.png" />

  <!-- Font Awesome Free 6.7.2 (one version - markup uses fa-solid throughout) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <link rel="stylesheet" href="css/open-iconic-bootstrap.min.css">
  <link rel="stylesheet" href="css/animate.css">
  <link rel="stylesheet" href="css/owl.carousel.min.css">
  <link rel="stylesheet" href="css/owl.theme.default.min.css">
  <link rel="stylesheet" href="css/magnific-popup.css">
  <link rel="stylesheet" href="css/aos.css">
  <link rel="stylesheet" href="css/ionicons.min.css">
  <link rel="stylesheet" href="css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="css/jquery.timepicker.css">
  <link rel="stylesheet" href="css/flaticon.css">
  <link rel="stylesheet" href="css/icomoon.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/productdet.css">
  <link rel="stylesheet" href="css/products.css?v=3">
  <link rel="stylesheet" href="css/login.css?v=20260911b">
  <link rel="stylesheet" href="css/premium-valluvam.css?v=20260926">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.css">

  <!-- ===== Header alignment fix (scoped to #ftco-navbar, desktop only) ===== -->
  <style>
    @media (min-width: 992px) {

      /* one flex row, everything vertically centred */
      #ftco-navbar>.container {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
      }

      /* logo: smaller, tightens the whole bar */
      #ftco-navbar .navbar-brand {
        padding: 0;
        margin: 0 20px 0 0;
        flex: 0 0 auto;
      }

      #ftco-navbar .navbar-brand img {
        max-height: 72px !important;
        width: auto;
        display: block;
      }

      /* search: vertically centred, allowed to shrink */
      #ftco-navbar #searchForm {
        flex: 0 1 280px;
        min-width: 0;
        margin: 0 20px 0 0;
      }

      #ftco-navbar .search {
        --padding: 9px;
        width: 100%;
        margin-top: 0;
      }

      #ftco-navbar .search-input {
        width: 100%;
        font-size: 15px;
      }

      #ftco-navbar #product-results {
        flex: 0 0 auto;
      }

      /* nav: cancel main.css offsets, keep on one line */
      #ftco-navbar .navbar-collapse {
        flex: 1 1 auto;
        min-width: 0;
      }

      #ftco-navbar #ftco-nav {
        margin-left: 0;
        margin-top: 0;
        margin-bottom: 0;
      }

      #ftco-navbar .navbar-nav {
        flex-wrap: nowrap;
        align-items: center;
      }

      #ftco-navbar .navbar-nav>.nav-item>.nav-link {
        padding: 14px 9px;
        letter-spacing: .5px;
        white-space: nowrap;
      }

      /* cart icon and count side by side */
      #ftco-navbar .navbar-nav>.nav-item.cta>.nav-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
      }

      #ftco-navbar .navbar-nav .login-nav {
        display: inline-flex;
        align-items: center;
        width: auto;
      }
    }

    /* narrow desktops: tighten so the menu never overflows */
    @media (min-width: 992px) and (max-width: 1199.98px) {
      #ftco-navbar .navbar-brand {
        margin-right: 12px;
      }

      #ftco-navbar #searchForm {
        flex: 0 1 190px;
        margin-right: 12px;
      }

      #ftco-navbar .navbar-nav>.nav-item>.nav-link {
        padding-left: 7px;
        padding-right: 7px;
        letter-spacing: .3px;
      }
    }
  </style>

  <!-- ===== Site-wide alignment & readability (additive only) ===== -->
  <style>
    /* 1. Orphan rows: on shop.php and cart.php a Bootstrap .row sits directly
       inside <section> with no .container, so its -15px gutters escape and
       scroll the whole page sideways. Give those rows container behaviour so
       they line up with every other section instead of running full-bleed. */
    section.ftco-section>.row {
      margin-left: auto;
      margin-right: auto;
      max-width: 1110px;
    }

    @media (max-width: 1199.98px) {
      section.ftco-section>.row {
        max-width: 930px;
      }
    }

    @media (max-width: 991.98px) {
      section.ftco-section>.row {
        max-width: 690px;
      }
    }

    @media (max-width: 767.98px) {
      section.ftco-section>.row {
        max-width: 510px;
      }
    }

    @media (max-width: 575.98px) {
      section.ftco-section>.row {
        max-width: 100%;
        margin-left: 0;
        margin-right: 0;
      }
    }

    /* 2. Body copy contrast: #808080 measures 3.5:1 on white, below the
       WCAG AA minimum of 4.5:1. #666 measures 5.7:1. */
    body.goto-here {
      color: #666;
    }

    /* 3. Reading measure: prose ran 148 characters per line (readable is
       45-75). The footer also carries .ftco-section, so it is excluded. */
    .ftco-section:not(.ftco-footer) .heading.text-center>p,
    .ftco-section:not(.ftco-footer) .col-lg-12>p,
    .ftco-section:not(.ftco-footer) .col-md-12>p,
    .ftco-section:not(.ftco-footer) .col-12-lg p {
      max-width: 72ch;
    }

    .ftco-section:not(.ftco-footer) .heading.text-center>p {
      margin-left: auto;
      margin-right: auto;
    }

    /* 4. Heading hierarchy: the page title rendered at 30px while section
       headings below it were 40px. Restores the page title to the top rank. */
    @media (min-width: 768px) {

      .hero-wrap.hero-bread h1.bread,
      .hero-wrap.hero-bread .wholesale-hero-title {
        font-size: 46px;
        line-height: 1.15;
      }
    }

    /* 4b. Page banner (bg-main.jpg, used by Shop + every category page).
       The photo is 2:1 with the products in its middle band, but the banner
       is ~4.5:1, so `cover` sliced the bottle tops off and dropped the
       white page title straight onto the busy product shot, where it
       disappeared. Now: title on the photo's own flat green on the left,
       the whole photo (uncropped) on the right. #467d06 is sampled from
       the photo's edge so the two halves read as one surface. */
    .hero-wrap.hero-bread[style*="bg-main.jpg"] {
      background-color: #467d06;
      background-repeat: no-repeat;
      background-size: auto 100%;
      background-position: right 3vw center;
      padding: 0;
    }

    .hero-wrap.hero-bread[style*="bg-main.jpg"] .slider-text {
      min-height: 340px;
      justify-content: flex-start !important;
    }

    .hero-wrap.hero-bread[style*="bg-main.jpg"] .slider-text > [class*="col-"] {
      flex: 0 0 42%;
      max-width: 42%;
      text-align: left !important;
      padding: 48px 0;
    }

    .hero-wrap.hero-bread[style*="bg-main.jpg"] .breadcrumbs {
      text-align: inherit;
      margin: 0 0 6px;
      padding: 0;
    }

    .hero-wrap.hero-bread[style*="bg-main.jpg"] .breadcrumbs a,
    .hero-wrap.hero-bread[style*="bg-main.jpg"] .breadcrumbs span {
      color: rgba(255, 255, 255, .85);
    }

    @media (max-width: 991.98px) {
      /* Stack: title on flat green on top, full photo below it. */
      .hero-wrap.hero-bread[style*="bg-main.jpg"] {
        background-size: 100% auto;
        background-position: center bottom;
        padding-bottom: 50vw; /* photo is 2:1 */
      }

      .hero-wrap.hero-bread[style*="bg-main.jpg"] .slider-text {
        min-height: 0;
        justify-content: center !important;
      }

      .hero-wrap.hero-bread[style*="bg-main.jpg"] .slider-text > [class*="col-"] {
        flex: 0 0 100%;
        max-width: 100%;
        text-align: center !important;
        padding: 36px 15px 8px;
      }
    }

    /* 4c. Category pills (shared category_pills.php, 14 items): on phones
       they wrapped into 4-5 rows and pushed products below the fold. One
       swipeable row instead; the fade on the right hints there is more. */
    @media (max-width: 767.98px) {
      body ul.product-category {
        flex-wrap: nowrap;
        justify-content: flex-start;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        padding: 6px 24px 6px 4px;
        margin-left: -15px;
        margin-right: -15px;
        -webkit-mask-image: linear-gradient(90deg, #000 85%, transparent);
        mask-image: linear-gradient(90deg, #000 85%, transparent);
      }

      body ul.product-category::-webkit-scrollbar {
        display: none;
      }

      body ul.product-category li {
        flex: 0 0 auto;
        margin: 0 4px;
      }
    }

    /* 5. Symmetrical vertical rhythm (was 115px top / 120px bottom). */
    .section-services {
      padding-top: 115px;
      padding-bottom: 115px;
    }

    /* ---------------------------------------------------------------
       6. Spacing system. The site used 6 different section paddings,
       5 button treatments and 5 text-input treatments with no rule
       behind them. These put every repeated element on one scale.
       --------------------------------------------------------------- */

    /* 6a. Section rhythm: main content sections sat at 30px while the
       newer B2B pages used 70px, so most of the site read as cramped.
       .ftco-no-pt / .ftco-no-pb keep their meaning. */
    .ftco-section:not(.ftco-footer) {
      padding-top: 52px;
      padding-bottom: 52px;
    }

    .ftco-section.ftco-no-pt {
      padding-top: 0;
    }

    .ftco-section.ftco-no-pb {
      padding-bottom: 0;
    }

    /* 6a-ii. The remaining bands onto the same two-tier scale
       (was 70px on B2B, 60px on the CTA band, 115px on services).
       b2b-wholesale.php declares .b2b-section in its own inline <style>,
       which lands after this file's <head>, so these are qualified with
       `body` to win on specificity rather than with !important. */
    body .b2b-section,
    body .bg-cta {
      padding-top: 52px;
      padding-bottom: 52px;
    }

    /* The newsletter Subscribe button was square with 12px type while
       every other button on the site is a 14px pill. */
    body .subscribe-form .form-group .submit {
      border-radius: 30px;
      font-size: 14px;
      border-left: 0;
    }

    .section-services {
      padding-top: 72px;
      padding-bottom: 72px;
    }

    /* 6b. Buttons: heights ran 33/52/53/54/58px, radius 0 and 30px,
       type 12-16px. Accordion toggles and carousel arrows keep theirs.
       contact.php's "Send Now" is a bare <button> with no styling at
       all, so it rendered as a 33px default browser control. */
    .btn:not(.btn-link):not(.owl-prev):not(.owl-next),
    input[type="submit"].submit,
    input[type="submit"],
    button[type="submit"]:not(.btn):not(.navbar-toggler),
    .btn-submit-enquiry {
      min-height: 48px;
      padding: 12px 28px;
      font-size: 14px;
      font-weight: 500;
      letter-spacing: .3px;
      line-height: 1.2;
      border-radius: 30px;
    }

    .btn:not(.btn-link):not(.owl-prev):not(.owl-next),
    button[type="submit"]:not(.btn):not(.navbar-toggler) {
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    /* The unstyled contact-form button needs a surface of its own. */
    button[type="submit"]:not(.btn):not(.navbar-toggler) {
      background: #82ae46;
      color: #fff;
      border: 0;
      cursor: pointer;
    }

    button[type="submit"]:not(.btn):not(.navbar-toggler):hover {
      background: #6e9a34;
      color: #fff;
    }

    /* 6c. Text inputs: five different heights and four radii.
       The navbar search pill and choice controls are left as they are. */
    .form-control:not(textarea):not(.search-input),
    input[type="text"]:not(.search-input),
    input[type="email"],
    input[type="tel"],
    input[type="number"],
    input[type="password"],
    select.form-control {
      height: 48px;
      padding: 0 16px;
      font-size: 15px;
      border-radius: 6px;
    }

    textarea.form-control {
      padding: 12px 16px;
      font-size: 15px;
      border-radius: 6px;
    }

    /* 6d. Heading spacing: h2 carried 8 different margin pairs, h3 six. */
    .ftco-section:not(.ftco-footer) h2 {
      margin-bottom: 16px;
    }

    .ftco-section:not(.ftco-footer) h3 {
      margin-bottom: 12px;
    }

    /* 7. Header action icons (wishlist / cart / account) were rendering at
       a tiny, default inherited size next to the nav text -- bump them up
       and give them room so they read as clear icon buttons. */
    #ftco-navbar .navbar-nav .nav-item.cta .nav-link .ion-ios-heart,
    #ftco-navbar .navbar-nav .nav-item.cta .nav-link .icon-shopping_cart {
      font-size: 24px;
      line-height: 1;
    }

    #ftco-navbar .navbar-nav .nav-item.cta .nav-link #cartCount {
      font-size: 15px;
      font-weight: 600;
      margin-left: 2px;
    }

    #ftco-navbar .navbar-nav .login-nav .ri-account-circle-fill {
      font-size: 26px;
      line-height: 1;
    }

    #ftco-navbar .navbar-nav .nav-item.cta > .nav-link,
    #ftco-navbar .navbar-nav .login-nav {
      padding-top: 6px;
      padding-bottom: 6px;
    }
  </style>

  <!-- ===== Header bar: logo presence, gap balance, topbar alignment ===== -->
  <style>
    @media (min-width: 992px) {

      /* 7a. The logo mark fills only ~50% of images/logo.jpeg - the file
         carries 21-27% white padding on every side, so at a 72px box the
         visible mark rendered at roughly 35px. Scaling inside a clipped
         box crops that dead padding without changing any layout height.
         The proper fix is re-exporting the logo cropped and transparent;
         this makes it read correctly until then. */
      #ftco-navbar .navbar-brand {
        padding: 4px 0;
        margin-right: 28px;
        flex: 0 0 auto;
      }

      #ftco-navbar .navbar-brand img.v-main-logo {
        height: 64px;
        width: auto;
        max-width: 140px;
        object-fit: contain;
        display: block;
        transform: none;
      }

      #ftco-navbar #searchForm {
        flex: 0 1 330px;
        margin-right: 0;
      }

      /* 7c. The green bar's e-mail sat mid-row, aligned to nothing. Right
         aligning it squares it with the menu's right edge below.
         .pr-4 is a Bootstrap utility, so this outranks it on specificity
         rather than with !important. */
      .bg-primary .row .topper:last-child {
        justify-content: flex-end;
      }

      .bg-primary .row .topper.pr-4:last-child {
        padding-right: 0;
      }

      /* .topper .text is width:calc(100% - 30px), which held the e-mail
         short of the edge even once it was right-aligned. */
      .bg-primary .row .topper:last-child .text {
        width: auto;
        flex: 0 0 auto;
      }
    }
  </style>

  <!-- ===== Homepage category strip ===== -->
  <style>
    /* 8a. The cards are fixed 250x140 boxes, but products.css styles the
       thumbnails with a bare `img { width:100% }` and no height, so each
       one kept its own aspect ratio inside that box: images ran 130px to
       321px tall. Short ones left the grey background showing, tall ones
       were cropped to whatever their top 44% happened to be. Filling the
       box makes every card frame its subject the same way. */
    .slid-er .slide .slide-content img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
    }

    /* 8b. .slide is 258px while .slide-content measures 266px with its
       8px margins, so each card ate 8px of the next one's gutter and the
       spacing came out at 8px instead of the intended 16px. Widened again
       to 278px so the cards sit 28px apart and read as separate tiles. */
    .slid-er .slide {
      width: 278px;
      flex: 0 0 278px;
    }

    .slid-er .slide .slide-content {
      margin: 8px 14px;
    }

    /* 8d. The strip butted straight against the hero banner with no gap at
       all, and left only 12px before the "OUR PRODUCTS" heading below. */
    .ftco-section-category {
      padding-top: 44px;
      padding-bottom: 44px;
    }

    /* 8c. The grey placeholder only ever showed through as a gap; with the
       images filling their boxes a neutral tone is a calmer fallback for a
       thumbnail that fails to load. */
    .slid-er .slide .slide-content {
      background-color: #eceae3;
    }

    /* 8e. Category name label: category_slider() now renders the category's own
       name (already returned by the query, previously unused) as a small caption
       over the image, above the existing "Explore Category" button, so every card
       is distinguishable without waiting for the photo to load or guessing from it. */
    .slid-er .slide .slide-content {
      position: relative;
    }

    .slide-cat-name {
      display: block;
      color: #fff;
      background: rgba(20, 40, 20, 0.55);
      font-size: 13px;
      font-weight: 600;
      letter-spacing: .3px;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 20px;
      margin-bottom: 8px;
    }
  </style>

  <!-- ===== Announcement strip (verified claims only - see body markup) ===== -->
  <style>
    .v-announce-bar {
      background-color: #1f3d1a;
      overflow: hidden;
    }

    .v-announce-track {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 6px 28px;
      padding: 7px 12px;
      max-width: 1400px;
      margin: 0 auto;
    }

    .v-announce-track span {
      color: #e7f2e2;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: .4px;
      white-space: nowrap;
    }

    /* Phones: the four claims wrapped to four lines (~100px) above the
       logo. Show one line at a time, cross-fading through all four. */
    @media (max-width: 767.98px) {
      .v-announce-track {
        position: relative;
        display: block;
        height: 28px;
        padding: 0;
      }

      .v-announce-track span {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        opacity: 0;
        animation: v-announce-cycle 14s infinite;
      }

      .v-announce-track span:nth-child(2) { animation-delay: 3.5s; }
      .v-announce-track span:nth-child(3) { animation-delay: 7s; }
      .v-announce-track span:nth-child(4) { animation-delay: 10.5s; }

      @keyframes v-announce-cycle {
        0% { opacity: 0; transform: translateY(4px); }
        4%, 22% { opacity: 1; transform: none; }
        26%, 100% { opacity: 0; transform: translateY(-4px); }
      }
    }

    @media (max-width: 767.98px) and (prefers-reduced-motion: reduce) {
      .v-announce-track span { animation: none; }
      .v-announce-track span:first-child { opacity: 1; }
    }

    /* Contact strip + navbar on phones: was 68px + 202px of chrome. */
    .topper a.text,
    .topper a.text:hover {
      color: inherit;
      text-decoration: none;
    }

    @media (max-width: 767.98px) {
      .py-1.bg-primary .row.d-flex {
        flex-wrap: nowrap;
        justify-content: space-between;
      }

      .py-1.bg-primary .topper {
        flex: 0 1 auto;
        padding-right: 0 !important;
        min-width: 0;
      }

      .py-1.bg-primary .topper .text {
        font-size: 11px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #ftco-navbar .navbar-brand img {
        max-height: 52px !important;
        width: auto;
      }

      #ftco-navbar {
        padding-top: 8px;
        padding-bottom: 8px;
      }

      #ftco-navbar #searchForm {
        width: 100%;
        margin: 8px 0 0;
      }

      #ftco-navbar .search {
        margin-top: 0;
      }

      #ftco-navbar .search-input {
        font-size: 15px;
        height: 42px;
      }
    }
  </style>

  <!-- Mega-menu & Sticky Header Alignment -->
  <style>
    #ftco-navbar .container {
      position: relative !important;
    }

    #ftco-navbar .nav-item.dropdown {
      position: static !important;
    }

    #ftco-navbar .dropdown-menu.v-mega-menu {
      position: absolute !important;
      top: 100% !important;
      left: 15px !important;
      right: 15px !important;
      width: calc(100% - 30px) !important;
      max-width: 1140px !important;
      margin: 8px auto 0 !important;
      transform: none !important;
      padding: 22px 24px !important;
      border: 1px solid rgba(19, 56, 38, 0.12) !important;
      border-radius: 16px !important;
      background: #ffffff !important;
      box-shadow: 0 16px 48px rgba(19, 56, 38, 0.14) !important;
      display: none;
      z-index: 1050 !important;
    }

    #ftco-navbar .dropdown-menu.v-mega-menu.show {
      display: block !important;
    }

    .v-mega-grid {
      display: grid !important;
      grid-template-columns: repeat(7, 1fr) !important;
      gap: 10px 12px !important;
      width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
    }

    @media (max-width: 1199.98px) {
      .v-mega-grid {
        grid-template-columns: repeat(5, 1fr) !important;
      }
    }

    @media (max-width: 991.98px) {
      #ftco-navbar .nav-item.dropdown {
        position: relative !important;
      }
      #ftco-navbar .dropdown-menu.v-mega-menu {
        position: static !important;
        left: auto !important;
        right: auto !important;
        width: 100% !important;
        margin: 8px 0 !important;
        box-shadow: none !important;
        padding: 14px !important;
        background: #f7f9f7 !important;
      }
      .v-mega-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
      }
    }

    #ftco-navbar .v-mega-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 8px;
      padding: 12px 6px;
      border-radius: 12px;
      border: 1px solid rgba(19, 56, 38, 0.06);
      background: #fafbf9;
      color: #1a2e22 !important;
      text-decoration: none !important;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    #ftco-navbar .v-mega-item:hover {
      background: #ffffff;
      border-color: #8fa88b;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(19, 56, 38, 0.08);
      color: #133826 !important;
    }

    #ftco-navbar .v-mega-item .v-mega-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #ffffff;
      border: 1px solid rgba(19, 56, 38, 0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: #133826;
      box-shadow: 0 2px 6px rgba(19, 56, 38, 0.04);
      transition: all 0.2s ease;
    }

    #ftco-navbar .v-mega-item:hover .v-mega-icon {
      background: #133826;
      color: #ffffff;
      transform: scale(1.05);
    }

    #ftco-navbar .v-mega-item span {
      font-size: 12px;
      font-weight: 600;
      line-height: 1.25;
      color: #2a3a30;
    }

    #ftco-navbar .v-mega-item.v-mega-all {
      background: #133826;
      border-color: #133826;
      color: #ffffff !important;
    }

    #ftco-navbar .v-mega-item.v-mega-all span {
      color: #ffffff !important;
      font-weight: 700;
    }

    #ftco-navbar .v-mega-item.v-mega-all .v-mega-icon {
      background: rgba(255, 255, 255, 0.2);
      border-color: transparent;
      color: #ffffff;
    }

    #ftco-navbar .v-mega-item.v-mega-all:hover {
      background: #0d2217;
      border-color: #0d2217;
    }

    /* Logo Clarity & Natural Proportions */
    #ftco-navbar .navbar-brand {
      display: flex !important;
      align-items: center !important;
      padding: 0 !important;
      margin-right: 16px !important;
    }

    #ftco-navbar .navbar-brand img.v-main-logo {
      height: 66px !important;
      max-height: 66px !important;
      width: auto !important;
      max-width: none !important;
      object-fit: contain !important;
      display: block !important;
    }

    @media (max-width: 991.98px) {
      #ftco-navbar .navbar-brand img.v-main-logo {
        height: 50px !important;
        max-height: 50px !important;
      }
    }

    /* Header Nav Links Professional Kerning & Spacing */
    #ftco-navbar .navbar-nav > .nav-item > .nav-link {
      padding: 8px 11px !important;
      font-size: 13.5px !important;
      letter-spacing: 0.1px !important;
      white-space: nowrap !important;
    }

    /* Top Trust Bar Clean Alignment */
    .v-top-bar .container {
      display: flex !important;
      justify-content: space-between !important;
      align-items: center !important;
      flex-wrap: nowrap !important;
    }

    .v-trust-pill-group {
      display: flex !important;
      align-items: center !important;
      gap: 12px !important;
      flex-wrap: nowrap !important;
      white-space: nowrap !important;
    }

    .v-trust-item, .v-top-links a {
      white-space: nowrap !important;
      font-size: 11.5px !important;
    }

    .v-top-links {
      display: flex !important;
      align-items: center !important;
      gap: 14px !important;
      flex-wrap: nowrap !important;
      white-space: nowrap !important;
    }

    @media (max-width: 1250px) {
      .v-top-email, .v-top-email-sep {
        display: none !important;
      }
    }
  </style>

  <!-- Site-wide motion/smoothness layer - loaded last so it refines
       (never fights) every redesign stylesheet above. -->
  <link rel="stylesheet" href="css/motion-system.css?v=1">
</head>

<body>
  <!-- Announcement strip: only claims already made elsewhere on the site (Why Choose
       Valluvam / Our Services on index.php) - quality checks, pan-India delivery with
       tracking, Razorpay secure payments, and the existing wholesale/bulk offering. No
       shipping-cost or delivery-time promise is made here since none is confirmed. -->
  <!-- TOP TRUST BAR -->
  <div class="v-top-bar">
    <div class="container">
      <div class="v-trust-pill-group">
        <span class="v-trust-item"><i class="fa-solid fa-leaf"></i> Naturally Sourced</span>
        <span class="v-top-sep">|</span>
        <span class="v-trust-item"><i class="fa-solid fa-circle-check"></i> Quality Checked</span>
        <span class="v-top-sep">|</span>
        <span class="v-trust-item"><i class="fa-solid fa-box-open"></i> Carefully Packed</span>
        <span class="v-top-sep">|</span>
        <span class="v-trust-item"><i class="fa-solid fa-truck-fast"></i> Delivered With Care</span>
      </div>
      <div class="v-top-links">
        <a href="tel:+918925969888"><i class="fa-solid fa-phone"></i> +91 89259 69888</a>
        <span class="v-top-sep">|</span>
        <a href="b2b-wholesale.php"><i class="fa-solid fa-briefcase"></i> Wholesale</a>
        <span class="v-top-sep v-top-email-sep">|</span>
        <a href="mailto:info.thefarmersfactory@gmail.com" class="v-top-email"><i class="fa-solid fa-envelope"></i> info.thefarmersfactory@gmail.com</a>
      </div>
    </div>
  </div>

  <!-- STICKY HEADER -->
  <nav class="navbar navbar-expand-lg ftco_navbar ftco-navbar-light" id="ftco-navbar">
    <div class="container">
      <!-- LEFT: Existing Valluvam Logo (Untouched Asset) -->
      <a class="navbar-brand d-flex align-items-center" href="index.php" title="Valluvam — As Pure As Nature">
        <img src="images/logo.png" alt="Valluvam - As Pure As Nature" class="v-main-logo img-fluid" width="140" height="70">
      </a>

      <!-- Mobile Toggler -->
      <button class="navbar-toggler d-lg-none" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
      </button>

      <?php
      // get the current page name
      $currentPage = basename($_SERVER['PHP_SELF']);
      $allowedPages = ['index.php', 'shop.php', 'dryfruits.php', 'nuts.php', 'combo.php', 'spices.php', 'oils.php', 'millets.php', 'rice.php', 'palm-jaggery.php', 'seeds.php', 'dal.php', 'honey.php', 'ghee.php', 'pulses.php', 'productdetail.php'];
      ?>

      <!-- CENTER: Main Navigation Links -->
      <div class="collapse navbar-collapse justify-content-center" id="ftco-nav">
        <ul class="navbar-nav">
          <li class="nav-item <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php" class="nav-link">Home</a>
          </li>
          <li class="nav-item dropdown <?php echo (in_array($currentPage, ['shop.php', 'dryfruits.php', 'nuts.php', 'combo.php', 'spices.php', 'oils.php', 'millets.php', 'rice.php', 'palm-jaggery.php', 'seeds.php', 'dal.php', 'honey.php', 'ghee.php', 'pulses.php'])) ? 'active' : ''; ?>">
            <a class="nav-link dropdown-toggle" href="shop.php" id="dropdownShop" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              Shop <i class="fa-solid fa-chevron-down ml-1" style="font-size:11px;"></i>
            </a>
            <!-- Premium Mega Menu with All 13 Categories + All Products -->
            <div class="dropdown-menu v-mega-menu" aria-labelledby="dropdownShop">
              <div class="v-mega-grid">
                <a class="v-mega-item" href="dryfruits.php">
                  <div class="v-mega-icon"><ion-icon name="nutrition-outline"></ion-icon></div>
                  <span>Dry Fruits</span>
                </a>
                <a class="v-mega-item" href="nuts.php">
                  <div class="v-mega-icon"><ion-icon name="leaf-outline"></ion-icon></div>
                  <span>Nuts</span>
                </a>
                <a class="v-mega-item" href="oils.php">
                  <div class="v-mega-icon"><ion-icon name="water-outline"></ion-icon></div>
                  <span>Cold-Pressed Oils</span>
                </a>
                <a class="v-mega-item" href="spices.php">
                  <div class="v-mega-icon"><ion-icon name="flame-outline"></ion-icon></div>
                  <span>Spices</span>
                </a>
                <a class="v-mega-item" href="millets.php">
                  <div class="v-mega-icon"><ion-icon name="flower-outline"></ion-icon></div>
                  <span>Millets</span>
                </a>
                <a class="v-mega-item" href="rice.php">
                  <div class="v-mega-icon"><ion-icon name="restaurant-outline"></ion-icon></div>
                  <span>Heritage Rice</span>
                </a>
                <a class="v-mega-item" href="combo.php">
                  <div class="v-mega-icon"><ion-icon name="gift-outline"></ion-icon></div>
                  <span>Curated Combos</span>
                </a>
                <a class="v-mega-item" href="palm-jaggery.php">
                  <div class="v-mega-icon"><ion-icon name="cafe-outline"></ion-icon></div>
                  <span>Palm Jaggery</span>
                </a>
                <a class="v-mega-item" href="seeds.php">
                  <div class="v-mega-icon"><ion-icon name="aperture-outline"></ion-icon></div>
                  <span>Seeds</span>
                </a>
                <a class="v-mega-item" href="dal.php">
                  <div class="v-mega-icon"><ion-icon name="basket-outline"></ion-icon></div>
                  <span>Native Dal</span>
                </a>
                <a class="v-mega-item" href="honey.php">
                  <div class="v-mega-icon"><ion-icon name="sunny-outline"></ion-icon></div>
                  <span>Raw Honey</span>
                </a>
                <a class="v-mega-item" href="ghee.php">
                  <div class="v-mega-icon"><ion-icon name="flask-outline"></ion-icon></div>
                  <span>Pure Ghee</span>
                </a>
                <a class="v-mega-item" href="pulses.php">
                  <div class="v-mega-icon"><ion-icon name="apps-outline"></ion-icon></div>
                  <span>Pulses</span>
                </a>
                <a class="v-mega-item v-mega-all" href="shop.php">
                  <div class="v-mega-icon"><ion-icon name="storefront-outline"></ion-icon></div>
                  <span>All Products &rarr;</span>
                </a>
              </div>
            </div>
          </li>
          <li class="nav-item">
            <a href="index.php#shop-by-category" class="nav-link">Categories</a>
          </li>
          <li class="nav-item <?php echo ($currentPage == 'about.php') ? 'active' : ''; ?>">
            <a href="about.php" class="nav-link">About</a>
          </li>
          <li class="nav-item <?php echo ($currentPage == 'b2b-wholesale.php') ? 'active' : ''; ?>">
            <a href="b2b-wholesale.php" class="nav-link">Wholesale</a>
          </li>
          <li class="nav-item <?php echo ($currentPage == 'blog.php') ? 'active' : ''; ?>">
            <a href="blog.php" class="nav-link">Blog</a>
          </li>
        </ul>
      </div>

      <!-- RIGHT: Interactive Actions (Search, Account, Wishlist, Cart) -->
      <div class="v-header-actions">
        <?php if (in_array($currentPage, $allowedPages)) : ?>
          <form id="searchForm" class="v-header-search-wrap d-none d-md-block">
            <div class="v-header-search-box search">
              <ion-icon name="search-outline"></ion-icon>
              <input class="search-input" type="search" id="search" placeholder="Search products..." autocomplete="off">
            </div>
          </form>
        <?php endif; ?>

        <!-- Wishlist -->
        <a href="wishlist.php" class="v-action-btn" title="Wishlist" aria-label="Wishlist">
          <i class="fa-regular fa-heart"></i>
        </a>

        <!-- Cart -->
        <a href="cart.php" class="v-action-btn cta cta-colored" title="Cart" aria-label="Cart">
          <i class="fa-solid fa-bag-shopping"></i>
          <span id="cartCount" class="v-badge-count">[0]</span>
        </a>

        <!-- Account / User Dropdown -->
        <div class="dropdown d-inline-block">
          <a class="v-action-btn login-nav <?php echo ($status == 1) ? 'dropdown-toggle' : ''; ?>"
             href="#"
             id="userIcon"
             title="Account"
             aria-label="Account"
             <?php if ($status == 1): ?>
             data-toggle="dropdown"
             aria-haspopup="true"
             aria-expanded="false"
             <?php endif; ?>>
            <i class="fa-regular fa-user"></i>
            <?php if ($status == 1): ?>
              <span class="v-user-name ml-1 font-weight-bold" style="font-size:12px; max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block;">
                <?php echo htmlspecialchars($user_name); ?>
              </span>
            <?php endif; ?>
          </a>

          <?php if ($status == 1): ?>
            <div class="dropdown-menu dropdown-menu-right shadow-sm" aria-labelledby="userIcon" style="border-radius:12px; border:1px solid #e8e3d8; margin-top:8px;">
              <a class="dropdown-item" href="order_tracking.php"><i class="fa-solid fa-shopping-bag mr-2 text-muted"></i>My Orders</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket mr-2"></i>Logout</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Live Search Results Dropdown -->
      <div id="product-results"></div>
    </div>
  </nav>
  <!-- Login/Signup Modal -->
  <div class="form-modal" id="popupForm" style="display:none;">
    <div class="form-close" onclick="closeForm()">×</div>
    <div class="auth-topbar">
      <span id="auth-topbar-question">New to Valluvam?</span>
      <a href="javascript:void(0)" id="auth-topbar-link" onclick="toggleSignup()">Create an account <ion-icon name="arrow-forward-outline"></ion-icon></a>
    </div>

    <div class="auth-modal">
      <div class="auth-visual">
        <img src="images/login-panel.jpg" class="auth-visual-img"
          alt="Pure Nature. Better Living. Bowls of almonds, cashews, raisins, cardamom and rice on a wooden table, highlighting Premium Quality, Trusted Sourcing, Reliable Delivery and Dedicated Support.">
      </div>

      <div class="auth-panel">
        <!-- FIX: removed the decorative leaf-corner overlays here - at
             common screen widths .auth-panel's overflow:hidden clipped
             them mid-shape against the card edge, which read as a stray/
             broken graphic rather than decoration. The logo right below
             (images/auth-logo.png, the site's actual Valluvam wordmark)
             is unaffected and still renders normally. -->
        <div class="auth-brand">
          <img src="images/auth-logo.png" class="auth-brand-logo" alt="Valluvam - Goodness from Nature">
        </div>

        <div class="form-toggle">
          <button id="login-toggle" onclick="toggleLogin()">Log In</button>
          <button id="signup-toggle" onclick="toggleSignup()">Sign Up</button>
        </div>

        <div id="login-form">
          <h1 class="auth-heading">Welcome Back</h1>
          <p class="auth-subheading">Log in to your account to continue your natural shopping journey.</p>
          <form id="loginForm">
            <div class="auth-input-wrap auth-input-wrap--pill">
              <ion-icon name="mail-outline" class="auth-input-icon"></ion-icon>
              <input type="text" id="loginIdentifier" name="identifier" placeholder="Email address or mobile number" required />
            </div>

            <div class="auth-input-wrap auth-input-wrap--pill">
              <ion-icon name="lock-closed-outline" class="auth-input-icon"></ion-icon>
              <input type="password" id="loginPassword" name="password" placeholder="Password" required />
              <button type="button" class="auth-pw-toggle" aria-label="Show password" data-target="loginPassword">
                <ion-icon name="eye-outline"></ion-icon>
              </button>
            </div>

            <label class="auth-remember">
              <input type="checkbox" id="rememberMe" name="remember" checked />
              <span>Remember me</span>
            </label>

            <button type="submit" class="btn login">Login <ion-icon name="arrow-forward-outline"></ion-icon></button>
            <!-- <p><a href="#" onclick="retryLogin()">Forgotten account?</a></p> -->
          </form>
          <div class="auth-divider"><span>Or login with</span></div>
          <p class="auth-switch">Don't have an account? <a href="javascript:void(0)" onclick="toggleSignup()">Create an account <ion-icon name="arrow-forward-outline"></ion-icon></a></p>
        </div>

        <div id="signup-form" style="display:none;">
          <h1 class="auth-heading">Create Your Account</h1>
          <p class="auth-subheading">Join Valluvam for faster checkout and easy order tracking.</p>
          <form id="signupForm">
            <label class="auth-label" for="signupEmail">Email Address</label>
            <div class="auth-input-wrap">
              <ion-icon name="mail-outline" class="auth-input-icon"></ion-icon>
              <input type="email" id="signupEmail" name="email" placeholder="Enter your email" required />
            </div>

            <label class="auth-label" for="signupPhone">Contact Number</label>
            <div class="auth-input-wrap">
              <ion-icon name="call-outline" class="auth-input-icon"></ion-icon>
              <input type="text" id="signupPhone" name="phone" placeholder="Enter your contact number" required />
            </div>

            <label class="auth-label" for="signupUsername">Username</label>
            <div class="auth-input-wrap">
              <ion-icon name="person-outline" class="auth-input-icon"></ion-icon>
              <input type="text" id="signupUsername" name="username" placeholder="Choose username" required />
            </div>

            <label class="auth-label" for="signupPassword">Password</label>
            <div class="auth-input-wrap">
              <ion-icon name="lock-closed-outline" class="auth-input-icon"></ion-icon>
              <input type="password" id="signupPassword" name="password" placeholder="Create password" required />
              <button type="button" class="auth-pw-toggle" aria-label="Show password" data-target="signupPassword">
                <ion-icon name="eye-outline"></ion-icon>
              </button>
            </div>

            <button type="submit" class="btn signup">Create Account <ion-icon name="arrow-forward-outline"></ion-icon></button>
            <p>Clicking <strong>create account</strong> means you agree to our <a href="#">terms of services</a>.</p>
          </form>
          <div class="auth-divider"><span>Or sign up with</span></div>
          <p class="auth-switch">Already have an account? <a href="javascript:void(0)" onclick="toggleLogin()">Log in <ion-icon name="arrow-forward-outline"></ion-icon></a></p>
        </div>

        <a href="javascript:void(0)" class="auth-back-link" onclick="closeForm()">&larr; Back to Store</a>
      </div>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="assets/js/utils/swal-helper.js"></script>
  <script src="assets/js/login/login.js?v=20260923"></script>
  <script src="assets/js/header/header.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {

      // REMOVE all FTCO auto navbar events
      $('.ftco-navbar-light').off();
      $('#ftco-nav').off();
      $('.navbar-toggler').off();
      $('.navbar-collapse').off();

      // Make sure navbar starts closed
      $('#ftco-nav').removeClass('show');

      // Bootstrap default toggle ONLY
      $('.navbar-toggler').on('click', function(e) {
        e.preventDefault();
        $('#ftco-nav').collapse('toggle');
      });

    });
  </script>

  <!-- Removed duplicate jQuery 3.2.1.slim - using jQuery 3.6.0 from above -->
  <!-- FIX: removed duplicate CDN popper.js + bootstrap 4.0 here; every page already loads js/popper.min.js + js/bootstrap.min.js. Two Bootstrap copies made menus/dropdowns toggle twice (open then close = flashing). -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>