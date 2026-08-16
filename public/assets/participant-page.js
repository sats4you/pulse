(() => {
  'use strict';

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('form[data-event-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const start = form.elements.starts_at?.value || '';
      const end = form.elements.ends_at?.value || '';
      if (start && end && end <= start) {
        event.preventDefault();
        window.alert(form.dataset.timingError);
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
