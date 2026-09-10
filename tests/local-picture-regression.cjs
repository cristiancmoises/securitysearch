'use strict';
const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const code=fs.readFileSync('static/local-background.js','utf8');const KEY='securitysearch.background.v1',DATA='data:image/webp;base64,QUJDRA==';
class El{constructor(){this.events={};this.style={setProperty:(k,v)=>this.paint=v,removeProperty:()=>this.paint=null};this.classList={add:()=>this.hasPicture=true,remove:()=>this.hasPicture=false};}addEventListener(k,fn){this.events[k]=fn;}change(){this.events.change?.();}click(){this.events.click?.();}}
function fixture(opts={}){const els={};for(const id of ['background-file','background-remember','background-status','background-remove'])els[id]=new El();const body=new El();const session=new Map(),local=new Map();if(opts.session)session.set(KEY,opts.session);if(opts.local)local.set(KEY,opts.local);
const store=map=>({getItem:k=>map.get(k)||null,setItem:(k,v)=>{if(opts.quota)throw Error('quota');map.set(k,v);},removeItem:k=>map.delete(k)});
const window={sessionStorage:store(session),localStorage:store(local)};if(opts.deniedLocal)Object.defineProperty(window,'localStorage',{get(){throw Error('blocked');}});
let reads=0,draws=0;const pending=[];
class Reader{readAsDataURL(){reads++;this.result=DATA;if(opts.delayed)pending.push(()=>this.onload());else this.onload();}}
class Image{constructor(){this.width=opts.large?50000:800;this.height=600;}set src(v){this.onload();}}
const document={body,getElementById:id=>els[id],createElement:name=>{assert.equal(name,'canvas');return {getContext:()=>({drawImage:()=>draws++}),toDataURL:()=>DATA};}};
vm.runInNewContext(code,{window,document,Image,FileReader:Reader,Error});
const choose=(type='image/png',size=100)=>{els['background-file'].files=[{type,size,name:'private-picture.png'}];els['background-file'].value='fakepath';els['background-file'].change();};
return {els,body,session,local,pending,choose,stats:()=>({reads,draws})};}
let f=fixture();f.choose();assert(f.body.hasPicture);assert.equal(f.session.get(KEY),DATA);assert.equal(f.local.size,0);assert.equal(f.els['background-file'].value,'');
f.els['background-remember'].checked=true;f.els['background-remember'].change();assert.equal(f.local.get(KEY),DATA);assert.equal(f.session.size,0);
f.els['background-remember'].checked=false;f.els['background-remember'].change();assert.equal(f.local.size,0);assert.equal(f.session.get(KEY),DATA);
f.els['background-remove'].click();assert.equal(f.local.size+f.session.size,0);assert(!f.body.hasPicture);
f=fixture({session:DATA,deniedLocal:true});assert(f.body.hasPicture,'Session works when persistent storage blocked');
f=fixture({local:DATA});assert(f.els['background-remember'].checked);assert(f.body.hasPicture);
for(const [type,size]of[['image/svg+xml',100],['image/png',0],['image/webp',9*1024*1024]]){f=fixture();f.choose(type,size);assert.equal(f.stats().reads,0);}
f=fixture({large:true});f.choose();assert.equal(f.stats().draws,0);assert(!f.body.hasPicture);
f=fixture({quota:true});f.choose();assert(f.body.hasPicture);assert.equal(f.local.size+f.session.size,0);
f=fixture({delayed:true});f.choose();f.els['background-remove'].click();f.pending[0]();assert(!f.body.hasPicture,'Late file callback cannot restore removed picture');
f=fixture({local:'https://evil.example/image'});assert.equal(f.local.size,0);assert(!f.body.hasPicture);
assert(!/\b(fetch|XMLHttpRequest|sendBeacon|WebSocket)\s*\(/.test(code),'No network API in local image controller');
console.log('PASS: session default, explicit persistence, removal, denied storage, MIME/size/pixel bounds, quota and stale callbacks. Storage/image decoding are fixtures.');
