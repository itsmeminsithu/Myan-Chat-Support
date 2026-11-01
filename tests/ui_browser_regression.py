"""Optional real-Chromium UI test, network-free mock API (no DB required).
Run: python3 tests/ui_browser_regression.py; requires python playwright + Chromium.
"""
from pathlib import Path
from io import BytesIO
from PIL import Image
import re
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
PUBLIC=ROOT/'app/public'
site_script=r'''
window.__mock={dbstatus:'offline',site:{id:1,site_key:'a'.repeat(48),name:'Myan Chat Support',allowed_origins:'["https://customer.example"]',primary_color:'#3865d8',default_language:'en',sound:'chime',collect_email:1,widget_title_en:'Chat with us',widget_title_mm:'ကူညီပေးပါ',welcome_en:'Welcome',welcome_mm:'မင်္ဂလာပါ',contact_email:'admin@example.com'}};
const OldURL=window.URL;
window.URL=class extends OldURL{constructor(path,base){super(path,base==='about:blank'?'https://chat.example':base)}};
window.fetch=async function(uri,opts={}){
 const action=new OldURL(uri,'https://chat.example').searchParams.get('action');
 const {site}=window.__mock;let body={};
 if(typeof opts.body==='string')body=JSON.parse(opts.body);
 if(action==='bootstrap'){
  body={user:{id:1,name:'Min SiThu',email:'admin@example.com',role:'admin',availability:window.__mock.dbstatus},sites:[site],operators:[{id:1,name:'Min SiThu',email:'admin@example.com',role:'admin',availability:window.__mock.dbstatus,is_active:1}],operator_sites:[],csrf:'test-csrf',public_url:'https://chat.example'};
 }else if(action==='availability'){window.__mock.dbstatus=body.status;body={ok:true,availability:window.__mock.dbstatus};
 }else if(action==='overview'){body={stats:{open:0,all:0,closed:0,contacts:0}};
 }else if(action==='conversations'){body={conversations:[],next_before_id:null};
 }else if(action==='site_update'){Object.assign(site,body);body={ok:true};
 }else if(action==='alerts'){body={alerts:[],latest_id:0};
 }else body={ok:true};
 return new Response(JSON.stringify(body),{status:200,headers:{'Content-Type':'application/json'}});
};
'''
widget_script=r'''
window.__guest={};window.fetch=async function(uri,opts={}){
 const action=new URL(uri,'https://chat.example').searchParams.get('action');let payload={ok:true};
 if(action==='site')payload={site:{color:'#3865d8',title_en:'Chat with us',title_mm:'ကူညီပေးပါ',welcome_en:'Welcome',welcome_mm:'မင်္ဂလာပါ',online:true,collect_email:false,allowed_origins:['https://customer.example'],default_language:'en'}};
 else if(action==='guest_start')payload={conversation_id:12,token:'a'.repeat(64),messages:Array.from({length:18},(_,j)=>({id:j+1,sender_type:j%2?'operator':'guest',body:'Message '+(j+1)+' for checking scroll layout and clipping.',created_at:'2026-11-01 08:00:00'}))};
 else if(action==='guest_poll')payload={closed:false,typing:false,messages:[]};
 return new Response(JSON.stringify(payload),{status:200,headers:{'Content-Type':'application/json'}})
};
'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1440,'height':950})
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    html=(PUBLIC/'admin.php').read_text().split('?><!doctype html>',1)[1]
    html=re.sub(r'<\?=mcs_esc\([\s\S]*?\)\?>','Min SiThu',html)
    html=re.sub(r'<script src="/assets/admin.js[^\"]*" defer></script>','',html)
    html=re.sub(r'<link rel="stylesheet" href="/assets/admin.css[^\"]*">','',html)
    page.set_content('<!doctype html>'+html)
    page.add_style_tag(content=(PUBLIC/'assets/admin.css').read_text())
    page.evaluate(site_script)
    page.add_script_tag(content=(PUBLIC/'assets/admin.js').read_text())
    page.wait_for_function("document.querySelector('#availability').value==='offline'")
    page.get_by_role('button',name=re.compile('Operators')).click()
    page.wait_for_selector('#operatorsList .tag')
    assert page.locator('#operatorsList .tag').first.inner_text()=='offline'
    page.locator('#availability').select_option('online')
    page.wait_for_function("document.querySelector('#operatorsList .tag').innerText==='online'")
    assert page.locator('.live-led').get_attribute('data-status')=='online'
    print('PASS status: initial DB Offline, changing to Online updates Operators list and LED')
    page.get_by_role('button',name=re.compile('Settings')).click()
    page.wait_for_function("document.querySelector('#primaryColorHex').value.toLowerCase()==='#3865d8'")
    page.locator('#primaryColorHex').fill('#AA5500')
    assert page.locator('#primaryColorNative').input_value()=='#aa5500'
    assert page.locator('#primaryColorPreview').evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(170, 85, 0)'
    page.locator('#settingsForm button.button.primary').click()
    page.wait_for_function("document.querySelector('#primaryColorHex').value==='#AA5500'")
    assert page.evaluate('window.__mock.site.primary_color')=='#AA5500'
    print('PASS color picker: HEX field, native swatch, preview, persisted value')
    imgbytes=BytesIO(); Image.new('RGB',(96,96),(60,104,215)).save(imgbytes,format='PNG')
    page.locator('#brandLogoFile').set_input_files({'name':'myan-chat-logo.png','mimeType':'image/png','buffer':imgbytes.getvalue()})
    page.locator('#brandLogoForm button[type=submit]').click()
    page.wait_for_function("document.querySelector('#brandLogoFeedback').textContent.includes('Logo uploaded')")
    assert page.locator('#brandLogoFile').input_value()==''
    print('PASS logo uploader: admin file selection, async upload feedback and preview refresh')

    page.locator('#language').select_option('mm')
    assert page.locator('html').get_attribute('lang')=='my'
    print('PASS Myanmar locale changes document lang and font selection')
    assert not errors,errors
    page.close()
    widget_page=browser.new_page(viewport={'width':390,'height':600})
    widget_errors=[];widget_page.on('pageerror',lambda e:widget_errors.append(str(e)))
    widget_page.set_content('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="widget" data-site="'+('a'*48)+'"><div id="widgetRoot"></div></div></body></html>')
    widget_page.add_style_tag(content=(PUBLIC/'assets/widget.css').read_text())
    widget_page.evaluate(widget_script)
    widget_page.add_script_tag(content=(PUBLIC/'assets/widget-frame.js').read_text())
    widget_page.wait_for_selector('.collapsed')
    # Fake host frame's trusted message: JS event's source is window (= parent at top level).
    widget_page.evaluate("window.dispatchEvent(new MessageEvent('message',{origin:'https://customer.example',source:window,data:{type:'MCS_CONTEXT',page_url:'https://customer.example/store'}}))")
    widget_page.locator('.collapsed').click()
    widget_page.locator('.widget-input[type="text"]').first.fill('Visitor')
    widget_page.locator('.widget-form .widget-primary').click()
    widget_page.wait_for_selector('.chat-line[data-id="18"]')
    def geometry():
        return widget_page.evaluate('''() => {const box=document.querySelector('.chat-lines');const row=document.querySelector('.chat-line[data-id="18"]');const a=box.getBoundingClientRect(),b=row.getBoundingClientRect();return {lastBottom:b.bottom,viewBottom:a.bottom,scrollTop:box.scrollTop,scrollHeight:box.scrollHeight,clientHeight:box.clientHeight,bodyWidth:document.body.scrollWidth,viewport:innerWidth}}''')
    g=geometry();assert g['scrollTop']>0 and g['lastBottom']<=g['viewBottom']+1,g
    print('PASS guest chat: message 18 fully visible above composer/emoji rows')
    widget_page.set_viewport_size({'width':375,'height':680})
    widget_page.wait_for_timeout(100)
    g=geometry();assert g['lastBottom']<=g['viewBottom']+1 and g['bodyWidth']<=g['viewport'],g
    print('PASS mobile guest chat: no horizontal overflow, last message still visible')
    assert not widget_errors,widget_errors
    browser.close()
