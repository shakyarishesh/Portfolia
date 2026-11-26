const panels = [...document.querySelectorAll('.panel')];
const links = [...document.querySelectorAll('.tabs a')];
const pill = document.querySelector('.pill');
const bar = document.querySelector('.progress span');
const dots = document.querySelector('.dots');
const DUR = 900;
let idx = 0, busy = false, quietUntil = 0, lastScroll = 0;

// stagger reveals within each parent
document.querySelectorAll('.reveal').forEach(el => {
  const i = [...el.parentElement.children].filter(c => c.classList.contains('reveal')).indexOf(el);
  el.style.setProperty('--d', `${Math.min(i, 8) * 0.08}s`);
});

const io = new IntersectionObserver(entries => {
  for (const e of entries) {
    if (!e.isIntersecting || !e.target.closest('.panel.active')) continue;
    e.target.classList.add('in');
    io.unobserve(e.target);
  }
}, { threshold: 0.15 });

function replay(panel) {
  panel.querySelectorAll('.reveal').forEach(el => { el.classList.remove('in'); io.unobserve(el); io.observe(el); });
}

panels.forEach((p, i) => {
  const b = document.createElement('button');
  b.setAttribute('aria-label', p.id);
  b.onclick = () => go(i);
  dots.append(b);
  p.addEventListener('scroll', () => {
    lastScroll = Date.now();
    if (p.classList.contains('active')) setProgress(p);
  }, { passive: true });
});

function setProgress(p) {
  const max = p.scrollHeight - p.clientHeight;
  bar.style.width = max > 0 ? `${(p.scrollTop / max) * 100}%` : '100%';
}

function syncNav() {
  links.forEach((a, i) => a.classList.toggle('active', i === idx));
  [...dots.children].forEach((d, i) => d.classList.toggle('active', i === idx));
  const a = links[idx];
  pill.style.left = `${a.offsetLeft}px`;
  pill.style.width = `${a.offsetWidth}px`;
  document.body.dataset.section = idx; // read by bg.js
}

function go(next, x, y) {
  if (busy || next === idx || next < 0 || next >= panels.length) return;
  busy = true;
  const from = panels[idx], to = panels[next], dir = next > idx ? 1 : -1;
  for (const p of [from, to]) p.style.setProperty('--dir', dir);
  to.style.setProperty('--cx', x != null ? `${x}px` : '50%');
  to.style.setProperty('--cy', y != null ? `${y}px` : '50%');

  from.classList.remove('active');
  from.classList.add('leaving', 'leave');
  to.scrollTop = 0;
  to.classList.add('active', 'enter');
  replay(to);

  idx = next;
  syncNav();
  setProgress(to);
  history.replaceState(null, '', `#${to.id}`);

  setTimeout(() => {
    from.classList.remove('leaving', 'leave');
    to.classList.remove('enter');
    busy = false;
    quietUntil = Date.now() + 400;
  }, DUR);
}

// wheel past the top/bottom edge of a section moves to the neighbouring tab
let acc = 0, accTimer;
addEventListener('wheel', e => {
  if (document.querySelector('dialog[open]') || e.target.closest?.('.assistant-panel')) return;
  if (busy || Date.now() < quietUntil || Date.now() - lastScroll < 150) return;
  const p = panels[idx];
  const atBottom = p.scrollTop + p.clientHeight >= p.scrollHeight - 2;
  const atTop = p.scrollTop <= 0;
  if (!((e.deltaY > 0 && atBottom) || (e.deltaY < 0 && atTop))) { acc = 0; return; }
  acc += e.deltaY;
  clearTimeout(accTimer);
  accTimer = setTimeout(() => acc = 0, 250);
  if (Math.abs(acc) > 120) { go(idx + Math.sign(acc)); acc = 0; }
}, { passive: true });

let touchY = null;
addEventListener('touchstart', e => touchY = e.touches[0].clientY, { passive: true });
addEventListener('touchend', e => {
  if (touchY == null) return;
  const dy = touchY - e.changedTouches[0].clientY, p = panels[idx];
  if (dy > 70 && p.scrollTop + p.clientHeight >= p.scrollHeight - 2) go(idx + 1);
  if (dy < -70 && p.scrollTop <= 0) go(idx - 1);
  touchY = null;
});

const typing = el => el && (el.closest('input, textarea, select, [contenteditable], dialog[open]') !== null);
addEventListener('keydown', e => {
  if (typing(e.target) || document.querySelector('dialog[open]')) return;
  if (e.key === 'ArrowRight') go(idx + 1);
  if (e.key === 'ArrowLeft') go(idx - 1);
});

