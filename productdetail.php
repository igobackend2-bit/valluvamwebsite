<?php
    // URL is the product name slug, e.g. "honey". Old numeric/id-slug links ("213" or "213-honey") still resolve via their leading id.
    $product_param = $_GET['product'] ?? '';

    // ---- Pretty product URL: /{category}/{slug} ----
    // Placed before header.php's include (and before any other output) so a
    // redirect header can still be sent. $pathSegments looks at the actual
    // browser URL (not just the rewritten query string) to tell an old-style
    // "/productdetail?product=..." request apart from one that already came
    // in on the new pretty URL.
    //
    // FIX (26 Sep 2026): this used to only recognise a hardcoded list of 7
    // category slugs (dryfruits|nuts|spices|oils|millets|rice|combo), so a
    // product in any other category (e.g. "honey") never got its 301
    // redirect to the pretty URL or its canonical/og:url set correctly -
    // same root cause as the matching .htaccess fix. This page is only ever
    // reached with a 2-segment path via that .htaccess rewrite (which now
    // accepts any category), so there's nothing left to validate here: any
    // 2-segment path is treated as the pretty URL, and any resolved category
    // is used for the redirect below. No category list to keep in sync when
    // admin adds a new category from now on.
    $requestPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $pathSegments = $requestPath === '' ? [] : explode('/', $requestPath);
    $isPrettyUrlAlready = count($pathSegments) === 2 && $pathSegments[0] !== '' && $pathSegments[1] !== '';
    $resolvedCategorySlug = $isPrettyUrlAlready ? strtolower($pathSegments[0]) : null;

    // Old-style access ("/productdetail?product=..." or "?product=..&..")
    // - look up the product's real category so we can 301 to the canonical
    // pretty URL. Wrapped in try/catch: if the DB is unreachable or the
    // product can't be matched, this silently falls through and the page
    // still renders exactly as it did before this change, at the old URL.
    if ($product_param !== '' && !$isPrettyUrlAlready) {
        try {
            require_once __DIR__ . '/assets/db_query/config.php';

            function slugify_product_name_pd($text) {
                $text = strtolower(trim($text));
                $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
                $text = preg_replace('/\s+/', '-', $text);
                $text = preg_replace('/-+/', '-', $text);
                return $text;
            }

            $rawParam = trim($product_param);
            $matchedCategory = null;

            if (ctype_digit($rawParam)) {
                $stmt = $pdo->prepare("SELECT category FROM product_details WHERE id = ?");
                $stmt->execute([(int) $rawParam]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $matchedCategory = strtolower(trim($row['category']));
                }
            } else {
                $stmt = $pdo->query("SELECT category, product_name FROM product_details");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (slugify_product_name_pd($row['product_name']) === $rawParam) {
                        $matchedCategory = strtolower(trim($row['category']));
                        break;
                    }
                }
            }

            if ($matchedCategory !== null && $matchedCategory !== '') {
                header('Location: https://www.valluvamproducts.com/' . $matchedCategory . '/' . rawurlencode($rawParam), true, 301);
                exit;
            }
        } catch (Throwable $e) {
            // DB unavailable or anything unexpected - render normally below
            // at the old URL rather than breaking the page.
        }
    }

    $actionpage = basename($_SERVER['PHP_SELF'], ".php");
    include "header.php";
    $product_id = preg_match('/^\d+/', $product_param, $m) ? $m[0] : $product_param;

    // Build a human-readable product name from the URL slug alone (no DB lookup) for the page title/meta.
    $product_display_raw = preg_replace('/^\d+-?/', '', $product_param);
    $product_display_name = trim(preg_replace('/[-_]+/', ' ', $product_display_raw));
    $product_display_name = $product_display_name !== '' ? ucwords($product_display_name) : '';
    $product_page_title = $product_display_name !== '' ? htmlspecialchars($product_display_name) . ' | Valluvam' : 'Product Details | Valluvam';
    $product_page_desc = $product_display_name !== ''
      ? 'Buy ' . htmlspecialchars($product_display_name) . ' online from Valluvam — farm-fresh, naturally processed and delivered to your door.'
      : "Explore product details, pricing and specifications for Valluvam's natural, farm-fresh products.";
    // Canonical now reflects the pretty "/{category}/{slug}" URL whenever the
    // category is known (either because the request already used it, or the
    // redirect lookup above resolved it); otherwise unchanged fallback.
    $product_canonical = $resolvedCategorySlug !== null
      ? 'https://www.valluvamproducts.com/' . $resolvedCategorySlug . '/' . urlencode($product_param)
      : 'https://www.valluvamproducts.com/productdetail.php' . ($product_param !== '' ? '?product=' . urlencode($product_param) : '');
    ?>

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product_page_title; ?></title>
    <meta name="description" content="<?php echo $product_page_desc; ?>">
    <link rel="canonical" href="<?php echo $product_canonical; ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?php echo $product_page_title; ?>">
    <meta property="og:description" content="<?php echo $product_page_desc; ?>">
    <meta property="og:url" content="<?php echo $product_canonical; ?>">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $product_page_title; ?>">
    <meta name="twitter:description" content="<?php echo $product_page_desc; ?>">
    <meta name="twitter:image" content="/images/logo.png">
    <link rel="stylesheet" href="css/product-detail.css">
  </head>

  <body class="goto-here">
      <div class="hero-wrap hero-bread" style="background-image: url('images/bg-main.jpg');">
          <div class="container">
              <div class="row no-gutters slider-text align-items-center justify-content-center">
                  <div class="col-md-9 ftco-animate text-center">
                      <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span class="mr-2"><a href="shop.php">Products</a></span> <span><?php echo $product_display_name !== '' ? htmlspecialchars($product_display_name) : 'Details'; ?></span></p>
                      <h1 class="mb-0 bread"><b><?php echo $product_display_name !== '' ? htmlspecialchars($product_display_name) : 'Our Products'; ?></b></h1>
                  </div>
              </div>
          </div>
      </div>
      <div id="product-details-container" class="container-fluid mt-2 mb-3"></div>
      <script>
          let productId = "<?php echo $product_id; ?>";
      </script>

      <?php include 'footer.php' ?>



      <!-- loader -->
      <div id="ftco-loader" class="show fullscreen"><svg class="circular" width="48px" height="48px">
              <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee" />
              <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#F96D00" />
          </svg></div>


      <script src="js/jquery.min.js"></script>
      <script src="js/jquery-migrate-3.0.1.min.js"></script>
      <script src="js/popper.min.js"></script>
      <script src="js/bootstrap.min.js"></script>
      <script src="js/jquery.easing.1.3.js"></script>
      <script src="js/jquery.waypoints.min.js"></script>
      <script src="js/owl.carousel.min.js"></script>
      <script src="js/jquery.magnific-popup.min.js"></script>
      <script src="js/aos.js"></script>
      <script src="js/jquery.animateNumber.min.js"></script>
      <script src="js/bootstrap-datepicker.js"></script>
      <script src="js/scrollax.min.js"></script>
      <!-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBVWaKrjvy3MaE7SQ74_uJiULgl1JY0H2s&sensor=false"></script>
      <script src="js/google-map.js"></script> -->
      <script src="js/main.js"></script>
      <!-- jQuery already loaded in header.php, removed duplicate -->
      <script src='https://sachinchoolur.github.io/lightslider/dist/js/lightslider.js'></script>
      <!-- <script src="js/product.js"></script> -->
      <script type='text/javascript' src='https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js'></script>
      <script type='text/javascript' src='assets/js/product_detail/product_detail.js?v=<?php echo @filemtime(__DIR__ . "/assets/js/product_detail/product_detail.js"); ?>'></script>
      <script type='text/javascript' src=''></script>
      <script type='text/Javascript'></script>


  </body>