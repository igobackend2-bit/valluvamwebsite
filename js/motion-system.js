/* ===== Motion system — scroll-reveal for JS/AJAX-rendered product grids =====
   The site already reveals static content via .ftco-animate + waypoints
   (js/main.js). Product cards in shop.php / the category pages / the
   homepage category strip are injected into the DOM after an AJAX call,
   so that system never sees them - they just appear instantly. This
   file watches those grids and fades each card in as it's added and as
   it scrolls into view, using the .v-reveal / .v-revealed classes from
   css/motion-system.css.

   Progressive enhancement: the "invisible until revealed" state (opacity:0)
   only exists on elements this script itself has classed .v-reveal, so if
   this script never runs (blocked, errors, old browser) cards simply render
   at full opacity with no animation - never stuck hidden. */
(function () {
  "use strict";

  if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    return;
  }

  if (typeof IntersectionObserver === "undefined") {
    return;
  }

  var GRID_SELECTOR = [
    ".v-cat-grid",
    ".v-shop-grid",
    "#productGrid",
    "#shopProductGrid",
    ".slid-er .slide-track",
    ".home-category-grid"
  ].join(", ");

  var CARD_SELECTOR = [
    ".v-cat-grid > [class*='col-'] > .product",
    ".v-shop-grid > [class*='col-'] > .product",
    ".v-cat-grid .product",
    ".v-shop-grid .product",
    ".slid-er .slide .slide-content",
    ".home-cat-card",
    ".home-product-card"
  ].join(", ");

  var revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add("v-revealed");
        revealObserver.unobserve(entry.target);
      }
    });
  }, { rootMargin: "0px 0px -8% 0px", threshold: 0.1 });

  var staggerIndex = new WeakMap();
  var order = 0;

  function armCard(card) {
    if (card.classList.contains("v-reveal") || card.dataset.vRevealSkip) {
      return;
    }
    card.classList.add("v-reveal");
    if (!staggerIndex.has(card)) {
      staggerIndex.set(card, order % 8);
      order++;
    }
    card.style.transitionDelay = (staggerIndex.get(card) * 0.05) + "s";
    revealObserver.observe(card);
  }

  function scanExisting() {
    document.querySelectorAll(CARD_SELECTOR).forEach(armCard);
  }

  // Grids are populated asynchronously (fetch/AJAX rendering product
  // cards after the initial page load), so watch for new cards rather
  // than only scanning once on DOMContentLoaded.
  function watchGrids() {
    var grids = document.querySelectorAll(GRID_SELECTOR);
    if (!grids.length) {
      return;
    }
    var mo = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) {
            return;
          }
          if (node.matches && node.matches(CARD_SELECTOR)) {
            armCard(node);
          }
          node.querySelectorAll && node.querySelectorAll(CARD_SELECTOR).forEach(armCard);
        });
      });
    });
    grids.forEach(function (grid) {
      mo.observe(grid, { childList: true, subtree: true });
    });
  }

  function init() {
    scanExisting();
    watchGrids();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
