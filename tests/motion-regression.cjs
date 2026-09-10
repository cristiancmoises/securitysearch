'use strict';
// Browser-independent event tests; real-browser coverage is separate.
const assert=require('node:assert/strict'), vm=require('node:vm'), fs=require('node:fs');
const script=fs.readFileSync('static/images-motion.js','utf8');
class E extends EventTarget {
 constructor(){super();this.attrs={};this.nodeType=1;this.children=[];this.hidden=false;}
 getAttribute(k){return this.attrs[k]??null;} setAttribute(k,v){this.attrs[k]=String(v);} removeAttribute(k){delete this.attrs[k];}
 set src(v){this.attrs.src=v;} get src(){return this.attrs.src??'';}
 appendChild(n){n.parent=this;this.children.push(n);return n;}
 remove(){if(this.parent)this.parent.children=this.parent.children.filter(n=>n!==this);this.parent=null;}
 querySelectorAll(){return this.children.flatMap(n=>[...(n.attrs['data-motion']?[n]:[]),...n.querySelectorAll()]);}
 contains(n){return n===this||this.children.some(c=>c.contains(n));}
 closest(s){return this.kind===s?this:this.parent?.closest(s)||null;}
 matches(s){return s==='img[data-motion]'&&!!this.attrs['data-motion'];}
}
function fixture(options={}){
 const grid=new E(),makeImage=id=>{const card=new E();card.kind='.image';const anchor=new E();anchor.kind='.thumb';card.appendChild(anchor);const img=new E();img.src='/proxy?i='+id+'&s=poster';img.setAttribute('data-motion','/proxy?i='+id+'&s=animated');anchor.appendChild(img);grid.appendChild(card);return img;};
 const images=Array.from({length:6},(_,i)=>makeImage(i));
 const document=new E();document.getElementById=()=>grid;document.createElement=()=>new E();document.hidden=!!options.hidden;
 const reduced=new E();reduced.matches=!!options.reduced;const connection=new E();connection.saveData=!!options.saveData;
 const window=new E();window.location=new URL('https://securityops.co/images');window.matchMedia=()=>reduced;
 const ios=[],mos=[],timers=new Map();let tick=0;
 class IO{constructor(cb,config){this.cb=cb;this.config=config;this.nodes=[];ios.push(this);}observe(n){this.nodes.push(n);}unobserve(n){this.nodes=this.nodes.filter(x=>x!==n);}}
 class MO{constructor(cb){this.cb=cb;mos.push(this);}observe(){}}
 window.IntersectionObserver=options.unsupported?null:IO;window.MutationObserver=MO;
 window.setTimeout=fn=>{timers.set(++tick,fn);return tick;};window.clearTimeout=id=>timers.delete(id);
 vm.runInNewContext(script,{window,document,navigator:{connection},IntersectionObserver:IO,MutationObserver:MO,URL,Image:E});
 const layer=i=>i.parent.children.find(c=>c.className==='motion-layer');
 const layers=()=>images.map(layer).filter(Boolean);
 const visible=(nodes,value=true)=>ios[0].cb(nodes.map(target=>({target,isIntersecting:value,intersectionRatio:value?1:0})));
 const button=i=>i.closest('.image').children.find(c=>c.className==='motion-toggle');
 return {grid,images,makeImage,document,reduced,connection,window,ios,mos,timers,layer,layers,visible,button};
}
let f=fixture();assert.equal(f.layers().length,0);assert.equal(f.ios[0].config.rootMargin,'0px');
f.visible(f.images);assert.equal(f.layers().length,2);assert(f.layers().every(l=>l.hidden));assert(f.images.every(i=>i.src.includes('s=poster')),'Posters remain throughout loading');
f.layer(f.images[0]).dispatchEvent(new Event('load'));f.layer(f.images[1]).dispatchEvent(new Event('load'));assert.equal(f.layers().length,4);
f.layer(f.images[2]).dispatchEvent(new Event('load'));f.layer(f.images[3]).dispatchEvent(new Event('load'));assert.equal(f.layers().filter(l=>!l.hidden).length,4);
f.button(f.images[0]).dispatchEvent(new Event('click'));assert(!f.layer(f.images[0]));assert(f.layer(f.images[4]));assert.equal(f.button(f.images[0]).getAttribute('aria-pressed'),'false');
f.layer(f.images[4]).dispatchEvent(new Event('error'));assert(!f.layer(f.images[4]));assert(f.layer(f.images[5]));
f.visible([f.images[4]],false);f.visible([f.images[4]]);assert(!f.layer(f.images[4]),'No automatic retry');
f.document.hidden=true;f.document.dispatchEvent(new Event('visibilitychange'));assert.equal(f.layers().length,0);assert.equal(f.timers.size,0);
f.document.hidden=false;f.document.dispatchEvent(new Event('visibilitychange'));assert.equal(f.layers().length,2);assert(!f.layer(f.images[0]),'Manual pause survives hidden/resume');
f.reduced.matches=true;f.reduced.dispatchEvent(new Event('change'));assert.equal(f.layers().length,0);
f.reduced.matches=false;f.connection.saveData=true;f.connection.dispatchEvent(new Event('change'));assert.equal(f.layers().length,0);
f.connection.saveData=false;f.connection.dispatchEvent(new Event('change'));assert.equal(f.layers().length,2);
f.window.dispatchEvent(new Event('pagehide'));assert.equal(f.layers().length,0);f.window.dispatchEvent(new Event('pageshow'));assert.equal(f.layers().length,2);
for(const fn of [...f.timers.values()])fn();assert(f.images.every(i=>i.src.includes('s=poster')));
for(const options of [{hidden:true},{reduced:true},{saveData:true},{unsupported:true}]){f=fixture(options);if(f.ios.length)f.visible(f.images);assert.equal(f.layers().length,0);}
f=fixture();const newImage=f.makeImage('appended');f.mos[0].cb([{addedNodes:[newImage.closest('.image')]}]);assert(f.ios[0].nodes.includes(newImage));f.visible([newImage]);assert(f.layer(newImage));
newImage.closest('.image').remove();f.mos[0].cb([{addedNodes:[]}]);assert(!f.ios[0].nodes.includes(newImage));assert.equal(f.timers.size,0,'Removed card releases request timer');
const bad=f.makeImage('bad');bad.setAttribute('data-motion','https://evil.example/proxy?s=animated');f.mos[0].cb([{addedNodes:[bad]}]);assert(!f.ios[0].nodes.includes(bad));
console.log('PASS: poster retained, 2 loading/4 active, pause/resume, hidden/offscreen/reduced/Save-Data, deadline, no failed retries, appended/removed cards and same-origin restriction.');
