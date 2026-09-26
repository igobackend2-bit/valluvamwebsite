/* Applies saved CMS overrides to the existing homepage markup. Defaults stay
 * untouched when no override has been saved. */
(function () {
  const targets = {
    hero: '.v-hero-banner', categories: '#shop-by-category', best_sellers: '#best-products-section',
    why: '#why-valluvam', healthy: '#healthy-choices-section', did_you_know: '.v-know-section',
    traditional: '#traditional-foods-section', story: '.v-story-section', gifting: '#gifting-section',
    curated_combos: '.v-category-section', everyday: '#everyday-essentials-section', trending: '#top-rated-section',
    reviews: '.v-reviews-section', marketplaces: '.v-mp-home-section', wholesale: '.v-b2b-section',
    private_label: '.v-privatelabel-section', kitchen: '.v-blog-section', newsletter: '.v-newsletter-section',
    final_cta: '.v-final-cta-section', footer: '#footer'
  };
  function text(el, selector, value) { const node = el && el.querySelector(selector); if (node && value) node.textContent = value; }
  function applyContent(content) {
      const sections = (content || {}).sections || {};
      Object.keys(sections).forEach(key => {
        const cfg = sections[key], el = document.querySelector(targets[key]);
        if (!el) return;
        if (cfg.enabled === false) { el.style.display = 'none'; return; }
        text(el, 'h1, h2', cfg.heading); text(el, '.v-section-subtitle, .v-hero-subtext, .v-dk-desc', cfg.description);
        if (cfg.image) {
          if (key === 'hero') el.style.backgroundImage = `url("${cfg.image.replace(/"/g, '%22')}")`;
          else { const image = el.querySelector('img'); if (image) image.src = cfg.image; }
        }
        const link = el.querySelector('a.v-btn-primary, a.v-btn-secondary, a.v-btn-tertiary');
        if (link && cfg.cta_text) link.textContent = cfg.cta_text;
        if (link && cfg.cta_url) link.href = cfg.cta_url;
      });
  }
  const preview = sessionStorage.getItem('valluvamHomepageCmsPreview');
  if (preview) {
    sessionStorage.removeItem('valluvamHomepageCmsPreview');
    try { applyContent(JSON.parse(preview)); } catch (_) {}
  } else {
    fetch('assets/db_query/site_content.php', { credentials: 'same-origin' })
      .then(r => r.json()).then(data => applyContent(data.content || {})).catch(() => {});
  }
})();
