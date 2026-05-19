(function(){
/* ===== NAVBAR SCROLL ===== */
const navbar=document.querySelector('.navbar');
if(navbar){
  const onScroll=()=>navbar.classList.toggle('scrolled',window.scrollY>50);
  window.addEventListener('scroll',onScroll,{passive:true});
  onScroll();
}

/* ===== PARALLAX HERO ===== */
const hero=document.querySelector('.hero');
if(hero){
  const heroContent=hero.querySelector('.hero-content');
  window.addEventListener('scroll',()=>{
    const y=window.scrollY;
    if(y<window.innerHeight&&heroContent){
      heroContent.style.transform=`translateY(${y*.25}px)`;
      heroContent.style.opacity=1-y/(window.innerHeight*.9);
    }
  },{passive:true});
}

/* ===== REVEAL ON SCROLL ===== */
const reveals=document.querySelectorAll('.reveal');
if(reveals.length){
  const io=new IntersectionObserver((entries)=>{
    entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target);}});
  },{threshold:.15});
  reveals.forEach(el=>io.observe(el));
}

/* ===== MOVIE MODAL ===== */
const movieModal=document.getElementById('movieModal');
const movieModalContent=document.getElementById('movieModalBody');
if(movieModal&&movieModalContent){
  document.querySelectorAll('[data-movie-card]').forEach(card=>{
    card.addEventListener('click',()=>{
      const d=card.dataset;
      const posterSrc=card.querySelector('.movie-poster img')?.src||'';
      const posterHtml=posterSrc?`<img class="modal-poster" src="${posterSrc}" alt="">`:'';
      movieModalContent.innerHTML=`
        ${posterHtml}
        <div class="modal-body">
          <h2>${d.movieTitle||''}</h2>
          <div class="modal-meta">
            <span class="badge badge-info">${d.movieDate||''}</span>
            <span class="badge badge-purple">Bs ${d.moviePrice||'0'}</span>
            <span class="badge badge-success">${d.movieAvail||'0'} disponibles</span>
          </div>
          <p style="color:var(--muted);line-height:1.7;margin-bottom:1.5rem">${d.movieDesc||'Función especial de cine universitario.'}</p>
          <a class="btn btn-primary btn-lg btn-block" href="comprar.php?pelicula=${d.movieId||''}">Comprar Entradas</a>
        </div>`;
      movieModal.classList.add('active');
      document.body.style.overflow='hidden';
    });
  });
  const closeModal=()=>{movieModal.classList.remove('active');document.body.style.overflow='';};
  movieModal.querySelector('.modal-close')?.addEventListener('click',closeModal);
  movieModal.addEventListener('click',e=>{if(e.target===movieModal)closeModal();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&movieModal.classList.contains('active'))closeModal();});
}

/* ===== STEPPER ===== */
const stepperForm=document.querySelector('[data-stepper-form]');
if(stepperForm){
  const panels=stepperForm.querySelectorAll('.step-panel');
  const items=document.querySelectorAll('.stepper-item');
  const lines=document.querySelectorAll('.stepper-line');
  let current=0;
  const showStep=(n)=>{
    panels.forEach((p,i)=>p.classList.toggle('active',i===n));
    items.forEach((it,i)=>{
      it.classList.remove('active','done');
      if(i<n)it.classList.add('done');
      if(i===n)it.classList.add('active');
    });
    lines.forEach((l,i)=>l.classList.toggle('filled',i<n));
    current=n;
  };
  document.querySelectorAll('[data-step-next]').forEach(btn=>btn.addEventListener('click',()=>{
    const panel=panels[current];
    const inputs=[...panel.querySelectorAll('input,select,textarea')];
    let valid=true;
    inputs.forEach(inp=>{
      inp.setCustomValidity('');
      if(inp.required&&!inp.value.trim()){inp.setCustomValidity('Campo requerido');valid=false;}
      if(!inp.checkValidity()){valid=false;}
      inp.classList.toggle('invalid',!inp.checkValidity());
      inp.classList.toggle('valid',inp.checkValidity()&&inp.value.trim()!=='');
    });
    if(valid&&current<panels.length-1)showStep(current+1);
  }));
  document.querySelectorAll('[data-step-prev]').forEach(btn=>btn.addEventListener('click',()=>{
    if(current>0)showStep(current-1);
  }));
  showStep(0);
}

