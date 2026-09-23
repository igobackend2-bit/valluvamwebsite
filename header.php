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

  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">
  <!-- Include Swiper and Animate.css -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
  <!-- <link rel="stylesheet" href="css/products.css"> -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <!-- Font Awesome Free 6 (latest stable via jsDelivr CDN) -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-CutkzkCZnQpjkGH5W8cztD8Lq1SxH0g3ssZzkYO4CDYQhQwH4iLRyKfUENtYyX6UGN7vNzQk5xFojy6LXz9lBA==" crossorigin="anonymous" referrerpolicy="no-referrer" /> -->
  <meta property="og:image" content="images/logo.png" />
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" integrity="sha512-5A8nwdMOWrSz20fDsjczgUidUBR8liPYU+WymTZP1lmY9G6Oc7HlZv156XqnsgNUzTyMefFTcsFH/tnJE/+xBg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/fontawesome.min.css">
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">

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
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

  <link href="https://fonts.googleapis.com/css?family=Poppins:400,500,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome (for icons) -->
  <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700,800&display=swap" rel="stylesheet"> -->
  <!-- <link href="https://fonts. leapis.com/css?family=Lora:400,400i,700,700i&display=swap" rel="stylesheet"> -->
  <!-- <link href="https://fonts.googleapis.com/css?family=Amatic+SC:400,700&display=swap" rel="stylesheet"> -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=account_circle" />
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
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

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
  <!-- bootstrap-4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

  <!-- <link rel="stylesheet" href="css/catelog.css"> -->
  <!-- sweetheart -->
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
        width: 72px;
        height: 72px;
        overflow: hidden;
        flex: 0 0 auto;
      }

      #ftco-navbar .navbar-brand img {
        transform: scale(1.4);
      }

      /* 7b. Gap balance: the logo sat 20px from the search box, then a
         162px void before the menu. Redistributed so the left cluster
         breathes and the void closes to about 95px. */
      #ftco-navbar .navbar-brand {
        margin-right: 36px;
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

    @media (max-width: 767.98px) {
      .v-announce-track {
        gap: 4px 16px;
      }

      .v-announce-track span {
        font-size: 10.5px;
      }
    }
  </style>

  <!-- ===== Shop mega-menu: visual upgrade of the existing category dropdown.
       Same 7 links/hrefs as before (nuts/dryfruits/oils/spices/millets/rice/combo);
       only presentation changes - a labelled icon grid instead of a plain text list. ===== -->
  <style>
    #ftco-navbar .dropdown-menu.v-mega-menu {
      display: none;
      padding: 18px;
      border: 0;
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(0, 0, 0, .18);
    }

    #ftco-navbar .dropdown-menu.v-mega-menu.show {
      display: grid;
      grid-template-columns: repeat(4, minmax(120px, 1fr));
      gap: 6px 10px;
    }

    @media (max-width: 575.98px) {
      #ftco-navbar .dropdown-menu.v-mega-menu.show {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    #ftco-navbar .v-mega-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 6px;
      padding: 14px 8px;
      border-radius: 8px;
      color: #2f2f2f !important;
      transition: background-color .15s ease, transform .15s ease;
    }

    #ftco-navbar .v-mega-item:hover {
      background-color: #f1f7ec;
      transform: translateY(-2px);
      text-decoration: none;
    }

    #ftco-navbar .v-mega-item ion-icon {
      font-size: 26px;
      color: #82ae46;
    }

    #ftco-navbar .v-mega-item span {
      font-size: 13px;
      font-weight: 500;
    }
  </style>
</head>

