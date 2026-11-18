// Content Journal: light/dark toggle. The page is fully usable without this file.
(function () {
  var root = document.documentElement;
  var btn = document.querySelector('.cj-theme');
  if (!btn) return;
  var dark = function () {
    var t = root.getAttribute('data-theme') || root.getAttribute('data-default');
    return t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  };
  var sync = function () { btn.setAttribute('aria-pressed', String(dark())); };
  btn.hidden = false;
  sync();
  btn.addEventListener('click', function () {
    var next = dark() ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('cj-theme', next); } catch (e) {}
    sync();
  });
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', sync);
})();
