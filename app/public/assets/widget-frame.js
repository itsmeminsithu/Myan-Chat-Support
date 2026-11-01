/* Myan Chat Support v1.0.2 — iframe UI. Rendering never replaces an active composer during polling. */
(function () {
 'use strict';
 const mount=document.getElementById('widget');
 if(!mount)return;
 const key=mount.dataset.site,root=document.getElementById('widgetRoot');
 const labels={
  en:{ready:'We are here to help',online:'Chat with us',offline:'Leave a message',name:'Your name',email:'Email address',message:'Write a message...',start:'Start conversation',send:'Send',contact:'Send offline message',sent:'Thank you — your message has been saved.',back:'Back',end:'Conversation ended',typing:'Support is typing...',retry:'Network error. Please try again.',powered:'Developed by Min SiThu',newChat:'Start a new chat',close:'Close chat',endChat:'End conversation',privacy:'Your messages and page path are stored by this website’s support team. Do not send passwords or payment details.',expired:'Your previous chat expired. Please begin a new conversation.'},
  mm:{ready:'ကူညီပေးဖို့ အသင့်ရှိပါတယ်',online:'စကားပြောမယ်',offline:'စာပို့ထားမယ်',name:'သင့်အမည်',email:'အီးမေးလ်',message:'စာရေးရန်...',start:'စကားပြောမယ်',send:'ပို့မယ်',contact:'စာပို့ထားမယ်',sent:'ကျေးဇူးတင်ပါတယ်။ သင့်စာကို သိမ်းထားပါပြီ။',back:'ပြန်သွားမယ်',end:'စကားပြောခြင်း ပြီးဆုံးပါပြီ',typing:'ပြန်စာရေးနေပါတယ်...',retry:'ချိတ်ဆက်မှု အဆင်မပြေပါ။ ပြန်ကြိုးစားပါ။',powered:'Developed by Min SiThu',newChat:'အသစ်စမယ်',close:'ပိတ်မယ်',endChat:'စကားပြောခြင်း အဆုံးသတ်မယ်',privacy:'သင့်စာများနှင့် သင်ကြည့်နေသည့် စာမျက်နှာလမ်းကြောင်းကို ဝဘ်ဆိုက်၏ Support အဖွဲ့ထံ သိမ်းဆည်းပေးပါသည်။ Password မပို့ပါနှင့်။',expired:'ယခင်စကားပြောခန်း သက်တမ်းကုန်သွားပါပြီ။ အသစ်ပြန်စပါ။'}
 };
 let site=null,language='en',open=false,conversation=null,token=null,after=0,pollTimer=null,contextOrigin=null,contextPage='',state='login',messages=[],loading=false,error='',name='',email='',offlineText='',draft='',operatorTyping=false,lastTyping=0,pollBusy=false;
 let lines=null,composerInput=null,typingView=null,errorView=null,forceScrollBottom=true;
 function t(k){return (labels[language]||labels.en)[k]||k;}
 function getStore(k){try{return localStorage.getItem(k);}catch(e){return null;}}
 function setStore(k,v){try{localStorage.setItem(k,v);}catch(e){/* memory-only session remains valid */}}
 function removeStore(k){try{localStorage.removeItem(k);}catch(e){}}
 function el(tag,cls,txt){const n=document.createElement(tag);if(cls)n.className=cls;if(txt!==undefined)n.textContent=txt;return n;}
 function button(cls,txt,fn){const b=el('button',cls,txt);b.type='button';b.addEventListener('click',fn);return b;}
 function api(action,body){return fetch('/api.php?action='+encodeURIComponent(action),{method:'POST',headers:{'Content-Type':'application/json'},credentials:'omit',body:JSON.stringify(body)}).then(async r=>{let json={};try{json=await r.json();}catch(e){}if(!r.ok){let err=new Error(json.error||t('retry'));err.status=r.status;throw err;}return json;});}
 function context(){return {site_key:key,origin:contextOrigin,page_url:contextPage};}
 function adjust(){parent.postMessage({type:'MCS_RESIZE',open:open},contextOrigin||'*');}
 function toggle(){open=!open;adjust();forceScrollBottom=true;render();if(open&&state==='chat')poll();}
 function keepSession(){if(conversation&&token)setStore('mcs-session-'+key,JSON.stringify({conversation:conversation,token:token}));}
 function clearSession(){removeStore('mcs-session-'+key);conversation=null;token=null;after=0;messages=[];stopPolling();}
 function stopPolling(){if(pollTimer)clearInterval(pollTimer);pollTimer=null;}
 function resetExpired(){clearSession();state=site&&site.online?'login':'offline';error=t('expired');render();}
 function newChat(){clearSession();state=site&&site.online?'login':'offline';error='';draft='';render();}
 function formatDate(raw){const d=new Date(String(raw||'').replace(' ','T')+'Z');return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat(language==='mm'?'my-MM':'en-US',{hour:'2-digit',minute:'2-digit'}).format(d);}
 function createBubble(m){const line=el('div','chat-line '+(m.sender_type==='guest'?'mine':m.sender_type==='system'?'system':'theirs'));line.append(el('div','bubble',m.body));const date=formatDate(m.created_at);if(date)line.append(el('time','',date));return line;}
 function updateChat(){
  if(state!=='chat'||!lines)return;
  const existing=new Set(Array.from(lines.children).map(n=>Number(n.dataset.id)));
  const nearBottom=lines.scrollHeight-lines.scrollTop-lines.clientHeight<90;
  for(const m of messages){const id=Number(m.id);if(existing.has(id))continue;const row=createBubble(m);row.dataset.id=String(id);lines.append(row);existing.add(id);}
  if(nearBottom||forceScrollBottom){lines.scrollTop=lines.scrollHeight;forceScrollBottom=false;}
  if(typingView)typingView.textContent=operatorTyping?t('typing'):'';
  if(errorView)errorView.textContent=error;
 }
 function logoMark(cls){
  const holder=el('span',cls,'✦');if(typeof Image!=='function')return holder;const image=new Image();image.alt='';image.className='chat-brand-image';
  image.onload=()=>{if(image.naturalWidth>0)holder.replaceChildren(image);};
  image.src='/brand-logo.php';return holder;
 }
 function render(){
  if(!site)return;
  document.documentElement.lang=language==='mm'?'my':'en';
  lines=composerInput=typingView=errorView=null;
  const wrap=el('section','chat-widget'+(open?' expanded':''));wrap.style.setProperty('--brand',site.color||'#3865D8');
  if(!open){const b=button('collapsed','',toggle);b.append(logoMark('chat-dot'),el('span','collapsed-copy',language==='mm'?site.title_mm:site.title_en),el('span','chevron','⌃'));wrap.append(b);root.replaceChildren(wrap);return;}
  const header=el('header','chat-top'),words=el('div','heading');words.append(el('strong','',language==='mm'?site.title_mm:site.title_en),el('small','',t('ready')));
  header.append(logoMark('head-icon'),words,button('close-btn','×',toggle));wrap.append(header);
  if(state==='login'||state==='offline'){
   const panel=el('div','form-content');panel.append(el('p','intro',state==='login'?(language==='mm'?site.welcome_mm:site.welcome_en):t('offline')));
   panel.append(button('lang-toggle',language==='en'?'မြန်မာ':'English',()=>{language=language==='en'?'mm':'en';setStore('mcs-lang-'+key,language);render();}));
   const form=el('form','widget-form');
   function field(type,label,value,change,required){const node=el(type==='textarea'?'textarea':'input','widget-input');if(type!=='textarea')node.type=type;node.placeholder=label;node.value=value;node.required=required;node.maxLength=type==='textarea'?3000:190;node.addEventListener('input',()=>change(node.value));return node;}
   form.append(field('text',t('name'),name,v=>name=v,true));
   if(site.collect_email||state==='offline')form.append(field('email',t('email'),email,v=>email=v,true));
   if(state==='offline')form.append(field('textarea',t('message'),offlineText,v=>offlineText=v,true));
   const trap=field('text','Company','',()=>{},false);trap.className='honey';trap.tabIndex=-1;trap.setAttribute('aria-hidden','true');form.append(trap);
   form.append(button('widget-primary',loading?'…':state==='offline'?t('contact'):t('start'),()=>form.requestSubmit()));
   form.addEventListener('submit',e=>{e.preventDefault();if(!loading)startOrContact(trap.value);});panel.append(form);
   panel.append(button('link-button',state==='login'?t('offline'):t('back'),()=>{state=state==='login'?'offline':'login';error='';render();}));
   panel.append(el('p','privacy-copy',t('privacy')));
   errorView=el('p','chat-error',error);panel.append(errorView);wrap.append(panel);
  }else if(state==='chat'){
   lines=el('div','chat-lines');lines.id='chatLines';wrap.append(lines);
   typingView=el('p','typing',operatorTyping?t('typing'):'');wrap.append(typingView);
   const form=el('form','composer');composerInput=el('textarea','composer-input');composerInput.placeholder=t('message');composerInput.rows=2;composerInput.maxLength=3000;composerInput.value=draft;
   composerInput.addEventListener('input',()=>{draft=composerInput.value;guestTyping();});composerInput.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();form.requestSubmit();}});
   form.append(composerInput,button('send-btn','➤',()=>form.requestSubmit()));
   const emojis=el('div','emoji-row');['😊','👍','🙏','❤️','✨','👋'].forEach(emoji=>emojis.append(button('emoji-btn',emoji,()=>{draft=(draft+emoji).slice(0,3000);composerInput.value=draft;composerInput.focus();})));
   wrap.append(emojis);form.addEventListener('submit',e=>{e.preventDefault();sendGuest();});wrap.append(form);
   wrap.append(button('link-button end-chat',t('endChat'),()=>{if(window.confirm(t('endChat')+'?'))endGuest();}));
   errorView=el('p','chat-error',error);wrap.append(errorView);
  }else{
   const panel=el('div','form-content');panel.append(el('p','intro',state==='thankyou'?t('sent'):t('end')),button('widget-primary',t('newChat'),newChat));wrap.append(panel);
  }
  wrap.append(el('footer','widget-footer',t('powered')));root.replaceChildren(wrap);updateChat();if(state==='chat'&&lines&&forceScrollBottom){lines.scrollTop=lines.scrollHeight;forceScrollBottom=false;}
 }
 function startOrContact(trap){if(!contextOrigin){error=t('retry');render();return;}loading=true;error='';render();const mode=state;const body=Object.assign(context(),{name:name,email:email,language:language,company:trap});if(mode==='offline')body.message=offlineText;
  api(mode==='offline'?'contact':'guest_start',body).then(r=>{loading=false;if(mode==='offline'){state='thankyou';offlineText='';}else{conversation=r.conversation_id;token=r.token;messages=r.messages||[];after=messages.reduce((v,m)=>Math.max(v,Number(m.id)||0),0);keepSession();state='chat';}render();if(state==='chat'){forceScrollBottom=true;startPolling();}}).catch(e=>{loading=false;error=e.message;render();});
 }
 function sendGuest(){const body=draft.trim();if(!body||!conversation||!token||loading)return;forceScrollBottom=true;draft='';if(composerInput)composerInput.value='';loading=true;error='';updateChat();
  api('guest_send',{conversation_id:conversation,token:token,body:body}).then(()=>{loading=false;return poll();}).catch(e=>{loading=false;if(e.status===401||e.status===404)return resetExpired();draft=body+(draft?'\n'+draft:'');if(composerInput)composerInput.value=draft;error=e.message;updateChat();});
 }
 function endGuest(){if(!conversation||!token)return;api('guest_end',{conversation_id:conversation,token:token}).then(()=>{state='closed';stopPolling();render();}).catch(e=>{if(e.status===401||e.status===404)return resetExpired();error=e.message;updateChat();});}
 async function poll(){if(!conversation||!token||!open||state!=='chat'||pollBusy)return;pollBusy=true;
  try{const response=await api('guest_poll',{conversation_id:conversation,token:token,after_id:after});
   if(response.closed){state='closed';stopPolling();render();return;}
   if(Array.isArray(response.messages)&&response.messages.length){const have=new Set(messages.map(m=>Number(m.id)));for(const m of response.messages){if(!have.has(Number(m.id)))messages.push(m);}messages=messages.slice(-250);after=Math.max(after,...response.messages.map(m=>Number(m.id)||0));}
   operatorTyping=!!response.typing;error='';updateChat();
   if(response.messages&&response.messages.length===100)setTimeout(()=>poll(),0);
  }catch(e){if(e.status===401||e.status===404){resetExpired();return;}error=e.message;updateChat();}
  finally{pollBusy=false;}
 }
 function startPolling(){stopPolling();pollTimer=setInterval(()=>{if(!document.hidden)poll();},4000);poll();}
 function guestTyping(){const now=Date.now();if(now-lastTyping<4000)return;lastTyping=now;if(conversation&&token)api('guest_typing',{conversation_id:conversation,token:token}).catch(()=>{});}
 function handleParent(e){if(!site||!Array.isArray(site.allowed_origins)||!site.allowed_origins.includes(e.origin)||e.source!==parent)return;
  contextOrigin=e.origin;const d=e.data||{};
  if(d.type==='MCS_CONTEXT'&&typeof d.page_url==='string'){try{const u=new URL(d.page_url);if(u.origin===contextOrigin)contextPage=(u.origin+u.pathname).slice(0,1024);}catch(e){}}
  if(d.type==='MCS_SHOW'&&!open)toggle();if(d.type==='MCS_HIDE'&&open)toggle();if(d.type==='MCS_TOGGLE')toggle();
  if(d.type==='MCS_SEND'&&typeof d.message==='string'){
   draft=d.message.slice(0,3000);if(!open)toggle();if(state==='chat'&&conversation&&token){if(composerInput)composerInput.value=draft;sendGuest();}
   // Before visitor starts chat, retain as a draft; do not send before consent.
  }
 }
 function init(){fetch('/api.php?action=site&site='+encodeURIComponent(key),{credentials:'omit'}).then(async r=>{if(!r.ok)throw Error('Unavailable');return r.json();}).then(res=>{
  site=res.site;language=getStore('mcs-lang-'+key)==='mm'?'mm':site.default_language;
  let saved=null;try{saved=JSON.parse(getStore('mcs-session-'+key)||'null');}catch(e){removeStore('mcs-session-'+key);}
  if(saved&&Number.isSafeInteger(saved.conversation)&&saved.conversation>0&&/^[a-f0-9]{64}$/.test(saved.token||'')){conversation=saved.conversation;token=saved.token;state='chat';}else if(!site.online){state='offline';}
  render();parent.postMessage({type:'MCS_READY'},'*');if(state==='chat'){forceScrollBottom=true;startPolling();}
 }).catch(()=>{root.replaceChildren(el('p','chat-error','Chat is currently unavailable.'));});}
 window.addEventListener('message',handleParent);init();
})();