document.querySelectorAll('[data-tab]').forEach(a => a.addEventListener('click', e => {
  e.preventDefault();
  go(panels.findIndex(p => p.id === a.dataset.tab), e.clientX, e.clientY);
}));

// typed role (optional: only when phrases are set)
const typed = document.querySelector('.typed');
const words = typed ? typed.dataset.words.split('|') : [];
if (words.length) (function type(w = 0, c = 0, del = false) {
  const word = words[w];
  typed.textContent = word.slice(0, c);
  if (!del && c === word.length) return setTimeout(() => type(w, c, true), 1600);
  if (del && c === 0) return setTimeout(() => type((w + 1) % words.length, 0, false), 250);
  setTimeout(() => type(w, c + (del ? -1 : 1), del), del ? 35 : 70);
})();

// theme toggle with a circular reveal from the button
const root = document.documentElement;
const toggle = document.querySelector('.theme-toggle');
const prefersLight = matchMedia('(prefers-color-scheme: light)');
const isDark = () => (root.dataset.theme || (prefersLight.matches ? 'light' : 'dark')) === 'dark';
const syncToggle = () => toggle.toggleAttribute('data-dark', isDark());
prefersLight.addEventListener('change', syncToggle);
syncToggle();
toggle.addEventListener('click', () => {
  const next = isDark() ? 'light' : 'dark';
  const apply = () => {
    root.dataset.theme = next;
    syncToggle();
    try { localStorage.setItem('theme', next); } catch (e) {}
  };
  if (!document.startViewTransition || matchMedia('(prefers-reduced-motion: reduce)').matches) return apply();
  const r = toggle.getBoundingClientRect(), x = r.left + r.width / 2, y = r.top + r.height / 2;
  const end = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
  document.startViewTransition(apply).ready.then(() => root.animate(
    { clipPath: [`circle(0 at ${x}px ${y}px)`, `circle(${end}px at ${x}px ${y}px)`] },
    { duration: 700, easing: 'cubic-bezier(.7, 0, .2, 1)', pseudoElement: '::view-transition-new(root)' }
  ));
});

// cursor (only when the "Neon ring & dot" cursor setting renders these elements): dot, lagging ring, glow, click burst
const glow = document.querySelector('.cursor-glow');
const ring = document.querySelector('.cursor-ring');
const dot = document.querySelector('.cursor-dot');
const fine = matchMedia('(pointer: fine)').matches;
if (fine && ring) {
  root.classList.add('fx-cursor', 'cursor-out');
  let mx = -999, my = -999, rx = mx, ry = my, gx = mx, gy = my;
  addEventListener('mousemove', e => {
    mx = e.clientX; my = e.clientY;
    root.classList.remove('cursor-out');
    dot.style.translate = `${mx}px ${my}px`;
  });
  (function follow() {
    rx += (mx - rx) * 0.4; ry += (my - ry) * 0.4;
    gx += (mx - gx) * 0.18; gy += (my - gy) * 0.18;
    ring.style.translate = `${rx}px ${ry}px`;
    glow.style.translate = `${gx}px ${gy}px`;
    requestAnimationFrame(follow);
  })();
  document.addEventListener('mouseover', e => ring.classList.toggle('hover', !!e.target.closest('a, button, summary, .card')));
  document.addEventListener('mouseleave', () => root.classList.add('cursor-out'));
  addEventListener('mousedown', e => {
    ring.classList.add('down');
    const b = document.createElement('span');
    b.className = 'burst';
    b.style.left = `${e.clientX}px`;
    b.style.top = `${e.clientY}px`;
    b.addEventListener('animationend', () => b.remove());
    document.body.append(b);
  });
  addEventListener('mouseup', () => ring.classList.remove('down'));
}
// card tilt + spotlight, magnetic buttons
if (fine) {
  document.querySelectorAll('.tilt').forEach(el => {
    el.addEventListener('mousemove', e => {
      const r = el.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      el.style.transform = `perspective(800px) rotateX(${(0.5 - py) * 8}deg) rotateY(${(px - 0.5) * 8}deg) translateY(-4px)`;
      el.style.setProperty('--mx', `${px * 100}%`);
      el.style.setProperty('--my', `${py * 100}%`);
    });
    el.addEventListener('mouseleave', () => el.style.transform = '');
  });
  document.querySelectorAll('.magnetic').forEach(el => {
    el.addEventListener('mousemove', e => {
      const r = el.getBoundingClientRect();
      el.style.translate = `${(e.clientX - r.left - r.width / 2) * 0.25}px ${(e.clientY - r.top - r.height / 2) * 0.35}px`;
    });
    el.addEventListener('mouseleave', () => el.style.translate = '');
  });
}

