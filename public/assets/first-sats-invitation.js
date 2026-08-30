(() => {
  'use strict';
  const adult=document.querySelector('[data-participant-adult]');
  const terms=document.querySelector('[data-participant-terms]');
  const button=document.querySelector('[data-participant-submit]');
  if(!adult||!terms||!button)return;
  const update=()=>{button.disabled=!(adult.checked&&terms.checked);};
  adult.addEventListener('change',update);terms.addEventListener('change',update);
  update();
})();
