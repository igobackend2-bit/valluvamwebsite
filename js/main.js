function slugify(text) {
	return String(text || '')
		.toLowerCase()
		.trim()
		.replace(/[^a-z0-9\s-]/g, '')
		.replace(/\s+/g, '-')
		.replace(/-+/g, '-');
}

AOS.init({
	duration: 800,
	easing: 'slide'
});

(function ($) {

	"use strict";

	var isMobile = {
		Android: function () {
			return navigator.userAgent.match(/Android/i);
		},
		BlackBerry: function () {
			return navigator.userAgent.match(/BlackBerry/i);
		},
		iOS: function () {
			return navigator.userAgent.match(/iPhone|iPad|iPod/i);
		},
		Opera: function () {
			return navigator.userAgent.match(/Opera Mini/i);
		},
		Windows: function () {
			return navigator.userAgent.match(/IEMobile/i);
		},
		any: function () {
			return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
		}
	};


	$(window).stellar({
		responsive: true,
		parallaxBackgrounds: true,
		parallaxElements: true,
		horizontalScrolling: false,
		hideDistantElements: false,
		scrollProperty: 'scroll'
	});


	var fullHeight = function () {

		$('.js-fullheight').css('height', $(window).height());
		$(window).resize(function () {
			$('.js-fullheight').css('height', $(window).height());
		});

	};
	fullHeight();

	// loader
	var loader = function () {
		setTimeout(function () {
			if ($('#ftco-loader').length > 0) {
				$('#ftco-loader').removeClass('show');
			}
		}, 1);
	};
	loader();

	// Scrollax
	$.Scrollax();

	var carousel = function () {
		$('.home-slider').owlCarousel({
			loop: true,
			autoplay: true,
			margin: 0,
			animateOut: 'fadeOut',
			animateIn: 'fadeIn',
			nav: false,
			autoplayHoverPause: false,
			items: 1,
			navText: ["<span class='ion-md-arrow-back'></span>", "<span class='ion-chevron-right'></span>"],
			responsive: {
				0: {
					items: 1
				},
				600: {
					items: 1
				},
				1000: {
					items: 1
				}
			}
		});

		$('.carousel-testimony').owlCarousel({
			center: true,
			loop: true,
			items: 1,
			margin: 30,
			stagePadding: 0,
			nav: false,
			navText: ['<span class="ion-ios-arrow-back">', '<span class="ion-ios-arrow-forward">'],
			responsive: {
				0: {
					items: 1
				},
				600: {
					items: 3
				},
				1000: {
					items: 3
				}
			}
		});

	};
	carousel();

	$('nav .dropdown').hover(function () {
		var $this = $(this);
		// 	 timer;
		// clearTimeout(timer);
		$this.addClass('show');
		$this.find('> a').attr('aria-expanded', true);
		// $this.find('.dropdown-menu').addClass('animated-fast fadeInUp show');
		$this.find('.dropdown-menu').addClass('show');
	}, function () {
		var $this = $(this);
		// timer;
		// timer = setTimeout(function(){
		$this.removeClass('show');
		$this.find('> a').attr('aria-expanded', false);
		// $this.find('.dropdown-menu').removeClass('animated-fast fadeInUp show');
		$this.find('.dropdown-menu').removeClass('show');
		// }, 100);
	});


	$('#dropdown04').on('show.bs.dropdown', function () {
		console.log('show');
	});

	// scroll
	var scrollWindow = function () {
		$(window).scroll(function () {
			var $w = $(this),
				st = $w.scrollTop(),
				navbar = $('.ftco_navbar'),
				sd = $('.js-scroll-wrap');

			if (st > 150) {
				if (!navbar.hasClass('scrolled')) {
					navbar.addClass('scrolled');
				}
			}
			if (st < 150) {
				if (navbar.hasClass('scrolled')) {
					navbar.removeClass('scrolled sleep');
				}
			}
			if (st > 350) {
				if (!navbar.hasClass('awake')) {
					navbar.addClass('awake');
				}

				if (sd.length > 0) {
					sd.addClass('sleep');
				}
			}
			if (st < 350) {
				if (navbar.hasClass('awake')) {
					navbar.removeClass('awake');
					navbar.addClass('sleep');
				}
				if (sd.length > 0) {
					sd.removeClass('sleep');
				}
			}
		});
	};
	scrollWindow();


	var counter = function () {

		$('#section-counter').waypoint(function (direction) {

			if (direction === 'down' && !$(this.element).hasClass('ftco-animated')) {

				var comma_separator_number_step = $.animateNumber.numberStepFactories.separator(',')
				$('.number').each(function () {
					var $this = $(this),
						num = $this.data('number');
					console.log(num);
					$this.animateNumber(
						{
							number: num,
							numberStep: comma_separator_number_step
						}, 7000
					);
				});

			}

		}, { offset: '95%' });

	}
	counter();

	var contentWayPoint = function () {
		var i = 0;
		$('.ftco-animate').waypoint(function (direction) {

			if (direction === 'down' && !$(this.element).hasClass('ftco-animated')) {

				i++;

				$(this.element).addClass('item-animate');
				setTimeout(function () {

					$('body .ftco-animate.item-animate').each(function (k) {
						var el = $(this);
						setTimeout(function () {
							var effect = el.data('animate-effect');
							if (effect === 'fadeIn') {
								el.addClass('fadeIn ftco-animated');
							} else if (effect === 'fadeInLeft') {
								el.addClass('fadeInLeft ftco-animated');
							} else if (effect === 'fadeInRight') {
								el.addClass('fadeInRight ftco-animated');
							} else {
								el.addClass('fadeInUp ftco-animated');
							}
							el.removeClass('item-animate');
						}, k * 50, 'easeInOutExpo');
					});

				}, 100);

			}

		}, { offset: '95%' });
	};
	contentWayPoint();


	// navigation
	var OnePageNav = function () {
		$(".smoothscroll[href^='#'], #ftco-nav ul li a[href^='#']").on('click', function (e) {
			e.preventDefault();

			var hash = this.hash,
				navToggler = $('.navbar-toggler');
			$('html, body').animate({
				scrollTop: $(hash).offset().top
			}, 700, 'easeInOutExpo', function () {
				window.location.hash = hash;
			});


			if (navToggler.is(':visible')) {
				navToggler.click();
			}
		});
		$('body').on('activate.bs.scrollspy', function () {
			console.log('nice');
		})
	};
	OnePageNav();


	// magnific popup
	$('.image-popup').magnificPopup({
		type: 'image',
		closeOnContentClick: true,
		closeBtnInside: false,
		fixedContentPos: true,
		mainClass: 'mfp-no-margins mfp-with-zoom', // class to remove default margin from left and right side
		gallery: {
			enabled: true,
			navigateByImgClick: true,
			preload: [0, 1] // Will preload 0 - before current, and 1 after the current image
		},
		image: {
			verticalFit: true
		},
		zoom: {
			enabled: true,
			duration: 300 // don't foget to change the duration also in CSS
		}
	});

	$('.popup-youtube, .popup-vimeo, .popup-gmaps').magnificPopup({
		disableOn: 700,
		type: 'iframe',
		mainClass: 'mfp-fade',
		removalDelay: 160,
		preloader: false,

		fixedContentPos: false
	});



	var goHere = function () {

		$('.mouse-icon').on('click', function (event) {

			event.preventDefault();

			$('html,body').animate({
				scrollTop: $('.goto-here').offset().top
			}, 500, 'easeInOutExpo');

			return false;
		});
	};
	goHere();


	function makeTimer() {

		var endTime = new Date("21 December 2019 9:56:00 GMT+01:00");
		endTime = (Date.parse(endTime) / 1000);

		var now = new Date();
		now = (Date.parse(now) / 1000);

		var timeLeft = endTime - now;

		var days = Math.floor(timeLeft / 86400);
		var hours = Math.floor((timeLeft - (days * 86400)) / 3600);
		var minutes = Math.floor((timeLeft - (days * 86400) - (hours * 3600)) / 60);
		var seconds = Math.floor((timeLeft - (days * 86400) - (hours * 3600) - (minutes * 60)));

		if (hours < "10") { hours = "0" + hours; }
		if (minutes < "10") { minutes = "0" + minutes; }
		if (seconds < "10") { seconds = "0" + seconds; }

		$("#days").html(days + "<span>Days</span>");
		$("#hours").html(hours + "<span>Hours</span>");
		$("#minutes").html(minutes + "<span>Minutes</span>");
		$("#seconds").html(seconds + "<span>Seconds</span>");

	}

	setInterval(function () { makeTimer(); }, 1000);



})(jQuery);
$(document).ready(function () {

	var $toggler = $('.navbar-toggler');
	var $menu = $('#ftco-nav');
	var $closeBtn = $('#navbarClose');

	// Hamburger click → OPEN menu + show X, hide hamburger
	$toggler.on("click", function () {
		if (!$menu.hasClass('show')) {
			// opening
			$menu.collapse('show');
			$closeBtn.show();
			$toggler.hide();    // hide menu button
		} else {
			// closing
			$menu.collapse('hide');
			$closeBtn.hide();
			$toggler.show();    // show menu button back
		}
	});

	// Close button click → CLOSE menu + show hamburger
	$closeBtn.on("click", function () {
		$menu.collapse('hide');
		$closeBtn.hide();
		$toggler.show();  // show hamburger
	});

	// When Bootstrap closes automatically
	$menu.on('hidden.bs.collapse', function () {
		$closeBtn.hide();
		$toggler.show();
	});

	// When Bootstrap opens automatically
	$menu.on('shown.bs.collapse', function () {
		$closeBtn.show();
		$toggler.hide();
	});

});

