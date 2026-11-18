// Gradient — optional enhancements. The page works fully without this file.
(() => {
  if (!('IntersectionObserver' in window)) return;

  // Scroll-spy: mark the header link (and the Contact CTA) for the section in view.
  const links = [...document.querySelectorAll('[data-spy]')];
  const targets = [...new Set(links.map(a => a.dataset.spy))].map(id => document.getElementById(id)).filter(Boolean);
  if (targets.length) {
    const spy = new IntersectionObserver(entries => entries.forEach(e => {
      if (!e.isIntersecting) return;
      links.forEach(a => a.dataset.spy === e.target.id ? a.setAttribute('aria-current', 'location') : a.removeAttribute('aria-current'));
    }), { rootMargin: '-30% 0px -65% 0px' });
    targets.forEach(t => spy.observe(t));
  }

  // Gentle reveal for cards below the fold; skipped for reduced-motion users.
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const items = [...document.querySelectorAll('.reveal')].filter(el => el.getBoundingClientRect().top > innerHeight);
  const show = new IntersectionObserver(entries => entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.remove('is-pending'); show.unobserve(e.target); }
  }), { rootMargin: '0px 0px -8% 0px' });
  items.forEach(el => { el.classList.add('is-pending'); show.observe(el); });
})();