/* ===== FORM VALIDATION ===== */
const forms=document.querySelectorAll('form');
const getMessage=i=>{
  if(i.validity.valueMissing)return'Este campo es obligatorio.';
  if(i.validity.typeMismatch&&i.type==='email')return'Ingresa un correo válido.';
  if(i.validity.patternMismatch)return i.title||'Revisa el formato.';
  return'Revisa este campo.';
};
const validateInput=i=>{
  if(i.disabled||i.type==='hidden')return true;
  i.setCustomValidity('');
  if(i.required&&typeof i.value==='string'&&i.value.trim()==='')i.setCustomValidity('Campo requerido.');
  if(i.dataset.digitsOnly==='true'&&i.value&&!/^\d+$/.test(i.value))i.setCustomValidity(i.title||'Solo números.');
  if(i.dataset.decimalOnly==='true'&&i.value&&!/^\d+(\.\d{1,2})?$/.test(i.value))i.setCustomValidity(i.title||'Monto inválido.');
  if(i.dataset.phoneLocal==='true'&&i.value&&!/^\d{5,15}$/.test(i.value))i.setCustomValidity(i.title||'Número inválido.');
  if(!i.validity.valid){
    i.classList.add('invalid');i.classList.remove('valid');
    let fb=i.parentElement?.querySelector('.form-error');
    if(!fb){fb=document.createElement('div');fb.className='form-error';i.insertAdjacentElement('afterend',fb);}
    fb.textContent=getMessage(i);return false;
  }
  i.classList.remove('invalid');
  if(i.value.trim())i.classList.add('valid');
  const fb=i.parentElement?.querySelector('.form-error');if(fb)fb.textContent='';
  return true;
};
document.querySelectorAll('[data-digits-only="true"]').forEach(i=>i.addEventListener('input',()=>{i.value=i.value.replace(/\D/g,'');validateInput(i);}));
document.querySelectorAll('[data-decimal-only="true"]').forEach(i=>i.addEventListener('input',()=>{let v=i.value.replace(',','.').replace(/[^0-9.]/g,'');const d=v.indexOf('.');if(d!==-1)v=v.slice(0,d+1)+v.slice(d+1).replace(/\./g,'');i.value=v;validateInput(i);}));
document.querySelectorAll('[data-phone-local="true"]').forEach(i=>i.addEventListener('input',()=>{i.value=i.value.replace(/\D/g,'').slice(0,15);validateInput(i);}));
document.querySelectorAll('[data-country-select]').forEach(sel=>{
  const setLabels=exp=>{[...sel.options].forEach(o=>o.textContent=exp?(o.dataset.label||o.textContent):(o.dataset.short||o.textContent));};
  sel.addEventListener('focus',()=>setLabels(true));sel.addEventListener('blur',()=>setLabels(false));
  sel.addEventListener('change',()=>setTimeout(()=>setLabels(false),120));setLabels(false);
});
forms.forEach(f=>f.addEventListener('submit',ev=>{
  f.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(i=>{if(i.type!=='password'&&typeof i.value==='string')i.value=i.value.trim();});
  const inputs=[...f.querySelectorAll('input,select,textarea')];
  inputs.forEach(validateInput);
  if(!f.checkValidity()){ev.preventDefault();ev.stopPropagation();const first=f.querySelector('.invalid');if(first)first.focus();}
}));
document.querySelectorAll('input,select,textarea').forEach(i=>{i.addEventListener('blur',()=>validateInput(i));});

/* ===== QUANTITY + TOTAL ===== */
const movieSelect=document.getElementById('movieSelect');
const quantity=document.getElementById('quantity');
const totalPrice=document.getElementById('totalPrice');
const totalPrice2=document.getElementById('totalPrice2');
const availableText=document.getElementById('availableText');
function updateTotal(){
  if(!movieSelect||!quantity||!totalPrice)return;
  const opt=movieSelect.selectedOptions[0];
  const price=parseFloat(opt?.dataset.price||'0');
  const avail=parseInt(opt?.dataset.available||'0',10);
  let qty=Math.max(1,Math.min(10,parseInt(quantity.value||'1',10)));
  qty=Math.min(qty,avail||1);quantity.value=qty;quantity.max=Math.min(10,avail||1);
  totalPrice.textContent='Bs '+(price*qty).toFixed(2);
  if(totalPrice2)totalPrice2.textContent=totalPrice.textContent;
  if(availableText)availableText.textContent=avail+' disponibles';
}
document.querySelectorAll('[data-qty]').forEach(b=>b.addEventListener('click',()=>{
  if(!quantity)return;quantity.value=(parseInt(quantity.value||'1',10)+parseInt(b.dataset.qty,10)).toString();updateTotal();
}));
if(movieSelect)movieSelect.addEventListener('change',updateTotal);
if(quantity)quantity.addEventListener('input',updateTotal);
updateTotal();

