(() => {
  'use strict';

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  const language = document.getElementById('language');
  if (!language) {
    return;
  }

  language.addEventListener('change', () => {
    window.location.assign(language.dataset.currentPath + '?lang=' + encodeURIComponent(language.value));
  });
})();
