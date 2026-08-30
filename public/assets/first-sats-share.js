(() => {
  'use strict';
  const button=document.querySelector('[data-share]'); const field=document.getElementById('share-text');
  if(!button||!field)return;
  const size=()=>{
    field.style.height='0px';
    field.style.height=`${Math.ceil(field.scrollHeight)+8}px`;
  };
  const scheduleSize=()=>requestAnimationFrame(()=>requestAnimationFrame(size));
  size();scheduleSize();
  window.addEventListener('load',scheduleSize);
  window.addEventListener('resize',scheduleSize);
  field.addEventListener('input',size);
  if(document.fonts?.ready)document.fonts.ready.then(scheduleSize);
  if('ResizeObserver' in window)new ResizeObserver(scheduleSize).observe(field.parentElement);
  document.querySelectorAll('[data-copy-target]').forEach(copy=>copy.addEventListener('click',async()=>{const source=document.getElementById(copy.dataset.copyTarget);if(!source)return;const value=source.textContent;try{await navigator.clipboard.writeText(value);copy.classList.add('copied');const status=document.querySelector('[data-copy-status]');if(status)status.textContent=copy.dataset.copiedLabel;}catch(error){const range=document.createRange();range.selectNodeContents(source);const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);}}));
  button.addEventListener('click',async()=>{const text=field.value;try{if(navigator.share){await navigator.share({title:button.dataset.title,text});return}await navigator.clipboard.writeText(text);}catch(error){field.focus();field.select();}});
})();