// highlight filters (project cards have no data-cat and are never hidden)
const cards = document.querySelectorAll('.card[data-cat]');
const hlChips = document.querySelectorAll('[data-filter-group="highlights"] .chip');
hlChips.forEach(chip => chip.addEventListener('click', () => {
  hlChips.forEach(c => { c.classList.toggle('active', c === chip); c.setAttribute('aria-pressed', c === chip); });
  const f = chip.dataset.filter;
  cards.forEach(card => {
    card.classList.toggle('hide', f !== 'all' && card.dataset.cat !== f);
    card.classList.remove('in');
    io.unobserve(card);
    io.observe(card);
  });
}));

// contact form: loading state while it submits
document.querySelector('.contact-form')?.addEventListener('submit', e => {
  const b = e.target.querySelector('[type=submit]');
  b.classList.add('loading');
  b.textContent = b.dataset.loading || b.textContent;
});

// initial tab from hash
const start = Math.max(0, panels.findIndex(p => `#${p.id}` === location.hash));
idx = start;
panels[idx].classList.add('active');
replay(panels[idx]);
syncNav();
setProgress(panels[idx]);
addEventListener('resize', syncNav);
document.fonts?.ready.then(syncNav);

// first visit: "welcome" in many languages, starting with Nepali, then the screen lifts
if (root.classList.contains('intro')) {
  const screen = document.querySelector('.intro-screen'), word = screen.firstElementChild;
  // words come from the portfolio's "Welcome words" setting (config portfolio.intro_words by default)
  const hellos = JSON.parse(screen.dataset.words || '["Welcome"]');
  const FIRST = 1300, STEP = 280, LAST = 700;
  busy = true;
  hellos.forEach((w, i) => setTimeout(() => {
    word.textContent = w;
    word.style.animation = 'none';
    word.offsetWidth; // restart the fade-in
    word.style.animation = '';
  }, i && FIRST + (i - 1) * STEP));
  setTimeout(() => {
    screen.classList.add('done');
    replay(panels[idx]);
    try { localStorage.setItem(screen.dataset.seenKey, '1'); } catch (e) {}
    setTimeout(() => { root.classList.remove('intro'); busy = false; }, 900);
  }, FIRST + Math.max(0, hellos.length - 2) * STEP + LAST);
}


// ---------- project filters: search, category, technology, featured, sort, show more ----------
const pTools = document.querySelector('.project-tools');
const pGrid = document.querySelector('.cards.projects');
if (pGrid) {
  const pCards = [...pGrid.querySelectorAll('.card.project')];
  const more = pGrid.parentElement.querySelector('.btn.more');
  const empty = pGrid.parentElement.querySelector('.empty-state');
  const size = +pGrid.dataset.pageSize || 6;
  let limit = size;
  const val = n => pTools?.elements[n]?.type === 'checkbox' ? pTools.elements[n].checked : (pTools?.elements[n]?.value || '');
  function applyProjects() {
    const q = val('q').trim().toLowerCase(), cat = val('category'), tech = val('tech'), featured = val('featured'), sort = val('sort');
    const sorted = [...pCards].sort((a, b) =>
      sort === 'name' ? a.dataset.name.localeCompare(b.dataset.name)
      : sort === 'newest' ? (b.dataset.start || '').localeCompare(a.dataset.start || '')
      : (b.dataset.featured - a.dataset.featured) || (a.dataset.order - b.dataset.order));
    let shown = 0, matches = 0;
    for (const c of sorted) {
      const ok = (!q || c.dataset.name.includes(q) || c.dataset.techs.includes(q))
        && (!cat || c.dataset.category === cat)
        && (!tech || c.dataset.techs.includes(`|${tech}|`))
        && (!featured || c.dataset.featured === '1');
      if (ok) matches++;
      const visible = ok && shown < limit;
      if (visible) shown++;
      c.classList.toggle('hide', !visible);
      pGrid.append(c); // re-append in sorted order
      if (visible) c.classList.add('in');
    }
    empty && (empty.hidden = matches > 0);
    more && (more.hidden = matches <= limit);
  }
  pTools?.addEventListener('input', () => { limit = size; applyProjects(); });
  more?.addEventListener('click', () => { limit += size; applyProjects(); });
  empty?.querySelector('[data-reset-filters]')?.addEventListener('click', () => { pTools?.reset(); limit = size; applyProjects(); });
  applyProjects();

  // any technology button (skills section, project cards) filters the project list
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-tech]');
    if (!b) return;
    e.preventDefault();
    const sel = pTools?.elements.tech;
    if (sel && [...sel.options].some(o => o.value === b.dataset.tech)) {
      pTools.reset();
      sel.value = b.dataset.tech;
      limit = size;
      applyProjects();
    }
    const target = panels.findIndex(p => p.id === 'projects');
    if (target >= 0 && target !== idx) go(target);
    else panels[idx].scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ---------- accent colour ----------
