/* Opt-in only. No upload, remote URL, telemetry, cookies, workers or network API. */
(() => {
    'use strict';
    const KEY='securitysearch.background.v1', MAX_INPUT=8*1024*1024, MAX_DATA=2*1024*1024;
    const input=document.getElementById('background-file');
    const remember=document.getElementById('background-remember');
    const message=document.getElementById('background-status');
    let generation=0;
    const status=text=>{if(message) message.textContent=text;};
    const valid=data=>typeof data==='string' && data.length<=MAX_DATA && /^data:image\/(?:webp|png|jpeg);base64,[A-Za-z0-9+/]+={0,2}$/.test(data);
    function clear(){
        for(const name of ['sessionStorage','localStorage']) {try{window[name].removeItem(KEY);}catch{}}
        document.body.style.removeProperty('--local-picture');document.body.classList.remove('local-picture');
    }
    function display(data){
        if(!valid(data)) throw new Error('Invalid picture data.');
        document.body.style.setProperty('--local-picture','url("'+data+'")');
        document.body.classList.add('local-picture');
    }
    const read=name=>{try{return window[name].getItem(KEY);}catch{return null;}};
    try {
        const session=read('sessionStorage'), persistent=read('localStorage');
        const saved=session || persistent;
        if(saved && valid(saved)){display(saved);if(remember)remember.checked=!session;status('Picture loaded locally. It is not uploaded.');}
        else if(saved) clear();
    }catch{status('Browser storage is unavailable. A picture can still be used on this page.');}
    input?.addEventListener('change',()=>{
        const file=input.files?.[0], current=++generation;
        input.value='';
        if(!file)return;
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>MAX_INPUT||file.size===0){status('Choose a JPEG, PNG or WebP picture up to 8 MiB.');return;}
        status('Preparing your picture locally…');
        const reader=new FileReader();
        reader.onerror=()=>{if(current===generation)status('The picture could not be read.');};
        reader.onload=()=>{
            if(current!==generation)return;
            const image=new Image();
            image.onerror=()=>{if(current===generation)status('The file is not a supported picture.');};
            image.onload=()=>{
                if(current!==generation)return;
                try{
                    if(!image.width||!image.height||image.width*image.height>24*1024*1024)throw new Error('Choose a picture smaller than 24 megapixels.');
                    const canvas=document.createElement('canvas');
                    const scale=Math.min(1,1920/Math.max(image.width,image.height));
                    canvas.width=Math.max(1,Math.round(image.width*scale));canvas.height=Math.max(1,Math.round(image.height*scale));
                    const context=canvas.getContext('2d');if(!context)throw new Error('Local picture processing is unavailable.');
                    context.drawImage(image,0,0,canvas.width,canvas.height);
                    // Re-encoding removes filenames/EXIF and makes animated inputs still backgrounds.
                    const data=canvas.toDataURL('image/webp',0.82);
                    canvas.width=canvas.height=1;
                    if(!valid(data))throw new Error('The normalized picture is too large. Choose a smaller picture.');
                    clear();display(data);
                    try{window[remember?.checked?'localStorage':'sessionStorage'].setItem(KEY,data);
                        status(remember?.checked?'Saved only on this device. Remove it before sharing the browser.':'Saved only for this tab session. Nothing was uploaded.');
                    }catch{status('Applied on this page only; your browser did not allow local storage.');}
                }catch(error){status(error.message || 'Could not prepare this picture.');}
            };
            image.src=reader.result;
        };
        reader.readAsDataURL(file);
    });
    remember?.addEventListener('change',()=>{
        try{
            const data=read('sessionStorage')||read('localStorage');
            if(!valid(data))return;
            window[remember.checked?'localStorage':'sessionStorage'].setItem(KEY,data);
            window[remember.checked?'sessionStorage':'localStorage'].removeItem(KEY);
            status(remember.checked?'Remembered locally on this device. Nothing was uploaded.':'Kept for this tab session only.');
        }catch{status('Could not change storage. Remove the picture to clear it.');}
    });
    document.getElementById('background-remove')?.addEventListener('click',()=>{
        generation++;clear();if(remember)remember.checked=false;status('Picture removed from this site’s browser storage.');
    });
})();