<body>
  <!-- Announcement strip: only claims already made elsewhere on the site (Why Choose
       Valluvam / Our Services on index.php) - quality checks, pan-India delivery with
       tracking, Razorpay secure payments, and the existing wholesale/bulk offering. No
       shipping-cost or delivery-time promise is made here since none is confirmed. -->
  <div class="v-announce-bar">
    <div class="v-announce-track">
      <span>QUALITY CHECKED PRODUCTS</span>
      <span>PAN-INDIA DELIVERY WITH TRACKING</span>
      <span>SECURE PAYMENTS VIA RAZORPAY</span>
      <span>BULK / WHOLESALE ORDERS AVAILABLE</span>
    </div>
  </div>
  <div class="py-1 bg-primary" style="background-color: green;">
    <div class="container">
      <div class="row no-gutters d-flex align-items-start align-items-center px-md-0">
        <div class="col-lg-12 d-block">
          <div class="row d-flex">
            <div class="col-md pr-4 d-flex topper align-items-center">
              <div class="icon mr-2 d-flex justify-content-center align-items-center"><span class="icon-phone2"></span></div>
              <span class="text">+918925969888</span>
            </div>
            <div class="col-md pr-4 d-flex topper align-item  s-center">
              <div class="icon mr-2 d-flex justify-content-center align-items-center"><span class="icon-paper-plane"></span></div>
              <span class="text">info.thefarmersfactory@gmail.com</span>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
  <nav class="navbar navbar-expand-lg navbar-dark ftco_navbar bg-dark ftco-navbar-light" id="ftco-navbar">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="index.php">
        <img src="images/logo.jpeg" alt="Valluvam - Premium Dry Fruits, Nuts, Spices &amp; Cold-Pressed Oils" class="img-fluid" style="max-height:100px;" width="100" height="100">
      </a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="oi oi-menu"></span> Menu
      </button>
      <button id="navbarClose" class="navbar-close" aria-label="Close menu">&times;</button>


      <?php
      // get the current page name
      $currentPage = basename($_SERVER['PHP_SELF']);

      // allowed pages where search should be shown
      // rice.php was missing from this list, so the search box didn't show on the Rice
      // category page even though the same search endpoint already supports it.
      $allowedPages = ['index.php', 'shop.php', 'dryfruits.php', 'nuts.php', 'combo.php', 'spices.php', 'oils.php', 'millets.php', 'rice.php', 'productdetail.php'];
      ?>

      <?php if (in_array($currentPage, $allowedPages)) : ?>
        <form id="searchForm">
          <div class="search">
            <ion-icon name="search"></ion-icon>
            <input class="search-input" type="search" id="search" placeholder="search products.....">
          </div>
        </form>
      <?php endif; ?>

      <!-- Products will display here -->
      <div id="product-results"></div>


      <div class="collapse navbar-collapse" id="ftco-nav">
        <ul class="navbar-nav ml-auto" style="gap: 2px;">
          <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown04" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Shop</a>
            <!-- Same 7 category links/hrefs as before - only the presentation changed
                 from a plain text list to a labelled icon grid. -->
            <div class="dropdown-menu v-mega-menu" aria-labelledby="dropdown04">
              <a class="v-mega-item" href="dryfruits.php"><ion-icon name="nutrition-outline"></ion-icon><span>Dry Fruits</span></a>
              <a class="v-mega-item" href="nuts.php"><ion-icon name="leaf-outline"></ion-icon><span>Nuts</span></a>
              <a class="v-mega-item" href="spices.php"><ion-icon name="flame-outline"></ion-icon><span>Spices</span></a>
              <a class="v-mega-item" href="oils.php"><ion-icon name="water-outline"></ion-icon><span>Oils</span></a>
              <a class="v-mega-item" href="millets.php"><ion-icon name="flower-outline"></ion-icon><span>Millets</span></a>
              <a class="v-mega-item" href="rice.php"><ion-icon name="restaurant-outline"></ion-icon><span>Rice</span></a>
              <a class="v-mega-item" href="combo.php"><ion-icon name="gift-outline"></ion-icon><span>Combos</span></a>
            </div>
          </li>

          <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
          <li class="nav-item"><a href="blog.php" class="nav-link">Blog</a></li>
          <li class="nav-item"><a href="b2b-wholesale.php" class="nav-link">B2B / Wholesale</a></li>
          <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
          <li class="nav-item cta">
            <a href="wishlist.php" class="nav-link" aria-label="Wishlist">
              <span class="ion-ios-heart"></span>
            </a>
          </li>
          <li class="nav-item cta cta-colored">
            <a href="cart.php" class="nav-link">
              <span class="icon-shopping_cart"></span>
              <span id="cartCount">[0]</span>
            </a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link <?php echo ($status == 1) ? 'dropdown-toggle' : ''; ?>"
              href="#"
              id="userIcon"
              <?php if ($status == 1): ?>
              data-toggle="dropdown"
              aria-haspopup="true"
              aria-expanded="false"
              <?php endif; ?>>
              <span class="login-nav">
                <i class="ri-account-circle-fill"></i>
                <?php if ($status == 1): ?>
                  <span style="font-weight:bold;margin-left:5px;">
                    <?php echo htmlspecialchars($user_name); ?>
                  </span>
                <?php endif; ?>

              </span>
            </a>

            <?php if ($status == 1): ?>
              <div class="dropdown-menu" aria-labelledby="userIcon">
                <a class="dropdown-item" href="order_tracking.php"><i class="fa fa-shopping-bag mr-2"></i>My Orders</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out mr-2"></i>Logout</a>
              </div>
            <?php endif; ?>
          </li>

        </ul>
      </div>
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
        <img src="images/leaf-corner-tr.png" class="auth-corner-leaf auth-corner-leaf--tr" alt="">
        <img src="images/leaf-corner-br.png" class="auth-corner-leaf auth-corner-leaf--br" alt="">
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