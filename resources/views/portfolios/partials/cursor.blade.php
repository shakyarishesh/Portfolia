{{--
  "Glowing trail" cursor effect (setting `cursor` = trail), shared by every template: a neon ribbon that follows the
  pointer and fades out. Canvas on top of the page that ignores the pointer; mouse/trackpad only; off for reduced
  motion; the animation loop only runs while there is a trail to draw.
--}}
@if (($settings['cursor'] ?? null) === 'trail')
@php
    $rgb = ['electric' => '56, 189, 248', 'violet' => '167, 139, 250', 'pink' => '244, 114, 182', 'green' => '74, 222, 128', 'amber' => '251, 191, 36'][$settings['cursor_color'] ?? 'electric'] ?? '56, 189, 248';
@endphp
<canvas id="pf-cursor-trail" aria-hidden="true" style="position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:2147481000"></canvas>
<script>
(() => {
  const canvas = document.getElementById('pf-cursor-trail');
  if (!matchMedia('(pointer: fine)').matches || matchMedia('(prefers-reduced-motion: reduce)').matches) return canvas.remove();
  const ctx = canvas.getContext('2d'), RGB = @json($rgb), LIFE = 800; // ms a point stays on the trail
  let pts = [], raf = 0, dpr = 1, light = false;
  // On light pages a white core and additive glow wash out: draw the core in the trail colour instead.
  const isLight = () => {
    const m = getComputedStyle(document.body).backgroundColor.match(/\d+(\.\d+)?/g) || [];
    return m.length >= 3 && (m[3] === undefined || +m[3] > 0.5) && (0.2126 * m[0] + 0.7152 * m[1] + 0.0722 * m[2]) / 255 > 0.6;
  };
  const size = () => {
    dpr = Math.min(devicePixelRatio || 1, 2);
    canvas.width = innerWidth * dpr;
    canvas.height = innerHeight * dpr;
  };
  size();
  addEventListener('resize', size);

  const mid = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
  const draw = now => {
    pts = pts.filter(p => now - p.t < LIFE);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, innerWidth, innerHeight);
    ctx.lineCap = 'round';
    ctx.globalCompositeOperation = light ? 'source-over' : 'lighter';
    // Each segment is a smoothed curve (midpoint to midpoint) drawn three times: wide glow, coloured body, white core.
    for (let i = 1; i < pts.length; i++) {
      const a = pts[i - 1], b = pts[i], from = mid(pts[i - 2] || a, a), to = mid(a, b);
      const life = Math.max(0, 1 - (now - b.t) / LIFE), taper = i / pts.length;
      const k = life * (0.35 + 0.65 * taper); // fresher and closer to the pointer = wider, brighter
      ctx.beginPath();
      ctx.moveTo(from.x, from.y);
      ctx.quadraticCurveTo(a.x, a.y, to.x, to.y);
      ctx.shadowBlur = 0;
      ctx.strokeStyle = `rgba(${RGB}, ${(light ? 0.12 : 0.2) * k})`; ctx.lineWidth = 22 * k; ctx.stroke();
      ctx.shadowBlur = 18; ctx.shadowColor = `rgba(${RGB}, ${k})`;
      ctx.strokeStyle = `rgba(${RGB}, ${0.95 * k})`; ctx.lineWidth = 5 * k; ctx.stroke();
      ctx.shadowBlur = 0;
      ctx.strokeStyle = light ? `rgba(${RGB}, ${k})` : `rgba(255, 255, 255, ${0.95 * k})`; ctx.lineWidth = 2 * k; ctx.stroke();
    }
    raf = pts.length > 1 ? requestAnimationFrame(draw) : 0;
    if (!raf) ctx.clearRect(0, 0, innerWidth, innerHeight);
  };

  addEventListener('pointermove', e => {
    if (e.pointerType !== 'mouse' && e.pointerType !== 'pen') return;
    // Coalesced events give the in-between positions of fast moves, so curves stay smooth.
    for (const c of e.getCoalescedEvents?.() || [e]) pts.push({ x: c.clientX, y: c.clientY, t: performance.now() });
    if (pts.length > 120) pts = pts.slice(-120);
    if (!raf) { light = isLight(); raf = requestAnimationFrame(draw); } // re-checked per stroke: themes can be toggled
  }, { passive: true });
  document.addEventListener('mouseleave', () => { pts = []; });
})();
</script>
@endif
