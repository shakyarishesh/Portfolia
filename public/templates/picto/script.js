// Picto — scroll-spy for the icon nav. Optional: the page works fully without this file.
(() => {
  const links = new Map([...document.querySelectorAll('.pc-nav [data-nav]')].map(a => [a.dataset.nav, a]));
  if (!links.size || !('IntersectionObserver' in window)) return;
  const spy = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    links.forEach(a => a.removeAttribute('aria-current'));
    const link = links.get(e.target.id);
    link.setAttribute('aria-current', 'location');
    // Keep the active item visible in the scrollable mobile bar.
    const bar = link.closest('ul');
    if (bar.scrollWidth > bar.clientWidth) bar.scrollLeft = link.parentElement.offsetLeft - (bar.clientWidth - link.offsetWidth) / 2;
  }), { rootMargin: '-40% 0px -55% 0px' });
  links.forEach((_, id) => { const s = document.getElementById(id); if (s) spy.observe(s); });
})();
