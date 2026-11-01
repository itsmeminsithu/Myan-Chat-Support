'use strict';
// Dependency-free headless DOM simulation. Does NOT substitute real browser QA.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {
 constructor(tag='div'){this.tag=tag;this.children=[];this.listeners={};this.style={setProperty(){}};this.dataset={};this.value='';this.focused=false;this.scrollHeight=100;this.scrollTop=0;this.clientHeight=50;this.textContent='';}
 set className(v){this._class=v;}get className(){return this._class||'';}
 append(...parts){this.children.push(...parts);}
 replaceChildren(...parts){this.children=[...parts];}
 addEventListener(n,f){(this.listeners[n]??=[]).push(f);}
 dispatch(n,arg={}){for(const fn of this.listeners[n]||[])fn({preventDefault(){},...arg});}
 requestSubmit(){this.dispatch('submit');}
 focus(){this.focused=true;}
 setAttribute(k,v){this[k]=v;}
}
function find(root,cls){if((root.className||'').split(' ').includes(cls))return root;for(const c of root.children){if(typeof c==='object'){const f=find(c,cls);if(f)return f;}}return null;}
async function flush(){await new Promise(setImmediate);await new Promise(setImmediate);}
const mount=new Node('div'),root=new Node('div');mount.dataset.site='a'.repeat(48);
const site={color:'#3865D8',title_en:'Support',title_mm:'ကူညီရေး',welcome_en:'Hi',welcome_mm:'မင်္ဂလာပါ',collect_email:true,default_language:'en',online:true,allowed_origins:['https://customer.example']};
let sent=0,requests=[],polls=0,sessionExpired=false,callback;
const fetch=async(url,{body}={})=>{
 const action=new URL(url,'https://chat.example').searchParams.get('action');const data=body?JSON.parse(body):{};requests.push({action,data});
 if(action==='site')return {ok:true,json:async()=>({site})};
 if(action==='guest_start')return {ok:true,json:async()=>({conversation_id:33,token:'b'.repeat(64),messages:[{id:1,body:'Hi',sender_type:'system'}]})};
 if(action==='guest_send'){sent++;return {ok:true,json:async()=>({ok:true,message_id:2})};}
 if(action==='guest_poll'){
  if(sessionExpired)return {ok:false,status:401,json:async()=>({error:'Session not found'})};
  polls++;return {ok:true,json:async()=>({messages:polls===1?[{id:2,body:'Response',sender_type:'operator'}]:[],typing:false,closed:false})};
 }
 return {ok:true,json:async()=>({ok:true})};
};
const windowObj={listeners:{},addEventListener(n,fn){this.listeners[n]=fn;},confirm:()=>true};
const context={document:{documentElement:{},getElementById(id){return id==='widget'?mount:id==='widgetRoot'?root:null;},createElement(tag){return new Node(tag);},hidden:false},window:windowObj,parent:{postMessage(){}},fetch,URL,Intl,Date,Number,Set,Array,JSON,Promise,Error,queueMicrotask,setTimeout(){},setInterval(fn){callback=fn;return 1;},clearInterval(){},localStorage:{getItem(){throw Error('Storage blocked');},setItem(){throw Error('Storage blocked');},removeItem(){throw Error('Storage blocked');}}};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../app/public/assets/widget-frame.js'),'utf8'),context);
(async()=>{
 await flush();assert(find(root,'collapsed'),'widget loads when localStorage denied');
 windowObj.listeners.message({source:context.parent,origin:'https://customer.example',data:{type:'MCS_CONTEXT',page_url:'https://customer.example/products?token=SECRET#private'}});
 windowObj.listeners.message({source:context.parent,origin:'https://customer.example',data:{type:'MCS_SHOW'}});
 let form=find(root,'widget-form');assert(form,'login form available');
 const inputs=[];(function walk(n){if(n.className==='widget-input'&&n.type==='text')inputs.push(n);for(const c of n.children)if(typeof c==='object')walk(c);})(root);
 inputs[0].value='Visitor';inputs[0].dispatch('input');form.requestSubmit();await flush();await flush();
 assert(find(root,'composer-input'),'chat started with blocked localStorage');
 const composer=find(root,'composer-input');composer.focus();composer.value='A long message being typed';composer.dispatch('input');
 callback();await flush();await flush();
 assert.equal(find(root,'composer-input'),composer,'poll does not replace composer DOM node');
 assert.equal(composer.value,'A long message being typed','draft preserved during poll');
 assert.equal(composer.focused,true,'typing focus preserved during poll');
 assert(requests.some(r=>r.action==='guest_start'&&r.data.page_url==='https://customer.example/products'),'page context strips tokens');
 windowObj.listeners.message({source:context.parent,origin:'https://customer.example',data:{type:'MCS_SEND',message:'API actual send'}});await flush();await flush();
 assert.equal(sent,1,'sendMessage delivers when chat already started');
 sessionExpired=true;callback();await flush();await flush();
 assert(find(root,'widget-form'),'expired token recovers to start-new-chat form');
 console.log('PASS 9 widget interaction assertions: blocked storage, chat startup, stable composer, draft, focus, URL privacy, direct send, expiry recovery');
})().catch(e=>{console.error(e);process.exit(1);});
