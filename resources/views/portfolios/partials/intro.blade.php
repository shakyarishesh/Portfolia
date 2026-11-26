{{--
  First-visit welcome: the portfolio's "Welcome words" one after another, then the screen lifts. Shared by every
  template except classic (which has its own in public/assets/main.js). Self-styled: takes the page's background,
  text colour and font. Once per browser per portfolio; skipped for reduced motion; click or any key skips it.
--}}
@if (($settings['intro'] ?? false) && empty($demo) && request()->routeIs('home'))
<div id="pf-intro" hidden aria-hidden="true" style="position:fixed;inset:0;z-index:2147482000;display:grid;place-items:center;cursor:pointer;clip-path:inset(0);transition:clip-path .9s cubic-bezier(.7,0,.2,1)"><span style="font-size:clamp(2.6rem,10vw,6rem);font-weight:700;letter-spacing:-.02em;line-height:1.1;padding:.1em;text-align:center"></span></div>
<script>
(() => {
  const el = document.getElementById('pf-intro'), word = el.firstElementChild;
  const key = @json('seenIntro:'.$portfolio->username), words = @json(\App\Support\Templates::introWords($settings));
  try {
    if (localStorage.getItem(key) || matchMedia('(prefers-reduced-motion: reduce)').matches) return el.remove();
  } catch (e) { return el.remove(); }

  // Runs before the rest of the page is parsed, so the overlay covers it from the first paint.
  const css = getComputedStyle(document.body), bg = c => c && c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent' ? c : null;
  el.style.background = bg(css.backgroundColor) || bg(getComputedStyle(document.documentElement).backgroundColor) || '#fff';
  el.style.color = css.color;
  el.hidden = false;
  document.documentElement.style.overflow = 'hidden';

  const FIRST = 1300, STEP = 280, LAST = 700, timers = [];
  const show = w => { word.textContent = w; word.animate?.([{ opacity: 0, transform: 'translateY(.25em)' }, { opacity: 1, transform: 'none' }], { duration: 400, easing: 'ease-out' }); };
  let done = false;
  const finish = () => {
    if (done) return;
    done = true;
    timers.forEach(clearTimeout);
    try { localStorage.setItem(key, '1'); } catch (e) {}
    el.style.clipPath = 'inset(0 0 100% 0)';
    document.documentElement.style.overflow = '';
    setTimeout(() => el.remove(), 900);
    removeEventListener('keydown', finish);
  };
  words.forEach((w, i) => timers.push(setTimeout(() => show(w), i && FIRST + (i - 1) * STEP)));
  timers.push(setTimeout(finish, FIRST + Math.max(0, words.length - 2) * STEP + LAST));
  el.addEventListener('click', finish);
  addEventListener('keydown', finish);
})();
</script>
@endif
