(() => {
  'use strict';
  const app = document.getElementById('first-sats-access');
  if (!app) return;
  const config = JSON.parse(app.dataset.config);
  const copy = document.getElementById('state-copy');
  const next = document.getElementById('continue');
  const secret = location.hash.startsWith('#') ? location.hash.slice(1) : '';
  if (!secret) {
    copy.textContent = config.incomplete;
    next.hidden = true;
    return;
  }
  fetch(config.exchange, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({secret})})
    .then(response => {
      if (!response.ok) throw new Error('denied');
      history.replaceState(null, '', location.pathname + location.search);
      const target = new URL(config.target, location.origin);
      target.searchParams.set('lang', config.locale);
      next.href = target.pathname + target.search;
      next.hidden = false;
      copy.textContent = config.ready;
    })
    .catch(() => {
      copy.textContent = config.denied;
      next.hidden = true;
    });
})();
