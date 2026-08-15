(() => {
  'use strict';

  const app = document.getElementById('pulse-app');
  const stateTitle = document.getElementById('state-title');
  const stateCopy = document.getElementById('state-copy');
  const continueLink = document.getElementById('continue');
  const language = document.getElementById('language');
  const config = JSON.parse(app.dataset.pulseConfig);

  language.addEventListener('change', () => {
    if (!continueLink.hidden) {
      window.location.assign(config.events + '?lang=' + encodeURIComponent(language.value));
      return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('lang', language.value);
    window.location.assign(url.toString());
  });

  const secret = window.location.hash.startsWith('#')
    ? window.location.hash.slice(1)
    : '';

  if (!secret) {
    stateTitle.textContent = config.incomplete;
    stateCopy.textContent = '';
    return;
  }

  fetch(config.exchange, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ slug: config.slug, secret })
  }).then((response) => {
    if (!response.ok) {
      throw new Error('access_denied');
    }

    history.replaceState(null, '', window.location.pathname + window.location.search);
    stateTitle.textContent = config.ready;
    continueLink.href = config.events + '?lang=' + encodeURIComponent(config.locale);
    continueLink.hidden = false;
  }).catch(() => {
    stateTitle.textContent = config.incomplete;
    stateCopy.textContent = '';
  });
})();
