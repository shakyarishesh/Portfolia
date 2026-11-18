// Minimal Editorial — optional enhancements. The page works fully without this file.
(() => {
  if (!('IntersectionObserver' in window)) return;

  // Active-section indicator in the sticky nav.
  const links = new Map([...document.querySelectorAll('[data-nav]')].map(a => [a.dataset.nav, a]));
  if (links.size) {
    const spy = new IntersectionObserver(entries => entries.forEach(e => {
      if (!e.isIntersecting) return;
      links.forEach(a => a.removeAttribute('aria-current'));
      links.get(e.target.id)?.setAttribute('aria-current', 'location');
    }), { rootMargin: '-35% 0px -60% 0px' });
    links.forEach((_, id) => { const s = document.getElementById(id); if (s) spy.observe(s); });
  }

  // Gentle reveal for items below the fold; skipped entirely for reduced-motion users.
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const items = [...document.querySelectorAll('.reveal')].filter(el => el.getBoundingClientRect().top > innerHeight);
  const show = new IntersectionObserver(entries => entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.remove('is-pending'); show.unobserve(e.target); }
  }), { rootMargin: '0px 0px -8% 0px' });
  items.forEach(el => { el.classList.add('is-pending'); show.observe(el); });
})();
