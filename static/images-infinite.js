/* Progressive image pagination. No dependencies, timers for navigation, or HTML injection. */
(() => {
    'use strict';
    const grid = document.getElementById('images');
    const next = document.querySelector('a.nextpage.img');
    if (!grid || !next || !window.IntersectionObserver || !window.fetch ||
        !window.AbortController || !window.ReadableStream || !window.TextDecoder ||
        navigator.connection?.saveData) return;

    const MAX_BYTES = 1024 * 1024;
    const MAX_CARDS = 480;
    const filmstrip = grid.classList.contains('images-view-filmstrip');
    const origin = window.location.origin;
    const provider = grid.getAttribute('data-provider');
    let loading = false, stopped = false, near = false, scrolled = false;
    let controller = null;
    let count = grid.querySelectorAll(':scope > .image-wrapper').length;
    const status = document.createElement('p');
    status.className = 'infinite-status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    next.before(status);
    status.textContent = 'More images load as you scroll.';

    function url(value, path) {
        if (typeof value !== 'string' || value.length > 18000) throw new Error('Invalid link');
        const result = new URL(value, window.location.href);
        if (!['http:', 'https:'].includes(result.protocol) || result.username || result.password ||
            (path && (result.origin !== origin || result.pathname !== path))) throw new Error('Unsafe link');
        return result;
    }
    function continuation(value) {
        const result = url(value, '/images');
        if (result.searchParams.get('scraper') !== provider || !result.searchParams.get('npt') || result.hash || result.searchParams.has('frame')) throw new Error('Invalid continuation');
        result.searchParams.delete('append');
        return result;
    }
    function proxy(value) { return url(value, '/proxy').href; }
    function label(value) {
        if (typeof value !== 'string' || value.length > 16000) throw new Error('Invalid text');
        return value;
    }
    function validate(item) {
        if (!item || !Number.isInteger(item.width) || !Number.isInteger(item.height) ||
            item.width < 1 || item.height < 1 || item.width > 100000 || item.height > 100000 ||
            !Array.isArray(item.links) || item.links.length < 1 || item.links.length > 3) throw new Error('Invalid image');
        return { original: proxy(item.original), preview: proxy(item.preview), source: url(item.source).href,
            title: label(item.title), host: label(item.host), width: item.width, height: item.height,
            motion: item.motion == null ? null : proxy(item.motion),
            links: item.links.map(link => {
                if (!link || !['Original', 'Preview', 'View animation'].includes(link.label)) throw new Error('Invalid action');
                return { label: link.label, href: proxy(link.href) };
            }) };
    }
    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function card(item) {
        const article = element('article', 'image-wrapper');
        const body = element('div', 'image');
        const thumb = element('a', 'thumb');
        thumb.href = item.original;
        thumb.setAttribute('aria-label', 'Open original: ' + item.title);
        const img = element('img');
        img.alt = item.title;
        img.width = item.width; img.height = item.height;
        img.loading = 'lazy'; img.decoding = 'async'; img.setAttribute('fetchpriority', 'low');
        img.src = item.preview;
        if (item.motion) img.setAttribute('data-motion', item.motion);
        thumb.append(img);
        const source = element('a');
        source.href = item.source; source.rel = 'noreferrer nofollow';
        source.append(element('div', 'title', item.host), element('div', 'description', item.title));
        const links = element('div', 'image-links');
        for (const link of item.links) {
            const anchor = element('a', '', link.label); anchor.href = link.href; links.append(anchor);
        }
        body.append(thumb, source, links); article.append(body);
        return article;
    }
    async function readPage(response) {
        if (!response.ok || response.redirected ||
            !/^application\/json(?:;|$)/i.test(response.headers.get('Content-Type') || '') ||
            Number(response.headers.get('Content-Length')) > MAX_BYTES || !response.body) throw new Error('Search unavailable');
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let bytes = 0, text = '';
        try {
            while (true) {
                const {done, value} = await reader.read();
                if (done) break;
                bytes += value.byteLength;
                if (bytes > MAX_BYTES) { await reader.cancel(); throw new Error('Response too large'); }
                text += decoder.decode(value, {stream: true});
            }
            return JSON.parse(text + decoder.decode());
        } finally { reader.releaseLock(); }
    }
    const observer = new IntersectionObserver(entries => {
        near = entries[entries.length - 1].isIntersecting;
        maybeLoad();
    }, {root: filmstrip ? grid : null, rootMargin: filmstrip ? '0px 240px' : '400px 0px'});
    function watch() {
        observer.disconnect(); near = false;
        const last = grid.lastElementChild;
        if (last && !stopped) observer.observe(last);
    }
    function stop(message) {
        stopped = true; observer.disconnect();
        window.removeEventListener('scroll', onScroll);
        grid.removeEventListener('scroll', onScroll);
        status.textContent = message;
    }
    function recover(message) {
        stop(message);
        const restart = new URL(window.location.href);
        for (const key of ['npt', 'append', 'frame', 'flow_action', 'flow_start', 'seconds']) restart.searchParams.delete(key);
        restart.hash = '';
        next.href = restart.href; next.textContent = 'Restart search';
    }
    async function load() {
        if (loading || stopped || document.hidden) return;
        let target;
        try { target = continuation(next.getAttribute('href')); }
        catch { stop('Use Next page to continue.'); return; }
        loading = true; scrolled = false; observer.disconnect();
        // A continuation is single-use. Remove its clickable URL while in flight.
        next.removeAttribute('href'); next.setAttribute('aria-disabled', 'true');
        grid.setAttribute('aria-busy', 'true');
        status.textContent = 'Loading more images…';
        controller = new AbortController();
        const timeout = window.setTimeout(() => controller?.abort(), 25000);
        try {
            const request = new URL(target); request.searchParams.set('append', '1');
            const response = await fetch(request.href, {credentials: 'same-origin', mode: 'same-origin',
                cache: 'no-store', redirect: 'error', headers: {Accept: 'application/json'}, signal: controller.signal});
            const page = await readPage(response);
            if (!page || page.version !== 1 || page.provider !== provider || !Array.isArray(page.items) || page.items.length > 24 ||
                !Number.isInteger(page.omitted) || page.omitted < 0) throw new Error('Invalid page');
            const following = page.next === null ? null : continuation(page.next);
            if (following && following.searchParams.get('npt') === target.searchParams.get('npt')) throw new Error('Repeated continuation');
            const items = page.items.map(validate);
            if (stopped) return;
            const fragment = document.createDocumentFragment();
            for (const item of items) fragment.append(card(item));
            grid.append(fragment); count += items.length;
            if (following) next.href = following.href;
            else { next.hidden = true; stop('You’ve reached the end of these results.'); return; }
            if (!items.length) { stop('No images on this page. Use Next page to continue.'); return; }
            if (count > MAX_CARDS - 24) { stop(count + ' images loaded. Use Next page to keep browsing with a fresh page.'); return; }
            status.textContent = items.length + ' more images loaded.' + (page.omitted ? ' Showing the first 24 from this provider page.' : '');
            watch();
        } catch {
            controller?.abort();
            recover('More images could not load. Restart the search or choose another provider in the filters.');
        } finally {
            window.clearTimeout(timeout); controller = null; loading = false; scrolled = false;
            next.removeAttribute('aria-disabled'); grid.removeAttribute('aria-busy');
        }
    }
    function maybeLoad() {
        if (!near || !scrolled || loading || stopped || document.hidden) return;
        // A horizontal root can intersect while the whole strip is off screen.
        if (filmstrip) {
            const rect = grid.getBoundingClientRect();
            if (rect.bottom <= 0 || rect.top >= window.innerHeight) return;
        }
        load();
    }
    function onScroll() {
        if (loading || stopped || document.hidden) return;
        scrolled = true; maybeLoad();
    }
    window.addEventListener('scroll', onScroll, {passive: true});
    if (filmstrip) grid.addEventListener('scroll', onScroll, {passive: true});
    next.addEventListener('click', event => {
        if (loading) { event.preventDefault(); return; }
        if (stopped || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); load();
    });
    window.addEventListener('pagehide', () => {
        if (loading) { recover('Loading stopped. Restart the search to continue.'); controller?.abort(); }
    });
    watch();
})();
