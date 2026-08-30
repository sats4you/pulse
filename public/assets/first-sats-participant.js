(() => {
  'use strict';
  const create=document.querySelector('[data-continuation]');
  const result=document.querySelector('[data-continuation-result]');
  const link=document.querySelector('[data-continuation-link]');
  const copy=document.querySelector('[data-copy-continuation]');
  const status=document.querySelector('[data-continuation-status]');
  if(!create||!result||!link||!copy||!status)return;

  create.addEventListener('click',async()=>{
    create.disabled=true;
    status.textContent='';
    try{
      const body=new URLSearchParams({csrf:create.dataset.csrf||''});
      const response=await fetch(create.dataset.url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
      if(!response.ok)throw new Error('continuation_failed');
      const data=await response.json();
      link.textContent=data.link;
      result.hidden=false;
      create.hidden=true;
    }catch(error){
      create.disabled=false;
      status.textContent=create.dataset.error||'';
    }
  });
  copy.addEventListener('click',async()=>{
    try{
      await navigator.clipboard.writeText(link.textContent||'');
      copy.classList.add('copied');
      status.textContent=copy.dataset.copied||'';
    }catch(error){
      const range=document.createRange();range.selectNodeContents(link);
      const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);
    }
  });
})();
