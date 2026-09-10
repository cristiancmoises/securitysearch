/* Visible-only motion layers: posters remain painted throughout loading/failure.
 * Native links work without this enhancement. No cross-origin browser fetches. */
(() => {
    'use strict';
    const grid=document.getElementById('images');
    if(!grid||!window.IntersectionObserver||!window.MutationObserver||!window.matchMedia)return;
    const reduced=window.matchMedia('(prefers-reduced-motion: reduce)'),connection=navigator.connection;
    const entries=new Map(),MAX_PLAYING=4,MAX_LOADING=2;
    let active=0,loading=0,suspended=false;
    const allowed=()=>!document.hidden&&!suspended&&!reduced.matches&&!connection?.saveData;
    function proxy(value,motion=false){
        try{const url=new URL(value,window.location.href);
            return url.origin===window.location.origin&&url.pathname==='/proxy'&&!url.username&&!url.password&&(!motion||url.searchParams.get('s')==='animated')?url.href:null;
        }catch{return null;}
    }
    function label(entry){
        entry.button.textContent=entry.state==='loading'?'Loading…':entry.state==='playing'?'Pause':entry.state==='failed'?'Retry':'Play';
        entry.button.setAttribute('aria-label',entry.state==='playing'?'Pause animated preview':'Play animated preview');
        entry.button.setAttribute('aria-pressed',String(entry.state==='playing'||entry.state==='loading'));
        entry.button.setAttribute('aria-busy',String(entry.state==='loading'));
        entry.button.disabled=!allowed();
    }
    function stop(entry,failed=false){
        if(entry.state==='loading'){loading--;entry.cleanup();}
        if(entry.state==='loading'||entry.state==='playing')active--;
        entry.layer?.removeAttribute('src');entry.layer?.remove();entry.layer=null;
        entry.state=failed?'failed':'idle';label(entry);
    }
    function pump(){
        if(!allowed())return;
        for(const entry of entries.values()){
            if(active>=MAX_PLAYING||loading>=MAX_LOADING)break;
            if(!entry.visible||entry.paused||entry.state!=='idle')continue;
            active++;loading++;entry.state='loading';
            const layer=new Image();entry.layer=layer;layer.className='motion-layer';layer.alt='';layer.hidden=true;
            layer.setAttribute('aria-hidden','true');layer.decoding='async';
            const done=success=>{
                if(entry.state!=='loading'||entry.layer!==layer)return;
                entry.cleanup();loading--;
                if(success&&entry.visible&&allowed()&&!entry.paused){entry.state='playing';layer.hidden=false;}
                else{active--;layer.removeAttribute('src');layer.remove();entry.layer=null;entry.state=success?'idle':'failed';}
                label(entry);pump();
            };
            const onload=()=>done(true),onerror=()=>done(false);
            const timeout=window.setTimeout(()=>done(false),15000);
            entry.cleanup=()=>{window.clearTimeout(timeout);layer.removeEventListener('load',onload);layer.removeEventListener('error',onerror);};
            layer.addEventListener('load',onload);layer.addEventListener('error',onerror);
            entry.anchor.appendChild(layer);layer.src=entry.motion;label(entry);
        }
    }
    const observer=new IntersectionObserver(changes=>{
        for(const change of changes){const entry=entries.get(change.target);if(!entry)continue;
            entry.visible=change.isIntersecting&&change.intersectionRatio>=0.1;
            if(!entry.visible&&(entry.state==='playing'||entry.state==='loading'))stop(entry);
        }pump();
    },{threshold:[0,0.1],rootMargin:'0px'});
    function add(image){
        if(entries.has(image))return;
        const motion=proxy(image.getAttribute('data-motion'),true),still=proxy(image.getAttribute('src'));
        const anchor=image.closest('.thumb'),card=image.closest('.image');
        if(!motion||!still||!anchor||!card)return;
        const button=document.createElement('button');button.type='button';button.className='motion-toggle';
        const entry={image,motion,poster:still,anchor,button,visible:false,paused:false,state:'idle',layer:null};
        entries.set(image,entry);card.appendChild(button);label(entry);
        button.addEventListener('click',event=>{
            event.preventDefault();if(!allowed())return;
            if(entry.state==='playing'||entry.state==='loading'){entry.paused=true;stop(entry);}
            else{entry.paused=false;if(entry.state==='failed')entry.state='idle';}
            pump();
        });
        observer.observe(image);
    }
    grid.querySelectorAll('img[data-motion]').forEach(add);
    const mutations=new MutationObserver(changes=>{
        for(const change of changes){
            for(const node of change.addedNodes){if(node.nodeType!==1)continue;
                if(node.matches?.('img[data-motion]'))add(node);
                node.querySelectorAll('img[data-motion]').forEach(add);
            }
        }
        for(const [image,entry] of entries){
            if(!grid.contains(image)){stop(entry);observer.unobserve(image);entry.button.remove();entries.delete(image);}
        }
        pump();
    });
    mutations.observe(grid,{childList:true,subtree:true});
    function preferences(){
        for(const entry of entries.values()){
            if(!allowed()&&(entry.state==='playing'||entry.state==='loading'))stop(entry);
            label(entry);
        }pump();
    }
    document.addEventListener('visibilitychange',preferences);
    reduced.addEventListener?.('change',preferences);connection?.addEventListener?.('change',preferences);
    window.addEventListener('pagehide',()=>{suspended=true;preferences();});
    window.addEventListener('pageshow',()=>{suspended=false;preferences();});
})();
