(() => {
  'use strict';

  const language = document.getElementById('language');
  if (!language) {
    return;
  }

  language.addEventListener('change', () => {
    window.location.assign(language.dataset.currentPath + '?lang=' + encodeURIComponent(language.value));
  });
})();
