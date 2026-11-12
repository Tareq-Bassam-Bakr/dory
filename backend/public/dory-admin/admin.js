/* DORY admin dashboard: confirmation before sensitive actions, filters
   that apply as soon as a choice changes, and print buttons. Loaded as a file, so the
   pages run under a strict Content Security Policy (no inline scripts). */
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) { return; }
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    // One submission at a time.
    var button = form.querySelector('button[type="submit"]');
    if (button && form.getAttribute('method') && form.getAttribute('method').toLowerCase() === 'post') {
      window.setTimeout(function () { button.disabled = true; }, 0);
    }
  });

  document.addEventListener('change', function (event) {
    var field = event.target;
    if (field instanceof HTMLSelectElement && field.form && field.form.hasAttribute('data-auto-submit')) {
      field.form.submit();
    }
  });

  // Print buttons (invoice page).
  document.addEventListener('click', function (event) {
    var target = event.target instanceof Element ? event.target.closest('[data-print]') : null;
    if (target) {
      event.preventDefault();
      window.print();
    }
  });
})();
