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
