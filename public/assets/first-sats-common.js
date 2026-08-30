(() => {
  'use strict';
  const language = document.querySelector('[data-language]');
  if (!language) return;
  language.addEventListener('change', () => {
    const url = new URL(location.href);
    url.searchParams.set('lang', language.value);
    location.assign(url.toString());
  });
})();
