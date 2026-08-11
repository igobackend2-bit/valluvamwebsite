<?php $activePage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php"
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- <style>
        /* Hero Video */
        .outter.hero-video {
            width: 100%;
            /* height: 100%; */
            display: flex;
            flex-direction: column;
            justify-content: center;

            @media (max-width: 767px) {
                height: 325px;
            }
        }

        .hero-video {

            .video-container {
                height: 550px;
                width: 100%;
                position: relative;
                overflow: hidden;

                @media (max-width: 767px) {
                    height: 325px;
                }
            }

            video {
                object-fit: cover;
                position: absolute;
                height: 550px;
                width: 100%;
                top: 0;
                left: 0;

                @media (max-width: 767px) {
                    height: 325px;
                }
            }

            .video-container:after {
                content: '';
                display: block;
                height: 100%;
                width: 100%;
                position: absolute;
                top: 0;
                left: 0;
                background: rgba(black, .2);
                z-index: 1;
            }

            h1 {
                text-transform: uppercase;
                margin: 0 0 1rem;
                padding: 0;
                line-height: 1;
                color: white;

                @media (max-width: 767px) {
                    font-size: 32px;
                }

                @media (min-width: 768px) {
                    font-size: 52px;
                }
            }

            .desc {
                color: white;
                font-weight: 400;
                font-size: 18px;
            }

            .callout {
                position: relative;
                display: flex;
                justify-content: center;
                align-items: center;
                flex-direction: column;
                height: 100%;
                text-align: center;
                position: relative;
                z-index: 10;
                width: 70%;
                margin: auto;

                @media (max-width: 767px) {
                    width: 90%;
                }
            }

            .button {
                text-transform: uppercase;
                background-color: transparent;
                border-radius: 0px;
                margin-top: 20px;
                background-color: #82bb00;
                padding: 15px 30px;
                border-radius: 0px;
                color: #fff;
                text-decoration: none;
                font-weight: bold;

                @media (max-width: 767px) {
                    padding: 10px 20px;
                }
            }

            .button:hover {
                cursor: pointer;
                background-color: #6e9e01;
            }
        }
    </style> -->
    <style>
        /* ===== MOBILE HERO VIEW CHANGE ONLY ===== */
        @media (max-width: 576px) {

            /* Reduce hero height */
            #home-section {
                height: auto;
            }

            #home-section .slider-item {
                height: 260px;
                /* Mobile banner height */
                min-height: auto;
                background-size: contain;
                /* SHOW FULL IMAGE */
                background-position: top center;
                background-repeat: no-repeat;
                background-color: #000;
                /* fallback background */
            }

            /* Remove dark overlay on mobile */
            #home-section .overlay {
                background: transparent;
            }

            /* Remove unnecessary vertical spacing */
            .home-slider .slider-text {
                min-height: 0;
                padding: 0;
            }

            /* Prevent Owl from stretching */
            .home-slider .owl-stage,
            .home-slider .owl-item {
                height: auto !important;
            }
        }
    </style>
    <!-- title tag -->
    <title>Buy Premium Dry Fruits, Nuts, Spices & Cold Pressed Oils Online | Valluvam
</title>
    <!-- meta tag -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Valluvam brings you organic nuts, dry fruits, cold-pressed oils, spices, and millets delivered fresh to your doorstep. Farm-fresh, pure, and nutritious.">
    <meta name="keywords" content="organic nuts, dry fruits, cold pressed oils, spices online, millets delivery, farm fresh groceries">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <!-- meta property -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Valluvam – Organic Nuts & More Delivered Fresh">
    <meta property="og:description" content="Discover organic nuts, dry fruits, cold-pressed oils, spices & millets from Valluvam. Fresh to your home, pure by nature.">
    <meta property="og:url" content="https://valluvamproducts.com/">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Valluvam – Organic Nuts & More Delivered Fresh">
    <meta name="twitter:description" content="Discover organic nuts, dry fruits, cold-pressed oils, spices & millets from Valluvam. Fresh to your home, pure by nature.">
    <meta name="twitter:image" content="/images/logo.png">
    <link rel="icon" href="/img" type="image/png">
    <!-- canonical tag -->
    <link rel="canonical" href="https://valluvamproducts.com/">

    <!-- Valluvam Products Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Apple touch icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/valluvam/images/favicon/apple-touch-icon.png">

    <!-- Optional PNG icons -->
    <link rel="icon" type="image/png" sizes="96x96" href="/valluvam/images/favicon/favicon-96x96.png">

    <!-- Web manifest -->
    <link rel="manifest" href="/valluvam/images/favicon/site.webmanifest">


</head>

