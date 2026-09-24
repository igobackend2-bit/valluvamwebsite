<?php $actionpage = basename($_SERVER['PHP_SELF'], ".php");
include "header.php" ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Valluvam</title>
    <meta name="description" content="Get in touch with Valluvam for orders, wholesale enquiries or support.">
    <meta name="keywords" content="nuts, dry fruits, cold pressed oils, spices online, millets delivery, farm fresh groceries">
    <link rel="canonical" href="https://valluvamproducts.com/contact.php">
    <link rel="stylesheet" href="css/supporting-pages.css?v=<?php echo @filemtime(__DIR__ . '/css/supporting-pages.css'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Contact Valluvam">
    <meta property="og:description" content="Get in touch with Valluvam for orders, wholesale enquiries or support.">
    <meta property="og:url" content="https://valluvamproducts.com/contact.php">
    <meta property="og:image" content="/images/logo.png">
    <meta property="og:site_name" content="Valluvam">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Contact Valluvam">
    <meta name="twitter:description" content="Get in touch with Valluvam for orders, wholesale enquiries or support.">
    <meta name="twitter:image" content="/images/logo.png">
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Store",
            "name": "Valluvam",
            "description": "Nuts, dry fruits, cold-pressed oils, spices & millets delivered fresh to your door.",
            "url": "https://www.valluvamproducts.com/",
            "logo": "https://www.valluvamproducts.com/assets/images/logo.png",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "No 17, Kovalan street, 2nd main road, Uthandi Kanathur",
                "addressLocality": "Chennai",
                "postalCode": "600119",
                "addressCountry": "IN"
            },
            "contactPoint": {
                "@type": "ContactPoint",
                "telephone": "+91-8925969888",
                "contactType": "Customer Support"
            },
            "sameAs": [
                "https://www.facebook.com/valluvamproducts/",
                "https://www.instagram.com/valluvam_agro_products/"
            ],
            "openingHours": "Mo-Su 10:00-07:30"
        }
    </script>

</head>


<body class="goto-here">
    <!-- <link rel="stylesheet" href="css/login.css"> -->
    <div class="hero-wrap hero-bread v-page-hero" style="background-image: url('images/bg-main.jpg');">
        <div class="container">
            <div class="row no-gutters slider-text align-items-center justify-content-center">
                <div class="col-md-9 ftco-animate text-center">
                    <p class="breadcrumbs"><span class="mr-2"><a href="index.php">Home</a></span> <span>Contact</span></p>
                    <h1 class="mb-0 bread">Contact Us</h1>
                    <p class="v-page-desc">Have a question about an order, a wholesale enquiry, or just want to say hello? Reach out — our team typically responds within 24 hours.</p>
                </div>
            </div>
        </div>
    </div>
    <section class="ftco-section contact-section bg-light v-content-section">
        <div class="container">
            <div class="row d-flex mb-5 contact-info">
                <div class="w-100"></div>
                <div class="col-md-3 d-flex">
                    <div class="v-info-card w-100">
                        <ion-icon name="location-outline"></ion-icon>
                        <span class="v-info-label">Address</span>
                        <p>No 17, Kovalan street, 2nd main road, Uthandi Kanathur-600119</p>
                    </div>
                </div>
                <div class="col-md-3 d-flex">
                    <div class="v-info-card w-100">
                        <ion-icon name="call-outline"></ion-icon>
                        <span class="v-info-label">Phone</span>
                        <a href="tel:+918925969888">+91 89259 69888</a>
                        <a href="tel:+918925878327">+91 89258 78327</a>
                        <a href="tel:+918925833758">+91 89258 33758</a>
                        <a href="tel:+918925958926">+91 89259 58926</a>
                        <a href="tel:+918925978983">+91 89259 78983</a>
                    </div>
                </div>
                <div class="col-md-3 d-flex">
                    <div class="v-info-card w-100">
                        <ion-icon name="mail-outline"></ion-icon>
                        <span class="v-info-label">Email</span>
                        <a href="mailto:info.thefarmersfactory@gmail.com">info.thefarmersfactory@gmail.com</a>
                    </div>
                </div>
                <div class="col-md-3 d-flex">
                    <div class="v-info-card w-100">
                        <ion-icon name="globe-outline"></ion-icon>
                        <span class="v-info-label">Website</span>
                        <a href="https://valluvamproducts.com">valluvamproducts.com</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Contact Form -->
                <div class="col-md-7">
                    <div class="contact-page-form">
                        <h2>Get in Touch</h2>
                        <form id="contactForm">
                            <div class="row">
                                <div class="col-md-6 col-sm-6">
                                    <div class="single-input-field">
                                        <label for="contact-name" class="sr-only">Your Name</label>
                                        <input type="text" id="contact-name" placeholder="Your Name" name="name" required />
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="single-input-field">
                                        <label for="contact-email" class="sr-only">E-mail</label>
                                        <input type="email" id="contact-email" placeholder="E-mail" name="email" required />
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="single-input-field">
                                        <label for="contact-phone" class="sr-only">Phone Number</label>
                                        <input type="text" id="contact-phone" placeholder="Phone Number" name="phone" />
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="single-input-field">
                                        <label for="contact-subject" class="sr-only">Subject</label>
                                        <input type="text" id="contact-subject" placeholder="Subject" name="subject" />
                                    </div>
                                </div>
                                <div class="col-md-12 message-input">
                                    <div class="single-input-field">
                                        <label for="contact-message" class="sr-only">Write Your Message</label>
                                        <textarea id="contact-message" placeholder="Write Your Message" name="message" required></textarea>
                                    </div>
                                </div>
                                <div class="single-input-fieldsbtn">
                                    <button type="submit" aria-label="Send message">Send Now</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Map Section -->
                <div class="col-md-5 mt-4 mt-md-0">
                    <div class="contact-page-map">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3888.444167315222!2d80.25131907460788!3d12.848826987466095!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3a5259ffba6cfd0f%3A0xc7d7dffb8934766!2sUthandi%2C%20Chennai%2C%20Tamil%20Nadu%20600119!5e0!3m2!1sen!2sin!4v1692788888888!5m2!1sen!2sin"
                            width="100%"
                            height="350"
                            style="border:0; border-radius: 10px;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- loader -->
    <div id="ftco-loader" class="show fullscreen"><svg class="circular" width="48px" height="48px">
            <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee" />
            <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10"
                stroke="#F96D00" />
        </svg></div>
    <?php include 'footer.php'; ?>

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
    <script src="assets/js/contact/contact.js"></script>

</body>

</html>