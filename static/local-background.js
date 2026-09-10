/* Opt-in local pictures only: no upload endpoint, network API, cookies or filenames.
 * Data stays in this tab by default. Persistent storage requires explicit consent. */
(() => {
    'use strict';
    const KEY = 'securitysearch.background.v1';
    const MAX_INPUT = 16 * 1024 * 1024, MAX_DATA = 2 * 1024 * 1024, MAX_PIXELS = 48000000;
    const input = document.getElementById('background-file');
    const remember = document.getElementById('background-remember');
    const remove = document.getElementById('background-remove');
    const message = document.getElementById('background-status');
    const preview = document.getElementById('background-preview');
    let generation = 0, currentData = null, cancel = () => {};
    const status = text => { if (message) message.textContent = text; };
    const valid = data => typeof data === 'string' && data.length <= MAX_DATA &&
        /^data:image\/(?:webp|png|jpeg);base64,[A-Za-z0-9+/]+={0,2}$/.test(data);
    const read = name => { try { return window[name].getItem(KEY); } catch { return null; } };
    const erase = name => { try { window[name].removeItem(KEY); return true; } catch { return false; } };
    function display(data) {
        if (!valid(data)) throw new Error('Invalid normalized picture.');
        document.body.style.setProperty('--local-picture', 'url("' + data + '")');
        document.body.classList.add('local-picture');
        currentData = data;
        if (preview) { preview.src = data; preview.hidden = false; }
    }
    function save(data) {
        const persistent = !!remember?.checked;
        const target = persistent ? 'localStorage' : 'sessionStorage';
        const other = persistent ? 'sessionStorage' : 'localStorage';
        try {
            window[target].setItem(KEY, data);
            const erased = erase(other);
            status(!erased ? 'Applied locally. Older storage could not be cleared; check this site’s browser storage before sharing the device.' : persistent ?
                'Applied and remembered only on this device. Nothing was uploaded.' :
                'Applied for this tab session. Nothing was uploaded.');
        } catch {
            // Avoid restoring a different old background when this page reloads.
            const a = erase('sessionStorage'), b = erase('localStorage');
            status('Applied on this page only: browser storage is blocked or full.' +
                (!a || !b ? ' Previous stored data may need clearing in browser settings.' : '') + ' Nothing was uploaded.');
        }
    }
    function sniff(data) {
        // Inspect file bytes, not the filename or OS MIME label (often blank on mobile).
        if (typeof data !== 'string') return null;
        const comma = data.indexOf(',');
        if (comma < 0 || !data.slice(0, comma).endsWith(';base64')) return null;
        const bytes = atob(data.slice(comma + 1, comma + 33));
        let type = null;
        if (bytes.startsWith('\xff\xd8\xff')) type = 'jpeg';
        else if (bytes.startsWith('\x89PNG\r\n\x1a\n')) type = 'png';
        else if (bytes.startsWith('RIFF') && bytes.slice(8, 12) === 'WEBP') type = 'webp';
        else if (bytes.startsWith('GIF87a') || bytes.startsWith('GIF89a')) type = 'gif';
        return type ? 'data:image/' + type + ';base64,' + data.slice(comma + 1) : null;
    }
    try {
        const tab = read('sessionStorage'), local = read('localStorage');
        const saved = valid(tab) ? tab : valid(local) ? local : null;
        if (tab && !valid(tab)) erase('sessionStorage');
        if (local && !valid(local)) erase('localStorage');
        if (saved) {
            display(saved);
            if (remember) remember.checked = !valid(tab);
            status('Your picture is loaded locally. Choose another file to replace it.');
        } else status('Ready. Choose a picture below; it applies automatically and is not uploaded.');
    } catch { status('Choose a picture to apply on this page. Browser storage is unavailable.'); }
    input?.addEventListener('change', () => {
        const file = input.files?.[0];
        input.value = ''; // Neither retain nor display a private filename.
        if (!file) return;
        const ticket = ++generation;
        cancel();
        if (!Number.isFinite(file.size) || file.size < 1 || file.size > MAX_INPUT) {
            status('Choose a JPEG, PNG, WebP or GIF file up to 16 MiB.'); return;
        }
        status('Preparing your picture on this device…');
        const reader = new FileReader();
        let image = null, timer;
        const cleanup = () => {
            clearTimeout(timer);
            reader.onload = reader.onerror = reader.onabort = null;
            if (reader.readyState === 1) reader.abort();
            if (image) { image.onload = image.onerror = null; image = null; }
        };
        cancel = cleanup;
        const fail = text => { cleanup(); if (ticket === generation) status(text); };
        timer = setTimeout(() => fail('Local processing timed out. Try a smaller picture.'), 10000);
        reader.onerror = () => fail('The file could not be read. Choose it again.');
        reader.onload = () => {
            if (ticket !== generation) { cleanup(); return; }
            let data;
            try { data = sniff(reader.result); } catch { data = null; }
            if (!data) { fail('Choose a real JPEG, PNG, WebP or GIF picture. HEIC/HEIF and SVG are not supported.'); return; }
            image = new Image();
            image.onerror = () => fail('This picture could not be decoded. Try a different image or export it as JPEG/PNG.');
            image.onload = () => {
                if (ticket !== generation) { cleanup(); return; }
                let canvas;
                try {
                    const width = image.naturalWidth, height = image.naturalHeight;
                    if (!width || !height || width * height > MAX_PIXELS) throw new Error('Choose a picture smaller than 48 megapixels.');
                    canvas = document.createElement('canvas');
                    const scale = Math.min(1, 1920 / Math.max(width, height));
                    canvas.width = Math.max(1, Math.round(width * scale));
                    canvas.height = Math.max(1, Math.round(height * scale));
                    const ctx = canvas.getContext('2d');
                    if (!ctx) throw new Error('Local image processing is not available in this browser.');
                    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                    // Strip metadata; animated files intentionally become one still background.
                    let normalized = canvas.toDataURL('image/webp', 0.82);
                    if (!valid(normalized)) normalized = canvas.toDataURL('image/jpeg', 0.70);
                    if (!valid(normalized)) throw new Error('The processed picture is still too large. Try a smaller image.');
                    display(normalized); save(normalized);
                } catch (error) { status(error.message || 'This picture could not be applied.'); }
                finally { if (canvas) canvas.width = canvas.height = 1; cleanup(); }
            };
            image.src = data;
        };
        try { reader.readAsDataURL(file); } catch { fail('The file could not be read. Please choose it again.'); }
    });
    remember?.addEventListener('change', () => { if (valid(currentData)) save(currentData); });
    remove?.addEventListener('click', () => {
        generation++; cancel();
        const tab = erase('sessionStorage'), local = erase('localStorage');
        currentData = null;
        document.body.style.removeProperty('--local-picture');
        document.body.classList.remove('local-picture');
        if (preview) { preview.hidden = true; preview.removeAttribute('src'); }
        if (remember) remember.checked = false;
        status(tab && local ? 'Picture removed from this page and this site’s browser storage.' :
            'Picture removed from this page. Browser storage is inaccessible; clear this site’s stored data to remove any saved copy.');
    });
    // Do not leave a silently nonfunctional native file control when scripts are blocked.
    if (input) input.disabled = false;
    if (remember) remember.disabled = false;
    if (remove) remove.disabled = false;
})();