<body class="goto-here">


    <!-- Swiper Section -->
    <section id="home-section" class="hero">
        <div class="home-slider owl-carousel">
            <div class="slider-item" style="background-image: url(images/hero-1.jpg);">
                <div class="overlay"></div>
                <div class="container">
                    <div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">

                        <div class="col-md-12 ftco-animate text-center">
                            <!-- <h1 class="mb-2">A perfect blend of crunchy nuts and sweet dry fruits, &amp; packed with nutrition and flavor!</h1>
                            <h2 class="subheading mb-4">We deliver organic Nuts,Dryfruits,Spices &amp;Oils,Millets</h2> -->
                            <!-- <p><a href="#" class="btn btn-primary">View Details</a></p>  -->
                        </div>

                    </div>
                </div>
            </div>

            <div class="slider-item" style="background-image: url(images/hero-2.jpg);">
                <div class="overlay"></div>
                <div class="container">
                    <div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">

                        <!-- <div class="col-sm-12 ftco-animate text-center">
                            <h1 class="mb-2">“A perfect blend of wholesome millets and natural goodness, &amp; packed with nutrition and flavor!”</h1>
                            <h2 class="subheading mb-4">We deliver organic Nuts,Dryfruits,Spices &amp; Oils,Millets</h2> -->
                        <!-- <p><a href="#" class="btn btn-primary">View Details</a></p> -->
                        <!-- </div>  -->

                    </div>
                </div>
            </div>
            <div class="slider-item" style="background-image: url(images/hero-3.jpg);">
                <div class="overlay"></div>
                <div class="container">
                    <div class="row slider-text justify-content-center align-items-center" data-scrollax-parent="true">

                        <!-- <div class="col-sm-12 ftco-animate text-center">
                            <h1 class="mb-2">“A perfect blend of wholesome millets and natural goodness, &amp; packed with nutrition and flavor!”</h1>
                            <h2 class="subheading mb-4">We deliver organic Nuts,Dryfruits,Spices &amp; Oils,Millets</h2> -->
                        <!-- <p><a href="#" class="btn btn-primary">View Details</a></p> -->
                        <!-- </div>  -->

                    </div>
                </div>
            </div>

        </div>
    </section>
    <!-- <section id="home-section" class="hero">
  <div class="home-slider owl-carousel">

   
    <div class="slider-item" style="background-image: url('images/hero-1.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
        </div>
      </div>
    </div>

   
    <div class="slider-item" style="background-image: url('images/hero-2.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
        </div>
      </div>
    </div>

   
    <div class="slider-item" style="background-image: url('images/hero-3.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text align-items-center justify-content-center">
        </div>
      </div>
    </div>

  </div>
</section> -->

    <section class="ftco-section-category">
        <div class="container">
            <div class="slid-er">
                <div class="slides" id="slides">
                    <!-- Products will be loaded here -->
                </div>
            </div>
    </section>
    <h2 class="product-tittle">OUR <span style="color: #82ae46;">products</span></h2>
    <section class="ftco-section">
        <div class="container">
            <div class="row" id="product-container" style="align-items:flex-start;">
                <!-- Products will be loaded here -->
            </div>
        </div>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>

    <!-- <section class="ftco-section img" style="background-image: url('images/why.jfif'); background-size: cover; background-position: center; width: 99.94%">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row no-gutters d-flex align-items-stretch shadow rounded overflow-hidden">

                         Left Image (hidden on mobile) -->
    <!-- <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-white">
                            <img src="images/why.jfif" alt="Why Choose Us" class="img-fluid w-100 p-3" style="object-fit: contain; max-height: 100%; height: auto;">
                        </div> -->

    <!-- Right Text Content -->
    <!-- <div class="col-12 col-lg-6 text-white p-4 d-flex flex-column justify-content-center" style="background: rgba(0, 0, 0, 0.5);">
                            <div>
                                <h3><a href="#" class="text-white text-decoration-none" style="justify-content: center;">WHY CHOOSE</a></h3>
                                <h2 class="mb-3" style="color: green;">Valluvam</h2>
                                <p><strong>Purity You Can Trust:</strong> Every product is carefully selected, processed, and packaged to maintain the highest standards of quality and freshness.</p>
                                <p><strong>Sustainable Practices:</strong> We work closely with local farmers and follow eco-friendly processes to support sustainability and ensure minimal impact on the environment.</p>
                                <p><strong>Convenience at Your Fingertips:</strong> With round-the-clock delivery, we bring premium products straight to your doorstep, making healthy living easier than ever.</p>
                                <p>At Valluvam, we blend the essence of tradition with modern convenience to create a brand you can rely on. Our goal is simple: to help you embrace a healthier lifestyle with pure and natural products delivered with care.</p>
                                <p>Discover the goodness of Valluvam today—because your health and satisfaction are our top priorities!</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div> -->
    <!-- </section> -->
    <?php include "service.php"; ?>
    <?php include "review.php"; ?>

    <div class="container py-4">
        <!-- <div class="row d-flex justify-content-center py-5"> 
                <div class="col-md-6">
                    <h2 style="font-size: 22px;" class="mb-0">Have you any questions?</h2>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <form action="#" class="subscribe-form">
                        <div class="form-group d-flex">
                            <input type="text" class="form-control" placeholder="Enter email address">
                            <input type="submit" value="Subscribe" class="submit px-3">
                        </div>
                    </form>
                </div>
             </div> -->
    </div>

    <?php include "footer.php"; ?>



    <!-- loader -->
    <div id="ftco-loader" class="show fullscreen"><svg class="circular" width="48px" height="48px">
            <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee" />
            <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#F96D00" />
        </svg></div>


    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-migrate-3.0.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.easing.1.3.js"></script>
    <script src="js/jquery.waypoints.min.js"></script>
    <script src="js/jquery.stellar.min.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/jquery.magnific-popup.min.js"></script>
    <script src="js/aos.js"></script>
    <script src="js/jquery.animateNumber.min.js"></script>
    <script src="js/bootstrap-datepicker.js"></script>
    <script src="js/scrollax.min.js"></script>
    <!-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBVWaKrjvy3MaE7SQ74_uJiULgl1JY0H2s&sensor=false"></script>
    <script src="js/google-map.js"></script> -->
    <script src="js/main.js"></script>
    <script src="assets/js/index/index.js"></script>



</body>

</html>