// -----------------------------------------------------------------------
// Scroll dampening (presentation only).
// Some mice/trackpads report very large wheel deltas per "notch", which
// makes the page jump long distances in one go and skip past product
// details before they can be read. This only steps in for those
// abnormally large jumps -- normal mouse-wheel and trackpad scrolling
// (the vast majority of wheel events) is left completely untouched and
// handled natively by the browser, so this does not change how scrolling
// feels for anyone whose scroll was already working normally. It also
// skips modals, dropdowns and any other inner-scrolling element so those
// keep scrolling exactly as before.
(function () {
	var MAX_STEP = 120;   // largest distance (px) a single wheel tick may move the page
	var DURATION = 320;   // ms for the eased catch-up animation
	var rafId = null;

	function easeOutCubic(t) {
		return 1 - Math.pow(1 - t, 3);
	}

	function isInnerScrollable(target) {
		return !!(target && target.closest && target.closest(
			'.form-modal, .modal, .dropdown-menu, .slider6, #slides, [data-no-scroll-dampen]'
		));
	}

	window.addEventListener('wheel', function (e) {
		if (e.ctrlKey || e.defaultPrevented) return;
		if (isInnerScrollable(e.target)) return;

		var delta = e.deltaY;
		if (e.deltaMode === 1) { delta *= 18; }
		else if (e.deltaMode === 2) { delta *= window.innerHeight; }

		if (Math.abs(delta) <= MAX_STEP) return; // normal-sized tick: let the browser handle it as usual

		e.preventDefault();

		var clamped = delta > 0 ? MAX_STEP : -MAX_STEP;
		var start = window.pageYOffset;
		var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
		var target = Math.max(0, Math.min(maxScroll, start + clamped));
		var startTime = null;

		if (rafId) { cancelAnimationFrame(rafId); }

		function step(ts) {
			if (!startTime) { startTime = ts; }
			var progress = Math.min(1, (ts - startTime) / DURATION);
			window.scrollTo(0, start + (target - start) * easeOutCubic(progress));
			if (progress < 1) {
				rafId = requestAnimationFrame(step);
			} else {
				rafId = null;
			}
		}
		rafId = requestAnimationFrame(step);
	}, { passive: false });
})();