/* ===== FILE UPLOAD ===== */
const fileInput=document.getElementById('receiptInput');
const preview=document.getElementById('receiptPreview');
const uploadZone=document.querySelector('.upload-zone');
if(fileInput&&preview){
  const fileError=document.querySelector('[data-file-error]');
  const fileName=document.querySelector('[data-file-name]');
  const fileMeta=document.querySelector('[data-file-meta]');
  const fileBadge=document.querySelector('[data-file-badge]');
  const formatFileSize=bytes=>{
    if(!bytes)return'0 KB';
    const units=['B','KB','MB'];
    let size=bytes;
    let unit=0;
    while(size>=1024&&unit<units.length-1){size/=1024;unit++;}
    return(size>=10||unit===0?size.toFixed(0):size.toFixed(1))+' '+units[unit];
  };
  const handleFile=f=>{
    if(fileError)fileError.style.display='none';
    if(!f)return;
    if(uploadZone)uploadZone.classList.remove('has-image','has-file');
    const ext=(f.name.split('.').pop()||'FILE').slice(0,4).toUpperCase();
    if(fileName)fileName.textContent=f.name||'Comprobante seleccionado';
    if(fileMeta)fileMeta.textContent=(f.type||'Comprobante')+' - '+formatFileSize(f.size||0);
    if(fileBadge)fileBadge.textContent=ext;
    if(f.type.startsWith('image/')){
      preview.src=URL.createObjectURL(f);
      if(uploadZone)uploadZone.classList.add('has-image');
    }else{
      preview.removeAttribute('src');
      if(uploadZone)uploadZone.classList.add('has-file');
    }
  };
  fileInput.addEventListener('change',()=>handleFile(fileInput.files[0]));
  document.querySelectorAll('[data-file-submit]').forEach(btn=>btn.addEventListener('click',e=>{
    const target=document.getElementById(btn.dataset.fileSubmit||'');
    if(target&&target.type==='file'&&!target.files.length){
      e.preventDefault();
      if(fileError)fileError.style.display='block';
      target.click();
    }
  }));
  document.querySelectorAll('[data-receipt-form]').forEach(form=>form.addEventListener('submit',e=>{
    if(!fileInput.files.length){
      e.preventDefault();
      e.stopPropagation();
      if(fileError)fileError.style.display='block';
      fileInput.click();
    }
  }));
  if(uploadZone){
    uploadZone.addEventListener('dragover',e=>{e.preventDefault();uploadZone.classList.add('dragover');});
    uploadZone.addEventListener('dragleave',()=>uploadZone.classList.remove('dragover'));
    uploadZone.addEventListener('drop',e=>{e.preventDefault();uploadZone.classList.remove('dragover');if(e.dataTransfer.files[0]){fileInput.files=e.dataTransfer.files;handleFile(e.dataTransfer.files[0]);}});
  }
}

/* ===== COUNTDOWN TIMER ===== */
const timerEl=document.querySelector('[data-countdown]');
if(timerEl){
  let secs=parseInt(timerEl.dataset.countdown||'0',10);
  const expireUrl=timerEl.dataset.expireUrl;
  const textEl=document.getElementById('countdownText');
  const circleEl=document.querySelector('.timer-progress');
  const totalSecs=secs;
  const tick=()=>{
    const m=Math.floor(secs/60).toString().padStart(2,'0');
    const s=(secs%60).toString().padStart(2,'0');
    if(textEl)textEl.textContent=m+':'+s;
    if(circleEl){const circ=2*Math.PI*46;circleEl.style.strokeDasharray=circ;circleEl.style.strokeDashoffset=circ*(1-secs/totalSecs);}
    if(secs<=0){window.location.href=expireUrl||'index.php';return;}
    secs--;setTimeout(tick,1000);
  };tick();
}

/* ===== PASSWORD TOGGLE ===== */
document.querySelectorAll('[data-toggle-password]').forEach(btn=>{
  btn.addEventListener('click',e=>{
    e.preventDefault();const inp=document.getElementById(btn.dataset.togglePassword);if(!inp)return;
    const vis=inp.type==='password';inp.type=vis?'text':'password';
    btn.classList.toggle('is-visible',vis);inp.focus();
  });
});

/* ===== COPY ===== */
document.querySelectorAll('[data-copy]').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const v=btn.dataset.copy||'';const wrap=btn.closest('.copy-box')||btn.parentElement;
    try{await navigator.clipboard.writeText(v);}catch(e){const t=document.createElement('textarea');t.value=v;t.style.position='fixed';t.style.left='-9999px';document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}
    btn.classList.add('copied');if(wrap)wrap.classList.add('copied');
    setTimeout(()=>{btn.classList.remove('copied');if(wrap)wrap.classList.remove('copied');},2000);
  });
});

