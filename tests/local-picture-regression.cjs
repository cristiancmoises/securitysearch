'use strict';
const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const code=fs.readFileSync('static/local-background.js','utf8');
const KEY='securitysearch.background.v1', DATA='data:image/webp;base64,QUJDRA==';
const INPUT='data:application/octet-stream;base64,'+Buffer.from('\x89PNG\r\n\x1a\n'+'.'.repeat(32),'binary').toString('base64');
class El{
 constructor(){this.disabled=true;this.hidden=true;this.events={};this.style={setProperty:(k,v)=>this.paint=v,removeProperty:()=>this.paint=null};this.classList={add:()=>this.hasPicture=true,remove:()=>this.hasPicture=false};}
 addEventListener(k,fn){this.events[k]=fn;}change(){this.events.change?.();}click(){this.events.click?.();}removeAttribute(k){delete this[k];}
}
function fixture(opts={}){
 const els={};for(const id of ['background-file','background-remember','background-status','background-remove','background-preview'])els[id]=new El();
 const body=new El(),session=new Map(),local=new Map();if(opts.session)session.set(KEY,opts.session);if(opts.local)local.set(KEY,opts.local);
 const store=map=>({getItem:k=>map.get(k)||null,setItem:(k,v)=>{if(opts.quota)throw Error('quota');map.set(k,v);},removeItem:k=>map.delete(k)});
 const window={sessionStorage:store(session),localStorage:store(local)};
 if(opts.deniedLocal)Object.defineProperty(window,'localStorage',{get(){throw Error('blocked');}});
 let reads=0,draws=0;const pending=[],timers=new Map();let timerId=0;
 class Reader{readAsDataURL(){reads++;if(opts.readThrow)throw Error('read failed');this.result=opts.input||INPUT;if(opts.delayed)pending.push(()=>this.onload?.());else this.onload?.();}abort(){}}
 class Image{constructor(){this.naturalWidth=opts.large?100000:800;this.naturalHeight=600;}set src(v){opts.badDecode?this.onerror?.():this.onload?.();}}
 const document={body,getElementById:id=>els[id],createElement:name=>{assert.equal(name,'canvas');return {getContext:()=>opts.noCanvas?null:{drawImage:()=>draws++},toDataURL:()=>opts.badOutput?'bad-data':DATA};}};
 vm.runInNewContext(code,{window,document,Image,FileReader:Reader,Error,atob:s=>Buffer.from(s,'base64').toString('binary'),setTimeout:f=>{timers.set(++timerId,f);return timerId;},clearTimeout:id=>timers.delete(id)});
 const choose=(type='',size=100)=>{els['background-file'].files=[{type,size,name:'private-picture.png'}];els['background-file'].value='fakepath';els['background-file'].change();};
 return {els,body,session,local,pending,timers,choose,stats:()=>({reads,draws})};
}
let checks=0;const ok=(v,m)=>{assert(v,m);checks++;};
let f=fixture();ok(!f.els['background-file'].disabled,'Listener enables initially disabled input');f.choose();ok(f.body.hasPicture,'Blank MIME handled from bytes');ok(f.session.get(KEY)===DATA,'Tab default');ok(f.local.size===0,'No implicit persistence');ok(f.els['background-file'].value==='','Filename cleared');ok(f.els['background-preview'].src===DATA&&!f.els['background-preview'].hidden,'Visible local preview');ok(f.timers.size===0,'Timer released');
f.els['background-remember'].checked=true;f.els['background-remember'].change();ok(f.local.get(KEY)===DATA&&f.session.size===0,'Explicit persistence');
f.els['background-remember'].checked=false;f.els['background-remember'].change();ok(f.local.size===0&&f.session.get(KEY)===DATA,'Revert to tab');
f.els['background-remove'].click();ok(f.local.size+f.session.size===0&&!f.body.hasPicture&&f.els['background-preview'].hidden,'Complete removal');
f=fixture({session:DATA,deniedLocal:true});ok(f.body.hasPicture,'Session works when persistent storage blocked');f.els['background-remove'].click();ok(f.els['background-status'].textContent.includes('inaccessible'),'Removal does not pretend inaccessible storage was cleared');
f=fixture({local:DATA});ok(f.els['background-remember'].checked&&f.body.hasPicture,'Restore persistent choice');
f=fixture({session:'bad',local:DATA});ok(f.body.hasPicture&&f.session.size===0,'Bad tab data cannot mask valid persistent image');
for(const size of [0,17*1024*1024]){f=fixture();f.choose('image/png',size);ok(f.stats().reads===0,'Reject file size before reading');}
for(const head of ['<svg>','<html>','not-an-image']){f=fixture({input:'data:image/png;base64,'+Buffer.from(head).toString('base64')});f.choose();ok(f.stats().draws===0&&!f.body.hasPicture,'Do not trust false MIME labels');}
for(const head of ['\xff\xd8\xff...','GIF89a........','RIFF....WEBP....']){f=fixture({input:'data:;base64,'+Buffer.from(head,'binary').toString('base64')});f.choose();ok(f.body.hasPicture,'Magic family recognized');}
for(const opt of [{large:true},{noCanvas:true},{badDecode:true},{badOutput:true},{readThrow:true}]){f=fixture({...opt,local:DATA});f.choose();ok(f.body.hasPicture&&f.local.get(KEY)===DATA,'Failed replacement preserves previous picture');ok(f.timers.size===0,'Cleanup failed local decode');}
f=fixture({quota:true});f.choose();ok(f.body.hasPicture&&f.local.size+f.session.size===0,'Quota page-only fallback');
f=fixture({delayed:true});f.choose();f.els['background-remove'].click();f.pending[0]();ok(!f.body.hasPicture,'Canceled callback cannot restore picture');
f=fixture({delayed:true});f.choose();[...f.timers.values()][0]();f.pending[0]();ok(!f.body.hasPicture&&f.els['background-status'].textContent.includes('timed out'),'Decode deadline');
f=fixture({local:'https://evil.example/image'});ok(f.local.size===0&&!f.body.hasPicture,'Reject non-data storage');
ok(!/\b(fetch|XMLHttpRequest|sendBeacon|WebSocket)\s*\(/.test(code),'No network API');ok(!/document\.cookie|file\.name/.test(code),'No cookie or filename transmission');
console.log(`PASS: ${checks} local-picture assertions. Storage and decoder fixtures; actual browser decoding is tested separately.`);
