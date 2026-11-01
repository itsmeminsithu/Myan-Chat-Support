// Myan Chat Support — static demo. Runs only in this browser (localStorage).
// No server, no messages leave this device. Open the user panel and the
// admin panel in two tabs and they stay in sync.
(function(){
  var KEY='mcs_demo_v1';
  function load(){ try{ return JSON.parse(localStorage.getItem(KEY))||null; }catch(e){ return null; } }
  function save(m){ localStorage.setItem(KEY, JSON.stringify(m)); }
  function seed(){
    var m=[{from:'operator',text:'Hello, welcome to Myan Chat Support. How can we help you today?',ts:Date.now()}];
    save(m); return m;
  }
  function msgs(){ return load()||seed(); }
  function push(from,text){ var m=msgs(); m.push({from:from,text:text,ts:Date.now()}); save(m); return m; }
  function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
  function render(box){
    var m=msgs();
    box.innerHTML=m.map(function(x){
      return '<div class="mcs-msg '+(x.from==='guest'?'guest':'operator')+'"><p>'+esc(x.text)+'</p><small>'+new Date(x.ts).toLocaleTimeString()+'</small></div>';
    }).join('');
    box.scrollTop=box.scrollHeight;
    var last=m[m.length-1];
    var t=document.getElementById('mcsThreadLast'); if(t&&last) t.textContent=last.text;
    var c=document.getElementById('mcsThreadCount'); if(c) c.textContent=m.length+' messages';
  }
  window.MCSDemo={msgs:msgs,push:push,render:render,reset:function(){ localStorage.removeItem(KEY); seed(); }};
})();
