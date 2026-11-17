// Confirm destructive actions, show a loading state on submit, toggle the mobile sidebar.
document.addEventListener('submit', e => {
  const form = e.target;
  const message = form.dataset.confirm;
  if (message && !confirm(message)) { e.preventDefault(); return; }
  const button = e.submitter || form.querySelector('[type=submit]');
  if (button) {
    button.classList.add('loading');
    if (button.dataset.loading) button.textContent = button.dataset.loading;
    // Restore if the browser keeps the page (back/forward cache) or validation stops navigation.
    setTimeout(() => button.classList.remove('loading'), 15000);
  }
});

document.addEventListener('click', e => {
  const toggle = e.target.closest('[data-toggle-sidebar]');
  if (toggle) document.querySelector('.sidebar').classList.toggle('open');
  const selectAll = e.target.closest('[data-select-all]');
  if (selectAll) {
    const boxes = [...document.querySelectorAll(selectAll.dataset.selectAll)];
    const limit = +selectAll.dataset.limit || boxes.length;
    const allOn = boxes.slice(0, limit).every(b => b.checked);
    boxes.forEach((b, i) => b.checked = !allOn && i < limit);
  }
});

// Import form: switch between file upload and paste.
document.querySelectorAll('[data-mode-switch]').forEach(group => {
  const sync = () => {
    const mode = group.querySelector('input[name=mode]:checked')?.value;
    document.querySelectorAll('[data-mode]').forEach(el => el.hidden = el.dataset.mode !== mode);
  };
  group.addEventListener('change', sync);
  sync();
});
