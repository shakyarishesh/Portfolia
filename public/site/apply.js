// /create: add and remove repeatable rows. Without JS the form still submits the rows rendered by the server.
document.querySelectorAll('[data-group]').forEach(group => {
  const list = group.querySelector('[data-rows]');
  const template = group.querySelector('[data-row-template]');
  const add = group.querySelector('[data-add]');
  let next = +list.dataset.next;
  const sync = () => add.disabled = list.children.length >= +list.dataset.max;

  add.hidden = false;
  list.querySelectorAll('[data-remove]').forEach(b => b.hidden = false);
  sync();

  add.addEventListener('click', () => {
    list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', next++));
    const row = list.lastElementChild;
    row.querySelector('[data-remove]').hidden = false;
    row.querySelector('input, select, textarea').focus();
    sync();
  });

  list.addEventListener('click', e => {
    const remove = e.target.closest('[data-remove]');
    if (!remove) return;
    remove.closest('[data-row]').remove();
    sync();
    add.focus();
  });
});

// Bring the error summary into view and focus so screen readers announce it.
document.querySelector('.error-summary')?.focus();

// End date "Present": while ticked the date is disabled (not submitted). Delegated so rows added later work too.
const syncPresent = box => {
  const date = box.closest('.field').querySelector('input[type=date]');
  if (date) date.disabled = box.checked;
};
document.querySelectorAll('[data-present]').forEach(syncPresent);
document.addEventListener('change', e => { if (e.target.matches('[data-present]')) syncPresent(e.target); });

// Step by step: one part of the form at a time, with Back / Continue and the progress list kept in sync.
// Without JavaScript every part shows on one page and the server validates as usual.
(() => {
  const root = document.querySelector('[data-stepper]');
  if (!root) return;
  const order = ['basics', 'template', 'details', 'review'];
  const nav = root.querySelector('[data-step-nav]');
  const back = nav.querySelector('[data-step-back]');
  const next = nav.querySelector('[data-step-next]');
  const parts = step => root.querySelectorAll(`[data-step="${step}"]`);
  let at = 0;

  const show = (i, focus) => {
    at = i;
    order.forEach((step, n) => parts(step).forEach(el => { el.hidden = n !== i; }));
    root.querySelectorAll('[data-progress-step]').forEach(li => {
      const n = order.indexOf(li.dataset.progressStep);
      if (n === -1) return; // "Portfolio type" is always done here
      li.classList.toggle('done', n < i);
      n === i ? li.setAttribute('aria-current', 'step') : li.removeAttribute('aria-current');
    });
    back.textContent = i === 0 ? 'Change type' : 'Back';
    next.hidden = i === order.length - 1;
    if (focus) {
      const first = parts(order[i])[0];
      first.scrollIntoView({ block: 'start' });
      (first.querySelector('legend, h2') || first).setAttribute('tabindex', '-1');
      (first.querySelector('legend, h2') || first).focus({ preventScroll: true });
    }
  };

  // The browser's own checks for the fields of this step (required, email, url, pattern …).
  const valid = () => {
    for (const el of root.querySelector('#apply-form').elements) {
      if (el.closest('[data-step]')?.dataset.step !== order[at]) continue;
      if (!el.disabled && !el.checkValidity()) { el.reportValidity(); return false; }
    }
    return true;
  };

  next.addEventListener('click', () => { if (valid()) show(at + 1, true); });
  back.addEventListener('click', () => {
    if (at === 0) location.href = root.querySelector('[data-progress-step="type"] a')?.href || '/create';
    else show(at - 1, true);
  });

  // After a failed submit, open the first step that has an error.
  const error = root.querySelector('#apply-form .error, #apply-form [aria-invalid=true]');
  const start = error ? Math.max(0, order.indexOf(error.closest('[data-step]')?.dataset.step)) : 0;
  nav.hidden = false;
  // A pending browser check in a hidden step would block submit silently: send the visitor to it.
  root.querySelector('#apply-form').addEventListener('invalid', e => {
    const step = order.indexOf(e.target.closest('[data-step]')?.dataset.step);
    if (step !== -1 && step !== at) show(step, false);
  }, true);
  show(start, false);
})();

// Slow submits (reading a CV): disable the button so a second click doesn't send it again.
document.querySelectorAll('button[data-loading]').forEach(b => b.form.addEventListener('submit', () => {
  b.disabled = true;
  b.textContent = b.dataset.loading;
}));

// Template / CV tabs above the form. The server marks the open tab (the CV one after a CV error).
document.querySelectorAll('[data-tabs]').forEach(list => {
  const tabs = [...list.querySelectorAll('[role=tab]')];
  const select = tab => tabs.forEach(t => {
    const on = t === tab;
    t.setAttribute('aria-selected', on);
    t.tabIndex = on ? 0 : -1;
    document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
  });
  list.hidden = false;
  select(tabs.find(t => t.getAttribute('aria-selected') === 'true') || tabs[0]);
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => select(t));
    t.addEventListener('keydown', e => {
      const step = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
      if (!step) return;
      const next = tabs[(i + step + tabs.length) % tabs.length];
      select(next);
      next.focus();
    });
  });
});