/* ===== PDF.JS LOADER ===== */
let pdfjsLib;const loadPdfJs=()=>{if(pdfjsLib)return Promise.resolve();const script=document.createElement('script');script.src='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';return new Promise((resolve,reject)=>{script.onload=()=>{pdfjsLib=window['pdfjs-dist/build/pdf'];pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';resolve();};script.onerror=reject;document.head.appendChild(script);});};

/* ===== RECEIPT MODAL ===== */
const rModal=document.getElementById('receiptModal');
const rContent=document.getElementById('receiptModalContent');
const closeReceipt=()=>{if(!rModal)return;rModal.classList.remove('is-open');document.body.style.overflow='';setTimeout(()=>{if(rContent)rContent.innerHTML='';},200);};

const renderPdfPreview=async(url)=>{try{await loadPdfJs();if(!pdfjsLib){rContent.innerHTML='<div class="p-3 text-danger">No se pudo cargar PDF.js.</div>';return;}const pdf=await pdfjsLib.getDocument(url).promise;const page=await pdf.getPage(1);const canvas=document.createElement('canvas');const context=canvas.getContext('2d');const containerWidth=rContent.clientWidth||600;const unscaledViewport=page.getViewport({scale:1});const scale=containerWidth/unscaledViewport.width;const outputScale=window.devicePixelRatio||1;const viewport=page.getViewport({scale});canvas.width=Math.floor(viewport.width*outputScale);canvas.height=Math.floor(viewport.height*outputScale);canvas.style.width=Math.floor(viewport.width)+'px';canvas.style.height=Math.floor(viewport.height)+'px';context.setTransform(outputScale,0,0,outputScale,0,0);await page.render({canvasContext:context,viewport}).promise;const openBtn=`<div style="margin-top:.75rem;text-align:right"><a class="btn btn-ghost" href="${url}" target="_blank" rel="noopener">Abrir PDF en nueva pestaña</a></div>`;rContent.innerHTML='';rContent.appendChild(canvas);canvas.classList.add('receipt-modal-canvas');const container=document.createElement('div');container.innerHTML=openBtn;rContent.appendChild(container);}catch(e){const openBtn=`<div style="margin-top:.75rem;text-align:right"><a class="btn btn-ghost" href="${url}" target="_blank" rel="noopener">Abrir PDF en nueva pestaña</a></div>`;rContent.innerHTML=`<div class="p-3" style="color:var(--muted)">No se puede mostrar vista previa del PDF. ${openBtn}</div>`;}};

document.querySelectorAll('[data-receipt-open]').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    if(!rModal||!rContent)return;
    const url = btn.dataset.receiptUrl || '';
    const kind = btn.dataset.receiptKind;
    const exists = btn.dataset.receiptExists === '1';
    if (!exists) {
      rContent.innerHTML = '<div class="p-3">El comprobante no está disponible en el servidor.</div>';
      rModal.classList.add('is-open');document.body.style.overflow='hidden';return;
    }
    if (kind === 'image') {
      rContent.innerHTML = `<img class="receipt-modal-media" src="${url}" alt="Comprobante">`;
      rModal.classList.add('is-open');document.body.style.overflow='hidden';
    } else if (kind === 'pdf') {
      rContent.innerHTML = '<div class="pdf-loading"><div class="spinner"></div><p>Cargando PDF...</p></div>';
      rModal.classList.add('is-open');document.body.style.overflow='hidden';
      await renderPdfPreview(url);
    } else {
      rContent.innerHTML = `<img class="receipt-modal-media" src="${url}" alt="Comprobante">`;
      rModal.classList.add('is-open');document.body.style.overflow='hidden';
    }
  });
});
document.querySelectorAll('[data-receipt-close]').forEach(b=>b.addEventListener('click',closeReceipt));
if(rModal){rModal.addEventListener('click',e=>{if(e.target===rModal)closeReceipt();});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&rModal.classList.contains('is-open'))closeReceipt();});}

/* ===== ADMIN SIDEBAR ===== */
const sidebar=document.querySelector('.sidebar');
const sidebarToggle=document.querySelector('.sidebar-toggle');
const sidebarOverlay=document.querySelector('.sidebar-overlay');
if(sidebar&&sidebarToggle){
  sidebarToggle.addEventListener('click',()=>{sidebar.classList.toggle('open');sidebarOverlay?.classList.toggle('active');});
  sidebarOverlay?.addEventListener('click',()=>{sidebar.classList.remove('open');sidebarOverlay.classList.remove('active');});
}

