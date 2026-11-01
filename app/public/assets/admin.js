'use strict';
(function(){
 const $=(s)=>document.querySelector(s); const $$=(s)=>Array.from(document.querySelectorAll(s));
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const csrf=$('meta[name="csrf-token"]').content;
 const safeGet=k=>{try{return localStorage.getItem(k)}catch(e){return null}};
 const safeSet=(k,v)=>{try{localStorage.setItem(k,v)}catch(e){}};

 let availability='offline',inboxCursor=null,historyCursor=null,contactCursor=null,cannedCursor=null,chatHasOlder=false;
 let initial=null,tab='overview',selected=null,selectedHistorical=false,activeSiteId=null,conversations=[],chatMessages=[],sites=[],operators=[],soundEnabled=false,lastSeen=0,lastGuestMessageId=null,siteForSettings=null;
 const date=s=>{const d=new Date(String(s||'').replace(' ','T')+'Z');return Number.isNaN(d.valueOf())?'':d.toLocaleString()};
 const age=s=>{const d=new Date(String(s||'').replace(' ','T')+'Z');if(Number.isNaN(d.valueOf()))return '';const n=Math.max(0,Date.now()-d.valueOf());return n<60000?'just now':n<3600000?Math.floor(n/60000)+'m ago':n<86400000?Math.floor(n/3600000)+'h ago':date(s)};
 const api=async(action,payload=null,qs={})=>{const url=new URL('/api.php',location.href);url.searchParams.set('action',action);Object.entries(qs).forEach(([k,v])=>url.searchParams.set(k,String(v)));const opts=payload===null?{}:{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(payload)};const res=await fetch(url,opts);const data=await res.json();if(!res.ok)throw Error(data.error||'Request failed');return data;};
 let toastTimer;function toast(message){const node=$('#toast');node.textContent=message;node.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>node.classList.remove('visible'),4000)}
 function guard(fn){return async(...args)=>{try{await fn(...args)}catch(e){toast(e.message)}}}
 const tabs={overview:'Overview',inbox:'Live Inbox',history:'History',contacts:'Offline Messages',canned:'Quick Replies',operators:'Operators',sites:'Websites & Widget',settings:'Settings'};
 const tabsMm={overview:'အကျဉ်းချုပ်',inbox:'တိုက်ရိုက် စကားပြော',history:'မှတ်တမ်းများ',contacts:'ဆက်သွယ်ရန်စာများ',canned:'အသင့်သုံးစာများ',operators:'ဝန်ထမ်းများ',sites:'ဝဘ်ဆိုက်များ',settings:'ဆက်တင်များ'};
 const selectTab=guard(async(name)=>{tab=name;$$('.screen').forEach(s=>s.classList.toggle('active',s.id===tab));$$('#nav button[data-tab]').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));$('#pageTitle').textContent=($('#language').value==='mm'?tabsMm:tabs)[name]||name;$('.sidebar').classList.remove('open');if(name==='overview')await overview();if(name==='inbox')await inbox();if(name==='history')await history();if(name==='contacts')await contacts();if(name==='canned')await canned();if(name==='sites')sitesView();if(name==='operators')operatorsView();if(name==='settings')settingsView();});
 $$('#nav button[data-tab]').forEach(b=>b.addEventListener('click',()=>selectTab(b.dataset.tab)));
 $$('[data-jump]').forEach(b=>b.addEventListener('click',()=>selectTab(b.dataset.jump)));
 $('#mobileMenu').addEventListener('click',()=>$('.sidebar').classList.toggle('open'));
 $('#language').value=safeGet('mcs-admin-lang')==='mm'?'mm':'en';document.documentElement.lang=$('#language').value==='mm'?'my':'en';
 $('#language').addEventListener('change',()=>{document.documentElement.lang=$('#language').value==='mm'?'my':'en';safeSet('mcs-admin-lang',$('#language').value);$$('#nav button[data-tab]').forEach(b=>{const n=b.dataset.tab;b.firstChild.textContent=['overview','inbox','history','contacts','canned','operators','sites','settings'].includes(n)?({'overview':'▦','inbox':'◉','history':'◷','contacts':'✉','canned':'▤','operators':'♙','sites':'⌘','settings':'⚙'}[n]+'  '+($('#language').value==='mm'?tabsMm:tabs)[n]+' '):b.firstChild.textContent});$('#pageTitle').textContent=($('#language').value==='mm'?tabsMm:tabs)[tab];});
 function optionHTML(items,idKey='id',labelKey='name',placeholder='Select'){return `<option value="">${esc(placeholder)}</option>`+items.map(x=>`<option value="${esc(x[idKey])}">${esc(x[labelKey])}</option>`).join('')}
 function updateSelector(){['#settingsSite','#cannedSite'].forEach(s=>{const old=$(s).value;$(s).innerHTML=optionHTML(sites);$(s).value=old||String(sites[0]?.id||'')})}
 async function bootstrap(){const d=await api('bootstrap');initial=d;sites=d.sites;operators=d.operators;availability=d.user.availability||'offline';updateSelector();
  const f=$('#operatorSiteIds');if(f){f.innerHTML=sites.map(site=>`<option value="${esc(site.id)}">${esc(site.name)}</option>`).join('');}
  const state=$('#availability');if(state)state.value=availability;updateAvailabilityIndicator();
if(d.user.role!=='admin'){$('#operatorForm').hidden=true;$('#siteForm').hidden=true;$('#settingsForm').querySelector('button[type="submit"]').disabled=true;}await selectTab(tab);}
 async function overview(){const d=await api('overview');const values=[['Open chats',d.stats.open,'Live now'],['Total conversations',d.stats.all,'All time'],['Closed conversations',d.stats.closed,'Resolved'],['Offline requests',d.stats.contacts,'Needs attention']];$('#stats').innerHTML=values.map(([k,v,s])=>`<div class="metric-card"><small>${esc(k)}</small><strong>${esc(v)}</strong><span>${esc(s)}</span></div>`).join('');const r=await api('conversations',null,{status:'open'});$('#recent').innerHTML=r.conversations.length?r.conversations.slice(0,7).map(c=>`<div class="data-row"><span class="tag">#${esc(c.id)}</span><div><strong>${esc(c.guest_name)}</strong><small>${esc(c.site_name)}</small></div><div>${esc(c.last_message||'')}</div><small>${esc(age(c.last_message_at))}</small></div>`).join(''):'<div class="empty">No conversations yet. Embed your widget to start receiving chats.</div>';$('#unreadCounter').textContent=d.stats.open}
 async function inbox(loadMore=false){
  if(!loadMore)inboxCursor=null;
  const q={status:'open',...(inboxCursor?{before_id:inboxCursor.id,before_at:inboxCursor.at}:{})};
  const r=await api('conversations',null,q);
  conversations=loadMore?[...conversations,...r.conversations]:r.conversations;
  inboxCursor=r.next_before_id?{id:r.next_before_id,at:r.next_before_at}:null;
  renderThreads();
  if(selected&&(selectedHistorical||conversations.some(x=>Number(x.id)===Number(selected)))){
   if(!loadMore)await loadChat();
  }else if(selected){selected=null;$('#guestTitle').textContent='Select a conversation';$('#chatMessages').innerHTML='<div class="empty">Choose a visitor conversation.</div>';}
}
 function renderThreads(){$('#threads').innerHTML=conversations.length?conversations.map(c=>`<button class="thread ${Number(c.id)===Number(selected)?'selected':''}" data-conv="${esc(c.id)}"><div class="thread-top"><strong>${esc(c.guest_name)}</strong><small>${esc(age(c.last_message_at))}</small></div><p>${esc(c.last_message||'New conversation')}</p><small>${esc(c.site_name)} · ${c.assigned_operator_id?'Assigned':'Unassigned'}</small></button>`).join(''):'<p class="empty">No active conversations.</p>';
  if(inboxCursor)$('#threads').insertAdjacentHTML('beforeend','<button class="button outline" id="inboxMore" type="button">Older active chats ↓</button>');
  $('#inboxMore')?.addEventListener('click',()=>inbox(true).catch(e=>toast(e.message)));
  $$('[data-conv]').forEach(b=>b.addEventListener('click',()=>{selected=Number(b.dataset.conv);selectedHistorical=false;renderThreads();loadChat().catch(e=>toast(e.message))}));}
 async function loadChat(){if(!selected)return;const d=await api('messages',null,{conversation_id:selected});const c=d.conversation;chatMessages=d.messages;chatHasOlder=!!d.has_older;activeSiteId=Number(c.site_id);
  $('#guestTitle').textContent=c.guest_name+' · #'+c.id;const page=$('#guestPage');page.replaceChildren();if(c.page_url){const link=document.createElement('a');link.href=c.page_url;link.textContent=c.page_url;link.target='_blank';link.rel='noopener noreferrer';page.append('Viewing: ',link)}else page.textContent='Page unknown';
  $('#operatorAssign').innerHTML=optionHTML(operators.filter(o=>Number(o.is_active)===1),'id','name','Unassigned');$('#operatorAssign').value=c.assigned_operator_id||'';
  const canReply=c.status==='open'&&(initial.user.role==='admin'||Number(c.assigned_operator_id)===Number(initial.user.id));$('#sendReply').disabled=!canReply;$('#replyText').disabled=!canReply;$('#closeChat').disabled=!canReply;
  $('#typingStatus').textContent=d.typing?'Visitor is typing…':'';
  $('#chatMessages').innerHTML=(chatHasOlder?'<button id="loadOlderMessages" class="button outline" type="button">Load older messages ↑</button>':'')+chatMessages.map(m=>`<div class="chat-line ${m.sender_type==='operator'?'mine':m.sender_type==='system'?'system':''}"><span class="chat-bubble">${esc(m.body)}</span><time>${esc(date(m.created_at))}</time></div>`).join('')||'<div class="empty">No messages.</div>';$('#chatMessages').scrollTop=$('#chatMessages').scrollHeight;
  const older=$('#loadOlderMessages');if(older)older.onclick=guard(async()=>{
   const oldest=Number(chatMessages[0]?.id||0);if(!oldest)return;
   const response=await api('messages',null,{conversation_id:selected,before_id:oldest});
   const currentFirst=chatMessages[0]?.id;
   chatMessages=[...response.messages,...chatMessages];chatHasOlder=!!response.has_older;
   const box=$('#chatMessages');const scroll=box.scrollHeight;
   box.innerHTML=(chatHasOlder?'<button id="loadOlderMessages" class="button outline" type="button">Load older messages ↑</button>':'')+chatMessages.map(m=>`<div class="chat-line ${m.sender_type==='operator'?'mine':m.sender_type==='system'?'system':''}"><span class="chat-bubble">${esc(m.body)}</span><time>${esc(date(m.created_at))}</time></div>`).join('');
   box.scrollTop=box.scrollHeight-scroll;
   const button=$('#loadOlderMessages');if(button)button.onclick=older.onclick;
  });
  if(!c.assigned_operator_id){const action=document.createElement('button');action.className='button primary';action.textContent='Claim chat';action.onclick=guard(async()=>{await api('claim',{conversation_id:selected});await inbox()});$('#chatMessages').prepend(action)}
 }
 $('#operatorAssign').addEventListener('change',guard(async()=>{if(!selected)return;if(initial.user.role!=='admin'){toast('Admin only');return}await api('assign',{conversation_id:selected,operator_id:Number($('#operatorAssign').value)});await inbox()}));
 $('#closeChat').addEventListener('click',guard(async()=>{if(!selected||!confirm('End this conversation?'))return;await api('close',{conversation_id:selected});selected=null;await inbox()}));
 $('#sendReply').addEventListener('click',guard(async()=>{if(!selected)return;let body=$('#replyText').value.trim();const reply=quickReplies.find(x=>x.shortcut===body&&Number(x.site_id)===activeSiteId);if(reply)body=reply.body;if(!body)return;await api('reply',{conversation_id:selected,body});$('#replyText').value='';await loadChat()}));
 let typingAt=0;$('#replyText').addEventListener('input',()=>{if(!selected||Date.now()-typingAt<4000)return;typingAt=Date.now();api('operator_typing',{conversation_id:selected}).catch(()=>{})});
 $('#replyText').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();$('#sendReply').click()}});
 async function history(loadMore=false){if(!loadMore)historyCursor=null;const q=$('#historySearch').value;const d=await api('conversations',null,{status:'closed',q:q,...(historyCursor?{before_id:historyCursor.id,before_at:historyCursor.at}:{})});historyCursor=d.next_before_id?{id:d.next_before_id,at:d.next_before_at}:null;
  const html=d.conversations.length?d.conversations.map(c=>`<button type="button" class="data-row history-view" data-hist="${esc(c.id)}"><span class="tag closed">#${esc(c.id)}</span><div><strong>${esc(c.guest_name)}</strong><small>${esc(c.guest_email||'No email')}</small></div><div>${esc(c.last_message||'')}</div><small>${esc(date(c.last_message_at))}</small></button>`).join(''):'<div class="empty">No matching history yet.</div>';
  if(loadMore){$('#historyList').querySelector('#historyMore')?.remove();$('#historyList').insertAdjacentHTML('beforeend',html);}else $('#historyList').innerHTML=html;
  if(historyCursor)$('#historyList').insertAdjacentHTML('beforeend','<button class="button outline" id="historyMore" type="button">Older conversations ↓</button>');
  $('#historyMore')?.addEventListener('click',()=>history(true).catch(e=>toast(e.message)));
  $$('[data-hist]').forEach(b=>b.addEventListener('click',guard(async()=>{await selectTab('inbox');selected=Number(b.dataset.hist);selectedHistorical=true;await loadChat()})))}
 $('#historySearch').addEventListener('change',()=>history().catch(e=>toast(e.message)));
 async function contacts(loadMore=false){if(!loadMore)contactCursor=null;const d=await api('contacts',null,contactCursor?{before_id:contactCursor}:{});contactCursor=d.next_before_id;const html=d.contacts.length?d.contacts.map(x=>`<div class="data-row"><span class="tag ${x.status==='reviewed'?'closed':''}">${esc(x.status)}</span><div><strong>${esc(x.name)}</strong><small>${esc(x.email)} · ${esc(date(x.created_at))}</small></div><div>${esc(x.message)}</div><button class="button outline" data-reviewed="${esc(x.id)}" ${x.status==='reviewed'?'disabled':''}>Mark reviewed</button></div>`).join(''):'<div class="empty">No offline messages yet.</div>';
  if(loadMore){$('#contactList').querySelector('#contactMore')?.remove();$('#contactList').insertAdjacentHTML('beforeend',html);}else $('#contactList').innerHTML=html;
  if(contactCursor)$('#contactList').insertAdjacentHTML('beforeend','<button class="button outline" id="contactMore" type="button">Older requests ↓</button>');
  $('#contactMore')?.addEventListener('click',()=>contacts(true).catch(e=>toast(e.message)));
  $$('[data-reviewed]').forEach(b=>b.onclick=guard(async()=>{await api('contact_review',{id:Number(b.dataset.reviewed)});await contacts()}));}
 let quickReplies=[];async function canned(loadMore=false){if(!loadMore)cannedCursor=null;const d=await api('canned',null,cannedCursor?{before_id:cannedCursor}:{});cannedCursor=d.next_before_id;quickReplies=loadMore?[...quickReplies,...d.replies]:d.replies;const html=d.replies.length?d.replies.map(x=>`<div class="data-row"><span class="tag">${esc(x.shortcut)}</span><div><strong>${esc(x.body)}</strong></div><small>Site #${esc(x.site_id)}</small><span>Reusable</span></div>`).join(''):'<div class="empty">No replies yet.</div>';
  if(loadMore){$('#cannedList').querySelector('#cannedMore')?.remove();$('#cannedList').insertAdjacentHTML('beforeend',html);}else $('#cannedList').innerHTML=html;
  if(cannedCursor)$('#cannedList').insertAdjacentHTML('beforeend','<button class="button outline" id="cannedMore" type="button">Older replies ↓</button>');
  $('#cannedMore')?.addEventListener('click',()=>canned(true).catch(e=>toast(e.message)));
 }
 $('#cannedForm').addEventListener('submit',guard(async e=>{e.preventDefault();const f=new FormData(e.target);await api('canned_add',{site_id:Number($('#cannedSite').value),shortcut:String(f.get('shortcut')),body:String(f.get('body'))});e.target.reset();toast('Reply saved');await canned()}));
 function updateAvailabilityIndicator(){
  const led=$('.live-led');if(!led)return;
  led.dataset.status=availability;
  led.title='Your status: '+availability;
 }
 function reflectOwnAvailability(next){
  availability=next;
  const own=operators.find(o=>Number(o.id)===Number(initial?.user?.id));
  if(own)own.availability=next;
  if(initial?.user)initial.user.availability=next;
  $('#availability').value=next;
  updateAvailabilityIndicator();
  if(tab==='operators')operatorsView();
 }
 async function persistAvailability(next){
  const r=await api('availability',{status:next});
  reflectOwnAvailability(r.availability||next);
 }
 function operatorsView(){$('#operatorsList').innerHTML=operators.map(o=>`<div class="data-row"><span class="tag ${o.availability==='offline'?'closed':o.availability==='away'?'away':''}">${esc(o.availability)}</span><div><strong>${esc(o.name)}</strong><small>${esc(o.email)}</small></div><small>${esc(o.role)}</small><small>${Number(o.is_active)?'Active':'Disabled'}</small>${initial.user.role==='admin'&&o.role==='operator'?`<button type="button" class="button outline" data-op-sites="${esc(o.id)}">Website access</button>`:''}</div>`).join('');
  $$('[data-op-sites]').forEach(b=>b.onclick=guard(async()=>{
   const operatorId=Number(b.dataset.opSites);const current=(initial.operator_sites||[]).filter(x=>Number(x.user_id)===operatorId).map(x=>Number(x.site_id));
   const labels=sites.map(x=>`${x.id}: ${x.name}`).join('\n');
   const response=prompt('Allowed website IDs (comma separated):\n'+labels,current.join(','));if(response===null)return;
   const ids=response.trim()?response.split(',').map(x=>Number(x.trim())):[];
   if(ids.some(x=>!Number.isSafeInteger(x)||!sites.some(site=>Number(site.id)===x)))throw Error('Invalid website ID');
   await api('operator_sites',{operator_id:operatorId,site_ids:ids});toast('Website access saved');await bootstrap();
  }));}
 $('#operatorForm').addEventListener('submit',guard(async e=>{e.preventDefault();const f=new FormData(e.target);const data=Object.fromEntries(f.entries());data.site_ids=Array.from($('#operatorSiteIds').selectedOptions).map(o=>Number(o.value));await api('operator_add',data);e.target.reset();toast('Operator created');await bootstrap()}));
 function sitesView(){$('#sitesList').innerHTML=sites.map(s=>{const snippet=`<script src="${initial.public_url}/widget.js" data-site-id="${s.site_key}" async><\/script>`;return `<article class="site-card"><span class="tag">Website #${esc(s.id)}</span><h2>${esc(s.name)}</h2><p>${esc((JSON.parse(s.allowed_origins||'[]')).join(', '))}</p><p>Paste this embed on your website before &lt;/body&gt;:</p><pre>${esc(snippet)}</pre><button class="button outline" data-copy="${esc(s.site_key)}">Copy embed code</button></article>`}).join('');$$('[data-copy]').forEach(b=>b.addEventListener('click',guard(async()=>{const s=sites.find(x=>x.site_key===b.dataset.copy);if(!s)return;const snippet=`<script src="${initial.public_url}/widget.js" data-site-id="${s.site_key}" async></script>`;await navigator.clipboard.writeText(snippet);toast('Widget code copied')})))}
 $('#siteForm').addEventListener('submit',guard(async e=>{e.preventDefault();const f=new FormData(e.target);await api('site_add',Object.fromEntries(f.entries()));e.target.reset();toast('Website created');await bootstrap();await selectTab('sites')}));
 const colorNative=$('#primaryColorNative'),colorHex=$('#primaryColorHex'),colorSwatch=$('#primaryColorPreview');
 const hexValid=(v)=>/^#[0-9a-fA-F]{6}$/.test(v||'');
 function syncColorControl(value){
  const next=hexValid(value)?value.toUpperCase():'#3865D8';
  colorNative.value=next;colorHex.value=next;colorSwatch.style.backgroundColor=next;
  colorHex.setCustomValidity('');
 }
 colorNative.addEventListener('input',()=>syncColorControl(colorNative.value));
 colorHex.addEventListener('input',()=>{
  const v=colorHex.value.trim();
  if(hexValid(v)){colorNative.value=v;colorSwatch.style.backgroundColor=v;colorHex.setCustomValidity('');}
  else colorHex.setCustomValidity('Use #RRGGBB (six hex digits).');
 });
 colorHex.addEventListener('blur',()=>{if(hexValid(colorHex.value))colorHex.value=colorHex.value.toUpperCase()});
 function settingsView(){const site=sites.find(x=>String(x.id)===$('#settingsSite').value)||sites[0];if(!site)return;siteForSettings=site;const f=$('#settingsForm');['site_id','primary_color','default_language','sound','widget_title_en','widget_title_mm','welcome_en','welcome_mm','allowed_origins','contact_email'].forEach(k=>{const value=k==='site_id'?String(site.id):k==='allowed_origins'?(JSON.parse(site.allowed_origins||'[]')).join('\n'):String(site[k]??'');if(f.elements[k])f.elements[k].value=value});f.elements.collect_email.checked=Number(site.collect_email)===1;syncColorControl(site.primary_color);}
 $('#settingsSite').addEventListener('change',settingsView);
 $('#settingsForm').addEventListener('submit',guard(async e=>{e.preventDefault();const f=e.target;const data=Object.fromEntries(new FormData(f).entries());data.site_id=Number(data.site_id);data.collect_email=f.elements.collect_email.checked;await api('site_update',data);toast('Settings saved');await bootstrap();await selectTab('settings')}));
 const brandLogoImg=$('#brandLogoPreview'),sidebarBrandImg=$('#sidebarBrandImage');
 function refreshBrandLogo(){
  for(const img of [brandLogoImg,sidebarBrandImg]){
   const container=img.parentElement;
   img.onload=()=>container.classList.add('has-logo');
   img.onerror=()=>container.classList.remove('has-logo');
   container.classList.remove('has-logo');
   img.src='/brand-logo.php?rev='+Date.now();
  }
 }
 refreshBrandLogo();
 $('#brandLogoForm').addEventListener('submit',guard(async e=>{
  e.preventDefault();const f=e.target;const file=$('#brandLogoFile').files?.[0];
  if(!file){toast('Select a logo image first');return;}
  if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>4*1024*1024){toast('Use PNG/JPG/WebP under 4 MB');return;}
  const res=await fetch('/brand-logo-upload.php',{method:'POST',body:new FormData(f),credentials:'same-origin'});
  const data=await res.json();if(!res.ok)throw Error(data.error||'Upload failed');
  f.reset();refreshBrandLogo();$('#brandLogoFeedback').textContent='Logo uploaded. Reload the guest demo to see the updated chat header.';toast('Logo saved');
 }));
 $('#removeBrandLogo').addEventListener('click',guard(async()=>{
  if(!window.confirm('Remove the uploaded brand logo?'))return;
  const body=new FormData();body.append('csrf',csrf);body.append('action','remove');
  const res=await fetch('/brand-logo-upload.php',{method:'POST',body,credentials:'same-origin'});
  const data=await res.json();if(!res.ok)throw Error(data.error||'Unable to remove logo');
  refreshBrandLogo();$('#brandLogoFeedback').textContent='Brand logo removed. Default sparkle icon restored.';toast('Logo removed');
 }));
 $('#enableNotifications').addEventListener('click',async()=>{soundEnabled=true;try{const C=window.AudioContext||window.webkitAudioContext;if(C){audio=audio||new C();await audio.resume();}}catch(e){}if('Notification'in window){if(Notification.permission==='default')await Notification.requestPermission();toast(Notification.permission==='granted'?'Notifications enabled':'Sounds enabled; notification permission not granted')}else toast('Sound alerts enabled')});
 let audio=null;
 const soundPatterns={chime:[660,990],ping:[880],bell:[523,784,1047],soft:[440],double:[660,660],bright:[880,1175],wood:[320,430],ripple:[550,660,770],crystal:[1047,1319],pop:[500,800],pulse:[660,660,880],none:[]};
 function ring(sound='chime'){if(!soundEnabled)return;const notes=soundPatterns[sound]||soundPatterns.chime;if(!notes.length)return;try{const C=window.AudioContext||window.webkitAudioContext;audio=audio||new C();if(audio.state==='suspended')audio.resume().catch(()=>{});notes.forEach((hz,i)=>{const when=audio.currentTime+i*.12;const o=audio.createOscillator(),g=audio.createGain();o.type=sound==='wood'?'triangle':'sine';o.frequency.value=hz;g.gain.setValueAtTime(.0001,when);g.gain.exponentialRampToValueAtTime(.055,when+.02);g.gain.exponentialRampToValueAtTime(.0001,when+.2);o.connect(g).connect(audio.destination);o.start(when);o.stop(when+.22)})}catch(e){}}
 async function refreshAlerts(){
  const r=await api('alerts',null,{after_id:lastGuestMessageId??0,baseline:lastGuestMessageId===null?'1':'0'});
  if(lastGuestMessageId===null){lastGuestMessageId=Number(r.latest_id)||0;return;}
  if(!r.alerts?.length)return;
  lastGuestMessageId=Math.max(lastGuestMessageId,...r.alerts.map(x=>Number(x.id)||0));
  const snd=sites.find(x=>Number(x.id)===Number(r.alerts[r.alerts.length-1].site_id))?.sound||'chime';
  ring(snd);
  if('Notification'in window&&Notification.permission==='granted')new Notification('Myan Chat Support',{body:'New visitor message in your inbox.'});
  if(document.hidden)document.title='● Myan Chat Support';
 }
 window.addEventListener('focus',()=>{document.title='Dashboard · Myan Chat Support'});
 async function backgroundRefresh(){try{await refreshAlerts();if(document.hidden)return;if(tab==='overview')await overview();if(tab==='operators'){const d=await api('bootstrap');operators=d.operators;operatorsView();}if(tab==='inbox'&&!selectedHistorical&&!inboxCursor)await inbox();const d=await api('conversations',null,{status:'open'});lastSeen=d.conversations.length;}catch(e){}}

 setInterval(backgroundRefresh,9000);
 setInterval(()=>{if(!document.hidden)persistAvailability(availability).catch(()=>{});},30000);
 $('#availability')?.addEventListener('change',()=>{const next=$('#availability').value;persistAvailability(next).catch(e=>{$('#availability').value=availability;toast(e.message)});});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)persistAvailability(availability).catch(()=>{})});
 bootstrap().then(async()=>{await persistAvailability(availability);await refreshAlerts()}).catch(e=>toast(e.message));
})();
