/* Myan Chat Support v1.0.2 — embeddable widget. No external dependencies. */
(function(){
 'use strict';
 if(window.MyanChat && window.MyanChat.__installed)return;
 var current=document.currentScript;
 if(!current)return;
 var key=current.getAttribute('data-site-id')||'';
 if(!/^[a-f0-9]{48}$/.test(key)){console.warn('[MyanChat] Missing valid data-site-id');return;}
 var base=new URL(current.getAttribute('data-chat-url')||current.src,document.baseURI).origin;
 var frame=document.createElement('iframe');
 frame.title='Myan Chat Support';frame.setAttribute('aria-label','Customer support chat');
 frame.setAttribute('allow','clipboard-write');
 frame.src=base+'/widget.php?site='+encodeURIComponent(key)+'&v=1.0.2';
 Object.assign(frame.style,{position:'fixed',right:'18px',bottom:'16px',zIndex:'2147483000',width:'min(390px, calc(100vw - 24px))',height:'72px',border:'0',background:'transparent',colorScheme:'normal',transition:'height 160ms ease',maxWidth:'100vw'});
 var open=false;var autoOpen=Number(current.getAttribute('data-auto-open')||'0');var width=Math.min(500,Math.max(300,Number(current.getAttribute('data-width'))||390));var height=Math.min(800,Math.max(400,Number(current.getAttribute('data-height'))||600));
 function sendPage(){try{frame.contentWindow.postMessage({type:'MCS_CONTEXT',page_url:location.origin+location.pathname},base);}catch(e){}}
 function resize(visible){open=visible;frame.style.width=visible?'min('+width+'px, calc(100vw - 16px))':'min('+width+'px, calc(100vw - 24px))';frame.style.height=visible?(innerWidth<600?'min(92dvh, '+height+'px)':'min('+height+'px, calc(100dvh - 24px))'):'72px';frame.style.right=visible&&innerWidth<600?'8px':'18px';frame.style.bottom=visible&&innerWidth<600?'8px':'16px';}
 function post(type,payload){if(frame.contentWindow){frame.contentWindow.postMessage(Object.assign({type:type},payload||{}),base);}}
 frame.addEventListener('load',sendPage);
 window.addEventListener('message',function(e){if(e.source!==frame.contentWindow||e.origin!==base)return;if(e.data&&e.data.type==='MCS_RESIZE')resize(!!e.data.open);if(e.data&&e.data.type==='MCS_READY'){sendPage();if(autoOpen>0&&autoOpen<=60)setTimeout(function(){post('MCS_SHOW')},autoOpen*1000)}});
 window.addEventListener('resize',function(){resize(open)});
 window.addEventListener('popstate',sendPage);window.addEventListener('hashchange',sendPage);
 document.addEventListener('visibilitychange',function(){if(!document.hidden)sendPage()});
 setInterval(function(){if(!document.hidden)sendPage()},15000);
 (document.body||document.documentElement).appendChild(frame);
 window.MyanChat={__installed:true,show:function(){post('MCS_SHOW');},hide:function(){post('MCS_HIDE');},toggle:function(){post('MCS_TOGGLE');},sendMessage:function(text){if(typeof text==='string'){post('MCS_SEND',{message:text.slice(0,3000)});post('MCS_SHOW');}}};
})();