/* ===== ANIMATED COUNTERS ===== */
document.querySelectorAll('[data-count]').forEach(el=>{
  const target=parseFloat(el.dataset.count);const prefix=el.dataset.prefix||'';const suffix=el.dataset.suffix||'';
  const dur=800;const start=performance.now();const isDecimal=String(target).includes('.');
  const animate=now=>{
    const p=Math.min((now-start)/dur,1);const eased=1-Math.pow(1-p,3);
    const val=eased*target;
    el.textContent=prefix+(isDecimal?val.toFixed(2):Math.floor(val))+suffix;
    if(p<1)requestAnimationFrame(animate);
  };
  const io=new IntersectionObserver(entries=>{if(entries[0].isIntersecting){requestAnimationFrame(animate);io.unobserve(el);}},{threshold:.3});
  io.observe(el);
});

/* ===== PDF TOOLS ===== */
async function buildTicketPdf(target){
  if(!target||!window.html2canvas||!window.jspdf)return null;
  const cards=[...target.querySelectorAll('.ticket-card')];const pages=cards.length?cards:[target];let pdf=null;
  for(const page of pages){
    const canvas=await html2canvas(page,{scale:2,backgroundColor:null,useCORS:true});
    const img=canvas.toDataURL('image/png');const orient=canvas.width>canvas.height?'landscape':'portrait';
    const m=48;const pw=canvas.width+m*2;const ph=canvas.height+m*2;
    if(!pdf)pdf=new window.jspdf.jsPDF({orientation:orient,unit:'px',format:[pw,ph]});else pdf.addPage([pw,ph],orient);
    pdf.setFillColor(255,255,255);pdf.rect(0,0,pw,ph,'F');pdf.addImage(img,'PNG',m,m,canvas.width,canvas.height);
  }return pdf;
}
async function withBusy(btn,label,task){const orig=btn.textContent;btn.disabled=true;btn.textContent=label;try{await task();}finally{btn.disabled=false;btn.textContent=orig;}}

document.querySelectorAll('[data-download-ticket]').forEach(btn=>{
  btn.addEventListener('click',()=>withBusy(btn,'Creando PDF...',async()=>{
    const pdf=await buildTicketPdf(document.querySelector(btn.dataset.downloadTicket||'#ticketPDF'));
    if(pdf)pdf.save(btn.dataset.filename||'ticket.pdf');
  }));
});

document.querySelectorAll('[data-email-ticket]').forEach(btn=>{
  const statusBox=document.querySelector('[data-email-ticket-status]');
  const setStatus=(t,m)=>{if(!statusBox)return;statusBox.className='alert alert-'+t;statusBox.style.display='block';statusBox.textContent=m;};
  const send=()=>withBusy(btn,'Enviando...',async()=>{
    setStatus('info','Generando PDF...');
    const pdf=await buildTicketPdf(document.querySelector(btn.dataset.emailTicket||'#ticketPDF'));if(!pdf)return;
    setStatus('info','Enviando correo...');
    const fd=new FormData();fd.append('compra',btn.dataset.compra||'');fd.append('csrf',btn.dataset.csrf||'');fd.append('pdf',pdf.output('blob'),btn.dataset.filename||'entradas.pdf');
    const r=await fetch(btn.dataset.emailUrl||'../api/enviar-ticket-pdf.php',{method:'POST',body:fd,credentials:'same-origin'});
    const d=await r.json().catch(()=>({ok:false,message:'Respuesta inválida.'}));
    if(!r.ok||!d.ok)throw new Error(d.message||'Error al enviar.');
    setStatus('success',d.message||'Correo enviado.');
  }).catch(e=>setStatus('danger',e.message||'Error al enviar.'));
  btn.addEventListener('click',send);
  if(btn.dataset.autoEmailTicket==='1')setTimeout(send,800);
});

document.querySelectorAll('[data-share-ticket]').forEach(btn=>{
  btn.addEventListener('click',()=>withBusy(btn,'Preparando...',async()=>{
    const pdf=await buildTicketPdf(document.querySelector(btn.dataset.shareTicket||'#ticketPDF'));if(!pdf)return;
    const blob=pdf.output('blob');const file=new File([blob],btn.dataset.filename||'ticket.pdf',{type:'application/pdf'});
    if(navigator.canShare?.({files:[file]})&&navigator.share){await navigator.share({files:[file],title:'Entradas Cine SOE'});return;}
    pdf.save(btn.dataset.filename||'ticket.pdf');if(btn.dataset.whatsappUrl)window.open(btn.dataset.whatsappUrl,'_blank');
  }));
});
})();