document.querySelectorAll('.accent-menu [data-accent]').forEach(b => b.addEventListener('click', () => {
  const a = b.dataset.accent;
  if (a === 'violet') delete root.dataset.accent; else root.dataset.accent = a;
  try { a === 'violet' ? localStorage.removeItem('accent') : localStorage.setItem('accent', a); } catch (e) {}
  b.closest('details').open = false;
}));
document.addEventListener('click', e => {
  const d = document.querySelector('.accent-picker[open]');
  if (d && !d.contains(e.target)) d.open = false;
});

// ---------- interactive terminal (reads the public /api/portfolio JSON; never runs anything server-side) ----------
const term = document.querySelector('dialog.terminal');
if (term) {
  const out = term.querySelector('.term-out'), form = term.querySelector('form'), input = form.elements.cmd;
  const hist = []; let hIdx = 0, data = null;
  const print = (text = '', cls = '') => {
    const line = document.createElement('div');
    if (cls) line.className = cls;
    line.textContent = text;
    out.append(line);
    out.scrollTop = out.scrollHeight;
  };
  const link = (label, href) => {
    const line = document.createElement('div'), a = document.createElement('a');
    a.href = href; a.textContent = label;
    if (/^https?:/.test(href) && !href.startsWith(location.origin)) { a.target = '_blank'; a.rel = 'noopener'; }
    line.append(a); out.append(line); out.scrollTop = out.scrollHeight;
  };
  async function load() {
    if (data) return data;
    const res = await fetch(term.dataset.api, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return data = await res.json();
  }
  const openUrl = (platform) => {
    const l = data.links.find(x => x.platform === platform);
    if (!l) return print(`No ${platform} link is published.`, 'err');
    print(`Opening ${l.url} …`); window.open(l.url, '_blank', 'noopener');
  };
  const commands = {
    help: () => {
      [['about', 'who I am'], ['skills', 'technical skills by category'], ['projects', 'list projects'], ['project <n|name>', 'open a project case study'],
       ['experience', 'work history'], ['education', 'academic background'], ['contact', 'how to reach me'], ['github / linkedin', 'open profiles'],
       ['resume', 'download my CV'], ['blog', 'open the engineering journal'], ['theme [light|dark]', 'switch colour theme'], ['clear', 'clear the screen'], ['exit', 'close the terminal']]
        .forEach(([c, d]) => print(`  ${c.padEnd(20)} ${d}`));
    },
    about: () => { const p = data.profile; print(`${p.name} — ${p.headline}`, 'hl'); if (p.location) print(p.location); print(); (p.about.length ? p.about : [p.intro || '']).forEach(t => print(t.replace(/\*\*|`/g, ''))); },
    whoami: () => print('guest — but you can get in touch with `contact`.'),
    skills: () => data.skills.forEach(c => print(`${c.category}: ${c.skills.map(s => s.name).join(', ')}`)),
    projects: () => { data.projects.forEach((p, i) => print(`  [${i + 1}] ${p.name}${p.tagline ? ' — ' + p.tagline : ''}`)); print('Type `project <number>` to open one.', 'dim'); },
    project: (arg) => {
      const p = data.projects[+arg - 1] || data.projects.find(x => x.slug === arg || x.name.toLowerCase().includes((arg || '').toLowerCase()));
      if (!arg || !p) return print('Usage: project <number|name>. See `projects`.', 'err');
      print(p.name, 'hl'); [p.period, p.company, p.role].filter(Boolean).forEach(t => print(t));
      if (p.technologies.length) print('Stack: ' + p.technologies.join(', '));
      link('Read the case study →', p.url);
    },
    experience: () => data.experience.forEach(e => { print(`${e.role} @ ${e.company}`, 'hl'); print(`  ${e.start || ''} → ${e.current ? 'present' : (e.end || '')}${e.location ? ' · ' + e.location : ''}`); }),
    education: () => data.education.length ? data.education.forEach(e => print(`${e.degree}${e.field ? ', ' + e.field : ''} — ${e.institution} (${[e.start_year, e.end_year].filter(Boolean).join('–')})`)) : print('No education entries published.'),
    contact: () => { const p = data.profile; if (p.email) link(p.email, 'mailto:' + p.email); if (p.phone) print(p.phone); data.links.forEach(l => link(`${l.label}: ${l.url}`, l.url)); print('Or use the contact form: type `exit` and open the Contact tab.', 'dim'); },
    github: () => openUrl('github'),
    linkedin: () => openUrl('linkedin'),
    resume: () => data.profile.resume ? (print('Downloading CV…'), location.assign(data.profile.resume)) : print('No CV is published yet.', 'err'),
    blog: () => location.assign('/blog'),
    theme: (arg) => { const t = arg || (root.dataset.theme === 'light' ? 'dark' : 'light'); if (!['light', 'dark'].includes(t)) return print('Usage: theme [light|dark]', 'err'); root.dataset.theme = t; try { localStorage.setItem('theme', t); } catch (e) {} toggle && syncToggle(); print(`Theme: ${t}`); },
    clear: () => out.replaceChildren(),
    exit: () => term.close(),
    sudo: () => print('Nice try. This terminal only reads public portfolio data.', 'err'),
  };
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const raw = input.value.trim().slice(0, 100);
    input.value = '';
    print(`$ ${raw}`, 'cmd');
    if (!raw) return;
    hist.push(raw); hIdx = hist.length;
    const [name, ...rest] = raw.split(/\s+/);
    const fn = commands[name.toLowerCase()];
    if (!fn) return print(`command not found: ${name}. Type \`help\`.`, 'err');
    try { await load(); fn(rest.join(' ')); } catch (err) { print('Could not load portfolio data. Check your connection and try again.', 'err'); }
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'ArrowUp' && hIdx > 0) { input.value = hist[--hIdx]; e.preventDefault(); }
    if (e.key === 'ArrowDown') { hIdx = Math.min(hist.length, hIdx + 1); input.value = hist[hIdx] || ''; e.preventDefault(); }
    if (e.key === 'l' && e.ctrlKey) { out.replaceChildren(); e.preventDefault(); }
  });
  const open = () => {
    if (term.open) return;
    term.showModal();
    if (!out.childElementCount) { print('Welcome. This terminal browses public portfolio content — nothing runs on the server.', 'dim'); print('Type `help` to see the commands.', 'dim'); }
    input.focus();
  };
  document.querySelectorAll('[data-terminal-open]').forEach(b => b.addEventListener('click', open));
  term.querySelector('.term-close').addEventListener('click', () => term.close());
  term.addEventListener('click', e => { if (e.target === term) term.close(); });
  addEventListener('keydown', e => { if (e.key === '`' && !typing(e.target) && !term.open) { e.preventDefault(); open(); } });
}

// ---------- portfolio assistant (only rendered when the owner enabled it) ----------
const asst = document.querySelector('.assistant');
if (asst) {
  const btn = asst.querySelector('.assistant-toggle'), panel = asst.querySelector('.assistant-panel'), log = asst.querySelector('.assistant-log'), aForm = asst.querySelector('form');
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const history = [];
  const say = (text, who) => { const p = document.createElement('p'); p.className = who; p.textContent = text; log.append(p); log.scrollTop = log.scrollHeight; return p; };
  btn.addEventListener('click', () => {
    const openNow = panel.hidden;
    panel.hidden = !openNow; btn.setAttribute('aria-expanded', openNow);
    if (openNow) aForm.elements.question.focus();
  });
  aForm.addEventListener('submit', async e => {
    e.preventDefault();
    const q = aForm.elements.question.value.trim();
    if (!q) return;
    aForm.elements.question.value = '';
    say(q, 'me');
    const pending = say('Thinking…', 'bot pending');
    aForm.querySelector('button').disabled = true;
    try {
      const res = await fetch(asst.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ question: q, history: history.slice(-6) }) });
      const json = await res.json().catch(() => ({}));
      const answer = res.ok ? json.answer : (res.status === 429 ? "You've asked a lot of questions — please try again in a little while." : (json.message || 'Something went wrong. Please use the contact form instead.'));
      pending.textContent = answer; pending.classList.remove('pending');
      if (res.ok) history.push({ role: 'user', text: q }, { role: 'assistant', text: answer });
    } catch (err) {
      pending.textContent = 'Network error — please try again.'; pending.classList.remove('pending');
    } finally {
      aForm.querySelector('button').disabled = false;
    }
  });
}
