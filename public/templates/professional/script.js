// Professional — progressive enhancements only. The page works fully without this file.
(() => {
  const root = document.documentElement;
  root.classList.add('js');

  // Mobile menu: a real <button aria-expanded aria-controls>; Escape and link clicks close it.
  const button = document.querySelector('.pro-burger');
  const menu = button && document.getElementById(button.getAttribute('aria-controls'));
  if (button && menu) {
    const set = (open) => {
      button.setAttribute('aria-expanded', String(open));
      menu.classList.toggle('is-open', open);
      button.querySelector('use')?.setAttribute('href', open ? '#pro-i-close' : '#pro-i-menu');
    };
    button.addEventListener('click', () => set(button.getAttribute('aria-expanded') !== 'true'));
    menu.addEventListener('click', (e) => { if (e.target.closest('a')) set(false); });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') { set(false); button.focus(); }
    });
    matchMedia('(min-width: 881px)').addEventListener?.('change', (e) => { if (e.matches) set(false); });
  }

  if (!('IntersectionObserver' in window)) return;

  // Current-section highlight in the nav (home page anchors only).
  const links = new Map([...document.querySelectorAll('.pro-menu a[href^="#"]:not(.pro-btn)')].map(a => [a.hash.slice(1), a]));
  if (links.size) {
    const spy = new IntersectionObserver(entries => entries.forEach(e => {
      if (!e.isIntersecting) return;
      links.forEach(a => a.removeAttribute('aria-current'));
      links.get(e.target.id)?.setAttribute('aria-current', 'true');
    }), { rootMargin: '-40% 0px -55% 0px' });
    links.forEach((_, id) => { const s = document.getElementById(id); if (s) spy.observe(s); });
  }

  // Subtle fade-in-up for items below the fold; skipped entirely under reduced motion.
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const items = [...document.querySelectorAll('.pro-reveal')].filter(el => el.getBoundingClientRect().top > innerHeight);
  const show = new IntersectionObserver(entries => entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.remove('is-pending'); show.unobserve(e.target); }
  }), { rootMargin: '0px 0px -8% 0px' });
  items.forEach(el => { el.classList.add('is-pending'); show.observe(el); });
})();
