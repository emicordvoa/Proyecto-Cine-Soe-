(function(){
  const resultBox=document.getElementById('resultBox');
  const resultTitle=document.getElementById('resultTitle');
  const resultMessage=document.getElementById('resultMessage');
  const entryData=document.getElementById('entryData');
  const lastScan=document.getElementById('lastScan');
  const statusDot=document.getElementById('statusDot');
  const countEls=document.querySelectorAll('#sessionCount,#sessionCountHero');
  const scannerMovieTitle=document.getElementById('scannerMovieTitle');
  const restartBtn=document.getElementById('restartBtn');
  const manualForm=document.getElementById('manualForm');
  const manualToken=document.getElementById('manualToken');
  const reader=document.getElementById('reader');
  const cameraPermission=document.getElementById('cameraPermission');
  const startCameraBtn=document.getElementById('startCameraBtn');

  if(!reader||!resultBox||!resultTitle||!resultMessage)return;

  let scanner=null;
  let running=false;
  let sessionCount=parseInt((countEls[0]&&countEls[0].textContent)||'0',10)||0;
  let lastCode='';
  let wakeLock=null;
  let autoRestartTimer=null;

  resultTitle.textContent='Esperando QR...';
  resultMessage.textContent='Activa la camara y apunta al codigo del ticket.';

  function isCameraSecureContext(){
    const host=window.location.hostname;
    return window.isSecureContext||host==='localhost'||host==='127.0.0.1'||host==='::1';
  }

  async function keepAwake(){
    try{
      if('wakeLock' in navigator && !wakeLock)wakeLock=await navigator.wakeLock.request('screen');
    }catch(error){}
  }

  function tokenFrom(text){
    const ticket=(text||'').toUpperCase().replace(/\s+/g,'').replace(/^SOE(?!-)/,'SOE-');
    if(/^SOE-\d{9}$/.test(ticket))return ticket;
    const match=(text||'').match(/[a-f0-9]{64}/i);
    return match?match[0]:(text||'').trim();
  }

  function beep(ok){
    try{
      const audio=new (window.AudioContext||window.webkitAudioContext)();
      const osc=audio.createOscillator();
      const gain=audio.createGain();
      osc.frequency.value=ok?880:220;
      gain.gain.value=.08;
      osc.connect(gain);
      gain.connect(audio.destination);
      osc.start();
      setTimeout(()=>{osc.stop();audio.close();},150);
    }catch(error){}
  }

  function paint(type,title,message,entry){
    const state=type==='valida'?'ok':type==='usada'?'warn':'bad';
    resultBox.className='scanner-result '+state;
    if(statusDot)statusDot.className='status-dot '+state;
    resultTitle.textContent=title;
    resultMessage.textContent=message;
    entryData.innerHTML=entry
      ? `<div class="entry-pill"><strong>${entry.cliente||''}</strong><span>${entry.pelicula||''}</span><small>${entry.codigo_compra||''}</small></div>`
      : '';
  }

  function showInfo(message){
    resultMessage.textContent=message;
  }

  function updateCounter(total){
    const parsed=parseInt(total,10);
    if(Number.isNaN(parsed))return;
    sessionCount=parsed;
    countEls.forEach(countEl=>{countEl.textContent=sessionCount;});
  }

  function updateCounterLabel(movie){
    if(scannerMovieTitle&&movie)scannerMovieTitle.textContent=movie;
  }

  function manualTicketValue(){
    return (manualToken.value||'').toUpperCase().replace(/\s+/g,'');
  }

  function manualTicketComplete(){
    return /^SOE-000\d{6}$/.test(manualTicketValue());
  }

  function showManualError(message){
    paint('invalida','Código',message,null);
    manualToken.focus();
  }

  function clearAutoRestart(){
    if(autoRestartTimer){
      clearTimeout(autoRestartTimer);
      autoRestartTimer=null;
    }
  }

  function scheduleAutoRestart(){
    clearAutoRestart();
    autoRestartTimer=setTimeout(async()=>{
      lastCode='';
      await start();
    },3000);
  }

  function setCameraPrompt(visible,message){
    if(cameraPermission)cameraPermission.classList.toggle('is-hidden',!visible);
    if(message)showInfo(message);
  }

  function cameraErrorMessage(error){
    if(!isCameraSecureContext())return 'La cámara del celular requiere HTTPS. Si entras por IP local con http://, el navegador no mostrará el permiso.';
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)return 'Este navegador no permite usar la cámara.';
    if(error&&error.name==='NotAllowedError')return 'Permiso de cámara rechazado. Actívalo en los permisos del navegador.';
    if(error&&error.name==='NotFoundError')return 'No se encontró una cámara disponible en este dispositivo.';
    if(error&&error.name==='NotReadableError')return 'La cámara está ocupada por otra aplicación.';
    return 'No se pudo activar la cámara. Revisa los permisos del navegador.';
  }

  async function requestCameraPermission(){
    if(!isCameraSecureContext()){
      const error=new Error('insecure context');
      error.name='SecurityError';
      throw error;
    }
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){
      throw new Error('mediaDevices unavailable');
    }
    const stream=await navigator.mediaDevices.getUserMedia({
      video:{facingMode:{ideal:'environment'}},
      audio:false
    });
    stream.getTracks().forEach(track=>track.stop());
  }

  async function validate(text,method='camara'){
    const token=tokenFrom(text);
    if(!token||token===lastCode)return;
    lastCode=token;
    if(lastScan)lastScan.textContent='Ultimo: '+token;
    if(running)await stop();

    try{
      const response=await fetch('../api/validar-qr.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({token,metodo:method})
      });
      const data=await response.json();
      const title=data.ok?'VALIDA':(data.tipo==='usada'?'YA USADA':'INVALIDA');
      paint(data.tipo,title,data.mensaje||title,data.entrada);
      if(data.total_validadas!==undefined)updateCounter(data.total_validadas);
      updateCounterLabel(data.contador_pelicula);
      if(navigator.vibrate)navigator.vibrate(data.ok?[90]:data.tipo==='usada'?[80,50,80]:[120,70,120]);
      beep(!!data.ok);
      scheduleAutoRestart();
    }catch(error){
      paint('invalida','SIN RED','No se pudo contactar al servidor.',null);
      beep(false);
      scheduleAutoRestart();
    }
  }

  function loadQrLibrary(){
    return new Promise(resolve=>{
      if(window.Html5Qrcode){resolve(true);return;}
      const script=document.createElement('script');
      script.src='../assets/js/html5-qrcode.min.js';
      script.onload=()=>resolve(!!window.Html5Qrcode);
      script.onerror=()=>resolve(false);
      document.head.appendChild(script);
    });
  }

  async function start(){
    keepAwake();
    if(!window.Html5Qrcode && !(await loadQrLibrary())){
      paint('invalida','ERROR','No se cargo la libreria QR.',null);
      return;
    }

    if(scanner&&running)return;
    clearAutoRestart();
    scanner=new Html5Qrcode('reader');
    running=true;
    lastCode='';
    resultBox.className='scanner-result';
    if(statusDot)statusDot.className='status-dot';
    if(lastScan)lastScan.textContent='Escaneando...';
    entryData.innerHTML='';
    resultTitle.textContent='Esperando QR...';
    resultMessage.textContent='Camara activa. Apunta al QR del ticket.';

    await scanner.start(
      {facingMode:'environment'},
      {fps:10,qrbox:{width:260,height:260},aspectRatio:0.75},
      validate,
      ()=>{}
    );

    setCameraPrompt(false);
  }

  async function activateCamera(){
    if(startCameraBtn){
      startCameraBtn.disabled=true;
      startCameraBtn.textContent='Activando...';
    }
    try{
      await requestCameraPermission();
      await start();
    }catch(error){
      const message=cameraErrorMessage(error);
      paint('invalida','CÁMARA',message,null);
      setCameraPrompt(true,message);
    }finally{
      if(startCameraBtn){
        startCameraBtn.disabled=false;
        startCameraBtn.textContent='Activar cámara';
      }
    }
  }

  async function stop(){
    if(scanner&&running){
      try{await scanner.stop();}catch(error){}
      try{await scanner.clear();}catch(error){}
    }
    running=false;
    scanner=null;
  }

  if(startCameraBtn)startCameraBtn.addEventListener('click',activateCamera);
  if(restartBtn)restartBtn.addEventListener('click',async()=>{lastCode='';clearAutoRestart();await stop();await start();});
  if(manualForm&&manualToken)manualForm.addEventListener('submit',event=>{
    event.preventDefault();
    if(!manualTicketComplete()){
      showManualError('Completa el código. Ejemplo: SOE-000210605.');
      return;
    }
    validate(manualToken.value,'manual');
    manualToken.value='SOE-000';
  });
  if(manualToken)manualToken.addEventListener('input',()=>{
    const prefix='SOE-000';
    const digits=manualToken.value.replace(/\D+/g,'');
    const suffix=(digits.startsWith('000')?digits.slice(3):digits).slice(0,6);
    manualToken.value=prefix+suffix;
  });

  if(startCameraBtn){
    if(!isCameraSecureContext()){
      paint('invalida','HTTPS','La cámara del celular requiere HTTPS. En PC puedes usar localhost; en celular no uses http://IP-local.',null);
      setCameraPrompt(true,'Para usar cámara desde celular abre el sistema con HTTPS o desde localhost en el mismo equipo.');
    }else{
      resultTitle.textContent='Activa cámara';
      resultMessage.textContent='Toca el botón Activar cámara y acepta el permiso del navegador.';
      setCameraPrompt(true);
    }
  }else{
    start().catch(error=>paint('invalida','CÁMARA',cameraErrorMessage(error),null));
  }
})();
