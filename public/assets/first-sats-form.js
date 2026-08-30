(() => {
  'use strict';
  const form=document.querySelector('.form'); if(!form)return;
  const amount=form.querySelector('[name="sats"]'),inviter=form.querySelector('[name="inviter"]'),invitee=form.querySelector('[name="invitee"]'),high=form.querySelector('[data-high-amount]'),summary=form.querySelector('[data-summary]'),submit=form.querySelector('[type="submit"]'),presets=[...form.querySelectorAll('[data-sats]')];
  if(!amount||!inviter||!invitee||!high||!summary||!submit)return;
  const number=value=>value.toLocaleString('de-CH').replaceAll('’',"'");
  const numeric=()=>Number(amount.value.replace(/[^0-9]/g,''))||0;
  const updateSubmit=()=>{submit.disabled=!(inviter.value.trim()&&invitee.value.trim()&&form.elements.adult.checked&&form.elements.terms.checked&&numeric()<=100000);};
  const chf=value=>{if(value===0)return amount.dataset.chfZero;if(!/^[0-9]+$/.test(amount.dataset.quoteSum))return amount.dataset.chfUnavailable;const sum=BigInt(amount.dataset.quoteSum);if(sum<=0n)return amount.dataset.chfUnavailable;const cents=Number((BigInt(value)*sum+10000000000n)/20000000000n);return cents===0?amount.dataset.chfUnderCent:amount.dataset.chfEstimate.replace('__VALUE__',(cents/100).toLocaleString('de-CH',{minimumFractionDigits:2,maximumFractionDigits:2}).replaceAll('’',"'"));};
  const update=()=>{const digits=amount.value.replace(/[^0-9]/g,'').slice(0,6),value=digits===''?0:Number(digits);amount.value=digits===''?'':number(value);high.hidden=value<50000;summary.textContent=number(value)+' Sats · '+chf(value);presets.forEach(button=>button.classList.toggle('active',Number(button.dataset.sats)===value));updateSubmit();};
  presets.forEach(button=>button.addEventListener('click',()=>{amount.value=button.dataset.sats;update();amount.focus();}));
  amount.addEventListener('input',update);inviter.addEventListener('input',updateSubmit);invitee.addEventListener('input',updateSubmit);form.elements.adult.addEventListener('change',updateSubmit);form.elements.terms.addEventListener('change',updateSubmit);update();
})();
