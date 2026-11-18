/* Creative Experience — progressive enhancement only. Without this file every page is complete and navigable.
   1. Project selector: stacked panels → accessible tabs (roving focus, arrows/Home/End).
   2. Scroll-spy for the primary nav (aria-current).
   3. Motion (html.fx) only when allowed: motion setting on, no reduced-motion, no Save-Data, >2 cores,
      fine pointer and a wide screen. Then: hero pointer parallax + a 2D canvas (particles | orbits),
      paused when the tab is hidden or the hero is off-screen, DPR capped at 2. */
(() => {
  'use strict';
  const doc = document, root = doc.documentElement, mm = q => matchMedia(q).matches;
  const nav = navigator, conn = nav.connection || {};

  /* 1. Project selector */
  doc.querySelectorAll('[data-selector]').forEach(list => {
    const tabs = [...list.querySelectorAll('[role="tab"]')];
    const panels = tabs.map(t => doc.getElementById(t.getAttribute('aria-controls')));
    if (!tabs.length || panels.includes(null)) return;
    const show = (i, focus) => {
      tabs.forEach((t, j) => {
        const on = i === j;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        panels[j].hidden = !on;
      });
      if (focus) tabs[i].focus();
    };
    panels.forEach((p, j) => { p.setAttribute('role', 'tabpanel'); p.tabIndex = 0; p.setAttribute('aria-labelledby', tabs[j].id); });
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => show(i));
      t.addEventListener('keydown', e => {
        const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
        const j = step ? (i + step + tabs.length) % tabs.length : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : -1;
        if (j < 0) return;
        e.preventDefault();
        show(j, true);
      });
    });
    list.hidden = false;
    list.parentElement.classList.add('tabbed');
    const fromHash = panels.findIndex(p => '#' + p.id === location.hash);
    show(Math.max(0, fromHash));
    if (fromHash > -1) panels[fromHash].scrollIntoView();
  });

  /* 2. Scroll-spy: mark the nav link of the section in view */
  const links = [...doc.querySelectorAll('.site-nav a[href^="#"]')];
  const targets = links.map(a => doc.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);
  if ('IntersectionObserver' in window && targets.length) {
    const spy = new IntersectionObserver(entries => entries.forEach(e => {
      if (!e.isIntersecting) return;
      links.forEach(a => a.getAttribute('href') === '#' + e.target.id ? a.setAttribute('aria-current', 'true') : a.removeAttribute('aria-current'));
    }), { rootMargin: '-45% 0px -50% 0px' });
    targets.forEach(t => spy.observe(t));
  }

  /* 3. Motion gate */
  const fx = root.dataset.motion === 'on' && !mm('(prefers-reduced-motion: reduce)') && !conn.saveData
    && (nav.hardwareConcurrency || 4) > 2 && !mm('(max-width: 720px), (pointer: coarse)');
  root.classList.add(fx ? 'fx' : 'still');
  const hero = doc.querySelector('.hero');
  if (!fx || !hero) return;

  const ptr = { x: -1e4, y: -1e4, on: false };
  hero.addEventListener('pointermove', e => {
    const r = hero.getBoundingClientRect();
    ptr.x = e.clientX - r.left; ptr.y = e.clientY - r.top; ptr.on = true;
    hero.style.setProperty('--px', (ptr.x / r.width - 0.5).toFixed(3));
    hero.style.setProperty('--py', (ptr.y / r.height - 0.5).toFixed(3));
  }, { passive: true });
  hero.addEventListener('pointerleave', () => { ptr.x = ptr.y = -1e4; ptr.on = false; });

  const cv = hero.querySelector('.hero-canvas');
  const ctx = cv && cv.getContext && cv.getContext('2d');
  if (!ctx) return;

  const mode = cv.dataset.mode;
  let words = [];
  try { words = JSON.parse(cv.dataset.skills || '[]'); } catch (e) { /* decorative only */ }
  const css = getComputedStyle(root), v = n => css.getPropertyValue(n).trim();
  const col = [v('--a1'), v('--a2')], ink = v('--ink');
  const orbit = mode === 'orbits' && words.length > 0;
  let W = 0, H = 0, pts = [], t = 0, raf = 0, shown = !doc.hidden, inView = true, speed = 1, rt;
  const wind = { x: 0, y: 0 };

  const seed = () => {
    const n = Math.min(110, Math.round(W * H / 11000));
    pts = Array.from({ length: n }, (_, i) => {
      const bx = (Math.random() - 0.5) * 0.35, by = (Math.random() - 0.5) * 0.35;
      return { x: Math.random() * W, y: Math.random() * H, vx: bx, vy: by, bx, by, r: 1 + Math.random() * 2.2, c: col[i % 2] };
    });
  };
  const size = () => {
    const dpr = Math.min(window.devicePixelRatio || 1, 2), r = cv.getBoundingClientRect();
    W = r.width; H = r.height;
    cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (!orbit) seed();
  };

  // Particles drift, flee the pointer and link up when close. Arrow keys blow "wind"; other keys send a pulse.
  const particles = () => {
    ctx.clearRect(0, 0, W, H);
    for (const p of pts) {
      const dx = p.x - ptr.x, dy = p.y - ptr.y, d = Math.hypot(dx, dy) || 1;
      if (d < 130) { const f = (1 - d / 130) * 0.5; p.vx += dx / d * f; p.vy += dy / d * f; }
      p.vx += (p.bx - p.vx) * 0.03 + wind.x * 0.04;
      p.vy += (p.by - p.vy) * 0.03 + wind.y * 0.04;
      p.x = (p.x + p.vx + W) % W; p.y = (p.y + p.vy + H) % H;
    }
    ctx.lineWidth = 1;
    ctx.strokeStyle = ink;
    for (let i = 0; i < pts.length; i++) {
      for (let j = i + 1; j < pts.length; j++) {
        const a = pts[i], b = pts[j], d = Math.hypot(a.x - b.x, a.y - b.y);
        if (d < 110) { ctx.globalAlpha = (1 - d / 110) * 0.22; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); }
      }
    }
    ctx.globalAlpha = 0.85;
    for (const p of pts) { ctx.fillStyle = p.c; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill(); }
    ctx.globalAlpha = 1;
    wind.x *= 0.94; wind.y *= 0.94;
  };

  // Top skills orbit a glowing core on tilted rings; the pointer tilts the system, arrows change speed.
  const orbits = () => {
    ctx.clearRect(0, 0, W, H);
    const R = Math.min(W * 0.17, H * 0.3, 260), cx = W - R - 140, cy = Math.min(H * 0.42, 140 + R);
    const tilt = 0.38 + (ptr.on ? (ptr.y / H - 0.5) * 0.3 : 0), spin = ptr.on ? (ptr.x / W - 0.5) * 0.4 : 0;
    const rings = [R * 0.5, R * 0.78, R];
    ctx.strokeStyle = ink; ctx.lineWidth = 1;
    rings.forEach(r => { ctx.globalAlpha = 0.16; ctx.beginPath(); ctx.ellipse(cx, cy, r, r * tilt, spin, 0, 6.2832); ctx.stroke(); });
    const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * 0.32);
    g.addColorStop(0, col[0]); g.addColorStop(1, 'transparent');
    ctx.globalAlpha = 0.5; ctx.fillStyle = g; ctx.beginPath(); ctx.arc(cx, cy, R * 0.32, 0, 6.2832); ctx.fill();
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    words.forEach((w, i) => {
      const k = i % 3, a = t * (0.35 - k * 0.08) * speed + i * 2.39996, r = rings[k];
      const ex = Math.cos(a) * r, ey = Math.sin(a) * r * tilt;
      const x = cx + ex * Math.cos(spin) - ey * Math.sin(spin), y = cy + ex * Math.sin(spin) + ey * Math.cos(spin);
      const depth = (Math.sin(a) + 1) / 2; // 0 = back, 1 = front
      ctx.globalAlpha = 0.35 + depth * 0.65;
      ctx.fillStyle = col[i % 2];
      ctx.beginPath(); ctx.arc(x, y, 3 + depth * 3, 0, 6.2832); ctx.fill();
      ctx.fillStyle = ink;
      ctx.font = `600 ${Math.round(11 + depth * 5)}px system-ui, sans-serif`;
      ctx.fillText(w, x, y - 14 - depth * 4);
    });
    ctx.globalAlpha = 1;
  };

  const draw = orbit ? orbits : particles;
  const loop = () => {
    raf = 0;
    if (!shown || !inView) return;
    t += 0.016;
    draw();
    raf = requestAnimationFrame(loop);
  };
  const go = () => { if (!raf && shown && inView) raf = requestAnimationFrame(loop); };

  doc.addEventListener('visibilitychange', () => { shown = !doc.hidden; go(); });
  if ('IntersectionObserver' in window) new IntersectionObserver(([e]) => { inView = e.isIntersecting; go(); }).observe(hero);
  addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(size, 150); }, { passive: true });
  addEventListener('keydown', e => {
    if (e.altKey || e.ctrlKey || e.metaKey || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
    const dir = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
    if (orbit) { if (dir) speed = Math.max(-3, Math.min(3, speed + (dir[0] || -dir[1]) * 0.5)); return; }
    if (dir) { wind.x += dir[0] * 4; wind.y += dir[1] * 4; return; }
    if (e.key.length !== 1) return; // printable keys only: a gentle pulse from the centre
    for (const p of pts) { const dx = p.x - W / 2, dy = p.y - H / 2, d = Math.hypot(dx, dy) || 1; p.vx += dx / d * 2; p.vy += dy / d * 2; }
  });

  hero.classList.add('live'); // show the canvas first so it has a size to measure
  size();
  go();
})();
