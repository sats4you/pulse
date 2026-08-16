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
      const valueFor = (name) => {
        const legacy = form.elements[name]?.value || '';
        if (legacy) {
          return legacy;
        }
        const date = form.elements[name + '_date']?.value || '';
        const hour = form.elements[name + '_hour']?.value || '';
        const minute = form.elements[name + '_minute']?.value || '';
        return date ? date + 'T' + hour + ':' + minute : '';
      };
      const start = valueFor('starts_at');
      const end = valueFor('ends_at');
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
